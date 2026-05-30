<?php
declare(strict_types=1);

function validate_required(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field]) && $data[$field] !== '0') {
            $errors[$field] = 'Este campo é obrigatório.';
        }
    }
    return $errors;
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_username(string $username): bool {
    return (bool)preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username);
}

function validate_password(string $password): bool {
    return strlen($password) >= 6;
}

function validate_min_length(string $value, int $min): bool {
    return mb_strlen(trim($value)) >= $min;
}

function validate_max_length(string $value, int $max): bool {
    return mb_strlen(trim($value)) <= $max;
}

function validate_integer(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool {
    if (!is_numeric($value)) {
        return false;
    }
    $int = (int)$value;
    return $int >= $min && $int <= $max;
}

function validate_date(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validate_time(string $time): bool {
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
}

function username_exists(string $username, ?int $excludeId = null): bool {
    $sql  = 'SELECT COUNT(*) FROM users WHERE username = ?';
    $args = [$username];
    if ($excludeId !== null) {
        $sql  .= ' AND id != ?';
        $args[] = $excludeId;
    }
    require_once __DIR__ . '/db.php';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return (int)$stmt->fetchColumn() > 0;
}

function email_exists(string $email, ?int $excludeId = null): bool {
    $sql  = 'SELECT COUNT(*) FROM users WHERE email = ?';
    $args = [$email];
    if ($excludeId !== null) {
        $sql  .= ' AND id != ?';
        $args[] = $excludeId;
    }
    require_once __DIR__ . '/db.php';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return (int)$stmt->fetchColumn() > 0;
}
