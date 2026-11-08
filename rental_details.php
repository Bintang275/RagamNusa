
<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rentalResponse(false, 'Method harus GET.', null, 405);
}

$user = rentalUser();

$rentalId = filter_input(
    INPUT_GET,
    'rentals_id',
    FILTER_VALIDATE_INT
);

if (!$rentalId) {
    rentalResponse(false, 'ID penyewaan tidak valid.', null, 400);
}

$stmt = $pdo->prepare(
    "SELECT r.*, u.name AS renter_name
     FROM rentals r
     INNER JOIN users u ON u.user_id = r.renter_id
     WHERE r.rentals_id = :id
     LIMIT 1"
);

$stmt->execute(['id' => $rentalId]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    rentalResponse(false, 'Transaksi tidak ditemukan.', null, 404);
}

// Hak akses: penyewa pemilik transaksi, admin,
// atau owner yang memiliki produk di transaksi tersebut.
$allowed = (
    $user['role'] === 'admin' ||
    (
        $user['role'] === 'renter' &&
        (int) $rental['renter_id'] === (int) $user['user_id']
    )
);

if ($user['role'] === 'owner') {
    $check = $pdo->prepare(
        "SELECT COUNT(*)
         FROM order_details od
         INNER JOIN products p ON p.product_id = od.product_id
         WHERE od.rentals_id = :rental_id
           AND p.owner_id = :owner_id"
    );

    $check->execute([
        'rental_id' => $rentalId,
        'owner_id' => $user['user_id']
    ]);

    $allowed = (int) $check->fetchColumn() > 0;
}

if (!$allowed) {
    rentalResponse(false, 'Tidak memiliki akses ke transaksi ini.', null, 403);
}

$sql = "SELECT
            od.order_detail_id,
            od.product_id,
            p.name AS product_name,
            od.quantity,
            od.price,
            od.subtotal,
            od.deposit_amount,
            p.owner_id
        FROM order_details od
        INNER JOIN products p ON p.product_id = od.product_id
        WHERE od.rentals_id = :rental_id";

$params = ['rental_id' => $rentalId];

// Owner hanya melihat produk miliknya.
if ($user['role'] === 'owner') {
    $sql .= " AND p.owner_id = :owner_id";
    $params['owner_id'] = $user['user_id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rental['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

rentalResponse(true, 'Detail penyewaan berhasil diambil.', $rental);
