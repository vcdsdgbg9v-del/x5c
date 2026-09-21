<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Email and password are required'
    ]);
    exit;
}

/*
 * هذا الجزء لا يرسل كلمة المرور إلى CPM أو أي API غير موثّق.
 * لا يوجد API رسمي موثّق يمكننا الاعتماد عليه لتسجيل دخول CPM1
 * من موقع خارجي بهذه الطريقة.
 */

echo json_encode([
    'success' => false,
    'error' => 'No verified official CPM1 login API is available'
], JSON_UNESCAPED_UNICODE);
