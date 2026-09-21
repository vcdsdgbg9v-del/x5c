<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

/*
 * ضع هنا رابط الـ API المصرح لك باستخدامه.
 */
$API_URL = 'https://YOUR-AUTHORIZED-API.example/api';

$action = $_GET['action'] ?? '';

function requestApi(string $url, array $data = []): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => $error
        ];
    }

    $decoded = json_decode($response, true);

    return [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'data' => $decoded ?? $response
    ];
}

switch ($action) {

    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Username and password are required'
            ]);
            exit;
        }

        echo json_encode(
            requestApi($API_URL . '/account_login', [
                'username' => $username,
                'password' => $password
            ])
        );
        break;

    case 'player':
        $token = $_POST['token'] ?? '';

        if ($token === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Token is required'
            ]);
            exit;
        }

        echo json_encode(
            requestApi($API_URL . '/get_data', [
                'token' => $token
            ])
        );
        break;

    case 'car':
        $token = $_POST['token'] ?? '';

        if ($token === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Token is required'
            ]);
            exit;
        }

        echo json_encode(
            requestApi($API_URL . '/get_car', [
                'token' => $token
            ])
        );
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Unknown action'
        ]);
}
