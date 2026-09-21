<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
$allowedOrigin = 'https://vcdsdgbg9v-del.github.io';
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header("Access-Control-Allow-Origin: $allowedOrigin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
/*
 * لا تضع مفتاح Firebase الذي ظهر في الأكواد المنشورة
 * إلا إذا كنت مخولاً باستخدامه.
 */
const FIREBASE_API_KEY = 'PUT_YOUR_FIREBASE_API_KEY_HERE';
const FIREBASE_LOGIN =
    'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword';
const CPM_BASE =
    'https://us-central1-cp-multiplayer.cloudfunctions.net';
function response_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}
function input_json(): array
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
    if ($ch === false) {
        throw new Exception('Unable to initialize cURL');
    }
    $defaultHeaders = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
        'Accept-Encoding: gzip',
        'User-Agent: okhttp/3.12.13'
    ];
    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        throw new Exception('cURL error: ' . $error);
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new Exception(
            'Invalid JSON response from server. HTTP ' . $status
        );
    }
    return [
        'status' => $status,
        'body' => $decoded
    ];
}
function require_token(): string
{
    if (empty($_SESSION['idToken'])) {
        response_json([
            'success' => false,
            'error' => 'Not logged in'
        ], 401);
    }
    return (string) $_SESSION['idToken'];
}
function cpm_headers(string $idToken): array
{
    return [
        'Authorization: Bearer ' . $idToken
    ];
}
function get_player_records(string $idToken): array
{
    return post_json(
        CPM_BASE . '/GetPlayerRecords2/',
        ['data' => null],
        cpm_headers($idToken)
    );
}
function get_all_cars(string $idToken): array
{
    return post_json(
        CPM_BASE . '/TestGetAllCars/',
        ['data' => null],
        cpm_headers($idToken)
    );
}
$action = $_GET['action'] ?? 'status';
try {
    /*
     * LOGIN
     *
     * POST:
     * {
     *   "email": "your@email.com",
     *   "password": "your-password"
     * }
     */
    if ($action === 'login') {
        $input = input_json();
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if ($email === '' || $password === '') {
            response_json([
                'success' => false,
                'error' => 'Email and password are required'
            ], 400);
        }
        if (FIREBASE_API_KEY === 'PUT_YOUR_FIREBASE_API_KEY_HERE') {
            response_json([
                'success' => false,
                'error' => 'Firebase API key is not configured'
            ], 500);
        }
        $url = FIREBASE_LOGIN . '?key=' . urlencode(FIREBASE_API_KEY);
        $login = post_json(
            $url,
            [
                'email' => $email,
                'password' => $password,
                'returnSecureToken' => true
            ]
        );
        $auth = $login['body'];
        if (isset($auth['error'])) {
            response_json([
                'success' => false,
                'error' => 'Login failed',
                'details' => $auth['error']
            ], 401);
        }
        if (
            empty($auth['idToken']) ||
            empty($auth['localId'])
        ) {
            response_json([
                'success' => false,
                'error' => 'Invalid authentication response'
            ], 401);
        }
        /*
         * نحفظ التوكنات في Session فقط.
         * كلمة المرور لا يتم حفظها.
         */
        $_SESSION['idToken'] = (string)$auth['idToken'];
        $_SESSION['localId'] = (string)$auth['localId'];
        response_json([
            'success' => true,
            'localID' => $_SESSION['localId']
        ]);
    }
    /*
     * LOGOUT
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
        response_json([
            'success' => true
        ]);
    }
    /*
     * STATUS
     */
    if ($action === 'status') {
        response_json([
            'success' => true,
            'logged_in' => !empty($_SESSION['idToken']),
            'localID' => $_SESSION['localId'] ?? null
        ]);
    }
    /*
     * PLAYER DATA
     */
    if ($action === 'player') {
        $token = require_token();
        $result = get_player_records($token);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            response_json([
                'success' => false,
                'error' => 'CPM request failed',
                'http_status' => $result['status'],
                'response' => $result['body']
            ], 502);
        }
        $raw = $result['body'];
        $account = null;
        if (isset($raw['result']) && is_string($raw['result'])) {
            $account = json_decode($raw['result'], true);
        }
        if (!is_array($account)) {
            response_json([
                'success' => false,
                'error' => 'Could not decode player data',
                'raw' => $raw
            ], 502);
        }
        /*
         * البيانات التي كان account_data يستخرجها
         */
        response_json([
            'success' => true,
            'Name' => $account['Name'] ?? null,
            'localID' => $account['localID'] ?? null,
            'money' => $account['money'] ?? null,
            'coin' => $account['coin'] ?? null,
            'flags' => $account['flags'] ?? null,
            'FriendsID' => $account['FriendsID'] ?? null,
            'fcar' => $account['fcar'] ?? null,
            'boughtFsos' => $account['boughtFsos'] ?? null,
            'floats' => $account['floats'] ?? null,
            'animations' => $account['animations'] ?? null,
            'carIDnStatus' => $account['carIDnStatus'] ?? null,
            'personEquipmentsMale' =>
                $account['personEquipmentsMale'] ?? null,
            'personEquipmentsFemale' =>
                $account['personEquipmentsFemale'] ?? null,
            'platesData' =>
                $account['platesData'] ?? null
        ]);
    }
    /*
     * CAR LIST
     */
    if ($action === 'cars') {
        $token = require_token();
        $result = get_all_cars($token);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            response_json([
                'success' => false,
                'error' => 'CPM request failed',
                'http_status' => $result['status'],
                'response' => $result['body']
            ], 502);
        }
        $raw = $result['body'];
        $cars = [];
        if (isset($raw['result']) && is_string($raw['result'])) {
            $decoded = json_decode($raw['result'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $car) {
                    if (is_array($car) && isset($car['CarID'])) {
                        $cars[] = $car['CarID'];
                    }
                }
            }
        }
        response_json([
            'success' => true,
            'cars' => $cars
        ]);
    }
    /*
     * ACCOUNT
     * يجمع بيانات اللاعب + قائمة السيارات
     */
    if ($action === 'account') {
        $token = require_token();
        $player = get_player_records($token);
        $cars = get_all_cars($token);
        $account = null;
        if (
            isset($player['body']['result']) &&
            is_string($player['body']['result'])
        ) {
            $account = json_decode(
                $player['body']['result'],
                true
            );
        }
        if (!is_array($account)) {
            response_json([
                'success' => false,
                'error' => 'Could not decode player data'
            ], 502);
        }
        $carList = [];
        if (
            isset($cars['body']['result']) &&
            is_string($cars['body']['result'])
        ) {
            $decodedCars = json_decode(
                $cars['body']['result'],
                true
            );
            if (is_array($decodedCars)) {
                foreach ($decodedCars as $car) {
                    if (
                        is_array($car) &&
                        isset($car['CarID'])
                    ) {
                        $carList[] = $car['CarID'];
                    }
                }
            }
        }
        response_json([
            'success' => true,
            'account' => [
                'Name' => $account['Name'] ?? null,
                'localID' => $account['localID'] ?? null,
                'money' => $account['money'] ?? null,
                'coin' => $account['coin'] ?? null,
                'flags' => $account['flags'] ?? null,
                'FriendsID' => $account['FriendsID'] ?? null,
                'animations' => $account['animations'] ?? null
            ],
            'cars' => $carList
        ]);
    }
    response_json([
        'success' => false,
        'error' => 'Unknown action'
    ], 404);
} catch (Throwable $e) {
    response_json([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
