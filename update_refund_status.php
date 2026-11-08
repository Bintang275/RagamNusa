<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

function updateRefundResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    updateRefundResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$refundId = (int)($input['refund_id'] ?? 0);
$newStatus = $input['refund_status'] ?? '';
$reference = trim($input['refund_reference'] ?? '');

$allowedStatuses = ['processing', 'completed', 'failed'];

if ($refundId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    updateRefundResponse(false, 'Data refund tidak valid.', null, 400);
}

try {
    $roleStmt = $pdo->prepare("
        SELECT role FROM users WHERE user_id = ?
    ");

    $roleStmt->execute([$authenticatedUserId]);
    $user = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['role'] !== 'admin') {
        updateRefundResponse(false, 'Akses ditolak.', null, 403);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM refunds
        WHERE refund_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$refundId]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$refund) {
        $pdo->rollBack();
        updateRefundResponse(false, 'Pengajuan refund tidak ditemukan.', null, 404);
    }

    $currentStatus = $refund['refund_status'];

    $transitions = [
        'pending' => ['processing', 'failed'],
        'processing' => ['completed', 'failed']
    ];

    if (
        !isset($transitions[$currentStatus]) ||
        !in_array($newStatus, $transitions[$currentStatus], true)
    ) {
        $pdo->rollBack();
        updateRefundResponse(false, 'Perubahan status refund tidak diizinkan.', null, 400);
    }

    $update = $pdo->prepare("
        UPDATE refunds
        SET refund_status = ?,
            refund_reference = ?,
            refunded_at = CASE WHEN ? = 'completed' THEN NOW() ELSE NULL END
        WHERE refund_id = ?
    ");

    $update->execute([
        $newStatus,
        $reference !== '' ? $reference : null,
        $newStatus,
        $refundId
    ]);

    $pdo->commit();

    updateRefundResponse(true, 'Status refund berhasil diperbarui.', [
        'refund_id' => $refundId,
        'refund_status' => $newStatus,
        'refund_reference' => $reference !== '' ? $reference : null
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    updateRefundResponse(false, 'Terjadi kesalahan saat memperbarui refund.', null, 500);
}