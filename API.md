# API бібліотеки книг про Формулу-1

Єдина точка входу: `api.php`. Усі відповіді мають формат JSON:
`{"success": true, "data": ...}` або `{"success": false, "error": "...", "fields": {...}}`.

Ресурс `books`: `id, title, author, year, isbn, is_available (bool)`.

| Метод | Запит | Успіх | Помилки |
|---|---|---|---|
| GET | `api.php?resource=books` | 200, масив книг | — |
| GET | `api.php?resource=books&id=N` | 200, одна книга | 400 (id некоректний), 404 |
| POST | `api.php?resource=books` | 201, створена книга | 400 (поля відсутні/некоректні) |
| POST | `api.php?resource=books&action=checkout&id=N` | 200, книга зі статусом `is_available=false` | 400, 404, 409 (вже видана) |
| інші | будь-який інший метод для `books` | — | 405 (заголовок `Allow: GET, POST`) |
| будь-який | невідомий `resource` | — | 404 |

## Створення книги (POST)

Дані можна передати формою або JSON-тілом (`Content-Type: application/json`).

| Поле | Правило |
|---|---|
| `title`, `author` | обов'язкові |
| `year` | ціле число від 1450 до поточного року |
| `isbn` | 10 або 13 цифр |
| `is_available` | необов'язкове, за замовчуванням `true` |

## Приклад відповіді (400)

```json
{
  "success": false,
  "error": "Некоректні або відсутні поля",
  "fields": { "year": "Рік має бути числом від 1450 до 2026" }
}
```

## Доповнення (практична №7)

| Запит | Опис |
|---|---|
| `GET api.php?resource=books` | у кожної книги додано поле `other_books_by_author` (скільки ще книг цього автора) |
| `GET api.php?resource=books&author=ІМ'Я` | книги автора (400, якщо `author` порожній) |
| `GET api.php?resource=books&available=1` | доступні книги, відповідь кешується на 30 с (400 для інших значень `available`) |
| `...&mode=baseline` | службовий режим для вимірювань: N+1-запити й без кешу |

Кеш скидається після `POST` створення та `checkout`.
Службові заголовки відповіді: `X-Time-Ms`, `X-Peak-Memory-Mb`, `X-Query-Count`, `X-Cache`.