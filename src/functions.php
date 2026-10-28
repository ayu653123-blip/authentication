<?php
declare(strict_types=1);

/** Helper HTTP + penyimpanan JSON untuk prototipe lokal. PHP >=7.4. */
function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(int $status, string $code, string $message): void
{
    json_response($status, ['error' => ['code' => $code, 'message' => $message]]);
}

function request_json(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!preg_match('~^application/json\s*(?:;|$)~i', $contentType)) {
        api_error(415, 'unsupported_media_type', 'Gunakan Content-Type: application/json.');
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 16384) {
        api_error(413, 'request_too_large', 'Body JSON maksimum 16 KiB.');
    }
    $decoded = json_decode($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !($decoded instanceof stdClass)) {
        api_error(400, 'invalid_json', 'Body wajib berupa objek JSON yang valid.');
    }
    return (array)$decoded;
}

function required_string(array $body, string $field): string
{
    if (!array_key_exists($field, $body)) {
        api_error(400, 'missing_field', 'Field wajib tidak lengkap: ' . $field);
    }
    if (!is_string($body[$field]) || trim($body[$field]) === '') {
        api_error(400, 'invalid_field', 'Field ' . $field . ' harus berupa string tidak kosong.');
    }
    return trim($body[$field]);
}

function json_file(string $filename): array
{
    $fp = fopen($filename, 'c+');
    if ($fp === false) {
        throw new RuntimeException('Gagal membuka penyimpanan.');
    }
    try {
        if (!flock($fp, LOCK_SH)) {
            throw new RuntimeException('Gagal mengunci file.');
        }
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('Format penyimpanan rusak.');
        }
        return $data;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** Lock seluruh siklus baca-ubah-tulis untuk mengurangi lost update. */
function update_json_file(string $filename, callable $transform)
{
    $fp = fopen($filename, 'c+');
    if ($fp === false) {
        throw new RuntimeException('Gagal membuka penyimpanan.');
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Gagal mengunci file.');
        }
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false) {
            throw new RuntimeException('Gagal membaca penyimpanan.');
        }
        $state = trim($raw) === '' ? [] : json_decode($raw, true);
        if (!is_array($state) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Format penyimpanan rusak.');
        }
        // Callback mengembalikan [data_baru, hasil, status].
        $out = $transform($state);
        if (!is_array($out) || !isset($out[0]) || !is_array($out[0])) {
            throw new RuntimeException('Operasi penyimpanan tidak valid.');
        }
        $serialized = json_encode($out[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($serialized === false) {
            throw new RuntimeException('Gagal mengubah data menjadi JSON.');
        }
        rewind($fp);
        if (!ftruncate($fp, 0)) {
            throw new RuntimeException('Gagal memperbarui file.');
        }
        if (fwrite($fp, $serialized . PHP_EOL) === false) {
            throw new RuntimeException('Gagal menyimpan data.');
        }
        fflush($fp);
        return $out;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
        api_error(401, 'missing_token', 'Kirim Authorization: Bearer <token>.');
    }
    return $matches[1];
}

function public_user(array $user): array
{
    return [
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'nim' => $user['nim'] ?? null,
        'prodi' => $user['prodi'] ?? null,
        'email' => $user['email'],
        'role' => $user['role'],
        'created_at' => $user['created_at'],
    ];
}

function current_session(string $sessionsFile, string $usersFile): array
{
    $token = bearer_token();
    $digest = hash('sha256', $token);
    $sessions = json_file($sessionsFile);
    foreach ($sessions as $session) {
        if (isset($session['token_hash'], $session['expires_at'], $session['user_id'])
            && hash_equals((string)$session['token_hash'], $digest)
            && (int)$session['expires_at'] > time()) {
            foreach (json_file($usersFile) as $user) {
                if ($user['user_id'] === $session['user_id']) {
                    return [$user, $session, $token];
                }
            }
        }
    }
    api_error(401, 'invalid_token', 'Token tidak valid, kedaluwarsa, atau sudah logout.');
}
