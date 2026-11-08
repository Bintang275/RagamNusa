<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT
            rf.refund_id,
            rf.payment_id,
            rf.amount,
            rf.refund_reason,
            rf.refund_status,
            rf.refund_reference,
            rf.refunded_at,
            rf.create_at,
            p.rentals_id,
            p.payment_method
        FROM refunds rf
        JOIN payments p ON p.payment_id = rf.payment_id
        JOIN rentals r ON r.rentals_id = p.rentals_id
        WHERE r.renter_id = ?
        ORDER BY rf.create_at DESC
    ");

    $stmt->execute([$authenticatedUserId]);

    echo json_encode([
        'success' => true,
        'message' => 'Riwayat refund berhasil diambil.',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil riwayat refund.',
        'data' => null
    ]);
}