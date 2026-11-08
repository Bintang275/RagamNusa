<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function rentalResponse($success, $message, $data = null, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

function rentalInput()
{
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        rentalResponse(false, 'Format JSON tidak valid.', null, 400);
    }

    return $input;
}

function rentalUser()
{
    global $pdo, $authenticatedUserId;

    $stmt = $pdo->prepare(
        "SELECT user_id, name, role, status
         FROM users
         WHERE user_id = :id
         LIMIT 1"
    );

    $stmt->execute(['id' => $authenticatedUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] !== 'active') {
        rentalResponse(false, 'Akun tidak aktif.', null, 403);
    }

    return $user;
}

function rentalRequireRole($role)
{
    $user = rentalUser();

    if ($user['role'] !== $role) {
        rentalResponse(false, 'Akses ditolak.', null, 403);
    }

    return $user;
}

function rentalValidDate($date)
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
}

function rentalActiveStatuses()
{
    return ['pending', 'confirmed', 'ongoing', 'disputed'];
}
