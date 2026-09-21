<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
/*
 * ضع هنا Firebase API Key مصرحًا لك باستخدامه.
 */
const FIREBASE_API_KEY = 'PUT_YOUR_AUTHORIZED_FIREBASE_API_KEY_HERE';
const FIREBASE_LOGIN =
    'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword';
const CPM_BASE =
    'https://us-central1-cp-multiplayer.cloudfunctions.net';
function response_json(
    bool $success,
    $data = null,
    ?string $error = null,
    int $status = 200
): void {
    http_response_code($status);
    $response = [
        'success' => $success
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}
function get_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function post_json(
    string $url,
    array $payload,
    array $headers = []
): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers),
        CURLOPT_POSTFIELDS => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception($error);
    }
    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );
    curl_close($ch);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new Exception(
            'الخادم أعاد استجابة غير JSON. HTTP: ' .
            $httpCode
        );
    }
    return [
        'http_code' => $httpCode,
        'body' => $json
    ];
}
/*
 * تسجيل الدخول إلى Firebase
 */
function login_firebase(
    string $email,
    string $password
): array {
    $url =
        FIREBASE_LOGIN .
        '?key=' .
        urlencode(FIREBASE_API_KEY);
    return post_json(
        $url,
        [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true
        ]
    );
}
/*
 * Headers المستخدمة لطلب CPM
 */
function cpm_headers(
    string $token
): array {
    return [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: okhttp/3.12.13'
    ];
}
/*
 * التأكد من وجود جلسة
 */
function require_login(): string
{
    if (empty($_SESSION['cpm_id_token'])) {
        response_json(
            false,
            null,
            'غير مسجل الدخول.',
            401
        );
    }
    return (string)$_SESSION['cpm_id_token'];
}
$input = get_input();
$action =
    $_GET['action']
    ?? $input['action']
    ?? 'status';
/*
 * STATUS
 */
if ($action === 'status') {
    response_json(
        true,
        [
            'logged_in' =>
                !empty($_SESSION['cpm_id_token']),
            'localId' =>
                $_SESSION['cpm_local_id'] ?? null
        ]
    );
}
/*
 * LOGIN
 */
if ($action === 'login') {
    if (
        FIREBASE_API_KEY ===
        'PUT_YOUR_AUTHORIZED_FIREBASE_API_KEY_HERE'
    ) {
        response_json(
            false,
            null,
            'ضع Firebase API Key مصرحًا لك باستخدامه أولًا.',
            500
        );
    }
    $email =
        trim((string)($input['email'] ?? ''));
    $password =
        (string)($input['password'] ?? '');
    if ($email === '' || $password === '') {
        response_json(
            false,
            null,
            'البريد وكلمة المرور مطلوبان.',
            400
        );
    }
    try {
        $result =
            login_firebase(
                $email,
                $password
            );
        $data =
            $result['body'];
        if (empty($data['idToken'])) {
            response_json(
                false,
                null,
                $data['error']['message']
                    ?? 'فشل تسجيل الدخول.',
                401
            );
        }
        /*
         * نحفظ Token في Session فقط.
         * لا نحفظ كلمة المرور.
         */
        $_SESSION['cpm_id_token'] =
            $data['idToken'];
        $_SESSION['cpm_local_id'] =
            $data['localId'] ?? null;
        response_json(
            true,
            [
                'logged_in' => true,
                'localId' =>
                    $_SESSION['cpm_local_id']
            ]
        );
    } catch (Throwable $e) {
        response_json(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/*
 * LOGOUT
 */
if ($action === 'logout') {
    unset(
        $_SESSION['cpm_id_token'],
        $_SESSION['cpm_local_id']
    );
    response_json(
        true,
        [
            'logged_in' => false
        ]
    );
}
/*
 * من هنا يلزم تسجيل الدخول
 */
$token = require_login();
/*
 * PLAYER
 *
 * قراءة بيانات الحساب فقط.
 */
if ($action === 'player') {
    try {
        $result =
            post_json(
                CPM_BASE . '/GetPlayerRecords2/',
                ['data' => null],
                cpm_headers($token)
            );
        response_json(
            true,
            $result['body']
        );
    } catch (Throwable $e) {
        response_json(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/*
 * CARS
 *
 * قراءة قائمة السيارات فقط.
 */
if ($action === 'cars') {
    try {
        $result =
            post_json(
                CPM_BASE . '/TestGetAllCars/',
                ['data' => null],
                cpm_headers($token)
            );
        response_json(
            true,
            $result['body']
        );
    } catch (Throwable $e) {
        response_json(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/*
 * ACCOUNT
 *
 * يجلب الحساب والسيارات.
 */
if ($action === 'account') {
    try {
        $player =
            post_json(
                CPM_BASE . '/GetPlayerRecords2/',
                ['data' => null],
                cpm_headers($token)
            );
        $cars =
            post_json(
                CPM_BASE . '/TestGetAllCars/',
                ['data' => null],
                cpm_headers($token)
            );
        response_json(
            true,
            [
                'player' =>
                    $player['body'],
                'cars' =>
                    $cars['body']
            ]
        );
    } catch (Throwable $e) {
        response_json(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/*
 * Action غير معروف
 */
response_json(
    false,
    null,
    'Action غير معروف.',
    400
);
