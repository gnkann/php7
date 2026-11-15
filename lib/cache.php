<?php

const AVAILABLE_CACHE_KEY = 'available_books';
const AVAILABLE_CACHE_TTL = 30; // секунд

function cacheFile(string $key): string {
    return sys_get_temp_dir() . '/php7_cache_' . md5($key) . '.json';
}

// Повертає дані з кешу або null, якщо кешу немає чи він застарів
function cacheGet(string $key, int $ttlSeconds): ?array {
    $file = cacheFile($key);
    if (!is_file($file) || (time() - filemtime($file)) >= $ttlSeconds) {
        return null;
    }
    $raw = file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function cacheSet(string $key, array $data): void {
    file_put_contents(cacheFile($key), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Явна інвалідація після зміни даних
function cacheForget(string $key): void {
    $file = cacheFile($key);
    if (is_file($file)) {
        unlink($file);
    }
}