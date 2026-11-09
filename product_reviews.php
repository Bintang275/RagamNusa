<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method harus GET.'
    ]);
    exit;
}

$productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT);

if (!$productId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter product_id wajib diisi.'
    ]);
    exit;
}

try {
    // 1. Ambil ulasan produk beserta data pengulas
    $stmt = $pdo->prepare("
        SELECT 
            r.review_id,
            r.rentals_id,
            r.product_id,
            r.user_id AS reviewer_id,
            u.name AS reviewer_name,
            u.profile_picture AS reviewer_photo,
            r.rating,
            r.comment,
            r.owner_reply,
            r.replied_at,
            r.create_at
        FROM reviews r
        INNER JOIN users u ON u.user_id = r.user_id
        WHERE r.product_id = :product_id
        ORDER BY r.create_at DESC
    ");
    $stmt->execute(['product_id' => $productId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Hitung ringkasan rating
    $summaryStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_reviews,
            COALESCE(AVG(rating), 0) AS average_rating
        FROM reviews
        WHERE product_id = :product_id
    ");
    $summaryStmt->execute(['product_id' => $productId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Daftar ulasan produk berhasil diambil.',
        'data' => [
            'total_reviews' => (int)$summary['total_reviews'],
            'average_rating' => round((float)$summary['average_rating'], 1),
            'reviews' => $reviews
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil ulasan produk.'
    ]);
}