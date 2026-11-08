
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";

// Periksa role dan status akun
try {
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
            "message" => "Hanya owner yang dapat melihat jadwal ketersediaan."
        ]);
        exit;
    }

    // Ambil jadwal ketersediaan produk milik owner
    $stmt = $pdo->prepare(
        "SELECT
            a.availability_id,
            a.product_id,
            p.name AS product_name,
            a.start_date,
            a.end_date,
            a.status,
            a.create_at,
            a.updated_at
         FROM availability a
         INNER JOIN products p
            ON a.product_id = p.product_id
         WHERE p.owner_id = :owner_id
         ORDER BY a.start_date ASC"
    );

    $stmt->execute([
        "owner_id" => $authenticatedUserId
    ]);

    $availabilities = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $availabilities
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil jadwal ketersediaan."
    ]);
}
