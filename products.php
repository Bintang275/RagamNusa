<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config.php";

try {
    $stmt = $pdo->query(
        "SELECT
            p.product_id, p.owner_id, p.category_id, p.name, p.description,
            p.product_images, p.price_per_day, p.deposit, p.stock,
            p.condition_status, p.location, p.status, p.create_at, p.update_at,
            c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.category_id = p.category_id
         WHERE p.status = 'active'
         ORDER BY p.create_at DESC"
    );

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$p) {
        $decoded = null;
        if (!empty($p['product_images'])) {
            $decoded = json_decode($p['product_images'], true);
            if (!is_array($decoded)) {
                $decoded = [$p['product_images']];
            }
        }
        $p['photos'] = $decoded ?? [];
    }
    unset($p);

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
