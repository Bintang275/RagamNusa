<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

$user = rentalUser();
$input = rentalInput();

$rentalId = filter_var($input['rentals_id'] ?? null, FILTER_VALIDATE_INT);
$reason = trim($input['reason'] ?? '');
$description = trim($input['description'] ?? '');
$evidenceImage = trim($input['evidence_image'] ?? '');

if (!$rentalId || $reason === '' || $description === '') {
    rentalResponse(
        false,
        'Data tidak lengkap. ID penyewaan, alasan, dan deskripsi wajib diisi.',
        null,
        400
    );
}

if (strlen($reason) > 255) {
    rentalResponse(false, 'Alasan maksimal 255 karakter.', null, 400);
}

try {
    $pdo->beginTransaction();

    // 1. Kunci data rental
    $stmt = $pdo->prepare(
        "SELECT rentals_id, renter_id, rental_status
         FROM rentals
         WHERE rentals_id = :id
         FOR UPDATE"
    );
    $stmt->execute(['id' => $rentalId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        rentalResponse(false, 'Transaksi penyewaan tidak ditemukan.', null, 404);
    }

    // 2. Verifikasi keterlibatan pengguna (harus Renter atau Owner produk di rental tsb)
    $isRenter = ((int)$rental['renter_id'] === (int)$user['user_id']);
    $isOwner = false;

    if (!$isRenter) {
        $checkOwnerStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM order_details od
             INNER JOIN products p ON p.product_id = od.product_id
             WHERE od.rentals_id = :rentals_id
               AND p.owner_id = :owner_id"
        );
        $checkOwnerStmt->execute([
            'rentals_id' => $rentalId,
            'owner_id' => $user['user_id']
        ]);
        $isOwner = ((int)$checkOwnerStmt->fetchColumn() > 0);
    }

    if (!$isRenter && !$isOwner && $user['role'] !== 'admin') {
        $pdo->rollBack();
        rentalResponse(
            false,
            'Akses ditolak. Anda tidak terlibat dalam transaksi penyewaan ini.',
            null,
            403
        );
    }

    // 3. Validasi status rental yang boleh diajukan sengketa
    $allowedStatuses = ['confirmed', 'ongoing', 'completed', 'disputed'];
    if (!in_array($rental['rental_status'], $allowedStatuses, true)) {
        $pdo->rollBack();
        rentalResponse(
            false,
            'Sengketa hanya dapat diajukan pada pesanan yang sudah dikonfirmasi, sedang berlangsung, atau selesai.',
            null,
            400
        );
    }

    // 4. Periksa apakah sudah ada sengketa yang masih terbuka / sedang diproses
    $checkDispute = $pdo->prepare(
        "SELECT dispute_id
         FROM disputes
         WHERE rentals_id = :rentals_id
           AND dispute_status IN ('open', 'in_review')
         LIMIT 1"
    );
    $checkDispute->execute(['rentals_id' => $rentalId]);
    if ($checkDispute->fetch()) {
        $pdo->rollBack();
        rentalResponse(
            false,
            'Sengketa untuk transaksi ini sedang aktif atau dalam proses peninjauan.',
            null,
            409
        );
    }

    // 5. Masukkan ke tabel disputes
    $insert = $pdo->prepare(
        "INSERT INTO disputes
         (rentals_id, user_id, reason, description, evidence_image, dispute_status)
         VALUES
         (:rentals_id, :user_id, :reason, :description, :evidence_image, 'open')"
    );

    $insert->execute([
        'rentals_id' => $rentalId,
        'user_id' => $user['user_id'],
        'reason' => $reason,
        'description' => $description,
        'evidence_image' => $evidenceImage !== '' ? $evidenceImage : null
    ]);

    $disputeId = (int)$pdo->lastInsertId();

    // 6. Perbarui status rental menjadi 'disputed' jika belum
    if ($rental['rental_status'] !== 'disputed') {
        $updateRental = $pdo->prepare(
            "UPDATE rentals
             SET rental_status = 'disputed'
             WHERE rentals_id = :id"
        );
        $updateRental->execute(['id' => $rentalId]);
    }

    $pdo->commit();

    rentalResponse(
        true,
        'Sengketa berhasil diajukan dan sedang menunggu peninjauan admin.',
        [
            'dispute_id' => $disputeId,
            'rentals_id' => $rentalId,
            'reason' => $reason,
            'dispute_status' => 'open'
        ],
        201
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rentalResponse(false, 'Terjadi kesalahan: ' . $e->getMessage(), null, 500);
}