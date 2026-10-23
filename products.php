<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "config.php";

try {
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $products
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data produk."
    ]);
}