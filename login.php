<?php
session_start();
require 'db.php';
$success = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = 'Регистрация прошла успешно! Войдите, используя свои данные.';
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (!empty($email) && !empty($password)) {
        // Ищем по почте
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: profile.php");
            exit;
        } else {
            $error = 'Неверный Email или пароль!';
        }
    } else {
        $error = 'Пожалуйста, заполните все поля!';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в систему</title>
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: #eef5fc;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 100vh; 
    }
    .form-container { 
        background: #ffffff; 
        padding: 35px 30px; 
        border-radius: 12px; 
        box-shadow: 0 8px 24px rgba(149, 173, 201, 0.2); 
        width: 100%;
        max-width: 360px; 
    }
    h2 {
        color: #2c3e50;
        margin-bottom: 25px;
        text-align: center;
        font-size: 24px;
    }
    .input-group { 
        margin-bottom: 20px; 
    }
    .input-group label { 
        display: block; 
        margin-bottom: 8px; 
        color: #5a6b7c;
        font-size: 14px;
        font-weight: 500;
    }
    .input-group input { 
        width: 100%; 
        padding: 12px 14px; 
        border: 1.5px solid #d0dfef;
        border-radius: 8px;
        background: #fcfdfe;
        font-size: 15px;
        transition: all 0.2s ease;
    }
    .input-group input:focus {
        outline: none;
        border-color: #3a97f9;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(58, 151, 249, 0.15);
    }
    .btn { 
        width: 100%; 
        padding: 12px; 
        background: #3a97f9;
        border: none; 
        border-radius: 8px;
        color: white; 
        font-size: 16px;
        font-weight: 600;
        cursor: pointer; 
        transition: background 0.2s ease, transform 0.1s ease;
    }
    .btn:hover { 
        background: #2582e5;
    }
    .btn:active {
        transform: scale(0.98);
    }
    .error { 
        background: #ffe3e3;
        color: #d63031; 
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 20px; 
        border-left: 4px solid #d63031;
    }
    .success { 
        background: #e3fcef;
        color: #27ae60; 
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 20px; 
        border-left: 4px solid #27ae60;
    }
    p {
        margin-top: 20px; 
        text-align: center;
        color: #7f8c8d;
        font-size: 14px;
    }
    p a {
        color: #3a97f9;
        text-decoration: none;
        font-weight: 500;
    }
    p a:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>
<div class="form-container">
    <h2>Вход</h2>
    <?php if ($error): ?> <div class="error"><?= $error ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="success"><?= $success ?></div> <?php endif; ?>
    <form action="login.php" method="POST">
        <div class="input-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>
        <div class="input-group">
            <label for="password">Пароль</label>
            <input type="password" name="password" id="password" required>
        </div>
        <button type="submit" class="btn">Войти</button>
    </form>
    <p style="margin-top: 15px; text-align: center;">Нет аккаунта? <a href="register.php">Создать</a></p>
</div>
</body>
</html>