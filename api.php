<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
const CPM_BASE = 'https://us-central1-cp-multiplayer.cloudfunctions.net';
const GOOGLE_AUTH =
    'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword?key=AIzaSyBW1ZbMiUeDZHYUO2bY8Bfnf5rRgrQGPTM';
/*
 * هذه القيم يجب أخذها حرفياً من cptypes.py
 * لا تضع مسارات من عندك.
 */
const GET_PLAYER_RECORDS = 'PUT_REAL_PATH_HERE';
const GET_ALL_CARS       = 'PUT_REAL_PATH_HERE';
function postJson(string $url, array $body, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'Content-Type: application/json'
        ], $headers),
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        return [
            'success' => false,
            'error' => $error
        ];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [
            'success' => false,
            'http_code' => $httpCode,
            'raw' => $response
        ];
    }
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $data
    ];
}
/*
 * تسجيل الدخول.
 *
 * كلمة المرور لا يتم حفظها.
 * يتم الاحتفاظ بالـ idToken في Session فقط.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Email and password are required'
            ]);
            exit;
        }
        $login = postJson(
            GOOGLE_AUTH,
            [
                'email' => $email,
                'password' => $password,
                'returnSecureToken' => true
            ]
        );
        if (!$login['success'] || empty($login['data']['idToken'])) {
            echo json_encode([
                'success' => false,
                'error' => 'CPM login failed',
                'details' => $login['data'] ?? null
            ]);
            exit;
        }
        $_SESSION['cpm_idToken'] = $login['data']['idToken'];
        $_SESSION['cpm_localId'] = $login['data']['localId'] ?? null;
        echo json_encode([
            'success' => true,
            'localId' => $_SESSION['cpm_localId']
        ]);
        exit;
    }
    /*
     * جلب بيانات الحساب الحقيقي.
     */
    if ($action === 'account') {
        if (empty($_SESSION['cpm_idToken'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Not logged in'
            ]);
            exit;
        }
        $token = $_SESSION['cpm_idToken'];
        $result = postJson(
            CPM_BASE . GET_PLAYER_RECORDS,
            [
                'data' => null
            ],
            [
                'Authorization: Bearer ' . $token
            ]
        );
        echo json_encode($result);
        exit;
    }
    /*
     * جلب السيارات.
     */
    if ($action === 'cars') {
        if (empty($_SESSION['cpm_idToken'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Not logged in'
            ]);
            exit;
        }
        $token = $_SESSION['cpm_idToken'];
        $result = postJson(
            CPM_BASE . GET_ALL_CARS,
            [
                'data' => null
            ],
            [
                'Authorization: Bearer ' . $token
            ]
        );
        echo json_encode($result);
        exit;
    }
    /*
     * تسجيل الخروج.
     */
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        echo json_encode([
            'success' => true
        ]);
        exit;
    }
}
echo json_encode([
    'success' => false,
    'error' => 'Invalid action'
]);
