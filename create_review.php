<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

// Hanya Renter yang dapat memberikan ulasan produk
$user = rentalRequireRole('renter');
$input = rentalInput();

$rentalId = filter_var($input['rentals_id'] ?? null, FILTER_VALIDATE_INT);
$productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
$comment = trim($input['comment'] ?? '');

if (!$rentalId || !$productId || !$rating) {
    rentalResponse(false, 'Data tidak lengkap. ID penyewaan, ID produk, dan rating wajib diisi.', null, 400);
}

if ($rating < 1 || $rating > 5) {
    rentalResponse(false, 'Rating harus bernilai antara 1 sampai 5 bintang.', null, 400);
}

try {
    $pdo->beginTransaction();

    // 1. Validasi transaksi rental
    $rentalStmt = $pdo->prepare("
        SELECT rentals_id, renter_id, rental_status
        FROM rentals
        WHERE rentals_id = :rentals_id
          AND renter_id = :renter_id
        LIMIT 1
    ");
    $rentalStmt->execute([
        'rentals_id' => $rentalId,
        'renter_id' => $user['user_id']
    ]);
    $rental = $rentalStmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        rentalResponse(false, 'Transaksi penyewaan tidak ditemukan atau bukan milik Anda.', null, 404);
    }

    if ($rental['rental_status'] !== 'completed') {
        $pdo->rollBack();
        rentalResponse(false, 'Ulasan hanya dapat diberikan setelah status penyewaan selesai (completed).', null, 400);
    }

    // 2. Pastikan produk memang termasuk dalam pesanan rental ini
    $itemStmt = $pdo->prepare("
        SELECT order_detail_id
        FROM order_details
        WHERE rentals_id = :rentals_id
          AND product_id = :product_id
        LIMIT 1
    ");
    $itemStmt->execute([
        'rentals_id' => $rentalId,
        'product_id' => $productId
    ]);
    if (!$itemStmt->fetch()) {
        $pdo->rollBack();
        rentalResponse(false, 'Produk tidak ditemukan dalam transaksi penyewaan ini.', null, 404);
    }

    // 3. Pastikan belum pernah memberi ulasan untuk produk ini di rental ini
    $checkReview = $pdo->prepare("
        SELECT review_id
        FROM reviews
        WHERE rentals_id = :rentals_id
          AND product_id = :product_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $checkReview->execute([
        'rentals_id' => $rentalId,
        'product_id' => $productId,
        'user_id' => $user['user_id']
    ]);
    if ($checkReview->fetch()) {
        $pdo->rollBack();
        rentalResponse(false, 'Anda sudah memberikan ulasan untuk produk ini pada penyewaan tersebut.', null, 409);
    }

    // 4. Simpan ulasan
    $insert = $pdo->prepare("
        INSERT INTO reviews
        (rentals_id, product_id, user_id, rating, comment)
        VALUES
        (:rentals_id, :product_id, :user_id, :rating, :comment)
    ");
    $insert->execute([
        'rentals_id' => $rentalId,
        'product_id' => $productId,
        'user_id' => $user['user_id'],
        'rating' => $rating,
        'comment' => $comment !== '' ? $comment : null
    ]);

    $reviewId = (int)$pdo->lastInsertId();
    $pdo->commit();

    rentalResponse(true, 'Ulasan berhasil dikirim. Terima kasih atas ulasan Anda!', [
        'review_id' => $reviewId,
        'rentals_id' => $rentalId,
        'product_id' => $productId,
        'rating' => $rating,
        'comment' => $comment
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rentalResponse(false, 'Terjadi kesalahan saat menyimpan ulasan: ' . $e->getMessage(), null, 500);
}