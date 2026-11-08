
<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rentalResponse(false, 'Method harus GET.', null, 405);
}

$user = rentalRequireRole('owner');

$stmt = $pdo->prepare(
    "SELECT
        r.rentals_id,
        r.renter_id,
        u.name AS renter_name,
        r.start_date,
        r.end_date,
        r.rental_status,
        r.create_at,
        od.order_detail_id,
        p.product_id,
        p.name AS product_name,
        od.quantity,
        od.price,
        od.subtotal,
        od.deposit_amount
     FROM rentals r
     INNER JOIN users u
        ON u.user_id = r.renter_id
     INNER JOIN order_details od
        ON od.rentals_id = r.rentals_id
     INNER JOIN products p
        ON p.product_id = od.product_id
     WHERE p.owner_id = :owner_id
     ORDER BY r.create_at DESC"
);

$stmt->execute(['owner_id' => $user['user_id']]);

rentalResponse(
    true,
    'Daftar penyewaan produk berhasil diambil.',
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);
