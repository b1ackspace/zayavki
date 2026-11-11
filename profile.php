<?php
session_start();
ob_start();
require 'db.php';
// Защита авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT ''");
    $pdo->exec("ALTER TABLE users ADD COLUMN skills TEXT DEFAULT ''");
} catch (PDOException $e) {
}
// Обработка формы редактирования профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $bio = trim($_POST['bio']);
    $skills = trim($_POST['skills']);
    if (empty($name)) {
        $msg = 'Имя не может быть пустым!';
        $msg_type = 'error';
    } elseif (mb_strlen($name) > 50) {
        $msg = 'Имя слишком длинное (максимум 50 символов)!';
        $msg_type = 'error';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, bio = ?, skills = ? WHERE id = ?');
        if ($stmt->execute([$name, $bio, $skills, $current_user_id])) {
            $_SESSION['user_name'] = $name;
            $msg = 'Профиль успешно обновлен!';
            $msg_type = 'success';
        } else {
            $msg = 'Ошибка при обновлении профиля.';
            $msg_type = 'error';
        }
    }
}
$stmt = $pdo->prepare('SELECT name, email, bio, skills FROM users WHERE id = ?');
$stmt->execute([$current_user_id]);
$user_profile = $stmt->fetch();
if (!$user_profile) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет — Настройки</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #eef5fc; 
            color: #2c3e50;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header {
            background: white;
            padding: 15px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .header h1 { font-size: 22px; color: #2c3e50; font-weight: 600; }
        .nav-block { display: flex; align-items: center; gap: 15px; }
        
        .btn-nav {
            background: #3a97f9; color: white; text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 10px 18px; border-radius: 8px; transition: background 0.2s;
        }
        .btn-nav:hover { background: #2582e5; }
        
        .logout-btn {
            color: #e74c3c; text-decoration: none; font-weight: 600; font-size: 14px;
            padding: 10px 14px; border-radius: 6px; transition: background 0.2s;
        }
        .logout-btn:hover { background: #fdeaea; }
        
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(149, 173, 201, 0.15); }
        .card h2 { font-size: 20px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eef5fc; color: #34495e; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #5a6b7c; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 1.5px solid #d0dfef; border-radius: 8px; font-size: 14px; background: #fcfdfe; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: #3a97f9; box-shadow: 0 0 0 3px rgba(58, 151, 249, 0.15);
        }
        
        .btn-save {
            width: 100%; padding: 13px; background: #2ecc71; border: none; border-radius: 8px; color: white; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-save:hover { background: #27ae60; }
        .alert { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert.success { background: #e3fcef; color: #27ae60; border-left: 4px solid #27ae60; }
        .alert.error { background: #ffe3e3; color: #d63031; border-left: 4px solid #d63031; }
        .user-meta-info { font-size: 13px; color: #7f8c8d; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <header class="header">
        <h1>Профиль пользователя</h1>
        <div class="nav-block">
            <!-- кнопка ухода в экран     -->
            <a class="btn-nav" href="index.php">← На главную (Лента)</a>
            <a class="logout-btn" href="login.php?logout=1">Выйти</a>
        </div>
    </header>
    <?php if ($msg): ?>
        <div class="alert <?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>
    <main class="card">
        <h2>Редактирование профиля</h2>
        <div class="user-meta-info">Ваш Email: <b><?= htmlspecialchars($user_profile['email']) ?></b></div>
        <form action="profile.php" method="POST">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="form-group">
                <label for="prof_name">Ваше имя</label>
                <input type="text" name="name" id="prof_name" value="<?= htmlspecialchars($user_profile['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="prof_bio">О себе</label>
                <textarea name="bio" id="prof_bio" rows="4" placeholder="Расскажите о себе, интересах или учебе..."><?= htmlspecialchars($user_profile['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="prof_skills">Ваши увлечения</label>
                <input type="text" name="skills" id="prof_skills" placeholder="Например: С++, высшая математика, физика" value="<?= htmlspecialchars($user_profile['skills'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-save">Сохранить изменения</button>
        </form>
    </main>
</div>
</body>
</html>