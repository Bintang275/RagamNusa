<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/config.php";

try {
    $stmt = $pdo->query(
        "SELECT category_id, category_name, description
         FROM categories
         ORDER BY category_id ASC"
    );

    $categories = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $categories
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data kategori."
    ]);
}