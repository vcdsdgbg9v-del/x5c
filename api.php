<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Email and password are required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
 * CPM1:
 * لا يوجد لدينا API رسمي موثّق يسمح بتسجيل الدخول
 * من موقع خارجي وجلب بيانات الحساب.
 *
 * لذلك لن نرسل كلمة المرور إلى API غير موثّق.
 */

unset($password);

echo json_encode([
    'success' => false,
    'error' => 'CPM1 API endpoint is not verified'
], JSON_UNESCAPED_UNICODE);
