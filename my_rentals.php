<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rentalResponse(false, 'Method harus GET.', null, 405);
}

$user = rentalRequireRole('renter');

$stmt = $pdo->prepare(
    "SELECT
        r.rentals_id,
        r.start_date,
        r.end_date,
        r.rental_price,
        r.deposit_total,
        r.shipping_cost,
        r.rental_status,
        r.create_at,
        COUNT(od.order_detail_id) AS total_items
     FROM rentals r
     LEFT JOIN order_details od
        ON od.rentals_id = r.rentals_id
     WHERE r.renter_id = :renter_id
     GROUP BY r.rentals_id
     ORDER BY r.create_at DESC"
);

$stmt->execute(['renter_id' => $user['user_id']]);

rentalResponse(
    true,
    'Daftar penyewaan berhasil diambil.',
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);
