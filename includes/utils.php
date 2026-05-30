<?php
declare(strict_types=1);

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_headers(): void {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://ui-avatars.com; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
}

function sanitize_string(string $value): string {
    return trim(strip_tags($value));
}

function avatar_url(?string $photo, string $name): string {
    if ($photo) {
        $avatarPath = __DIR__ . '/../public/images/avatars/' . $photo;
        if (file_exists($avatarPath)) {
            return '/images/avatars/' . $photo;
        }

        $uploadPath = __DIR__ . '/../public/uploads/' . $photo;
        if (file_exists($uploadPath)) {
            return '/uploads/' . $photo;
        }
    }

    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach (array_slice($parts ?: [], 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $initials = $initials !== '' ? $initials : 'U';

    $palette = ['#e0453f', '#c9342f', '#ff9f1a', '#4eaf5a', '#2a9af3', '#6f7bf7'];
    $index = (int)(crc32(mb_strtolower($name)) % count($palette));
    $bg = $palette[$index];

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
         . '<rect width="128" height="128" fill="' . $bg . '"/>'
         . '<text x="50%" y="50%" dy="0.35em" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="56" fill="#ffffff">'
         . htmlspecialchars($initials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
         . '</text></svg>';

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

function format_time(string $time): string {
    return substr($time, 0, 5);
}

function day_name(int $day): string {
    $days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    return $days[$day] ?? '';
}

function day_abbr(int $day): string {
    $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    return $days[$day] ?? '';
}

function star_rating(float $rating): string {
    $full  = (int)floor($rating);
    $half  = ($rating - $full) >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $html  = '<div style="display:inline-flex;align-items:center;gap:0.1rem;">';
    for ($i = 0; $i < $full; $i++) {
        $html .= '<span class="star full">★</span>';
    }
    if ($half) {
        $html .= '<span class="star half">★</span>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<span class="star empty">☆</span>';
    }
    $html .= '</div>';
    return $html;
}

function class_type_label(string $type): string {
    return $type;
}

function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pt_status_label(string $status): string {
    $labels = [
        'pendente'   => 'Pendente',
        'confirmado' => 'Confirmado',
        'cancelado'  => 'Cancelado',
        'concluido'  => 'Concluído',
    ];
    return $labels[$status] ?? $status;
}

function equipment_status_label(string $status): string {
    $labels = [
        'disponivel' => 'Disponível',
        'em_uso'     => 'Em Uso',
        'manutencao' => 'Manutenção',
        'inativo'    => 'Inativo',
    ];
    return $labels[$status] ?? $status;
}

function equipment_status_class(string $status): string {
    $classes = [
        'disponivel' => 'status-available',
        'em_uso'     => 'status-in-use',
        'manutencao' => 'status-maintenance',
        'inativo'    => 'status-inactive',
    ];
    return $classes[$status] ?? '';
}

function class_image_url(array $cls): ?string {
    $name = (string)($cls['name'] ?? '');
    $type = (string)($cls['type'] ?? '');
    $id = (string)($cls['id'] ?? '');

    $candidates = array_filter([
        class_image_slug($name),
        class_image_slug($type),
        $id !== '' ? 'class-' . $id : '',
    ]);

    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $baseDir = __DIR__ . '/../public/images/classes/';

    foreach ($candidates as $slug) {
        foreach ($extensions as $ext) {
            $path = $baseDir . $slug . '.' . $ext;
            if (file_exists($path)) {
                return '/images/classes/' . $slug . '.' . $ext;
            }
        }
    }

    return null;
}

function class_image_slug(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $ascii = $value;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $ascii = $converted;
        }
    }

    $ascii = strtolower($ascii);
    $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';
    $ascii = trim($ascii, '-');

    return $ascii;
}
