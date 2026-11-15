<?php
/*
 * API бібліотеки книг про Формулу-1 (практична №7, варіант 1)
 * Контракт з практичної №6 не змінено. Додано необов'язкові параметри:
 *
 *  GET api.php?resource=books                      -> список + other_books_by_author
 *  GET api.php?resource=books&author=ІМ'Я          -> книги автора (findByAuthor, використовує індекс)
 *  GET api.php?resource=books&available=1          -> доступні книги (listAvailable, кеш 30 с)
 *  &mode=baseline                                  -> службовий режим для вимірювань:
 *                                                     N+1-запити, без кешу
 *
 *  Кожна відповідь має заголовки: X-Time-Ms, X-Peak-Memory-Mb, X-Query-Count
 *  (для available=1 також X-Cache: HIT | MISS | BYPASS).
 *  Те саме пишеться в error_log (виводиться в терміналі з php -S).
 */

$startTime = microtime(true);

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/classes/BookRepository.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/cache.php';

function respond(int $status, array $payload): never {
    global $startTime;
    $elapsedMs = (microtime(true) - $startTime) * 1000;
    $peakMb = memory_get_peak_usage(true) / 1024 / 1024;
    $queries = CountingPDO::$queryCount;

    http_response_code($status);
    header(sprintf('X-Time-Ms: %.2f', $elapsedMs));
    header(sprintf('X-Peak-Memory-Mb: %.2f', $peakMb));
    header('X-Query-Count: ' . $queries);
    error_log(sprintf(
        '[api] %s %s -> %d | %.2f мс | %.2f МБ | SQL-запитів: %d',
        $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $status, $elapsedMs, $peakMb, $queries
    ));

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function success(int $status, mixed $data): never {
    respond($status, ['success' => true, 'data' => $data]);
}

function fail(int $status, string $error, array $fields = []): never {
    $payload = ['success' => false, 'error' => $error];
    if ($fields !== []) {
        $payload['fields'] = $fields;
    }
    respond($status, $payload);
}

function formatBook(array $book): array {
    return [
        'id'           => (int) $book['id'],
        'title'        => $book['title'],
        'author'       => $book['author'],
        'year'         => (int) $book['year'],
        'isbn'         => $book['isbn'],
        'is_available' => (bool) $book['is_available'],
    ];
}

function formatBookWithCount(array $book): array {
    return formatBook($book) + ['other_books_by_author' => (int) $book['other_books_by_author']];
}

function parseId(mixed $raw): ?int {
    if (!is_string($raw) || !ctype_digit($raw) || (int) $raw < 1) {
        return null;
    }
    return (int) $raw;
}

function readInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        if (!is_array($decoded)) {
            fail(400, 'Тіло запиту має бути коректним JSON-об\'єктом');
        }
        $input = $decoded;
    } else {
        $input = $_POST;
    }

    $clean = [];
    foreach (['title', 'author', 'year', 'isbn'] as $field) {
        $value = $input[$field] ?? null;
        $clean[$field] = is_scalar($value) ? (string) $value : '';
    }

    if (filter_var($input['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
        $clean['is_available'] = '1';
    }

    return $clean;
}

// ---------- GET ----------

function listBooks(BookRepository $repo, bool $baseline): never {
    $books = $baseline ? $repo->listWithAuthorCountSlow() : $repo->listWithAuthorCount();
    success(200, array_map('formatBookWithCount', $books));
}

function listByAuthor(BookRepository $repo, mixed $rawAuthor): never {
    $author = is_string($rawAuthor) ? trim($rawAuthor) : '';
    if ($author === '') {
        fail(400, 'Параметр author не може бути порожнім', ['author' => 'Некоректний автор']);
    }
    success(200, array_map('formatBook', $repo->findByAuthor($author)));
}

function listAvailableBooks(BookRepository $repo, mixed $rawAvailable, bool $baseline): never {
    if ($rawAvailable !== '1') {
        fail(400, 'Параметр available може мати лише значення 1', ['available' => 'Некоректне значення']);
    }

    if (!$baseline) {
        $cached = cacheGet(AVAILABLE_CACHE_KEY, AVAILABLE_CACHE_TTL);
        if ($cached !== null) {
            header('X-Cache: HIT');
            success(200, array_map('formatBook', $cached));
        }
    }

    $rows = $repo->listAvailable();
    if (!$baseline) {
        cacheSet(AVAILABLE_CACHE_KEY, $rows);
    }
    header('X-Cache: ' . ($baseline ? 'BYPASS' : 'MISS'));
    success(200, array_map('formatBook', $rows));
}

function getBook(BookRepository $repo, mixed $rawId): never {
    $id = parseId($rawId);
    if ($id === null) {
        fail(400, 'Параметр id має бути додатним цілим числом', ['id' => 'Некоректний id']);
    }

    $book = $repo->findById($id);
    if ($book === null) {
        fail(404, "Книгу з id $id не знайдено");
    }
    success(200, formatBook($book));
}

// ---------- POST ----------

function createBook(BookRepository $repo): never {
    [$data, $errors] = validateBookInput(readInput());

    if (!empty($errors)) {
        fail(400, 'Некоректні або відсутні поля', $errors);
    }

    $newId = $repo->addBook(
        $data['title'],
        $data['author'],
        (int) $data['year'],
        $data['isbn'],
        $data['is_available']
    );

    cacheForget(AVAILABLE_CACHE_KEY); // список доступних змінився
    success(201, formatBook($repo->findById($newId)));
}

function checkoutBook(BookRepository $repo, mixed $rawId): never {
    $id = parseId($rawId);
    if ($id === null) {
        fail(400, 'Для checkout потрібен параметр id (додатне ціле число)', ['id' => 'Некоректний id']);
    }

    if ($repo->findById($id) === null) {
        fail(404, "Книгу з id $id не знайдено");
    }

    if (!$repo->checkoutBook($id)) {
        fail(409, "Книга з id $id уже видана");
    }

    cacheForget(AVAILABLE_CACHE_KEY); // список доступних змінився
    success(200, formatBook($repo->findById($id)));
}

// ---------- Маршрутизація ----------

$method    = $_SERVER['REQUEST_METHOD'];
$resource  = $_GET['resource'] ?? null;
$action    = $_GET['action'] ?? null;
$id        = $_GET['id'] ?? null;
$author    = $_GET['author'] ?? null;
$available = $_GET['available'] ?? null;
$baseline  = ($_GET['mode'] ?? null) === 'baseline';

if ($resource !== 'books') {
    fail(404, 'Невідомий ресурс. Доступно: resource=books');
}

try {
    $repo = new BookRepository($pdo);

    if ($method === 'GET') {
        if ($id !== null) {
            getBook($repo, $id);
        }
        if ($author !== null) {
            listByAuthor($repo, $author);
        }
        if ($available !== null) {
            listAvailableBooks($repo, $available, $baseline);
        }
        listBooks($repo, $baseline);
    } elseif ($method === 'POST') {
        if ($action === null) {
            createBook($repo);
        } elseif ($action === 'checkout') {
            checkoutBook($repo, $id);
        } else {
            fail(400, 'Невідома дія. Доступно: action=checkout', ['action' => 'Невідома дія']);
        }
    } else {
        header('Allow: GET, POST');
        fail(405, "Метод $method не підтримується для ресурсу books");
    }
} catch (PDOException $e) {
    fail(500, 'Помилка бази даних');
}