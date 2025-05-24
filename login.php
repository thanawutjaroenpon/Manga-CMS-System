<?php
require_once "db.php";
session_start();
$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - MangaCMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    body {
        background: #f2f4f7;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin: 0;
        color: #202041;
    }
    .login-center {
        max-width: 400px;
        margin: 0 auto;
        padding: 6em 0 2em 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-height: 92vh;
    }
    .login-card {
        background: #fff;
        color: #202041;
        border-radius: 18px;
        box-shadow: 0 8px 30px #d3d6ee40;
        padding: 2.3em 2em 2.2em 2em;
        width: 100%;
        margin-bottom: 2em;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }
    .login-card h2 {
        font-size: 2em;
        margin-top: 0;
        margin-bottom: 1.3em;
        color: #3258ae;
        font-weight: 800;
        letter-spacing: .04em;
        text-align: center;
    }
    .login-card form {
        display: flex;
        flex-direction: column;
        gap: 1.15em;
    }
    input[type="text"], input[type="password"] {
        border: 1.1px solid #b5c5db;
        border-radius: 7px;
        padding: 0.6em 0.9em;
        font-size: 1.09em;
        background: #f7f7fc;
        color: #202041;
        margin-bottom: .15em;
    }
    input[type="text"]:focus, input[type="password"]:focus {
        border: 1.6px solid #3258ae;
        outline: none;
    }
    button {
        background: #3258ae;
        color: #fff;
        border: none;
        border-radius: 16px;
        font-weight: 600;
        padding: 0.55em 1.4em;
        font-size: 1.11em;
        cursor: pointer;
        transition: background .14s, box-shadow .11s;
        box-shadow: 0 1px 7px #b4c9f044;
        margin-top: 0.4em;
    }
    button:hover { background: #21346c; }
    .error-msg {
        background: #faeaea;
        color: #c94d2f;
        border-radius: 10px;
        padding: 12px 19px;
        margin-bottom: 1.1em;
        font-weight: 500;
        text-align: center;
    }
    .success-msg {
        background: #e7f9e9;
        color: #188d3c;
        border-radius: 10px;
        padding: 12px 19px;
        margin-bottom: 1.1em;
        font-weight: 500;
        text-align: center;
    }
    .register-link {
        margin-top: 1.5em;
        color: #3258ae;
        font-size: 1em;
        text-align: center;
    }
    .register-link a {
        color: #21346c;
        text-decoration: underline;
        font-weight: 500;
        transition: color .13s;
    }
    .register-link a:hover { color: #ff8800; }
    .logo-center {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 2em;
        margin-top: .3em;
    }
    .logo-center img {
        height: 2.3em;
        margin-right: .45em;
        border-radius: 10px;
        box-shadow: 0 2px 12px #20161c11;
    }
    .logo-center span {
        font-size: 1.3em;
        font-weight: 900;
        color: #3258ae;
        letter-spacing: 0.02em;
        text-shadow: 0 2px 12px #140a0e20;
    }
    </style>
</head>
<body>
<div class="login-center">
    <div class="logo-center">
        <img src="https://img.icons8.com/color/48/000000/book.png">
        <span>MangaCMS</span>
    </div>
    <div class="login-card">
        <h2>Login</h2>
        <?php if(isset($_GET['register'])): ?>
            <div class="success-msg">Registration successful! Please login.</div>
        <?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?=$error?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <input name="username" type="text" placeholder="Username" required autocomplete="off">
            <input name="password" type="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="register-link">
            No account? <a href="register.php">Register</a>
        </div>
    </div>
</div>
</body>
</html>
