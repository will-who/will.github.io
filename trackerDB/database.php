<?php
// Pripojenie na databázu "tracker" (XAMPP default)
const DB_HOST = 'localhost';
const DB_NAME = 'tracker';
const DB_USER = 'root';
const DB_PASS = '';



header('Content-Type: application/json; charset=utf-8');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function read_json(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['message' => 'Method not allowed.'], 405);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}




$host = 'localhost';
$dbname = 'tracker';
$username = 'root';
$password = ''; // Replace if your MySQL account has a password

try {
    $pdo = new PDO(
        "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Connected to tracker successfully!";
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Connection failed. Check your database settings.');
}