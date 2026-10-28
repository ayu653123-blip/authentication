<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['service' => 'gateway-demo', 'status' => 'ok']);
    exit;
}
$upstream = null;
if (strpos($path, '/api/auth/') === 0) {
    $upstream = 'http://127.0.0.1:8102';
} elseif ($path === '/api/courses') {
    $upstream = 'http://127.0.0.1:8103';
}
if ($upstream === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error'=>['code'=>'not_found','message'=>'Route gateway demo tidak tersedia.']]);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$headers = ['Accept: application/json'];
if (!empty($_SERVER['CONTENT_TYPE'])) $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($authorization !== '') $headers[] = 'Authorization: ' . $authorization;
$raw = file_get_contents('php://input');
$target = $upstream . ($_SERVER['REQUEST_URI'] ?? $path);
$context = stream_context_create(['http' => [
    'method' => $method,
    'header' => implode("\r\n", $headers),
    'content' => ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') ? $raw : '',
    'ignore_errors' => true,
    'timeout' => 3,
]]);
$response = @file_get_contents($target, false, $context);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error'=>['code'=>'bad_gateway','message'=>'Service tujuan tidak terhubung.']]);
    exit;
}
$status = 502;
foreach ($http_response_header ?? [] as $line) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) $status = (int)$m[1];
}
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo $response;
