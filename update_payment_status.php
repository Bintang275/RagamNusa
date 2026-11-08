<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

function paymentStatusResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentStatusResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$paymentId = (int)($input['payment_id'] ?? 0);
$newStatus = $input['payment_status'] ?? '';

$allowedStatuses = ['paid', 'failed', 'expired'];

if ($paymentId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    paymentStatusResponse(false, 'Data status pembayaran tidak valid.', null, 400);
}

try {
    $roleStmt = $pdo->prepare("
        SELECT role FROM users WHERE user_id = ?
    ");
    $roleStmt->execute([$authenticatedUserId]);
    $user = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'admin') {
        paymentStatusResponse(false, 'Akses ditolak.', null, 403);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM payments
        WHERE payment_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $pdo->rollBack();
        paymentStatusResponse(false, 'Pembayaran tidak ditemukan.', null, 404);
    }

    if ($payment['payment_status'] !== 'pending') {
        $pdo->rollBack();
        paymentStatusResponse(false, 'Status pembayaran tidak dapat diubah lagi.', null, 400);
    }

    $update = $pdo->prepare("
        UPDATE payments
        SET payment_status = ?,
            paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE NULL END
        WHERE payment_id = ?
    ");

    $update->execute([$newStatus, $newStatus, $paymentId]);

    $pdo->commit();

    paymentStatusResponse(true, 'Status pembayaran berhasil diperbarui.', [
        'payment_id' => $paymentId,
        'payment_status' => $newStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    paymentStatusResponse(false, 'Terjadi kesalahan saat memperbarui pembayaran.', null, 500);
}