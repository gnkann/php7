<?php

class BookRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): array {
        return $this->pdo->query('SELECT * FROM books ORDER BY id')->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // Запит із фільтром за author: саме його прискорює індекс
    public function findByAuthor(string $author): array {
        $stmt = $this->pdo->prepare('SELECT * FROM books WHERE author = :author ORDER BY id');
        $stmt->execute([':author' => $author]);
        return $stmt->fetchAll();
    }

    // Рідко змінюваний список: кешується в api.php
    public function listAvailable(): array {
        return $this->pdo->query('SELECT * FROM books WHERE is_available = 1 ORDER BY id')->fetchAll();
    }

    public function addBook(string $title, string $author, int $year, string $isbn, bool $isAvailable): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO books (title, author, year, isbn, is_available)
             VALUES (:title, :author, :year, :isbn, :is_available)'
        );
        $stmt->execute([
            ':title' => $title,
            ':author' => $author,
            ':year' => $year,
            ':isbn' => $isbn,
            ':is_available' => $isAvailable ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function checkoutBook(int $id): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE books SET is_available = 0 WHERE id = :id AND is_available = 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ---------- N+1 ----------

    // Один запит на одного автора (викликається в циклі, див. listWithAuthorCountSlow)
    public function countByAuthor(string $author): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE author = :author');
        $stmt->execute([':author' => $author]);
        return (int) $stmt->fetchColumn();
    }

    // Усі автори одним запитом
    public function getAuthorCounts(): array {
        return $this->pdo
            ->query('SELECT author, COUNT(*) AS cnt FROM books GROUP BY author')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // ПОВІЛЬНО: 1 запит на список + N запитів у циклі
    public function listWithAuthorCountSlow(): array {
        $books = $this->getAll();
        foreach ($books as &$book) {
            $book['other_books_by_author'] = $this->countByAuthor($book['author']) - 1;
        }
        unset($book);
        return $books;
    }

    // ШВИДКО: 2 запити незалежно від кількості книг
    public function listWithAuthorCount(): array {
        $books = $this->getAll();
        $counts = $this->getAuthorCounts();
        foreach ($books as &$book) {
            $book['other_books_by_author'] = (int) ($counts[$book['author']] ?? 1) - 1;
        }
        unset($book);
        return $books;
    }
}