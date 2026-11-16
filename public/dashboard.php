<?php
session_start();
ob_start();
require_once __DIR__ . '/../config/database.php';
$pdo = getDatabaseConnection();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';
$allowed_filters = ['my', 'completed', 'incoming', 'outgoing'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters) ? $_GET['filter'] : 'my';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_offer'])) {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        if ($category_id > 0 && !empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('INSERT INTO offers (user_id, category_id, title, description, contact_link, status) VALUES (?, ?, ?, ?, "", "Активно")');
            if ($stmt->execute([$current_user_id, $category_id, $title, $description])) {
                header("Location: dashboard.php?filter=my&success=1");
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
    if (isset($_POST['send_response'])) {
        $offer_id = intval($_POST['offer_id']);
        $first_message = trim($_POST['message'] ?? '');
        if ($first_message === '') {
            $msg = 'Ошибка: нельзя отправить пустое сообщение!';
            $msg_type = 'error';
        } else {
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
                    header("Location: dashboard.php?filter=outgoing&open_chat=" . $response_id);
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $msg = ($e->getCode() == 23000) 
                        ? 'Ошибка: вы уже отправляли отклик на эту публикацию!' 
                        : 'Ошибка при создании отклика.';
                    $msg_type = 'error';
                }
            }
        }
    }
    if (isset($_POST['change_response_status'])) {
        $response_id = intval($_POST['response_id']);
        $new_status = $_POST['new_status'] ?? '';
        $stmt = $pdo->prepare('SELECT * FROM responses WHERE id = ?');
        $stmt->execute([$response_id]);
        $resp = $stmt->fetch();
        if (!$resp || $resp['status'] !== 'Ожидает решения') {
            $msg = 'Ошибка: отклик не найден или решение уже принято.';
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
                $msg = 'Ошибка доступа!';
                $msg_type = 'error';
            }
        }
    }
    if (isset($_POST['send_chat_message'])) {
        $response_id = intval($_POST['response_id']);
        $chat_text = trim($_POST['chat_message'] ?? '');
        if ($chat_text !== '') {
            $stmt = $pdo->prepare('SELECT id, status FROM responses WHERE id = ? AND (sender_id = ? OR receiver_id = ?)');
            $stmt->execute([$response_id, $current_user_id, $current_user_id]);
            $resp = $stmt->fetch();
            if ($resp && !in_array($resp['status'], ['Отклонена', 'Отменена'])) {
                $stmtMsg = $pdo->prepare('INSERT INTO messages (response_id, user_id, message) VALUES (?, ?, ?)');
                $stmtMsg->execute([$response_id, $current_user_id, $chat_text]);
                header("Location: dashboard.php?filter=" . urlencode($filter) . "&open_chat=" . $response_id);
                exit;
            }
        }
    }
}
$respond_target_offer = null;
if (isset($_GET['respond_to'])) {
    $stmt = $pdo->prepare('SELECT id, title FROM offers WHERE id = ? AND status = "Активно" AND user_id != ?');
    $stmt->execute([intval($_GET['respond_to']), $current_user_id]);
    $respond_target_offer = $stmt->fetch();
}
$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$stmt->execute([$current_user_id]);
$user_profile = $stmt->fetch();
$categories = $pdo->query('SELECT * FROM categories')->fetchAll();
$offers_list = [];
$responses_list = [];
$chat_messages_map = [];
if (in_array($filter, ['completed', 'my'])) {
    $sql = 'SELECT offers.*, users.name as author_name, categories.name as category_name 
            FROM offers 
            JOIN users ON offers.user_id = users.id 
            JOIN categories ON offers.category_id = categories.id 
            WHERE offers.user_id = ? ';
    $params = [$current_user_id];
    if ($filter === 'completed') {
        $sql .= 'AND offers.status = "Завершено" ';
    }
    $sql .= 'ORDER BY offers.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offers_list = $stmt->fetchAll();
} elseif ($filter === 'incoming') {
    $stmt = $pdo->prepare('
        SELECT r.*, o.title as offer_title, o.status as offer_status, u.name as sender_name 
        FROM responses r JOIN offers o ON r.offer_id = o.id JOIN users u ON r.sender_id = u.id
        WHERE r.receiver_id = ? ORDER BY r.created_at DESC
    ');
    $stmt->execute([$current_user_id]);
    $responses_list = $stmt->fetchAll();
} elseif ($filter === 'outgoing') {
    $stmt = $pdo->prepare('
        SELECT r.*, o.title as offer_title, o.status as offer_status, u.name as receiver_name 
        FROM responses r JOIN offers o ON r.offer_id = o.id JOIN users u ON r.receiver_id = u.id
        WHERE r.sender_id = ? ORDER BY r.created_at DESC
    ');
    $stmt->execute([$current_user_id]);
    $responses_list = $stmt->fetchAll();
}
if (!empty($responses_list)) {
    $resp_ids = array_column($responses_list, 'id');
    $placeholders = implode(',', array_fill(0, count($resp_ids), '?'));
    $stmtMsgs = $pdo->prepare("
        SELECT m.*, u.name as author_name FROM messages m JOIN users u ON m.user_id = u.id 
        WHERE m.response_id IN ($placeholders) ORDER BY m.created_at ASC
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
    <title>Vzаимопомощь — Мои заявки и чаты</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef5fc; color: #2c3e50; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 15px 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { font-size: 22px; color: #2c3e50; font-weight: 600; }
        .profile-widget { background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 10px 15px; border-radius: 10px; display: flex; align-items: center; gap: 15px; }
        .btn-open-modal { background: #3a97f9; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-catalog { background: #eef5fc; color: #3a97f9; border: 1.5px solid #d0dfef; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; }
        .btn-theme-toggle { background: #f8fbfe; border: 1.5px solid #d0dfef; color: #2c3e50; width: 40px; height: 40px; border-radius: 10px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .widget-info { text-align: right; }
        .widget-info .username { font-weight: 600; color: #3a97f9; display: block; font-size: 14px; }
        .widget-info .email { font-size: 11px; color: #7f8c8d; }
        .widget-actions { display: flex; flex-direction: column; gap: 2px; }
        .widget-link { font-size: 12px; color: #3a97f9; text-decoration: none; font-weight: 600; }
        .logout-link { font-size: 11px; color: #e74c3c; text-decoration: none; }
        .main-grid { display: grid; grid-template-columns: 260px 1fr; gap: 25px; }
        @media (max-width: 768px) { .main-grid { grid-template-columns: 1fr; } }
        .sidebar-menu { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); height: fit-content; }
        .sidebar-menu h3 { font-size: 13px; text-transform: uppercase; color: #7f8c8d; margin: 15px 0 10px 0; }
        .sidebar-menu h3:first-child { margin-top: 0; }
        .menu-list { list-style: none; }
        .menu-item a { display: block; padding: 11px 15px; color: #5a6b7c; text-decoration: none; border-radius: 8px; font-weight: 500; margin-bottom: 5px; }
        .menu-item.active a { background: #3a97f9; color: white; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .card h2 { font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; color: #34495e; }
        .offer-item { border: 1.5px solid #eef5fc; padding: 20px; border-radius: 10px; margin-bottom: 15px; background: #fcfdfe; }
        .offer-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .category-badge { background: #eef5fc; color: #3a97f9; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .status-badge { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
        .status-badge.active, .status-badge.accepted { background: #e3fcef; color: #27ae60; }
        .status-badge.completed { background: #f2f4f4; color: #7f8c8d; }
        .status-badge.pending { background: #fef5e7; color: #f39c12; }
        .status-badge.rejected { background: #ffe3e3; color: #d63031; }
        .offer-title { font-size: 17px; font-weight: 600; margin-bottom: 8px; word-break: break-word; }
        .offer-desc { font-size: 14px; color: #5a6b7c; margin-bottom: 10px; word-break: break-word; }
        .author-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; border-top: 1px dashed #d0dfef; padding-top: 12px; }
        .btn-edit { background: #f39c12; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-complete { background: #2ecc71; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-reject { background: #e74c3c; color: white; border: none; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-chat-toggle { background: #eef5fc; color: #3a97f9; border: 1.5px solid #d0dfef; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
        .chat-box { margin-top: 15px; background: #f4f8fc; border: 1.5px solid #d0dfef; border-radius: 10px; padding: 15px; display: none; }
        .chat-box.open { display: block; }
        .chat-messages { max-height: 250px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
        .chat-msg { padding: 10px 12px; border-radius: 8px; font-size: 13px; max-width: 80%; word-break: break-word; }
        .chat-msg.mine { background: #3a97f9; color: white; align-self: flex-end; }
        .chat-msg.theirs { background: white; border: 1px solid #d0dfef; align-self: flex-start; }
        .chat-form { display: flex; gap: 8px; }
        .chat-form input { flex: 1; padding: 9px 12px; border: 1.5px solid #d0dfef; border-radius: 8px; }
        .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert.success { background: #e3fcef; color: #27ae60; border-left: 4px solid #27ae60; }
        .alert.error { background: #ffe3e3; color: #d63031; border-left: 4px solid #d63031; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(44, 62, 80, 0.4); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eef5fc; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; color: #5a6b7c; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 11px; border: 1.5px solid #d0dfef; border-radius: 8px; }
        .btn-submit { padding: 12px 24px; background: #3a97f9; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; }
        .no-data { text-align: center; color: #95adc9; padding: 30px 0; font-style: italic; }
        body.dark-theme { background: #121822; color: #e2e8f0; color-scheme: dark; }
        body.dark-theme .header, body.dark-theme .sidebar-menu, body.dark-theme .card, body.dark-theme .modal-content { background: #1c2432; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35); color: #e2e8f0; }
        body.dark-theme .header h1, body.dark-theme .card h2, body.dark-theme .modal-header h2, body.dark-theme .offer-title { color: #f1f5f9; border-bottom-color: #2d3748; }
        body.dark-theme .profile-widget, body.dark-theme .offer-item, body.dark-theme .chat-box { background: #161d29; border-color: #2d3748; }
        body.dark-theme .btn-theme-toggle { background: #161d29; border-color: #2d3748; color: #facc15; }
        body.dark-theme .menu-item a { color: #cbd5e1; }
        body.dark-theme .menu-item.active a { background: #3a97f9; color: #ffffff; }
        body.dark-theme .offer-desc, body.dark-theme .widget-info .email, body.dark-theme .form-group label, body.dark-theme .sidebar-menu h3 { color: #94a3b8; }
        body.dark-theme .form-group input, body.dark-theme .form-group textarea, body.dark-theme .form-group select, body.dark-theme .chat-form input { background: #121822; border-color: #2d3748; color: #f1f5f9; }
        body.dark-theme .btn-catalog, body.dark-theme .btn-chat-toggle, body.dark-theme .category-badge { background: #253042; border-color: #334155; color: #93c5fd; }
        body.dark-theme .chat-msg.theirs { background: #253042; border-color: #334155; color: #f1f5f9; }
        body.dark-theme .author-actions { border-top-color: #2d3748; }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1>Vzаимопомощь — Кабинет</h1>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="index.php" class="btn-catalog">← В общий каталог</a>
            <button class="btn-open-modal" onclick="document.getElementById('offerModal').style.display='flex'">+ Создать заявку</button>
            <button type="button" id="themeToggle" class="btn-theme-toggle" title="Сменить тему">🌙</button>
            <div class="profile-widget">
                <div class="widget-info">
                    <span class="username"><?= htmlspecialchars($user_profile['name']) ?></span>
                    <span class="email"><?= htmlspecialchars($user_profile['email']) ?></span>
                </div>
                <div class="widget-actions">
                    <a class="widget-link" href="profile.php">Настройки</a>
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
            <h3>Мои публикации</h3>
            <ul class="menu-list">
                <li class="menu-item <?= $filter === 'my' ? 'active' : '' ?>"><a href="dashboard.php?filter=my">📁 Все мои заявки</a></li>
                <li class="menu-item <?= $filter === 'completed' ? 'active' : '' ?>"><a href="dashboard.php?filter=completed">✅ Завершенные</a></li>
            </ul>
            <h3>Отклики и чаты</h3>
            <ul class="menu-list">
                <li class="menu-item <?= $filter === 'incoming' ? 'active' : '' ?>"><a href="dashboard.php?filter=incoming"> Входящие отклики</a></li>
                <li class="menu-item <?= $filter === 'outgoing' ? 'active' : '' ?>"><a href="dashboard.php?filter=outgoing"> Исходящие отклики</a></li>
            </ul>
        </aside>
        <main class="content-area">
            <section class="card">
                <h2>
                    <?php
                    if ($filter === 'completed') echo 'Мои завершенные публикации';
                    if ($filter === 'my') echo 'Управление моими публикациями';
                    if ($filter === 'incoming') echo 'Входящие отклики на мои публикации';
                    if ($filter === 'outgoing') echo 'Мои исходящие отклики';
                    ?>
                </h2>

                <?php if (in_array($filter, ['completed', 'my'])): ?>
                    <?php if (empty($offers_list)): ?>
                        <p class="no-data">У вас пока нет публикаций в этом разделе</p>
                    <?php else: ?>
                        <?php foreach ($offers_list as $offer): ?>
                            <div class="offer-item">
                                <div class="offer-meta">
                                    <div>
                                        <span class="category-badge"><?= htmlspecialchars($offer['category_name']) ?></span>
                                        <span class="status-badge <?= $offer['status'] === 'Активно' ? 'active' : 'completed' ?>"><?= htmlspecialchars($offer['status']) ?></span>
                                    </div>
                                    <span style="font-size:13px; color:#7f8c8d;"><?= date('d.m.Y', strtotime($offer['created_at'])) ?></span>
                                </div>
                                <div class="offer-title"><?= htmlspecialchars($offer['title']) ?></div>
                                <div class="offer-desc"><?= nl2br(htmlspecialchars($offer['description'])) ?></div>
                                <div class="author-actions">
                                    <button class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($offer)) ?>)">✏️ Редактировать</button>
                                    <?php if ($offer['status'] === 'Активно'): ?>
                                        <form action="dashboard.php?filter=<?= $filter ?>" method="POST" style="display:inline;" onsubmit="return confirm('Завершить публикацию?');">
                                            <input type="hidden" name="complete_offer" value="1">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <button type="submit" class="btn-complete">✅ Завершить</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                                    <span style="font-size:13px; color:#7f8c8d;">
                                        <?= date('d.m.Y H:i', strtotime($resp['created_at'])) ?> | 
                                        <?php if ($filter === 'incoming'): ?>
                                            От кого: <a href="user.php?id=<?= $resp['sender_id'] ?>" style="color:#3a97f9;font-weight:600;text-decoration:none;"><?= htmlspecialchars($resp['sender_name']) ?></a>
                                        <?php else: ?>
                                            Кому: <a href="user.php?id=<?= $resp['receiver_id'] ?>" style="color:#3a97f9;font-weight:600;text-decoration:none;"><?= htmlspecialchars($resp['receiver_name']) ?></a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="author-actions">
                                    <button type="button" class="btn-chat-toggle" onclick="document.getElementById('chat-box-<?= $resp['id'] ?>').classList.toggle('open')">
                                        💬 Чат (<?= count($chat_messages_map[$resp['id']] ?? []) ?>)
                                    </button>
                                    <?php if ($resp['status'] === 'Ожидает решения'): ?>
                                        <?php if ($filter === 'incoming'): ?>
                                            <form action="dashboard.php?filter=incoming" method="POST" style="display:inline;">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Принята">
                                                <button type="submit" class="btn-complete">✅ Принять</button>
                                            </form>
                                            <form action="dashboard.php?filter=incoming" method="POST" style="display:inline;">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Отклонена">
                                                <button type="submit" class="btn-reject">✖ Отклонить</button>
                                            </form>
                                        <?php else: ?>
                                            <form action="dashboard.php?filter=outgoing" method="POST" style="display:inline;" onsubmit="return confirm('Отменить свой отклик?');">
                                                <input type="hidden" name="change_response_status" value="1">
                                                <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                                <input type="hidden" name="new_status" value="Отменена">
                                                <button type="submit" class="btn-reject">Отменить отклик</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="chat-box <?= $isChatOpen ? 'open' : '' ?>" id="chat-box-<?= $resp['id'] ?>">
                                    <div class="chat-messages">
                                    <div class="chat-msg <?= $m['user_id'] == $current_user_id ? 'mine' : 'theirs' ?>">
                                                <small style="display:block; opacity:0.8; margin-bottom:4px;"><?= htmlspecialchars($m['author_name']) ?> • <?= date('H:i', strtotime($m['created_at'])) ?></small>
                                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!in_array($resp['status'], ['Отклонена', 'Отменена'])): ?>
                                        <form action="dashboard.php?filter=<?= $filter ?>" method="POST" class="chat-form">
                                            <input type="hidden" name="send_chat_message" value="1">
                                            <input type="hidden" name="response_id" value="<?= $resp['id'] ?>">
                                            <input type="text" name="chat_message" placeholder="Напишите сообщение..." required>
                                            <button type="submit" class="btn-open-modal">Отправить</button>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size:12px; color:#7f8c8d; text-align:center;">Чат закрыт, так как отклик был отклонён или отменён.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<div class="modal-overlay" id="offerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Описать новую проблему</h2>
            <button style="background:none;border:none;font-size:24px;color:#7f8c8d;cursor:pointer;" onclick="document.getElementById('offerModal').style.display='none'">&times;</button>
        </div>
        <form action="dashboard.php?filter=my" method="POST">
            <input type="hidden" name="create_offer" value="1">
            <div class="form-group">
                <label>Предмет</label>
                <select name="category_id" required>
                    <option value="" disabled selected>Выберите предмет...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Тема </label><input type="text" name="title" required></div>
            <div class="form-group"><label>Подробное описание проблемы</label><textarea name="description" rows="4" required></textarea></div>
            <button type="submit" class="btn-submit">Опубликовать</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать публикацию</h2>
            <button style="background:none;border:none;font-size:24px;color:#7f8c8d;cursor:pointer;" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
        </div>
        <form action="dashboard.php?filter=<?= $filter ?>" method="POST">
            <input type="hidden" name="edit_offer" value="1">
            <input type="hidden" name="offer_id" id="edit_offer_id">
            <div class="form-group">
                <label>Предмет / дисциплина</label>
                <select name="category_id" id="edit_category_id" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Тема</label><input type="text" name="title" id="edit_title" required></div>
            <div class="form-group"><label>Описание проблемы</label><textarea name="description" id="edit_description" rows="4" required></textarea></div>
            <div class="form-group">
                <label>Статус</label>
                <select name="status" id="edit_status">
                    <option value="Активно">Активно</option>
                    <option value="Завершено">Завершено</option>
                </select>
            </div>
            <button type="submit" class="btn-submit" style="background:#f39c12;">Сохранить изменения</button>
        </form>
    </div>
</div>

<?php if ($respond_target_offer): ?>
<div class="modal-overlay" id="respondModal" style="display:flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Откликнуться на заявку</h2>
            <a href="index.php" style="text-decoration:none;font-size:24px;color:#7f8c8d;">&times;</a>
        </div>
        <p style="font-size:14px;color:#3a97f9;font-weight:600;margin-bottom:15px;">Публикация: <?= htmlspecialchars($respond_target_offer['title']) ?></p>
        <form action="dashboard.php" method="POST">
            <input type="hidden" name="send_response" value="1">
            <input type="hidden" name="offer_id" value="<?= $respond_target_offer['id'] ?>">
            <div class="form-group">
                <label>Ваше сообщение автору</label>
                <textarea name="message" rows="4" placeholder="Напишите, чем можете помочь..." required></textarea>
            </div>
            <button type="submit" class="btn-submit">Отправить отклик</button>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
function openEditModal(offer) {
    document.getElementById('edit_offer_id').value = offer.id;
    document.getElementById('edit_category_id').value = offer.category_id;
    document.getElementById('edit_title').value = offer.title;
    document.getElementById('edit_description').value = offer.description;
    document.getElementById('edit_status').value = offer.status;
    document.getElementById('editModal').style.display = 'flex';
}
const themeToggleBtn = document.getElementById('themeToggle');
if (localStorage.getItem('site_theme') === 'dark') {
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