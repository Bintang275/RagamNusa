<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.rentals_id,
            p.payment_method,
            p.amount,
            p.payment_status,
            p.transaction_reference,
            p.paid_at,
            p.create_at,
            r.start_date,
            r.end_date,
            r.rental_status
        FROM payments p
        JOIN rentals r ON r.rentals_id = p.rentals_id
        WHERE r.renter_id = ?
        ORDER BY p.create_at DESC
    ");

    $stmt->execute([$authenticatedUserId]);

    echo json_encode([
        'success' => true,
        'message' => 'Riwayat pembayaran berhasil diambil.',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil riwayat pembayaran.',
        'data' => null
    ]);
}