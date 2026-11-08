<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

$paymentId = (int)($_GET['payment_id'] ?? 0);

if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment ID tidak valid.',
        'data' => null
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            r.renter_id,
            r.start_date,
            r.end_date,
            r.rental_price,
            r.deposit_total,
            r.shipping_cost,
            r.rental_status
        FROM payments p
        JOIN rentals r ON r.rentals_id = p.rentals_id
        WHERE p.payment_id = ?
    ");

    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Pembayaran tidak ditemukan.',
            'data' => null
        ]);
        exit;
    }

    $userStmt = $pdo->prepare("
        SELECT role FROM users WHERE user_id = ?
    ");

    $userStmt->execute([$authenticatedUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        (
            $user['role'] !== 'admin' &&
            (int)$payment['renter_id'] !== (int)$authenticatedUserId
        )
    ) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Akses ditolak.',
            'data' => null
        ]);
        exit;
    }

    unset($payment['renter_id']);

    echo json_encode([
        'success' => true,
        'message' => 'Detail pembayaran berhasil diambil.',
        'data' => $payment
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil detail pembayaran.',
        'data' => null
    ]);
}