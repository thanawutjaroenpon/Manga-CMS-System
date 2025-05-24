<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE username=?");
$stmt->execute([$username]);
$row = $stmt->fetch();
if (!$row || $row['role'] != 'admin') {
    echo "<div style='max-width:400px;margin:8em auto;text-align:center;font-size:1.2em;background:#fff;padding:3em 2em;border-radius:17px;box-shadow:0 6px 24px #cabcee29;'>You are not authorized to access this page.<br><a href='index.php' style='color:#4476e3;text-decoration:underline;'>Return to home</a></div>";
    exit();
}

// Get user list
$users = $pdo->query("SELECT username, points FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_user'], $_POST['points'])) {
    $target = trim($_POST['target_user']);
    $amount = intval($_POST['points']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$target]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE username=?");
        $stmt->execute([$amount, $target]);
        $success = "Points updated for <b>" . htmlspecialchars($target) . "</b>!";
        // Update users list for immediate refresh
        $users = $pdo->query("SELECT username, points FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: Edit Points</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
    :root {
        --accent: #6857db;
        --bg: #f4f6fb;
        --surface: #fff;
        --surface-alt: #f9fafe;
        --border: #e1e7f0;
        --danger: #e34848;
        --success: #36bb62;
    }
    body {
        background: var(--bg);
        font-family: 'Segoe UI', Arial, sans-serif;
        margin: 0;
        color: #262653;
    }
    .admin-container {
        max-width: 460px;
        margin: 2.7em auto;
        background: var(--surface);
        border-radius: 18px;
        box-shadow: 0 6px 30px #aabcee18;
        padding: 2.5em 2.2em 2em 2.2em;
    }
    .admin-title {
        font-size: 1.42em;
        font-weight: 800;
        letter-spacing: .01em;
        color: var(--accent);
        margin-bottom: 1.7em;
        text-align: center;
    }
    .form-group {
        margin-bottom: 1.2em;
    }
    label {
        font-weight: 500;
        font-size: 1.03em;
        margin-bottom: 0.2em;
        display: block;
    }
    select, input[type="number"] {
        width: 100%;
        padding: .66em .7em;
        margin-top: .2em;
        border: 1.3px solid var(--border);
        border-radius: 8px;
        background: var(--surface-alt);
        font-size: 1.06em;
        transition: border-color .16s;
    }
    select:focus, input[type="number"]:focus {
        border-color: var(--accent);
        outline: none;
    }
    .points-row {
        display: flex;
        align-items: center;
        margin-bottom: .4em;
    }
    .points-row span {
        display: inline-block;
        min-width: 75px;
        font-weight: 600;
        color: #5c66b4;
        font-size: 1.02em;
    }
    .points-row .current-points {
        font-weight: 700;
        color: var(--accent);
        font-size: 1.08em;
        margin-left: 1em;
    }
    .submit-btn {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 9px;
        padding: .72em 1.5em;
        font-size: 1.13em;
        font-weight: 600;
        margin-top: .5em;
        cursor: pointer;
        box-shadow: 0 2px 9px #cabcee23;
        transition: background .15s;
        width: 100%;
    }
    .submit-btn:hover { background: #4b3cb6; }
    .message-success {
        background: #ebfcee;
        color: var(--success);
        border: 1.5px solid #c2ebd3;
        padding: .85em 1em;
        border-radius: 8px;
        font-size: 1.09em;
        margin-bottom: 1.3em;
        text-align: center;
        font-weight: 600;
    }
    .message-error {
        background: #fff3f3;
        color: var(--danger);
        border: 1.5px solid #eed2d2;
        padding: .85em 1em;
        border-radius: 8px;
        font-size: 1.08em;
        margin-bottom: 1.3em;
        text-align: center;
        font-weight: 600;
    }
    .user-list-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 2.2em;
        background: var(--surface-alt);
        border-radius: 13px;
        overflow: hidden;
        font-size: 1.04em;
    }
    .user-list-table th, .user-list-table td {
        padding: .6em 1em;
        text-align: left;
    }
    .user-list-table th {
        background: var(--surface);
        color: #5a5f85;
        font-weight: 700;
        border-bottom: 2px solid var(--border);
    }
    .user-list-table tr:not(:last-child) td {
        border-bottom: 1px solid var(--border);
    }
    .user-list-table tr:nth-child(even) td {
        background: #f3f6ff;
    }
    .user-list-table tr:hover td {
        background: #e8ecfa;
    }
    .back-link {
        display: inline-block;
        margin-top: 2.4em;
        color: var(--accent);
        text-decoration: underline;
        font-weight: 500;
        transition: color .13s;
        font-size: 1.08em;
    }
    .back-link:hover { color: #4b3cb6; }
    @media (max-width:600px) {
        .admin-container { padding:1.4em .7em; }
        .user-list-table th, .user-list-table td { padding:.6em .4em;}
    }
    </style>
    <script>
    // For updating current points when selecting user
    function updateCurrentPoints() {
        let userSelect = document.getElementById('target_user');
        let currentPoints = userSelect.options[userSelect.selectedIndex].getAttribute('data-points');
        document.getElementById('current-points-value').innerText = currentPoints !== null ? currentPoints : '?';
    }
    </script>
</head>
<body>
    <div class="admin-container">
        <div class="admin-title">🛠️ Manage User Points</div>
        <?php if ($success): ?>
            <div class="message-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message-error"><?= $error ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="target_user">User</label>
                <select name="target_user" id="target_user" required onchange="updateCurrentPoints()">
                    <option value="" disabled selected>Select user</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?=htmlspecialchars($user['username'])?>" data-points="<?=intval($user['points'])?>">
                            <?=htmlspecialchars($user['username'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group points-row">
                <span>Current Points:</span>
                <span class="current-points" id="current-points-value">?</span>
            </div>
            <div class="form-group">
                <label for="points">Add/Subtract Points<br>
                    <span style="color:#7c7cb3;font-size:.93em;font-weight:400;">(Enter negative to subtract)</span>
                </label>
                <input type="number" name="points" id="points" required placeholder="e.g. 5 or -3">
            </div>
            <button class="submit-btn" type="submit">Update Points</button>
        </form>
        <table class="user-list-table">
            <tr>
                <th>User</th>
                <th style="text-align:right;">Points</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?=htmlspecialchars($user['username'])?></td>
                <td style="text-align:right;"><?=intval($user['points'])?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <a href="index.php" class="back-link">&larr; Back to Home</a>
    </div>
    <script>
    // Make sure current points display updates on first select
    document.addEventListener('DOMContentLoaded', function(){
        let sel = document.getElementById('target_user');
        sel.addEventListener('change', updateCurrentPoints);
        // Pre-select first user if desired (optional)
    });
    </script>
</body>
</html>
