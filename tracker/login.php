<?php
require __DIR__ . '/db.php';

$in       = read_json();
$username = trim($in['username'] ?? '');
$password = $in['password'] ?? '';

if ($username === '' || $password === '') {
    respond(['message' => 'Enter username and password.'], 400);
}

try {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respond(['message' => 'Invalid username or password.'], 401);
    }
    if (!$user['is_active']) {
        respond(['message' => 'Account is disabled.'], 403);
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    respond(['username' => $user['username']]);
} catch (PDOException $e) {
    respond(['message' => 'Database error.'], 500);
}