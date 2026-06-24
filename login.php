<?php
session_start();
require_once 'includes/db.php';

// Sudah login? langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

$db    = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = $db->prepare("SELECT id_user, username, password FROM users WHERE username = ?");
        $st->bind_param('s', $username);
        $st->execute();
        $user = $st->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php'); exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Warung Pintar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --pink-dark:  #B8456E;
    --pink-main:  #EC6F95;
    --pink-light: #FDEAF0;
    --peach:      #F4A261;
    --ink:        #2B1B22;
    --ink-2:      #5C4350;
    --ink-3:      #9B7C8A;
    --border:     #F3DCE4;
    --bg:         #FFF8F5;
}
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: linear-gradient(135deg, #FFF1E6 0%, #FDEAF0 50%, #FCE0EA 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    color: var(--ink);
}
.card {
    background: #fff;
    width: 100%;
    max-width: 380px;
    border-radius: 18px;
    box-shadow: 0 10px 40px rgba(184,69,110,.15);
    padding: 36px 32px;
    border: 1px solid var(--border);
}
.brand { text-align: center; margin-bottom: 26px; }
.brand .emoji { font-size: 40px; }
.brand h1 { font-size: 20px; font-weight: 800; color: var(--pink-dark); margin-top: 6px; }
.brand p { font-size: 12.5px; color: var(--ink-3); margin-top: 2px; }

.form-group { margin-bottom: 16px; }
label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink-2); margin-bottom: 5px; }
input {
    width: 100%;
    padding: 11px 13px;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: inherit;
    font-size: 13.5px;
    outline: none;
    transition: border-color .15s;
}
input:focus { border-color: var(--pink-main); }

.btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 9px;
    background: var(--pink-main);
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 6px;
    transition: background .15s;
}
.btn:hover { background: var(--pink-dark); }

.alert {
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 8px;
    padding: 10px 13px;
    font-size: 12.5px;
    margin-bottom: 16px;
}
.foot { text-align: center; font-size: 12.5px; color: var(--ink-3); margin-top: 20px; }
.foot a { color: var(--pink-dark); font-weight: 700; text-decoration: none; }
.foot a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="card">
    <div class="brand">
        <div class="emoji">🏪</div>
        <h1>Masuk ke Warung Kamu</h1>
        <p>Sistem Kasir & Inventori</p>
    </div>

    <?php if ($error): ?>
    <div class="alert">⚠️ <?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="cth: sarifah" required autofocus value="<?= clean($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button class="btn" type="submit">Masuk</button>
    </form>

    <div class="foot">Belum punya akun? <a href="register.php">Daftar warung baru</a></div>
</div>

</body>
</html>
