
<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

$user = rentalRequireRole('renter');
$input = rentalInput();

$rentalId = filter_var(
    $input['rentals_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$rentalId) {
    rentalResponse(false, 'ID penyewaan tidak valid.', null, 400);
}

$stmt = $pdo->prepare(
    "SELECT rentals_id, rental_status
     FROM rentals
     WHERE rentals_id = :id
       AND renter_id = :renter_id
     FOR UPDATE"
);

try {
    $pdo->beginTransaction();

    $stmt->execute([
        'id' => $rentalId,
        'renter_id' => $user['user_id']
    ]);

    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        rentalResponse(false, 'Transaksi tidak ditemukan.', null, 404);
    }

    if ($rental['rental_status'] !== 'pending') {
        $pdo->rollBack();
        rentalResponse(
            false,
            'Hanya transaksi pending yang dapat dibatalkan oleh penyewa.',
            null,
            400
        );
    }

    $update = $pdo->prepare(
        "UPDATE rentals
         SET rental_status = 'cancelled'
         WHERE rentals_id = :id"
    );

    $update->execute(['id' => $rentalId]);

    $pdo->commit();

    rentalResponse(true, 'Transaksi berhasil dibatalkan.', [
        'rentals_id' => $rentalId,
        'rental_status' => 'cancelled'
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    rentalResponse(false, 'Terjadi kesalahan database.', null, 500);
}
