<?php
session_start();
ob_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

//состояние заявки
$allowed_filters = ['active', 'completed', 'my', 'incoming', 'outgoing'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters) ? $_GET['filter'] : 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Создание 
    if (isset($_POST['create_offer'])) {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        if ($category_id > 0 && !empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('INSERT INTO offers (user_id, category_id, title, description, contact_link, status) VALUES (?, ?, ?, ?, "", "Активно")');
            if ($stmt->execute([$current_user_id, $category_id, $title, $description])) {
                header("Location: index.php?filter=active&success=1");
                exit;
            } else {
                $msg = 'Не удалось сохранить публикацию.';
                $msg_type = 'error';
            }
        } else {
            $msg = 'Пожалуйста, заполните все обязательные поля!';
            $msg_type = 'error';
        }
    }

    // Завершение 
    if (isset($_POST['complete_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        $stmt = $pdo->prepare('UPDATE offers SET status = "Завершено" WHERE id = ? AND user_id = ?');
        $stmt->execute([$offer_id, $current_user_id]);
        if ($stmt->rowCount() > 0) {
            $msg = 'Публикация перенесена в завершенные!';
            $msg_type = 'success';
        } else {
            $msg = 'Ошибка доступа: чужая публикация или она уже завершена.';
            $msg_type = 'error';
        }
    }

    // Редактирование 
    if (isset($_POST['edit_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $status = trim($_POST['status']);

        if ($category_id > 0 && !empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('UPDATE offers SET category_id = ?, title = ?, description = ?, status = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$category_id, $title, $description, $status, $offer_id, $current_user_id]);
            if ($stmt->rowCount() > 0) {
                $msg = 'Публикация успешно обновлена!';
                $msg_type = 'success';
            } else {
                $msg = 'Ошибка доступа или данные не изменились.';
                $msg_type = 'error';
            }
        } else {
            $msg = 'Заполните все текстовые поля!';
            $msg_type = 'error';
        }
    }

    // Отправка отклика
    if (isset($_POST['send_response'])) {
        $offer_id = intval($_POST['offer_id']);
        $first_message = trim($_POST['message'] ?? '');

        if ($first_message === '') {
            $msg = 'Ошибка: нельзя отправить пустое сообщение!';
            $msg_type = 'error';
        } else {
            // Ищем публикацию в БД
            $stmt = $pdo->prepare('SELECT user_id, status FROM offers WHERE id = ?');
            $stmt->execute([$offer_id]);
            $target_offer = $stmt->fetch();

            if (!$target_offer || $target_offer['status'] !== 'Активно') {
                $msg = 'Ошибка: публикация не найдена или уже завершена!';
                $msg_type = 'error';
            } elseif ($target_offer['user_id'] == $current_user_id) {
                $msg = 'Ошибка: нельзя откликаться на собственную публикацию!';
                $msg_type = 'error';
            } else {
                $receiver_id = $target_offer['user_id'];
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('INSERT INTO responses (offer_id, sender_id, receiver_id, status) VALUES (?, ?, ?, "Ожидает решения")');
                    $stmt->execute([$offer_id, $current_user_id, $receiver_id]);
                    $response_id = $pdo->lastInsertId();

                    $stmtMsg = $pdo->prepare('INSERT INTO messages (response_id, user_id, message) VALUES (?, ?, ?)');
                    $stmtMsg->execute([$response_id, $current_user_id, $first_message]);
                    $pdo->commit();

                    $msg = 'Отклик успешно отправлен! Вы можете продолжить общение в разделе «Исходящие».';
                    $msg_type = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->getCode() == 23000) {
                        $msg = 'Ошибка: вы уже отправляли отклик на эту публикацию!';
                    } else {
                        $msg = 'Ошибка при создании отклика.';
                    }
                    $msg_type = 'error';
                }
            }
        }
    }

    // Ответ на отклик типа там принять и т.д
    if (isset($_POST['change_response_status'])) {
        $response_id = intval($_POST['response_id']);
        $new_status = $_POST['new_status'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM responses WHERE id = ?');
        $stmt->execute([$response_id]);
        $resp = $stmt->fetch();

        if (!$resp) {
            $msg = 'Ошибка: отклик не найден.';
            $msg_type = 'error';
        } elseif ($resp['status'] !== 'Ожидает решения') {
            $msg = 'Ошибка: решение по этому отклику уже принято.';
            $msg_type = 'error';
        } else {
            if ($resp['receiver_id'] == $current_user_id && in_array($new_status, ['Принята', 'Отклонена'])) {
                $upd = $pdo->prepare('UPDATE responses SET status = ? WHERE id = ?');
                $upd->execute([$new_status, $response_id]);
                $msg = 'Статус отклика изменен на: ' . $new_status;
                $msg_type = 'success';
            } elseif ($resp['sender_id'] == $current_user_id && $new_status === 'Отменена') {
                $upd = $pdo->prepare('UPDATE responses SET status = "Отменена" WHERE id = ?');
                $upd->execute([$response_id]);
                $msg = 'Вы отменили свой отклик.';
                $msg_type = 'success';
            } else {
                $msg = 'Ошибка доступа: у вас нет прав на это действие!';
                $msg_type = 'error';
            }
        }
    }

    // Отправка сообщения в чат отклика
    if (isset($_POST['send_chat_message'])) {
        $response_id = intval($_POST['response_id']);
        $chat_text = trim($_POST['chat_message'] ?? '');

        if ($chat_text === '') {
            $msg = 'Ошибка: сообщение не может быть пустым!';
            $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare('SELECT id, status FROM responses WHERE id = ? AND (sender_id = ? OR receiver_id = ?)');
            $stmt->execute([$response_id, $current_user_id, $current_user_id]);
            $resp = $stmt->fetch();

            if (!$resp) {
                $msg = 'Ошибка доступа: чужой чат!';
                $msg_type = 'error';
            } elseif (in_array($resp['status'], ['Отклонена', 'Отменена'])) {
                $msg = 'Ошибка: чат закрыт, так как заявка отклонена или отменена.';
                $msg_type = 'error';
            } else {
                $stmtMsg = $pdo->prepare('INSERT INTO messages (response_id, user_id, message) VALUES (?, ?, ?)');
                $stmtMsg->execute([$response_id, $current_user_id, $chat_text]);
                header("Location: index.php?filter=" . urlencode($filter) . "&open_chat=" . $response_id);
                exit;
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$stmt->execute([$current_user_id]);
$user_profile = $stmt->fetch();
$categories = $pdo->query('SELECT * FROM categories')->fetchAll();
$offers_list = [];
$responses_list = [];
$chat_messages_map = [];
$stmtMyResp = $pdo->prepare('SELECT offer_id FROM responses WHERE sender_id = ?');
$stmtMyResp->execute([$current_user_id]);
$my_responded_offers = $stmtMyResp->fetchAll(PDO::FETCH_COLUMN);
if (in_array($filter, ['active', 'completed', 'my'])) {
    $search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $search_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $search_date = isset($_GET['date']) ? trim($_GET['date']) : '';
    $sql = 'SELECT offers.*, users.name as author_name, categories.name as category_name 
            FROM offers 
            JOIN users ON offers.user_id = users.id 
            JOIN categories ON offers.category_id = categories.id ';
    $conditions = [];
    $params = [];
    if ($filter === 'active') {
        $conditions[] = 'offers.status = "Активно"';
    } elseif ($filter === 'completed') {
        $conditions[] = 'offers.status = "Завершено"';
        $conditions[] = 'offers.user_id = ?';
        $params[] = $current_user_id;
    } elseif ($filter === 'my') {
        $conditions[] = 'offers.user_id = ?';
        $params[] = $current_user_id;
    }
    if ($search_query !== '') {
        $conditions[] = '(offers.title LIKE ? OR offers.description LIKE ?)';
        $params[] = '%' . $search_query . '%';
        $params[] = '%' . $search_query . '%';
    }
    if ($search_category > 0) {
        $conditions[] = 'offers.category_id = ?';
        $params[] = $search_category;
    }
    if ($search_date !== '') {
        $conditions[] = 'DATE(offers.created_at) = ?';
        $params[] = $search_date;
    }

    if (!empty($conditions)) {
        $sql .= 'WHERE ' . implode(' AND ', $conditions) . ' ';
    }
    $sql .= 'ORDER BY offers.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offers_list = $stmt->fetchAll();
} elseif ($filter === 'incoming') {
    $stmt = $pdo->prepare('
        SELECT r.*, o.title as offer_title, o.status as offer_status, u.name as sender_name 
        FROM responses r
        JOIN offers o ON r.offer_id = o.id
        JOIN users u ON r.sender_id = u.id
        WHERE r.receiver_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$current_user_id]);
    $responses_list = $stmt->fetchAll();
} elseif ($filter === 'outgoing') {
    $stmt = $pdo->prepare('
        SELECT r.*, o.title as offer_title, o.status as offer_status, u.name as receiver_name 
        FROM responses r
        JOIN offers o ON r.offer_id = o.id
        JOIN users u ON r.receiver_id = u.id
        WHERE r.sender_id = ?
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$current_user_id]);
    $responses_list = $stmt->fetchAll();
}
// загрузка чата
if (!empty($responses_list)) {
    $resp_ids = array_column($responses_list, 'id');
    $placeholders = implode(',', array_fill(0, count($resp_ids), '?'));
    $stmtMsgs = $pdo->prepare("
        SELECT m.*, u.name as author_name 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.response_id IN ($placeholders) 
        ORDER BY m.created_at ASC
    ");
    $stmtMsgs->execute($resp_ids);
    foreach ($stmtMsgs->fetchAll() as $m) {
        $chat_messages_map[$m['response_id']][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Платформа Взаимопомощи</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef5fc; color: #2c3e50; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: white; padding: 15px 30px; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .header h1 { font-size: 22px; color: #2c3e50; font-weight: 600; }
        .profile-widget {
            background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 10px 15px;
            border-radius: 10px; display: flex; align-items: center; gap: 15px;
        }
        .btn-open-modal {
            background: #3a97f9; color: white; border: none; padding: 10px 18px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-open-modal:hover { background: #2582e5; }
        .widget-info { text-align: right; }
        .widget-info .username { font-weight: 600; color: #3a97f9; display: block; font-size: 14px; }
        .widget-info .email { font-size: 11px; color: #7f8c8d; }
        .widget-actions { display: flex; flex-direction: column; gap: 2px; }
        .widget-link { font-size: 12px; color: #3a97f9; text-decoration: none; font-weight: 600; }
        .logout-link { font-size: 11px; color: #e74c3c; text-decoration: none; }
        
        .main-grid { display: grid; grid-template-columns: 260px 1fr; gap: 25px; }
        @media (max-width: 768px) { .main-grid { grid-template-columns: 1fr; } }
        
        .sidebar-menu { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); height: fit-content; }
        .sidebar-menu h3 { font-size: 13px; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; margin: 15px 0 10px 0; }
        .sidebar-menu h3:first-child { margin-top: 0; }
        .menu-list { list-style: none; }
        .menu-item a {
            display: block; padding: 11px 15px; color: #5a6b7c; text-decoration: none; border-radius: 8px; font-weight: 500; margin-bottom: 5px; transition: all 0.2s;
        }
        .menu-item a:hover { background: #f0f7ff; color: #3a97f9; }
        .menu-item.active a { background: #3a97f9; color: white; }
        
        .content-area { display: flex; flex-direction: column; gap: 25px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .card h2 { font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; color: #34495e; }
        
        /* Поиск */
        .search-panel {
            background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 15px;
            border-radius: 10px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
        }
        .search-input { flex: 2; min-width: 180px; padding: 10px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; }
        .search-select, .search-date { flex: 1; min-width: 140px; padding: 10px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; background: #fff; }
        .btn-search { background: #3a97f9; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-reset { background: #eef5fc; color: #5a6b7c; text-decoration: none; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; border: 1.5px solid #d0dfef; }

        /* Карточки */
        .offer-item { display: block; border: 1.5px solid #eef5fc; padding: 20px; border-radius: 10px; margin-bottom: 15px; background: #fcfdfe; }
        .offer-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .category-badge { background: #eef5fc; color: #3a97f9; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .status-badge { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
        .status-badge.active, .status-badge.accepted { background: #e3fcef; color: #27ae60; }
        .status-badge.completed { background: #f2f4f4; color: #7f8c8d; }
        .status-badge.pending { background: #fef5e7; color: #f39c12; }
        .status-badge.rejected { background: #ffe3e3; color: #d63031; }
        
        .author-tag { font-size: 13px; color: #7f8c8d; }
        .offer-title { font-size: 17px; color: #2c3e50; margin-bottom: 8px; font-weight: 600; word-break: break-word; }
        .offer-desc { font-size: 14px; color: #5a6b7c; line-height: 1.5; margin-bottom: 10px; word-break: break-word; }
        .no-data { text-align: center; color: #95adc9; font-style: italic; padding: 30px 0; font-size: 14px; }
        
        /* Кнопки действий */
        .author-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; border-top: 1px dashed #d0dfef; padding-top: 12px; }
        .btn-edit { background: #f39c12; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-complete { background: #2ecc71; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-respond { background: #3a97f9; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-reject { background: #e74c3c; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-chat-toggle { background: #eef5fc; color: #3a97f9; border: 1.5px solid #d0dfef; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .responded-label { font-size: 13px; color: #27ae60; font-weight: 600; padding: 6px 0; }

        /* Чат внутри отклика */
        .chat-box {
            margin-top: 15px; background: #f4f8fc; border: 1.5px solid #d0dfef;
            border-radius: 10px; padding: 15px; display: none;
        }
        .chat-box.open { display: block; }
        .chat-messages {
            max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;
        }
        .chat-msg {
            padding: 10px 12px; border-radius: 8px; font-size: 13px; max-width: 80%; word-break: break-word;
        }
        .chat-msg.mine { background: #3a97f9; color: white; align-self: flex-end; }
        .chat-msg.theirs { background: white; border: 1px solid #d0dfef; color: #2c3e50; align-self: flex-start; }
        .chat-msg-meta { font-size: 10px; opacity: 0.8; margin-bottom: 4px; display: block; }
        .chat-form { display: flex; gap: 8px; }
        .chat-form input { flex: 1; padding: 9px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 13px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #5a6b7c; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 11px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; }
        .btn-submit { padding: 12px 24px; background: #3a97f9; border: none; border-radius: 8px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert.success { background: #e3fcef; color: #27ae60; border-left: 4px solid #27ae60; }
        .alert.error { background: #ffe3e3; color: #d63031; border-left: 4px solid #d63031; }
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(44, 62, 80, 0.4); backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eef5fc; padding-bottom: 10px; }
        .btn-close-modal { background: none; border: none; font-size: 24px; color: #7f8c8d; cursor: pointer; }
            /* Кнопка переключения темы */
            .btn-theme-toggle {
            background: #f8fbfe;
            border: 1.5px solid #d0dfef;
            color: #2c3e50;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .btn-theme-toggle:hover {
            border-color: #3a97f9;
            background: #eef5fc;
        }

        /* Тёмная тема */
        body.dark-theme {
            background: #121822;
            color: #e2e8f0;
        }
        body.dark-theme .header,
        body.dark-theme .sidebar-menu,
        body.dark-theme .card,
        body.dark-theme .modal-content {
            background: #1c2432;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
            color: #e2e8f0;
        }
        body.dark-theme .header h1,
        body.dark-theme .card h2,
        body.dark-theme .modal-header h2,
        body.dark-theme .offer-title {
            color: #f1f5f9;
            border-bottom-color: #2d3748;
        }
        body.dark-theme .profile-widget,
        body.dark-theme .search-panel,
        body.dark-theme .offer-item,
        body.dark-theme .chat-box {
            background: #161d29;
            border-color: #2d3748;
        }
        body.dark-theme .btn-theme-toggle {
            background: #161d29;
            border-color: #2d3748;
            color: #facc15;
        }
        body.dark-theme .btn-theme-toggle:hover {
            border-color: #3a97f9;
        }
        body.dark-theme .menu-item a {
            color: #cbd5e1;
        }
        body.dark-theme .menu-item a:hover {
            background: #253042;
            color: #60a5fa;
        }
        body.dark-theme .menu-item.active a {
            background: #3a97f9;
            color: #ffffff;
        }
        body.dark-theme .offer-desc,
        body.dark-theme .author-tag,
        body.dark-theme .widget-info .email,
        body.dark-theme .form-group label,
        body.dark-theme .sidebar-menu h3 {
            color: #94a3b8;
        }
        body.dark-theme .search-input,
        body.dark-theme .search-select,
        body.dark-theme .search-date,
        body.dark-theme .form-group input,
        body.dark-theme .form-group textarea,
        body.dark-theme .form-group select,
        body.dark-theme .chat-form input {
            background: #121822;
            border-color: #2d3748;
            color: #f1f5f9;
        }
        body.dark-theme .btn-reset,
        body.dark-theme .btn-chat-toggle,
        body.dark-theme .category-badge {
            background: #253042;
            border-color: #334155;
            color: #93c5fd;
        }
        body.dark-theme .chat-msg.theirs {
            background: #253042;
            border-color: #334155;
            color: #f1f5f9;
        }
        body.dark-theme .author-actions {
            border-top-color: #2d3748;
        }
    </style>
</head>
<body>
<div class="container">
<header class="header">
        <h1> Vzаимопомощь</h1>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button class="btn-open-modal" onclick="openModal('offerModal')">+ Создать заявку</button>
            <button type="button" id="themeToggle" class="btn-theme-toggle" title="Сменить тему">🌙</button>
            <div class="profile-widget">
                <div class="widget-info">
                    <span class="username"><?= htmlspecialchars($user_profile['name']) ?></span>
                    <span class="email"><?= htmlspecialchars($user_profile['email']) ?></span>
                </div>
                <div class="widget-actions">
                    <a class="widget-link" href="profile.php">Мой профиль</a>
                    <a class="logout-link" href="login.php?logout=1">Выйти</a>
                </div>
            </div>
        </div>
    </header>
    <?php if ($msg): ?>
        <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <div class="main-grid">
        <aside class="sidebar-menu">
            <h3>Публикации</h3>
            <ul class="menu-list">
                <li class="menu-item <?= $filter === 'active' ? 'active' : '' ?>"><a href="index.php?filter=active">🔥 Актуальные</a></li>
                <li class="menu-item <?= $filter === 'completed' ? 'active' : '' ?>"><a href="index.php?filter=completed">✅ Мои завершенные</a></li>
                <li class="menu-item <?= $filter === 'my' ? 'active' : '' ?>"><a href="index.php?filter=my">📁 Мои публикации</a></li>
            </ul>
            <h3>Отклики и чаты</h3>
            <ul class="menu-list">
                <li class="menu-item <?= $filter === 'incoming' ? 'active' : '' ?>"><a href="index.php?filter=incoming">📥 Входящие отклики</a></li>
                <li class="menu-item <?= $filter === 'outgoing' ? 'active' : '' ?>"><a href="index.php?filter=outgoing">📤 Исходящие отклики</a></li>
            </ul>
        </aside>
        <main class="content-area">
            <section class="card">
                <h2>
                    <?php
                    if ($filter === 'active') echo 'Актуальные публикации студентов';
                    if ($filter === 'completed') echo 'Мои завершенные публикации';
                    if ($filter === 'my') echo 'Управление моими публикациями';
                    if ($filter === 'incoming') echo 'Входящие отклики на мои публикации';
                    if ($filter === 'outgoing') echo 'Мои исходящие отклики';
                    ?>
                </h2>
                <!-- Вкладки публикаций -->
                <?php if (in_array($filter, ['active', 'completed', 'my'])): ?>
                    <form action="index.php" method="GET" class="search-panel">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="text" name="q" class="search-input" placeholder="Поиск по теме или описанию..." value="<?= htmlspecialchars($search_query) ?>">
                        <select name="category" class="search-select">
                            <option value="0">Все предметы</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $search_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" class="search-date" value="<?= htmlspecialchars($search_date) ?>">
                        <button type="submit" class="btn-search">🔍 Найти</button>
                        <?php if ($search_query !== '' || $search_category > 0 || $search_date !== ''): ?>
                            <a href="index.php?filter=<?= htmlspecialchars($filter) ?>" class="btn-reset">✖ Сбросить</a>
                        <?php endif; ?>
                    </form>
                    <?php if (empty($offers_list)): ?>
                        <p class="no-data">Публикаций не найдено</p>
                    <?php else: ?>
                        <?php foreach ($offers_list as $offer): ?>
                            <div class="offer-item">
                                <div class="offer-meta">
                                    <div>
                                        <span class="category-badge"><?= htmlspecialchars($offer['category_name']) ?></span>
                                        <span class="status-badge <?= $offer['status'] === 'Активно' ? 'active' : 'completed' ?>"><?= htmlspecialchars($offer['status']) ?></span>
                                    </div>
                                    <span class="author-tag">📅 <?= date('d.m.Y', strtotime($offer['created_at'])) ?> | Автор: <a href="user.php?id=<?= $offer['user_id']?>" style="color: #3a97f9; text-decoration: none; font-weight: 600;"><?= htmlspecialchars($offer['author_name']) ?></a></span>
                                </div>
                                <div class="offer-title"><?= htmlspecialchars($offer['title']) ?></div>
                                <div class="offer-desc"><?= htmlspecialchars($offer['description']) ?></div>
                                <div class="author-actions">
                                    <?php if ($offer['user_id'] == $current_user_id): ?>
                                        <button class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($offer)) ?>)">✏️ Редактировать</button>
                                        <?php if ($offer['status'] === 'Активно'): ?>
                                            <form action="index.php?filter=<?= $filter ?>" method="POST" style="display:inline;" onsubmit="return confirm('Завершить публикацию?');">
                                                <input type="hidden" name="complete_offer" value="1">
                                                <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                                <button type="submit" class="btn-complete">✅ Завершить</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (in_array($offer['id'], $my_responded_offers)): ?>
                                            <span class="responded-label">✔ Вы уже откликнулись (см. в «Исходящих»)</span>
                                        <?php elseif ($offer['status'] === 'Активно'): ?>
                                            <button class="btn-respond" onclick="openRespondModal(<?= $offer['id'] ?>, '<?= htmlspecialchars(addslashes($offer['title'])) ?>')">💬 Откликнуться</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <!-- Вкладки входящих и исходящих откликов -->
                <?php else: ?>
                    <?php if (empty($responses_list)): ?>
                        <p class="no-data">Список откликов пуст</p>
                    <?php else: ?>
                        <?php foreach ($responses_list as $resp): ?>
                            <?php
                                $badgeClass = 'pending';
                                if ($resp['status'] === 'Принята') $badgeClass = 'accepted';
                                if (in_array($resp['status'], ['Отклонена', 'Отменена'])) $badgeClass = 'rejected';
                                $isChatOpen = (isset($_GET['open_chat']) && intval($_GET['open_chat']) === intval($resp['id']));
                            ?>
                            <div class="offer-item">
                                <div class="offer-meta">
                                    <div>
                                        <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($resp['status']) ?></span>
                                        <span class="category-badge">Публикация: <?= htmlspecialchars($resp['offer_title']) ?></span>
                                    </div>
                                    <span class="author-tag">
                                        📅 <?= date('d.m.Y H:i', strtotime($resp['created_at'])) ?> | 
                                        <?php if ($filter === 'incoming'): ?>
                                            От кого: <a href="user.php?id=<?= $resp['sender_id'] ?>" style="color:#3a97f9;font-weight:600;text-decoration:none;"><?= htmlspecialchars($resp['sender_name']) ?></a>
                                        <?php else: ?>
                                            Кому: <a href="user.php?id=<?= $resp['receiver_id'] ?>" style="color:#3a97f9;font-weight:600;text-decoration:none;"><?= htmlspecialchars($resp['receiver_name']) ?></a>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="author-actions">
                                    <button type="button" class="btn-chat-toggle" onclick="toggleChat(<?= $resp['id'] ?>)">
                                        💬 Чат (<?= count($chat_messages_map[$resp['id']] ?? []) ?>)
                                    </button>

                                    <?php if ($resp['status'] === 'Ожидает решения'): ?>
                                        <?php if ($filter === 'incoming' && $resp['receiver_id'] == $current_user_id): ?>
                                            <form action="index.php?filter=incoming" method="POST" style="display:inline;">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Принята">
                                                <button type="submit" class="btn-complete">✅ Принять</button>
                                            </form>
                                            <form action="index.php?filter=incoming" method="POST" style="display:inline;">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Отклонена">
                                                <button type="submit" class="btn-reject">✖ Отклонить</button>
                                            </form>
                                        <?php elseif ($filter === 'outgoing' && $resp['sender_id'] == $current_user_id): ?>
                                            <form action="index.php?filter=outgoing" method="POST" style="display:inline;" onsubmit="return confirm('Отменить свой отклик?');">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Отменена">
                                                <button type="submit" class="btn-reject">🚫 Отменить отклик</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-box <?= $isChatOpen ? 'open' : '' ?>" id="chat-box-<?= $resp['id'] ?>">
                                    <div class="chat-messages">
                                        <?php foreach (($chat_messages_map[$resp['id']] ?? []) as $m): ?>
                                            <div class="chat-msg <?= $m['user_id'] == $current_user_id ? 'mine' : 'theirs' ?>">
                                                <span class="chat-msg-meta"><?= htmlspecialchars($m['author_name']) ?> • <?= date('H:i', strtotime($m['created_at'])) ?></span>
                                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (!in_array($resp['status'], ['Отклонена', 'Отменена'])): ?>
                                        <form action="index.php?filter=<?= $filter ?>" method="POST" class="chat-form">
                                            <input type="hidden" name="send_chat_message" value="1">
                                            <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                            <input type="text" name="chat_message" placeholder="Напишите сообщение..." required>
                                            <button type="submit" class="btn-respond">Отправить</button>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size:12px; color:#7f8c8d; text-align:center;">Чат закрыт, так как отклик был отклонён или отменён.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<!-- Окно создания публикации -->
<div class="modal-overlay" id="offerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Описать новую проблему</h2>
            <button class="btn-close-modal" onclick="closeModal('offerModal')">&times;</button>
        </div>
        <form action="index.php?filter=<?= $filter ?>" method="POST">
            <input type="hidden" name="create_offer" value="1">
            <div class="form-group">
                <label for="category_id">Предмет / дисциплина</label>
                <select name="category_id" id="category_id" required>
                    <option value="" disabled selected>Выберите предмет...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="title">Тема (Краткая суть)</label>
                <input type="text" name="title" id="title" required>
            </div>
            <div class="form-group">
                <label for="description">Подробное описание проблемы</label>
                <textarea name="description" id="description" rows="4" placeholder="Опишите детали задачи..." required></textarea>
            </div>
            <button type="submit" class="btn-submit">Опубликовать</button>
        </form>
    </div>
</div>
<!-- Окно редактирования публикации -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать публикацию</h2>
            <button class="btn-close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form action="index.php?filter=<?= $filter ?>" method="POST">
            <input type="hidden" name="edit_offer" value="1">
            <input type="hidden" name="offer_id" id="edit_offer_id">
            <div class="form-group">
                <label for="edit_category_id">Предмет / дисциплина</label>
                <select name="category_id" id="edit_category_id" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_title">Тема</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Описание проблемы</label>
                <textarea name="description" id="edit_description" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label for="edit_status">Статус</label>
                <select name="status" id="edit_status">
                    <option value="Активно">Активно</option>
                    <option value="Завершено">Завершено</option>
                </select>
            </div>
            <button type="submit" class="btn-submit" style="background: #f39c12;">Сохранить изменения</button>
        </form>
    </div>
</div>
<!-- Окно отправки отклика -->
<div class="modal-overlay" id="respondModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Откликнуться на заявку</h2>
            <button class="btn-close-modal" onclick="closeModal('respondModal')">&times;</button>
        </div>
        <p id="respond_offer_title" style="font-size: 14px; color: #3a97f9; font-weight: 600; margin-bottom: 15px;"></p>
        <form action="index.php?filter=<?= $filter ?>" method="POST">
            <input type="hidden" name="send_response" value="1">
            <input type="hidden" name="offer_id" id="respond_offer_id">
            <div class="form-group">
                <label for="respond_message">Ваше сообщение автору (обязательно)</label>
                <textarea name="message" id="respond_message" rows="4" placeholder="Напишите, чем можете помочь..." required></textarea>
            </div>
            <button type="submit" class="btn-submit">Отправить отклик</button>
        </form>
    </div>
</div>
<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function openEditModal(offer) {
    document.getElementById('edit_offer_id').value = offer.id;
    document.getElementById('edit_category_id').value = offer.category_id;
    document.getElementById('edit_title').value = offer.title;
    document.getElementById('edit_description').value = offer.description;
    document.getElementById('edit_status').value = offer.status;
    openModal('editModal');
}
function openRespondModal(offerId, title) {
    document.getElementById('respond_offer_id').value = offerId;
    document.getElementById('respond_offer_title').textContent = 'Публикация: ' + title;
    document.getElementById('respond_message').value = '';
    openModal('respondModal');
}
function toggleChat(responseId) {
    const box = document.getElementById('chat-box-' + responseId);
    box.classList.toggle('open');
}
const themeToggleBtn = document.getElementById('themeToggle');
const savedTheme = localStorage.getItem('site_theme');

if (savedTheme === 'dark') {
    document.body.classList.add('dark-theme');
    themeToggleBtn.textContent = '☀️';
}

themeToggleBtn.addEventListener('click', () => {
    document.body.classList.toggle('dark-theme');
    const isDark = document.body.classList.contains('dark-theme');
    themeToggleBtn.textContent = isDark ? '☀️' : '🌙';
    localStorage.setItem('site_theme', isDark ? 'dark' : 'light');
});
</script>
</body>
</html>