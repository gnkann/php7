<?php
// Запуск: php seed.php [кількість книг, за замовчуванням 1000]

if (PHP_SAPI !== 'cli') {
    exit("Запускайте з терміналу: php seed.php\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/cache.php';

$count = isset($argv[1]) && ctype_digit($argv[1]) ? (int) $argv[1] : 1000;
$authorsCount = 100;

// Прибираємо попередні тестові дані (свої книги не чіпаємо)
$pdo->exec("DELETE FROM books WHERE title LIKE 'Тестова книга %'");

$pdo->beginTransaction();
$stmt = $pdo->prepare(
    'INSERT INTO books (title, author, year, isbn, is_available)
     VALUES (:title, :author, :year, :isbn, :is_available)'
);
for ($i = 1; $i <= $count; $i++) {
    $stmt->execute([
        ':title' => "Тестова книга $i",
        ':author' => 'Тестовий автор ' . (($i % $authorsCount) + 1),
        ':year' => 1950 + ($i % 75),
        ':isbn' => str_pad((string) $i, 13, '0', STR_PAD_LEFT),
        ':is_available' => ($i % 3 !== 0) ? 1 : 0,
    ]);
}
$pdo->commit();

cacheForget(AVAILABLE_CACHE_KEY);
echo "Додано $count тестових книг ($authorsCount авторів).\n";