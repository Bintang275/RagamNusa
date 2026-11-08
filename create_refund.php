<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

function refundResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    refundResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$rentalsId = (int)($input['rentals_id'] ?? 0);
$reason = trim($input['refund_reason'] ?? '');

if ($rentalsId <= 0) {
    refundResponse(false, 'Rental ID tidak valid.', null, 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM rentals
        WHERE rentals_id = ?
          AND renter_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$rentalsId, $authenticatedUserId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        refundResponse(false, 'Transaksi tidak ditemukan.', null, 404);
    }

    if ($rental['rental_status'] !== 'completed') {
        $pdo->rollBack();
        refundResponse(false, 'Refund hanya dapat diajukan setelah rental selesai.', null, 400);
    }

    $paymentStmt = $pdo->prepare("
        SELECT payment_id
        FROM payments
        WHERE rentals_id = ?
          AND payment_status = 'paid'
        ORDER BY paid_at DESC, payment_id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $paymentStmt->execute([$rentalsId]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $pdo->rollBack();
        refundResponse(false, 'Pembayaran lunas tidak ditemukan.', null, 400);
    }

    $refundStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM refunds
        WHERE payment_id = ?
          AND refund_status IN ('pending', 'processing', 'completed')
    ");

    $refundStmt->execute([$payment['payment_id']]);
    $alreadyRefunded = (float)$refundStmt->fetchColumn();

    $deposit = (float)$rental['deposit_total'];
    $remaining = $deposit - $alreadyRefunded;

    if ($remaining <= 0) {
        $pdo->rollBack();
        refundResponse(false, 'Deposit sudah diajukan atau dikembalikan seluruhnya.', null, 400);
    }

    $insert = $pdo->prepare("
        INSERT INTO refunds
        (payment_id, amount, refund_reason, refund_status)
        VALUES (?, ?, ?, 'pending')
    ");

    $insert->execute([
        $payment['payment_id'],
        $remaining,
        $reason !== '' ? $reason : null
    ]);

    $refundId = $pdo->lastInsertId();

    $pdo->commit();

    refundResponse(true, 'Pengajuan refund berhasil dibuat.', [
        'refund_id' => (int)$refundId,
        'payment_id' => (int)$payment['payment_id'],
        'amount' => $remaining,
        'refund_status' => 'pending'
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    refundResponse(false, 'Terjadi kesalahan saat mengajukan refund.', null, 500);
}