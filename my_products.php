
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";

try {
    // Periksa role dan status akun
    $stmt = $pdo->prepare(
        "SELECT role, status
         FROM users
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        "user_id" => $authenticatedUserId
    ]);

    $user = $stmt->fetch();

    if (!$user || $user["status"] !== "active") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Akun tidak aktif atau tidak ditemukan."
        ]);
        exit;
    }

    if ($user["role"] !== "owner") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Hanya owner yang dapat melihat produk miliknya."
        ]);
        exit;
    }

    // Ambil produk milik owner yang sedang login
    $stmt = $pdo->prepare(
        "SELECT
            product_id, owner_id, category_id, name, description,
            product_images, price_per_day, deposit, stock,
            condition_status, location, status, create_at, update_at
         FROM products
         WHERE owner_id = :owner_id
         ORDER BY create_at DESC"
    );

    $stmt->execute([
        "owner_id" => $authenticatedUserId
    ]);

    $products = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $products
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil produk milik owner."
    ]);
}
