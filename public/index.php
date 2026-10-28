<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/functions.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$dataDir = dirname(__DIR__) . '/data';
$usersFile = $dataDir . '/users.json';
$sessionsFile = $dataDir . '/sessions.json';

try {
    if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Folder data tidak bisa dibuat.');
    }

    $routes = [
        '/health' => ['GET'],
        '/api/auth/register' => ['POST'],
        '/api/auth/login' => ['POST'],
        '/api/auth/me' => ['GET'],
        '/api/auth/validate' => ['GET'],
        '/api/auth/logout' => ['DELETE'],
    ];
    if (!isset($routes[$path])) {
        api_error(404, 'endpoint_not_found', 'Endpoint tidak ditemukan.');
    }
    if (!in_array($method, $routes[$path], true)) {
        header('Allow: ' . implode(', ', $routes[$path]));
        api_error(405, 'method_not_allowed', 'HTTP method tidak didukung pada endpoint ini.');
    }

    if ($path === '/health') {
        json_response(200, ['service' => 'authentication-service', 'status' => 'ok', 'version' => '1.0']);
    }

    if ($path === '/api/auth/register') {
        $body = request_json();
        $name = required_string($body, 'name');
        $nim = required_string($body, 'nim');
        $prodi = required_string($body, 'prodi');
        $email = strtolower(required_string($body, 'email'));
        $password = required_string($body, 'password');
        if (strlen($name) > 100 || strlen($name) < 2) {
            api_error(400, 'invalid_field', 'Nama harus 2–100 karakter.');
        }
        // NIM diperlakukan sebagai string agar angka nol di depan tidak hilang.
        if (!preg_match('/^[0-9]{6,20}$/', $nim)) {
            api_error(400, 'invalid_field', 'NIM harus berisi 6–20 digit angka.');
        }
        if (strlen($prodi) < 2 || strlen($prodi) > 120) {
            api_error(400, 'invalid_field', 'Prodi harus 2–120 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            api_error(400, 'invalid_field', 'Format email tidak valid.');
        }
        if (strlen($password) < 8 || strlen($password) > 128) {
            api_error(400, 'invalid_field', 'Password harus 8–128 karakter.');
        }
        $result = update_json_file($usersFile, function (array $users) use ($name, $nim, $prodi, $email, $password): array {
            foreach ($users as $u) {
                if (($u['email'] ?? null) === $email) {
                    return [$users, null, 'email_exists'];
                }
                if (($u['nim'] ?? null) === $nim) {
                    return [$users, null, 'nim_exists'];
                }
            }
            $user = [
                'user_id' => 'usr-' . bin2hex(random_bytes(8)),
                'name' => $name,
                'nim' => $nim,
                'prodi' => $prodi,
                'email' => $email,
                'role' => 'student', // Registrasi publik tidak boleh menetapkan admin.
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => gmdate('c'),
            ];
            $users[] = $user;
            return [$users, public_user($user), 'created'];
        });
        if ($result[2] === 'email_exists') {
            api_error(409, 'email_exists', 'Email sudah terdaftar. Gunakan endpoint login.');
        }
        if ($result[2] === 'nim_exists') {
            api_error(409, 'nim_exists', 'NIM sudah terdaftar. Gunakan NIM yang berbeda.');
        }
        json_response(201, ['data' => $result[1]]);
    }

    if ($path === '/api/auth/login') {
        $body = request_json();
        $email = strtolower(required_string($body, 'email'));
        $password = required_string($body, 'password');
        $matching = null;
        foreach (json_file($usersFile) as $u) {
            if ($u['email'] === $email) {
                $matching = $u;
                break;
            }
        }
        if ($matching === null || !password_verify($password, $matching['password_hash'])) {
            api_error(401, 'invalid_credentials', 'Email atau password salah.');
        }
        $token = bin2hex(random_bytes(32));
        $now = time();
        $expires = $now + 3600;
        update_json_file($sessionsFile, function (array $sessions) use ($matching, $token, $now, $expires): array {
            $sessions = array_values(array_filter($sessions, function ($s) use ($now): bool {
                return isset($s['expires_at']) && (int)$s['expires_at'] > $now;
            }));
            $sessions[] = [
                'token_hash' => hash('sha256', $token),
                'user_id' => $matching['user_id'],
                'created_at' => gmdate('c', $now),
                'expires_at' => $expires,
            ];
            return [$sessions, true, 'created'];
        });
        json_response(200, [
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'user' => public_user($matching),
            ],
        ]);
    }

    if ($path === '/api/auth/me' || $path === '/api/auth/validate') {
        list($user, $session) = current_session($sessionsFile, $usersFile);
        if ($path === '/api/auth/validate') {
            json_response(200, [
                'data' => [
                    'valid' => true,
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'expires_at' => gmdate('c', (int)$session['expires_at']),
                ],
            ]);
        }
        json_response(200, ['data' => public_user($user)]);
    }

    if ($path === '/api/auth/logout') {
        list($user, $session, $token) = current_session($sessionsFile, $usersFile);
        $hash = hash('sha256', $token);
        update_json_file($sessionsFile, function (array $sessions) use ($hash): array {
            $remaining = array_values(array_filter($sessions, function ($s) use ($hash): bool {
                return !isset($s['token_hash']) || !hash_equals((string)$s['token_hash'], $hash);
            }));
            return [$remaining, true, 'revoked'];
        });
        json_response(200, ['data' => ['message' => 'Logout berhasil. Token tidak berlaku lagi.']]);
    }
} catch (Throwable $e) {
    error_log('Authentication storage failure: ' . $e->getMessage());
    api_error(500, 'internal_error', 'Terjadi gangguan internal pada layanan.');
}
