<?php

header('Content-Type: application/json');
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
    exit;
}
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Datos recibidos']);