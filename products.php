
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config.php";

try {
    $stmt = $pdo->query(
        "SELECT
            product_id, owner_id, category_id, name, description,
            product_images, price_per_day, deposit, stock,
            condition_status, location, status, create_at, update_at
         FROM products
         WHERE status = 'active'
         ORDER BY create_at DESC"
    );

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
        "message" => "Gagal mengambil data produk."
    ]);
}
