<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

function response(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response([
        'success' => false,
        'error' => 'POST request required'
    ], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
| لا يتم تخزين كلمة المرور.
| هذا الجزء لا يرسل بيانات الدخول إلى API غير موثّق.
*/

if ($action === 'login') {

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($email === '' || $password === '') {
        response([
            'success' => false,
            'error' => 'Email and password are required'
        ], 400);
    }

    /*
     * لا يوجد API رسمي موثّق من CPM1 يسمح لنا
     * بتسجيل الدخول من موقع خارجي بهذه الطريقة.
     *
     * لذلك لا يتم إرسال كلمة المرور إلى جهة غير موثوقة.
     */

    unset($password);

    response([
        'success' => false,
        'error' => 'No verified official CPM1 login API is available'
    ], 501);
}


/*
|--------------------------------------------------------------------------
| ACCOUNT
|--------------------------------------------------------------------------
| قراءة بيانات الحساب فقط.
*/

if ($action === 'account') {

    if (empty($_SESSION['cpm_authenticated'])) {
        response([
            'success' => false,
            'error' => 'Not logged in'
        ], 401);
    }

    response([
        'success' => false,
        'error' => 'No verified official read-only CPM1 endpoint is configured'
    ], 501);
}


/*
|--------------------------------------------------------------------------
| CARS
|--------------------------------------------------------------------------
| قراءة السيارات فقط.
*/

if ($action === 'cars') {

    if (empty($_SESSION['cpm_authenticated'])) {
        response([
            'success' => false,
            'error' => 'Not logged in'
        ], 401);
    }

    response([
        'success' => false,
        'error' => 'No verified official read-only CPM1 endpoint is configured'
    ], 501);
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
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

    response([
        'success' => true
    ]);
}


response([
    'success' => false,
    'error' => 'Invalid action'
], 400);
