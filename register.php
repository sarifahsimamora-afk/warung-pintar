<?php
session_start();
require_once 'includes/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

$db    = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirm  = $_POST['konfirmasi'] ?? '';

    if ($username === '' || $password === '' || $konfirm === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter, hanya huruf/angka/underscore (tanpa spasi).';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $st = $db->prepare("SELECT id_user FROM users WHERE username = ?");
        $st->bind_param('s', $username);
        $st->execute();
        if ($st->get_result()->fetch_assoc()) {
            $error = "Username \"$username\" sudah dipakai. Coba nama lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $st->bind_param('ss', $username, $hash);
            $st->execute();

            // Auto-login setelah daftar
            $_SESSION['user_id']  = $db->insert_id;
            $_SESSION['username'] = $username;
            $_SESSION['flash']    = ['type' => 'success', 'msg' => 'Selamat datang, Warung ' . ucwords($username) . '! Akun berhasil dibuat.'];
            header('Location: index.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Warung Baru</title>
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
.brand { text-align: center; margin-bottom: 22px; }
.brand .emoji { font-size: 40px; }
.brand h1 { font-size: 20px; font-weight: 800; color: var(--pink-dark); margin-top: 6px; }
.brand p { font-size: 12.5px; color: var(--ink-3); margin-top: 2px; }

.preview {
    background: var(--pink-light);
    border: 1px dashed var(--pink-main);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 12.5px;
    color: var(--pink-dark);
    text-align: center;
    margin-bottom: 18px;
    font-weight: 600;
}

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
.hint { font-size: 11px; color: var(--ink-3); margin-top: 4px; }

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
        <h1>Daftar Warung Baru</h1>
        <p>Bikin akun kasir kamu sendiri</p>
    </div>

    <div class="preview" id="previewBox">🏪 Nama tokomu nanti tampil sebagai: <strong>Warung ...</strong></div>

    <?php if ($error): ?>
    <div class="alert">⚠️ <?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" id="inputUsername" placeholder="cth: sarifah" required
                   value="<?= clean($_POST['username'] ?? '') ?>" oninput="updatePreview()">
            <div class="hint">Akan tampil di web sebagai "Warung [username]". 3-30 karakter, tanpa spasi.</div>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
        </div>
        <div class="form-group">
            <label>Konfirmasi Password</label>
            <input type="password" name="konfirmasi" placeholder="Ulangi password" required minlength="6">
        </div>
        <button class="btn" type="submit">Daftar & Masuk</button>
    </form>

    <div class="foot">Sudah punya akun? <a href="login.php">Masuk di sini</a></div>
</div>

<script>
function updatePreview() {
    const val = document.getElementById('inputUsername').value.trim();
    const nama = val ? val.replace(/\b\w/g, c => c.toUpperCase()) : '...';
    document.getElementById('previewBox').innerHTML =
        '🏪 Nama tokomu nanti tampil sebagai: <strong>Warung ' + nama + '</strong>';
}
updatePreview();
</script>

</body>
</html>
