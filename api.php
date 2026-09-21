<?php
declare(strict_types=1);
/*
 * غـشيـم 🇴🇲 - CPM Read Only Backend
 *
 * READ ONLY:
 * - Login
 * - GetPlayerRecords2
 * - TestGetAllCars
 * - Logout
 *
 * لا يوجد:
 * - تعديل Money
 * - تعديل Coins
 * - تعديل السيارات
 * - حذف الحساب
 * - send_device_os
 *
 * ملاحظة:
 * هذا اتصال غير رسمي بخدمات CPM كما ظهرت في مشروع
 * CarParkingMultiTool على GitHub.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
/* =========================================================
   CONFIG
   ========================================================= */
/*
 * هذا المفتاح ظاهر داخل المشروع العام الذي فحصناه.
 * قد يكون قديمًا أو متوقفًا.
 *
 * الأفضل استبداله بمفتاح Firebase مصرح لك باستخدامه.
 */
const FIREBASE_API_KEY =
    'AIzaSyBW1ZbMiUeDZHYUO2bY8Bfnf5rRgrQGPTM';
const FIREBASE_LOGIN_URL =
    'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyPassword';
const CPM_BASE_URL =
    'https://us-central1-cp-multiplayer.cloudfunctions.net';
/*
 * ظهر هذا الـ Firebase Instance ID داخل المشروع العام.
 * قد يتغير في المستقبل.
 */
const FIREBASE_INSTANCE_ID_TOKEN =
    'ePMUzsAGQb-f-NP9CQVsvJ:APA91bEI7czAgeEGx7qLl_U13REgY2bxindAuDiqGOYEwUtT7YZHwxGaz901AAoKAv8UborjjK7R78ldwkIJOqj9YmFrs6vz8eYqmBgp6VvGRvzxOZ01aAs';
/* =========================================================
   HELPERS
   ========================================================= */
function json_response(
    bool $success,
    $data = null,
    ?string $error = null,
    int $status = 200
): void {
    http_response_code($status);
    $out = [
        'success' => $success
    ];
    if ($data !== null) {
        $out['data'] = $data;
    }
    if ($error !== null) {
        $out['error'] = $error;
    }
    echo json_encode(
        $out,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );
    exit;
}
function read_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
/* =========================================================
   CURL
   ========================================================= */
function post_json(
    string $url,
    array $payload,
    array $headers = []
): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json'
        ], $headers),
        CURLOPT_POSTFIELDS =>
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );
    curl_close($ch);
    if ($body === false) {
        throw new Exception(
            'cURL error: ' . $error
        );
    }
    $decoded = json_decode(
        $body,
        true
    );
    if (!is_array($decoded)) {
        throw new Exception(
            'الخادم أعاد استجابة غير JSON. HTTP ' .
            $http_code
        );
    }
    return [
        'http_code' => $http_code,
        'body' => $decoded
    ];
}
/* =========================================================
   CPM HEADERS
   ========================================================= */
function cpm_headers(
    string $idToken,
    array $payload
): array {
    return [
        'Host: us-central1-cp-multiplayer.cloudfunctions.net',
        'Authorization: Bearer ' . $idToken,
        'firebase-instance-id-token: ' .
            FIREBASE_INSTANCE_ID_TOKEN,
        'Content-Type: application/json; charset=utf-8',
        'Accept-Encoding: gzip',
        'User-Agent: okhttp/3.12.13'
    ];
}
/* =========================================================
   FIREBASE LOGIN
   ========================================================= */
function firebase_login(
    string $email,
    string $password
): array {
    $url =
        FIREBASE_LOGIN_URL .
        '?key=' .
        urlencode(FIREBASE_API_KEY);
    $payload = [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => 'true'
    ];
    return post_json(
        $url,
        $payload
    );
}
/* =========================================================
   CPM: PLAYER RECORDS
   ========================================================= */
function get_player_records(
    string $idToken
): array {
    $url =
        CPM_BASE_URL .
        '/GetPlayerRecords2/';
    $payload = [
        'data' => null
    ];
    $response = post_json(
        $url,
        $payload,
        cpm_headers(
            $idToken,
            $payload
        )
    );
    return $response;
}
/* =========================================================
   CPM: ALL CARS
   ========================================================= */
function get_all_cars(
    string $idToken
): array {
    $url =
        CPM_BASE_URL .
        '/TestGetAllCars/';
    $payload = [
        'data' => null
    ];
    $response = post_json(
        $url,
        $payload,
        cpm_headers(
            $idToken,
            $payload
        )
    );
    return $response;
}
/* =========================================================
   PARSE ACCOUNT
   ========================================================= */
function parse_account(
    array $response
): array {
    if (
        !isset($response['body']['result'])
    ) {
        return [
            'raw' => $response['body']
        ];
    }
    $result = json_decode(
        $response['body']['result'],
        true
    );
    if (!is_array($result)) {
        return [
            'raw' => $response['body']
        ];
    }
    /*
     * الحقول التي يقرأها result.py
     * في المشروع الأصلي.
     */
    return [
        'localID' =>
            $result['localID'] ?? null,
        'name' =>
            $result['Name'] ?? null,
        'money' =>
            $result['money'] ?? null,
        'coin' =>
            $result['coin'] ?? null,
        'flags' =>
            $result['flags'] ?? null,
        'FriendsID' =>
            $result['FriendsID'] ?? null,
        'fcar' =>
            $result['fcar'] ?? null,
        'boughtFsos' =>
            $result['boughtFsos'] ?? null,
        'floats' =>
            $result['floats'] ?? null,
        'animations' =>
            $result['animations'] ?? null,
        'carIDnStatus' =>
            $result['carIDnStatus'] ?? null,
        'personEquipmentsMale' =>
            $result['personEquipmentsMale'] ?? null,
        'personEquipmentsFemale' =>
            $result['personEquipmentsFemale'] ?? null,
        'platesData' =>
            $result['platesData'] ?? null
    ];
}
/* =========================================================
   PARSE CARS
   ========================================================= */
function parse_cars(
    array $response
): array {
    if (
        !isset($response['body']['result'])
    ) {
        return [
            'raw' => $response['body']
        ];
    }
    $result = json_decode(
        $response['body']['result'],
        true
    );
    if (!is_array($result)) {
        return [
            'raw' => $response['body']
        ];
    }
    $cars = [];
    foreach ($result as $car) {
        if (is_array($car)) {
            $cars[] = [
                'CarID' =>
                    $car['CarID'] ?? null
            ];
        }
    }
    return $cars;
}
/* =========================================================
   ROUTER
   ========================================================= */
$data = read_json();
$action =
    $_GET['action']
    ?? $data['action']
    ?? 'status';
/* =========================================================
   STATUS
   ========================================================= */
if ($action === 'status') {
    json_response(
        true,
        [
            'logged_in' =>
                isset($_SESSION['cpm_id_token']),
            'localId' =>
                $_SESSION['cpm_local_id'] ?? null
        ]
    );
}
/* =========================================================
   LOGIN
   ========================================================= */
if ($action === 'login') {
    $email =
        trim(
            (string)($data['email'] ?? '')
        );
    $password =
        (string)($data['password'] ?? '');
    if ($email === '' || $password === '') {
        json_response(
            false,
            null,
            'أدخل البريد وكلمة المرور.',
            400
        );
    }
    try {
        $login =
            firebase_login(
                $email,
                $password
            );
        $body =
            $login['body'];
        if (
            !isset($body['idToken'])
        ) {
            $message =
                $body['error']['message']
                ?? 'فشل تسجيل الدخول';
            json_response(
                false,
                null,
                $message,
                401
            );
        }
        /*
         * لا نحفظ كلمة المرور.
         *
         * نحفظ فقط token داخل Session.
         */
        $_SESSION['cpm_id_token'] =
            $body['idToken'];
        $_SESSION['cpm_local_id'] =
            $body['localId']
            ?? null;
        /*
         * refreshToken لا نحتاجه
         * في هذا الإصدار.
         */
        json_response(
            true,
            [
                'logged_in' => true,
                'localId' =>
                    $_SESSION['cpm_local_id']
            ]
        );
    } catch (Throwable $e) {
        json_response(
            false,
            null,
            $e->getMessage(),
            500
        );
    }
}
/* =========================================================
   LOGOUT
   ========================================================= */
if ($action === 'logout') {
    unset(
        $_SESSION['cpm_id_token'],
        $_SESSION['cpm_local_id']
    );
    json_response(
        true,
        [
            'logged_in' => false
        ]
    );
}
/* =========================================================
   REQUIRE LOGIN
   ========================================================= */
if (
    !isset($_SESSION['cpm_id_token'])
) {
    json_response(
        false,
        null,
        'غير مسجل الدخول.',
        401
    );
}
$idToken =
    (string)$_SESSION['cpm_id_token'];
/* =========================================================
   PLAYER
   ========================================================= */
if ($action === 'player') {
    try {
        $response =
            get_player_records(
                $idToken
            );
        $account =
            parse_account(
                $response
            );
        json_response(
            true,
            $account
        );
    } catch (Throwable $e) {
        json_response(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/* =========================================================
   CARS
   ========================================================= */
if ($action === 'cars') {
    try {
        $response =
            get_all_cars(
                $idToken
            );
        $cars =
            parse_cars(
                $response
            );
        json_response(
            true,
            [
                'cars' => $cars
            ]
        );
    } catch (Throwable $e) {
        json_response(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/* =========================================================
   ACCOUNT = PLAYER + CARS
   ========================================================= */
if ($action === 'account') {
    try {
        $player =
            get_player_records(
                $idToken
            );
        $cars =
            get_all_cars(
                $idToken
            );
        json_response(
            true,
            [
                'account' =>
                    parse_account($player),
                'cars' =>
                    parse_cars($cars)
            ]
        );
    } catch (Throwable $e) {
        json_response(
            false,
            null,
            $e->getMessage(),
            502
        );
    }
}
/* =========================================================
   UNKNOWN ACTION
   ========================================================= */
json_response(
    false,
    null,
    'Action غير معروف.',
    400
);
