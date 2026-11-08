
<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

rentalRequireRole('admin');

$input = rentalInput();

$rentalId = filter_var(
    $input['rentals_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = $input['rental_status'] ?? '';

if (!$rentalId) {
    rentalResponse(false, 'ID penyewaan tidak valid.', null, 400);
}

$allowedTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['ongoing', 'cancelled', 'disputed'],
    'ongoing' => ['completed', 'disputed'],
    'disputed' => ['confirmed', 'ongoing', 'completed', 'cancelled']
];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT rentals_id, rental_status
         FROM rentals
         WHERE rentals_id = :id
         FOR UPDATE"
    );

    $stmt->execute(['id' => $rentalId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        rentalResponse(false, 'Transaksi tidak ditemukan.', null, 404);
    }

    $currentStatus = $rental['rental_status'];

    if (
        !isset($allowedTransitions[$currentStatus]) ||
        !in_array($newStatus, $allowedTransitions[$currentStatus], true)
    ) {
        $pdo->rollBack();

        rentalResponse(false, 'Perubahan status tidak diizinkan.', [
            'current_status' => $currentStatus,
            'requested_status' => $newStatus
        ], 400);
    }

    $update = $pdo->prepare(
        "UPDATE rentals
         SET rental_status = :status
         WHERE rentals_id = :id"
    );

    $update->execute([
        'status' => $newStatus,
        'id' => $rentalId
    ]);

    $pdo->commit();

    rentalResponse(true, 'Status penyewaan berhasil diperbarui.', [
        'rentals_id' => $rentalId,
        'previous_status' => $currentStatus,
        'current_status' => $newStatus
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    rentalResponse(false, 'Terjadi kesalahan database.', null, 500);
}
