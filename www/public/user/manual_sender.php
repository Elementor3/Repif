<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$ok = isset($_GET['ok']);
$id = $_GET['id'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manual Data Sender</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f7f7f7; padding:20px; }
        .form-box { background:#fff; padding:20px; border-radius:8px; max-width:420px; margin:auto; box-shadow:0 2px 6px rgba(0,0,0,.1); }
        label { font-weight:bold; margin-top:10px; display:block; }
        input { width:100%; padding:8px; margin-top:4px; border:1px solid #ccc; border-radius:4px; }
        button { margin-top:15px; width:100%; padding:10px; background:#0078ff; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:15px; }
        .ok { color:#0a7f2e; margin-bottom:10px; }
        .err { color:#b42318; margin-bottom:10px; }
    </style>
</head>
<body>
<div class="form-box">
    <h2>Manual Data Sender</h2>

    <?php if ($ok): ?>
        <div class="ok">Saved successfully. Measurement ID: <?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="/api/recieve_data.php">
        <input type="hidden" name="from_form" value="1">

        <label>Station Serial:</label>
        <input type="text" name="station_serial" required>

        <label>Timestamp (YYYY-MM-DD HH:MM:SS):</label>
        <input type="text" name="timestamp" required>

        <label>Temperature:</label>
        <input type="number" step="0.01" name="temperature">

        <label>Pressure:</label>
        <input type="number" step="0.01" name="pressure">

        <label>Light:</label>
        <input type="number" step="0.01" name="light">

        <label>Gas:</label>
        <input type="number" step="0.01" name="gas">

        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>