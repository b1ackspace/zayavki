<?php
session_start();
ob_start();
require 'db.php';
//Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
//Получение ID пользователя
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($view_user_id <= 0) {
    die("Некорректный идентификатор пользователя.");
}
$stmt = $pdo->prepare('SELECT name, email, bio, skills FROM users WHERE id = ?');
$stmt->execute([$view_user_id]);
$user_profile = $stmt->fetch();

if (!$user_profile) {
    die("Пользователь не найден.");
}
$is_own_profile = ($view_user_id === $current_user_id);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Профиль пользователя — <?= htmlspecialchars($user_profile['name']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #eef5fc; 
            color: #2c3e50;
            padding: 20px;
        }
        .container { max-width: 600px; margin: 40px auto 0 auto; }
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-back {
            background: #3a97f9; color: white; text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 10px 18px; border-radius: 8px; transition: background 0.2s;
        }
        .btn-back:hover { background: #2582e5; }
        .profile-card { background: white; padding: 35px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .avatar-placeholder {
            width: 80px; height: 80px; background: #3a97f9; color: white; border-radius: 50%;
            display: flex; justify-content: center; align-items: center; font-size: 32px; font-weight: 600;
            margin: 0 auto 20px auto; text-transform: uppercase;
        }
        .user-name { font-size: 24px; text-align: center; color: #2c3e50; margin-bottom: 5px; font-weight: 600; }
        .user-status-badge { text-align: center; margin-bottom: 25px; }
        .status-tag { display: inline-block; background: #eef5fc; color: #7f8c8d; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 20px; }
        .info-section { margin-bottom: 20px; }
        .info-label { font-size: 13px; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600; }
        .info-value { background: #f8fbfe; border: 1.5px solid #d0dfef; padding: 12px; border-radius: 8px; font-size: 15px; line-height: 1.5; color: #34495e; }
        .info-value.empty { color: #95adc9; font-style: italic; }
        .btn-edit-link {
            display: block; text-align: center; background: #2ecc71; color: white; text-decoration: none;
            padding: 12px; border-radius: 8px; font-size: 15px; font-weight: 600; margin-top: 25px; transition: background 0.2s;
        }
        .btn-edit-link:hover { background: #27ae60; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-nav">
        <a class="btn-back" href="index.php">← Вернуться к ленте</a>
    </div>
    <main class="profile-card">
        <div class="avatar-placeholder">
            <?= mb_substr(htmlspecialchars($user_profile['name']), 0, 1) ?>
        </div>
        <h1 class="user-name"><?= htmlspecialchars($user_profile['name']) ?></h1>
        <div class="user-status-badge">
            <span class="status-tag"><?= $is_own_profile ? 'Это ваш профиль' : 'Пользователь платформы' ?></span>
        </div>
        <div class="info-section">
            <div class="info-label">Электронная почта (Email)</div>
            <div class="info-value"><b><?= htmlspecialchars($user_profile['email']) ?></b></div>
        </div>
        <div class="info-section">
            <div class="info-label">О себе</div>
            <?php if (!empty($user_profile['bio'])): ?>
                <div class="info-value"><?= nl2br(htmlspecialchars($user_profile['bio'])) ?></div>
            <?php else: ?>
                <div class="info-value empty">Пользователь ещё не заполнил информацию «О себе»</div>
            <?php endif; ?>
        </div>
        <div class="info-section">
            <div class="info-label">Увлечения / Навыки</div>
            <?php if (!empty($user_profile['skills'])): ?>
                <div class="info-value"><?= htmlspecialchars($user_profile['skills']) ?></div>
            <?php else: ?>
                <div class="info-value empty">Список увлечений пуст</div>
            <?php endif; ?>
        </div>
        <?php if ($is_own_profile): ?>
            <a href="profile.php" class="btn-edit-link">⚙️ Настроить профиль</a>
        <?php endif; ?>
    </main>
</div>
</body>
</html>