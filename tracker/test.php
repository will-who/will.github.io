<?php
// Otvor v prehliadači: http://localhost/tracker/api/test.php
require __DIR__ . '/db.php';

try {
    $count = db()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    respond(['status' => 'OK - pripojenie na DB funguje', 'pocet_userov' => (int) $count]);
} catch (Throwable $e) {
    respond(['status' => 'CHYBA', 'detail' => $e->getMessage()], 500);
}