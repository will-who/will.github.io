<?php
require __DIR__ . '/db.php';

$in       = read_json();
$username = trim($in['username'] ?? '');
$email    = trim($in['email'] ?? '');
$password = $in['password'] ?? '';

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    respond(['message' => 'Username must be 3-50 characters (letters, numbers, _).'], 400);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['message' => 'Invalid email address.'], 400);
}
if (strlen($password) < 6) {
    respond(['message' => 'Password must be at least 6 characters.'], 400);
}

// email je voliteľný -> ak je prázdny, uložíme NULL
$email = $email === '' ? null : $email;

try {
    $pdo = db();

    $check = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)');
    $check->execute([$username, $email]);
    if ($row = $check->fetch()) {
        $msg = (strcasecmp($row['username'], $username) === 0)
            ? 'Username is already taken.'
            : 'Email is already registered.';
        respond(['message' => $msg], 409);
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

    respond(['message' => 'Account created.'], 201);
} catch (PDOException $e) {
    respond(['message' => 'Database error.'], 500);
}