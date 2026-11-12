<?php
session_start();
ob_start();
require 'db.php';
// Контроль авторизации пользователя
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';
// Вкладка заявок
$allowed_filters = ['active', 'completed', 'my'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters) ? $_GET['filter'] : 'active';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Создание заявки
    if (isset($_POST['create_offer'])) {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $contact_link = isset($_POST['contact_link']) ? trim($_POST['contact_link']) : '';
        if ($category_id > 0 && !empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('INSERT INTO offers (user_id, category_id, title, description, contact_link, status) VALUES (?, ?, ?, ?, ?, "Активно")');
            if ($stmt->execute([$current_user_id, $category_id, $title, $description, $contact_link])) {
                header("Location: index.php?filter=active&success=1");
                exit;
            } else {
                $msg = 'Не удалось сохранить заявку.';
                $msg_type = 'error';
            }
        } else {
            $msg = 'Пожалуйста, заполните все обязательные поля!';
            $msg_type = 'error';
        }
    }
    // Завершение заявки
    if (isset($_POST['complete_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        $stmt = $pdo->prepare('UPDATE offers SET status = "Завершено" WHERE id = ? AND user_id = ?');
        if ($stmt->execute([$offer_id, $current_user_id])) {
            $msg = 'Заявка отмечена как завершенная и перенесена в архив!';
            $msg_type = 'success';
        } else {
            $msg = 'Ошибка при завершении заявки или у вас нет прав.';
            $msg_type = 'error';
        }
    }
    // Редактирование
    if (isset($_POST['edit_offer'])) {
        $offer_id = intval($_POST['offer_id']);
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $contact_link = trim($_POST['contact_link']);
        $status = trim($_POST['status']);

        if ($category_id > 0 && !empty($title) && !empty($description)) {
            $stmt = $pdo->prepare('UPDATE offers SET category_id = ?, title = ?, description = ?, contact_link = ?, status = ? WHERE id = ? AND user_id = ?');
            if ($stmt->execute([$category_id, $title, $description, $contact_link, $status, $offer_id, $current_user_id])) {
                $msg = 'Заявка успешно обновлена!';
                $msg_type = 'success';
            } else {
                $msg = 'Ошибка обновления заявки.';
                $msg_type = 'error';
            }
        } else {
            $msg = 'Заполните все текстовые поля!';
            $msg_type = 'error';
        }
    }
}
$stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$stmt->execute([$current_user_id]);
$user_profile = $stmt->fetch();

$categories = $pdo->query('SELECT * FROM categories')->fetchAll();
// Параметры поиска и фильтрации
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Платформа Взаимопомощи</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #eef5fc; color: #2c3e50; padding: 20px;
        }
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
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 4px 10px rgba(58, 151, 249, 0.15);
        }
        .btn-open-modal:hover { background: #2582e5; }
        .widget-info { text-align: right; }
        .widget-info .username { font-weight: 600; color: #3a97f9; display: block; font-size: 14px; }
        .widget-info .email { font-size: 11px; color: #7f8c8d; }
        .widget-actions { display: flex; flex-direction: column; gap: 2px; }
        .widget-link { font-size: 12px; color: #3a97f9; text-decoration: none; font-weight: 600; }
        .widget-link:hover { text-decoration: underline; }
        .logout-link { font-size: 11px; color: #e74c3c; text-decoration: none; }
        .logout-link:hover { text-decoration: underline; }
        .main-grid { display: grid; grid-template-columns: 260px 1fr; gap: 25px; }
        @media (max-width: 768px) { .main-grid { grid-template-columns: 1fr; } }
        .sidebar-menu { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); height: fit-content; }
        .sidebar-menu h3 { font-size: 14px; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; margin-bottom: 15px; }
        .menu-list { list-style: none; }
        .menu-item a {
            display: block; padding: 12px 15px; color: #5a6b7c; text-decoration: none; border-radius: 8px; font-weight: 500; margin-bottom: 5px; transition: all 0.2s;
        }
        .menu-item a:hover { background: #f0f7ff; color: #3a97f9; }
        .menu-item.active a { background: #3a97f9; color: white; }
        
        .content-area { display: flex; flex-direction: column; gap: 25px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .card h2 { font-size: 18px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; color: #34495e; }
        
        /* Панель поиска */
        .search-panel {
            background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 15px;
            border-radius: 10px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
        }
        .search-input {
            flex: 2; min-width: 180px; padding: 10px 12px; border: 1.5px solid #d0dfef;
            border-radius: 8px; font-size: 14px; background: #fff;
        }
        .search-select, .search-date {
            flex: 1; min-width: 140px; padding: 10px 12px; border: 1.5px solid #d0dfef;
            border-radius: 8px; font-size: 14px; background: #fff; color: #2c3e50;
        }
        .search-input:focus, .search-select:focus, .search-date:focus {
            outline: none; border-color: #3a97f9; box-shadow: 0 0 0 3px rgba(58, 151, 249, 0.15);
        }
        .btn-search {
            background: #3a97f9; color: white; border: none; padding: 10px 18px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-search:hover { background: #2582e5; }
        .btn-reset {
            background: #eef5fc; color: #5a6b7c; text-decoration: none; padding: 10px 14px;
            border-radius: 8px; font-size: 14px; font-weight: 600; border: 1.5px solid #d0dfef; transition: all 0.2s;
        }
        .btn-reset:hover { background: #ffe3e3; color: #d63031; border-color: #fab1a0; }

        /* Карточки заявок */
        .offer-item { 
            display: block; border: 1.5px solid #eef5fc; padding: 20px; 
            border-radius: 10px; margin-bottom: 15px; background: #fcfdfe; 
        }
        .offer-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .category-badge { background: #eef5fc; color: #3a97f9; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .status-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; }
        .status-badge.active { background: #e3fcef; color: #27ae60; }
        .status-badge.completed { background: #f2f4f4; color: #7f8c8d; }
        .author-tag { font-size: 13px; color: #7f8c8d; }
        .offer-title { font-size: 17px; color: #2c3e50; margin-bottom: 8px; font-weight: 600; word-break: break-word; }
        .offer-desc { font-size: 14px; color: #5a6b7c; line-height: 1.5; margin-bottom: 10px; word-break: break-word; }
        .offer-contact { font-size: 14px; color: #2ecc71; margin-bottom: 12px; font-weight: 600; word-break: break-word; }
        .no-data { text-align: center; color: #95adc9; font-style: italic; padding: 30px 0; font-size: 14px; }
        .author-actions { display: flex; gap: 10px; margin-top: 10px; border-top: 1px dashed #eef5fc; padding-top: 10px; }
        .btn-edit { background: #f39c12; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-edit:hover { background: #d35400; }
        .btn-complete { background: #2ecc71; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; transition: background 0.2s; }
        .btn-complete:hover { background: #27ae60; }
        /* Формы и модальные окна */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; color: #5a6b7c; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 11px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; background: #fcfdfe; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #3a97f9; box-shadow: 0 0 0 3px rgba(58, 151, 249, 0.15); }
        .btn-submit { padding: 12px 24px; background: #3a97f9; border: none; border-radius: 8px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #2582e5; }
        .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert.success { background: #e3fcef; color: #27ae60; border-left: 4px solid #27ae60; }
        .alert.error { background: #ffe3e3; color: #d63031; border-left: 4px solid #d63031; }
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(44, 62, 80, 0.4); backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; }
        .modal-header h2 { font-size: 18px; color: #2c3e50; font-weight: 600; }
        .btn-close-modal { background: none; border: none; font-size: 24px; color: #7f8c8d; cursor: pointer; }
        .btn-close-modal:hover { color: #e74c3c; }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1>Сайт взаимопомощи</h1>
        <button class="btn-open-modal" onclick="openModal('offerModal')">+ Создать заявку</button>
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
    </header>
    <?php if ($msg): ?>
        <div class="alert <?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>
    <div class="main-grid">
        <aside class="sidebar-menu">
            <h3>Категории ленты</h3>
            <ul class="menu-list">
                <li class="menu-item <?= $filter === 'active' ? 'active' : '' ?>"><a href="index.php?filter=active">🔥 Актуальные 🔥</a></li>
                <li class="menu-item <?= $filter === 'completed' ? 'active' : '' ?>"><a href="index.php?filter=completed">✅ Завершенные ✅</a></li>
                <li class="menu-item <?= $filter === 'my' ? 'active' : '' ?>"><a href="index.php?filter=my">📁 Твои заявки 📁</a></li>
            </ul>
        </aside>
        <main class="content-area">
            <section class="card">
                <h2>
                    <?php
                    if ($filter === 'active') echo 'Актуальные заявки помощи от студентов';
                    if ($filter === 'completed') echo 'Архив решенных задач';
                    if ($filter === 'my') echo 'Управление моими опубликованными заявками';
                    ?>
                </h2>
                <!-- Панель поиска -->
                <form action="index.php" method="GET" class="search-panel">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    
                    <input type="text" name="q" class="search-input" 
                           placeholder="Поиск по теме или описанию..." 
                           value="<?= htmlspecialchars($search_query) ?>">
                    <select name="category" class="search-select">
                        <option value="0">Все предметы</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $search_category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date" class="search-date" 
                           value="<?= htmlspecialchars($search_date) ?>" title="Дата публикации">
                    <button type="submit" class="btn-search">🔍 Найти</button>
                    <?php if ($search_query !== '' || $search_category > 0 || $search_date !== ''): ?>
                        <a href="index.php?filter=<?= htmlspecialchars($filter) ?>" class="btn-reset">✖ Сбросить</a>
                    <?php endif; ?>
                </form>
                <?php if (empty($offers_list)): ?>
                    <p class="no-data">По вашему запросу заявок не найдено</p>
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
                            <?php if (!empty($offer['contact_link'])): ?>
                                <div class="offer-contact">💬 Контакты: <?= htmlspecialchars($offer['contact_link']) ?></div>
                            <?php endif; ?>
                            <?php if ($offer['user_id'] == $current_user_id): ?>
                                <div class="author-actions">
                                    <button class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($offer)) ?>)">✏️ Редактировать</button>
                                    <?php if ($offer['status'] === 'Активно'): ?>
                                        <form action="index.php?filter=<?= $filter ?>" method="POST" style="display:inline;" onsubmit="return confirm('Отметить заявку как завершенную?');">
                                            <input type="hidden" name="complete_offer" value="1">
                                            <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                            <button type="submit" class="btn-complete">✅ Завершить</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<!-- создание заявки -->
<div class="modal-overlay" id="offerModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Описать новую проблему</h2>
            <button class="btn-close-modal" onclick="closeModal('offerModal')">&times;</button>
        </div>
        <form action="index.php?filter=<?= $filter ?>" method="POST">
            <input type="hidden" name="create_offer" value="1">
            <div class="form-group">
                <label for="category_id">Какой предмет / дисциплина?</label>
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
            <div class="form-group">
                <label for="contact_link">Ссылка на соцсеть / Никнейм (ТГ, ВК и т.д.)</label>
                <input type="text" name="contact_link" id="contact_link" placeholder="@username или https://vk.com..." required>
            </div>
            <button type="submit" class="btn-submit">Оставить заявку</button>
        </form>
    </div>
</div>
<!-- редактирование заявки -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать заявку</h2>
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
                <label for="edit_contact_link">Ссылка на соцсеть / Никнейм</label>
                <input type="text" name="contact_link" id="edit_contact_link" required>
            </div>
            <div class="form-group">
                <label for="edit_status">Статус заявки</label>
                <select name="status" id="edit_status">
                    <option value="Активно">Активно</option>
                    <option value="Завершено">Завершено</option>
                </select>
            </div>
            <button type="submit" class="btn-submit" style="background: #f39c12;">Сохранить изменения</button>
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
    document.getElementById('edit_contact_link').value = offer.contact_link || '';
    document.getElementById('edit_status').value = offer.status;
    openModal('editModal');
}
</script>
</body>
</html>