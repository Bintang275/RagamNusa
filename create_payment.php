<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

function paymentResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$rentalsId = (int)($input['rentals_id'] ?? 0);
$method = $input['payment_method'] ?? '';

$allowedMethods = ['bank_transfer', 'e_wallet', 'qris', 'cash'];

if ($rentalsId <= 0 || !in_array($method, $allowedMethods, true)) {
    paymentResponse(false, 'Data pembayaran tidak valid.', null, 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM rentals
        WHERE rentals_id = ?
          AND renter_id = ?
        FOR UPDATE
    ");

    $pdo->beginTransaction();

    $stmt->execute([$rentalsId, $authenticatedUserId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        paymentResponse(false, 'Transaksi tidak ditemukan.', null, 404);
    }

    if (!in_array($rental['rental_status'], ['pending', 'confirmed'], true)) {
        $pdo->rollBack();
        paymentResponse(false, 'Transaksi tidak dapat dibayar.', null, 400);
    }

    $check = $pdo->prepare("
        SELECT payment_id
        FROM payments
        WHERE rentals_id = ?
          AND payment_status IN ('pending', 'paid')
        LIMIT 1
    ");

    $check->execute([$rentalsId]);

    if ($check->fetch()) {
        $pdo->rollBack();
        paymentResponse(false, 'Pembayaran untuk transaksi ini sudah tersedia.', null, 409);
    }

    $amount = (float)$rental['rental_price']
            + (float)$rental['deposit_total']
            + (float)$rental['shipping_cost'];

    if ($amount <= 0) {
        $pdo->rollBack();
        paymentResponse(false, 'Total pembayaran tidak valid.', null, 400);
    }

    $insert = $pdo->prepare("
        INSERT INTO payments
        (rentals_id, payment_method, amount, payment_status)
        VALUES (?, ?, ?, 'pending')
    ");

    $insert->execute([$rentalsId, $method, $amount]);

    $paymentId = $pdo->lastInsertId();

    $pdo->commit();

    paymentResponse(true, 'Pembayaran berhasil dibuat.', [
        'payment_id' => (int)$paymentId,
        'rentals_id' => $rentalsId,
        'payment_method' => $method,
        'amount' => $amount,
        'payment_status' => 'pending'
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    paymentResponse(false, 'Terjadi kesalahan saat membuat pembayaran.', null, 500);
}