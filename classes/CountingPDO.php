<?php

/**
 * PDO з лічильником SQL-запитів: рахує і query(), і execute().
 */
class CountingPDO extends PDO {
    public static int $queryCount = 0;

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        self::$queryCount++;
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

class CountingStatement extends PDOStatement {
    public function execute(?array $params = null): bool {
        CountingPDO::$queryCount++;
        return parent::execute($params);
    }
}