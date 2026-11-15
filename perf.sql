USE practicum4;

-- 1. ДО індексу (очікується type = ALL, rows ≈ кількість книг у таблиці)
EXPLAIN SELECT * FROM books WHERE author = 'Тестовий автор 1';

-- 2. Створення індексу
CREATE INDEX idx_books_author ON books (author);

-- 3. ПІСЛЯ індексу (очікується type = ref, rows ≈ 10)
EXPLAIN SELECT * FROM books WHERE author = 'Тестовий автор 1';

-- Щоб повторити експеримент з нуля:
-- DROP INDEX idx_books_author ON books;