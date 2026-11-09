<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

// Hanya Owner yang dapat mengakses endpoint ini
$user = rentalRequireRole('owner');
$ownerId = (int)$user['user_id'];

// ====================================================================
// A. METHOD POST: Owner membalas ulasan penyewa
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = rentalInput();

    $reviewId = filter_var($input['review_id'] ?? null, FILTER_VALIDATE_INT);
    $reply = trim($input['reply'] ?? '');

    if (!$reviewId || $reply === '') {
        rentalResponse(false, 'Review ID dan teks balasan wajib diisi.', null, 400);
    }

    try {
        $pdo->beginTransaction();

        // Pastikan ulasan tersebut ditujukan untuk produk milik owner ini
        $stmt = $pdo->prepare("
            SELECT r.review_id, p.name AS product_name
            FROM reviews r
            INNER JOIN products p ON p.product_id = r.product_id
            WHERE r.review_id = :review_id
              AND p.owner_id = :owner_id
            FOR UPDATE
        ");
        $stmt->execute([
            'review_id' => $reviewId,
            'owner_id' => $ownerId
        ]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            $pdo->rollBack();
            rentalResponse(false, 'Ulasan tidak ditemukan atau produk bukan milik Anda.', null, 404);
        }

        $update = $pdo->prepare("
            UPDATE reviews
            SET owner_reply = :reply,
                replied_at = NOW()
            WHERE review_id = :review_id
        ");
        $update->execute([
            'reply' => $reply,
            'review_id' => $reviewId
        ]);

        $pdo->commit();

        rentalResponse(true, 'Balasan ulasan berhasil disimpan.', [
            'review_id' => $reviewId,
            'owner_reply' => $reply,
            'replied_at' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        rentalResponse(false, 'Gagal menyimpan balasan: ' . $e->getMessage(), null, 500);
    }
}

// ====================================================================
// B. METHOD GET: Mengambil seluruh ulasan toko milik owner
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // 1. Ambil daftar ulasan produk toko owner
        $stmt = $pdo->prepare("
            SELECT 
                r.review_id,
                r.rentals_id,
                r.product_id,
                p.name AS product_name,
                r.user_id AS reviewer_id,
                u.name AS reviewer_name,
                u.profile_picture AS reviewer_photo,
                r.rating,
                r.comment,
                r.owner_reply,
                r.replied_at,
                r.create_at
            FROM reviews r
            INNER JOIN products p ON p.product_id = r.product_id
            INNER JOIN users u ON u.user_id = r.user_id
            WHERE p.owner_id = :owner_id
            ORDER BY r.create_at DESC
        ");
        $stmt->execute(['owner_id' => $ownerId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Statistik ulasan toko untuk kartu reputasi (OwnerReviewsPage)
        $statStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_reviews,
                COALESCE(AVG(r.rating), 0) AS average_rating,
                SUM(CASE WHEN r.owner_reply IS NULL OR TRIM(r.owner_reply) = '' THEN 1 ELSE 0 END) AS unanswered_count
            FROM reviews r
            INNER JOIN products p ON p.product_id = r.product_id
            WHERE p.owner_id = :owner_id
        ");
        $statStmt->execute(['owner_id' => $ownerId]);
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

        rentalResponse(true, 'Ulasan toko berhasil diambil.', [
            'stats' => [
                'total_reviews' => (int)$stats['total_reviews'],
                'average_rating' => round((float)$stats['average_rating'], 1),
                'unanswered_count' => (int)$stats['unanswered_count']
            ],
            'reviews' => $reviews
        ]);

    } catch (PDOException $e) {
        error_log($e->getMessage());
        rentalResponse(false, 'Gagal mengambil ulasan toko.', null, 500);
    }
}

rentalResponse(false, 'Method tidak diizinkan.', null, 405);