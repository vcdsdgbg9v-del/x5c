<?php
session_start();

/*
|--------------------------------------------------------------------------
| غـشيـم 🇴🇲 - CPM Backend
|--------------------------------------------------------------------------
| ملاحظات:
| - لا يتم تخزين كلمة مرور CPM.
| - لا يوجد send_device_os.
| - التوكن يحفظ في Session فقط.
| - هذا الكود مخصص لعمليات القراءة فقط.
|--------------------------------------------------------------------------
*/

const CPM_API = 'https://YOUR-AUTHORIZED-CPM-API.example/api2';
const REQUEST_TIMEOUT = 15;

/*
|--------------------------------------------------------------------------
| HTTP Request
|--------------------------------------------------------------------------
*/

function cpmRequest(string $endpoint, array $data): array
{
    $url = rtrim(CPM_API, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => http_build_query($data),
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => REQUEST_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT  => 8,
        CURLOPT_SSL_VERIFYPEER  => true,
        CURLOPT_SSL_VERIFYHOST  => 2,
        CURLOPT_HTTPHEADER      => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'error'   => $error
        ];
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            'success' => false,
            'error'   => 'Invalid API response',
            'status'  => $status
        ];
    }

    return $json;
}


/*
|--------------------------------------------------------------------------
| CPM Login
|--------------------------------------------------------------------------
*/

function cpmLogin(string $email, string $password, string $accessKey): array
{
    /*
     * كلمة المرور موجودة هنا مؤقتاً فقط.
     * لا يتم حفظها في Database أو Session.
     */

    return cpmRequest('account_login', [
        'email'      => $email,
        'password'   => $password,
        'access_key' => $accessKey
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Player Data
|--------------------------------------------------------------------------
*/

function getPlayerData(string $authToken, string $accessKey): array
{
    return cpmRequest('get_data', [
        'auth_token' => $authToken,
        'access_key' => $accessKey
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Car
|--------------------------------------------------------------------------
*/

function getPlayerCar(
    string $authToken,
    string $accessKey,
    string $carId
): array {

    return cpmRequest('get_car', [
        'auth_token' => $authToken,
        'access_key' => $accessKey,
        'car_id'     => $carId
    ]);
}


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

function logout(): void
{
    unset($_SESSION['cpm_auth_token']);
    unset($_SESSION['cpm_access_key']);
    unset($_SESSION['cpm_player']);

    session_regenerate_id(true);
}


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

$message = null;
$error   = null;


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'login') {

    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $accessKey  = trim($_POST['access_key'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح.';
    }

    elseif ($password === '') {
        $error = 'أدخل كلمة المرور.';
    }

    elseif ($accessKey === '') {
        $error = 'أدخل Access Key.';
    }

    else {

        $login = cpmLogin(
            $email,
            $password,
            $accessKey
        );

        /*
         * مهم:
         * لا نخزن $password هنا.
         */

        if (!empty($login['auth_token'])) {

            session_regenerate_id(true);

            $_SESSION['cpm_auth_token'] = $login['auth_token'];
            $_SESSION['cpm_access_key'] = $accessKey;

            /*
             * جلب بيانات اللاعب
             */

            $player = getPlayerData(
                $_SESSION['cpm_auth_token'],
                $_SESSION['cpm_access_key']
            );

            if (!empty($player)) {

                $_SESSION['cpm_player'] = $player;

                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            $error = 'تم تسجيل الدخول لكن تعذر جلب بيانات الحساب.';
        }

        else {
            $error = $login['error'] ?? 'فشل تسجيل الدخول.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

if (isset($_GET['logout'])) {
    logout();

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


/*
|--------------------------------------------------------------------------
| Current Player
|--------------------------------------------------------------------------
*/

$player = $_SESSION['cpm_player'] ?? null;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>غـشيـم 🇴🇲</title>

<style>

body {
    margin: 0;
    background: #0d0d0d;
    color: white;
    font-family: Arial, sans-serif;
}

.container {
    width: min(600px, 92%);
    margin: 60px auto;
}

.card {
    background: #171717;
    padding: 25px;
    border-radius: 18px;
    margin-bottom: 20px;
}

h1 {
    text-align: center;
}

input {
    width: 100%;
    box-sizing: border-box;
    padding: 14px;
    margin: 7px 0;
    border-radius: 10px;
    border: 1px solid #333;
    background: #101010;
    color: white;
}

button {
    width: 100%;
    padding: 14px;
    margin-top: 10px;
    border: 0;
    border-radius: 10px;
    cursor: pointer;
    background: #ffffff;
    color: #000;
    font-weight: bold;
}

.error {
    background: #3b1515;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.data {
    background: #101010;
    padding: 12px;
    border-radius: 10px;
    margin-top: 8px;
}

a {
    color: white;
}

</style>

</head>

<body>

<div class="container">

<h1>غـشيـم 🇴🇲</h1>

<?php if ($error): ?>

<div class="error">
    <?= htmlspecialchars($error) ?>
</div>

<?php endif; ?>


<?php if (!$player): ?>

<div class="card">

<h2>ربط حساب CPM</h2>

<form method="POST">

<input type="hidden"
       name="action"
       value="login">

<input
    type="email"
    name="email"
    placeholder="CPM Email"
    required
>

<input
    type="password"
    name="password"
    placeholder="CPM Password"
    required
>

<input
    type="text"
    name="access_key"
    placeholder="Access Key"
    required
>

<button type="submit">
    تسجيل الدخول
</button>

</form>

</div>

<?php else: ?>


<div class="card">

<h2>بيانات الحساب</h2>

<?php

/*
 * نحاول قراءة البيانات بأكثر من شكل
 * لأن أسماء الحقول تختلف حسب الـAPI.
 */

$name = $player['Name']
    ?? $player['name']
    ?? 'غير معروف';

$id = $player['localID']
    ?? $player['id']
    ?? 'غير معروف';

$money = $player['money']
    ?? 0;

$coins = $player['coin']
    ?? $player['coins']
    ?? 0;

?>

<div class="data">
<strong>الاسم:</strong>
<?= htmlspecialchars((string)$name) ?>
</div>

<div class="data">
<strong>ID:</strong>
<?= htmlspecialchars((string)$id) ?>
</div>

<div class="data">
<strong>Money:</strong>
<?= htmlspecialchars((string)$money) ?>
</div>

<div class="data">
<strong>Coins:</strong>
<?= htmlspecialchars((string)$coins) ?>
</div>

</div>


<div class="card">

<h2>السيارات</h2>

<p>
يمكن هنا إضافة واجهة اختيار السيارة ثم استدعاء
getPlayerCar().
</p>

</div>


<div class="card">

<a href="?logout">
    تسجيل الخروج
</a>

</div>

<?php endif; ?>

</div>

</body>
</html>
