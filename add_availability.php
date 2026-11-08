
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
            "message" => "Hanya owner yang dapat mengatur ketersediaan produk."
        ]);
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memeriksa hak akses."
    ]);
    exit;
}

// Pastikan menggunakan POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ]);
    exit;
}

// Ambil JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format JSON tidak valid."
    ]);
    exit;
}

// Validasi product_id
if (
    !isset($input["product_id"]) ||
    filter_var($input["product_id"], FILTER_VALIDATE_INT) === false ||
    (int)$input["product_id"] <= 0
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID produk tidak valid."
    ]);
    exit;
}

// Ambil data
$productId = (int)$input["product_id"];
$startDate = $input["start_date"] ?? "";
$endDate = $input["end_date"] ?? "";
$status = $input["status"] ?? "available";

// Validasi tipe data
if (
    !is_string($startDate) ||
    !is_string($endDate) ||
    !is_string($status)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format data ketersediaan tidak valid."
    ]);
    exit;
}

// Validasi format tanggal
$start = DateTime::createFromFormat("!Y-m-d", $startDate);
$end = DateTime::createFromFormat("!Y-m-d", $endDate);

if (
    !$start ||
    !$end ||
    $start->format("Y-m-d") !== $startDate ||
    $end->format("Y-m-d") !== $endDate ||
    $endDate < $startDate
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Rentang tanggal tidak valid."
    ]);
    exit;
}

// Owner hanya boleh mengatur available atau unavailable
$allowedStatuses = ["available", "unavailable"];

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Status ketersediaan tidak valid."
    ]);
    exit;
}

try {

    // Pastikan produk milik owner yang login dan masih aktif
    $stmt = $pdo->prepare(
        "SELECT product_id
         FROM products
         WHERE product_id = :product_id
           AND owner_id = :owner_id
           AND status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        "product_id" => $productId,
        "owner_id" => $authenticatedUserId
    ]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Produk tidak ditemukan atau tidak aktif."
        ]);
        exit;
    }

    // Cek apakah tanggal bertabrakan
    $stmt = $pdo->prepare(
        "SELECT availability_id
         FROM availability
         WHERE product_id = :product_id
           AND start_date <= :end_date
           AND end_date >= :start_date
         LIMIT 1"
    );

    $stmt->execute([
        "product_id" => $productId,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Rentang tanggal bertabrakan dengan jadwal yang sudah ada."
        ]);
        exit;
    }

    // Simpan ketersediaan
    $stmt = $pdo->prepare(
        "INSERT INTO availability
            (product_id, start_date, end_date, status)
         VALUES
            (:product_id, :start_date, :end_date, :status)"
    );

    $stmt->execute([
        "product_id" => $productId,
        "start_date" => $startDate,
        "end_date" => $endDate,
        "status" => $status
    ]);

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Ketersediaan produk berhasil ditambahkan.",
        "data" => [
            "availability_id" => (int)$pdo->lastInsertId(),
            "product_id" => $productId,
            "start_date" => $startDate,
            "end_date" => $endDate,
            "status" => $status
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menambahkan ketersediaan produk."
    ]);
}
