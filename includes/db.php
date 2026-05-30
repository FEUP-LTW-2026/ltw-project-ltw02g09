<?php
declare(strict_types=1);

define('DB_PATH', __DIR__ . '/../database/database.db');
define('DB_SCHEMA_PATH', __DIR__ . '/../database/database.sql');

function initialize_database_if_needed(PDO $pdo): void {
    $check = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users' LIMIT 1");
    if ($check && $check->fetchColumn()) {
        return;
    }

    $schema = file_get_contents(DB_SCHEMA_PATH);
    if ($schema === false) {
        throw new RuntimeException('Não foi possível carregar o ficheiro de schema da base de dados.');
    }

    $pdo->exec($schema);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        initialize_database_if_needed($pdo);
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}
