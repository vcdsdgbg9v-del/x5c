<?php
/*
 * غشيم 🇴🇲 - CPM Read-Only Backend
 *
 * الوظيفة:
 * 1) تسجيل الدخول المصرح به
 * 2) حفظ idToken في Session فقط
 * 3) جلب بيانات الحساب عبر GetPlayerRecords2
 * 4) جلب قائمة السيارات عبر TestGetAllCars
 *
 * لا يتم حفظ كلمة المرور.
 * لا يوجد send_device_os.
 * لا توجد عمليات تعديل على الحساب.
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
/*
 * ضع API Key المصرح به هنا.
 * لا تضع كلمة مرور حسابك هنا.
 */
const FIREBASE_API_KEY = 'PUT_YOUR_AUTHORIZED_FIREBASE_API_KEY_HERE';
const FIREBASE_LOGIN =
    'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword';
const CPM_BASE =
    'https://us-central1-cp-multiplayer.cloudfunctions.net';
function response_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );
    exit;
}
function read_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function curl_json(
    string $url,
    array $payload,
    array $headers = []
): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        response_json([
            'success' => false,
            'error' => 'Connection error',
            'details' => $error
        ], 502);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($body, true);
    return [
        'http_code' => $status,
        'body' => $body,
        'json' => is_array($decoded) ? $decoded : null
    ];
}
/*
 * تسجيل الدخول
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_GET['action'] ?? '') === 'login') {
    $data = read_json();
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    if ($email === '' || $password === '') {
        response_json([
            'success' => false,
            'error' => 'Email and password are required'
        ], 400);
    }
    if (FIREBASE_API_KEY ===
        'PUT_YOUR_AUTHORIZED_FIREBASE_API_KEY_HERE') {
        response_json([
            'success' => false,
            'error' => 'Firebase API key is not configured'
        ], 500);
    }
    /*
     * كلمة المرور تستخدم فقط أثناء طلب تسجيل الدخول.
     * لا يتم تخزينها في Session.
     */
    $url = FIREBASE_LOGIN . '?key=' .
        rawurlencode(FIREBASE_API_KEY);
    $result = curl_json($url, [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ]);
    if ($result['http_code'] < 200 ||
        $result['http_code'] >= 300) {
        response_json([
            'success' => false,
            'error' => 'CPM login failed',
            'http_code' => $result['http_code'],
            'response' => $result['json'] ?? $result['body']
        ], 401);
    }
    $login = $result['json'];
    if (!is_array($login) ||
        empty($login['idToken'])) {
        response_json([
            'success' => false,
            'error' => 'Login response did not contain idToken',
            'response' => $login ?? $result['body']
        ], 502);
    }
    /*
     * نخزن التوكن فقط.
     * لا نخزن كلمة المرور.
     */
    $_SESSION['cpm_id_token'] = $login['idToken'];
    if (!empty($login['localId'])) {
        $_SESSION['cpm_local_id'] = $login['localId'];
    }
    response_json([
        'success' => true,
        'message' => 'Login successful'
    ]);
}
/*
 * تسجيل الخروج
 */
if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['cpm_id_token']);
    unset($_SESSION['cpm_local_id']);
    response_json([
        'success' => true,
        'message' => 'Logged out'
    ]);
}
/*
 * فحص حالة تسجيل الدخول
 */
if (($_GET['action'] ?? '') === 'status') {
    response_json([
        'success' => true,
        'logged_in' => !empty($_SESSION['cpm_id_token'])
    ]);
}
/*
 * التأكد من وجود Session
 */
if (empty($_SESSION['cpm_id_token'])) {
    response_json([
        'success' => false,
        'error' => 'Not logged in'
    ], 401);
}
$token = $_SESSION['cpm_id_token'];
/*
 * جلب بيانات اللاعب
 */
if (($_GET['action'] ?? '') === 'player') {
    $result = curl_json(
        CPM_BASE . '/GetPlayerRecords2/',
        [
            'data' => null
        ],
        [
            'Authorization: Bearer ' . $token
        ]
    );
    response_json([
        'success' => (
            $result['http_code'] >= 200 &&
            $result['http_code'] < 300
        ),
        'http_code' => $result['http_code'],
        'data' => $result['json'] ?? $result['body']
    ], $result['http_code'] ?: 502);
}
/*
 * جلب قائمة السيارات
 */
if (($_GET['action'] ?? '') === 'cars') {
    $result = curl_json(
        CPM_BASE . '/TestGetAllCars/',
        [
            'data' => null
        ],
        [
            'Authorization: Bearer ' . $token
        ]
    );
    response_json([
        'success' => (
            $result['http_code'] >= 200 &&
            $result['http_code'] < 300
        ),
        'http_code' => $result['http_code'],
        'data' => $result['json'] ?? $result['body']
    ], $result['http_code'] ?: 502);
}
/*
 * جلب بيانات الحساب + السيارات دفعة واحدة
 */
if (($_GET['action'] ?? '') === 'account') {
    $player = curl_json(
        CPM_BASE . '/GetPlayerRecords2/',
        [
            'data' => null
        ],
        [
            'Authorization: Bearer ' . $token
        ]
    );
    $cars = curl_json(
        CPM_BASE . '/TestGetAllCars/',
        [
            'data' => null
        ],
        [
            'Authorization: Bearer ' . $token
        ]
    );
    response_json([
        'success' => true,
        'player' => [
            'http_code' => $player['http_code'],
            'data' => $player['json'] ?? $player['body']
        ],
        'cars' => [
            'http_code' => $cars['http_code'],
            'data' => $cars['json'] ?? $cars['body']
        ]
    ]);
}
/*
 * أمر غير معروف
 */
response_json([
    'success' => false,
    'error' => 'Unknown action'
], 404);
