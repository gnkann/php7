<?php

require_once __DIR__ . '/classes/CountingPDO.php';

$dbHost = '127.0.0.1';
$dbName = 'practicum4';
$dbUser = 'root';
$dbPass = 'password';

try {
    $pdo = new CountingPDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CountingStatement::class]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(
        ['success' => false, 'error' => 'Не вдалося підключитися до бази даних'],
        JSON_UNESCAPED_UNICODE
    ));
}

return $pdo;
