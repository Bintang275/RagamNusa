
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
            "message" => "Hanya owner yang dapat menonaktifkan produk."
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

// Ambil data JSON
$input = json_decode(file_get_contents("php://input"), true);

if (
    !is_array($input) ||
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

$productId = (int)$input["product_id"];

try {

    // Periksa kepemilikan produk
    $stmt = $pdo->prepare(
        "SELECT product_id, status
         FROM products
         WHERE product_id = :product_id
           AND owner_id = :owner_id
         LIMIT 1"
    );

    $stmt->execute([
        "product_id" => $productId,
        "owner_id" => $authenticatedUserId
    ]);

    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Produk tidak ditemukan atau bukan milik Anda."
        ]);
        exit;
    }

    if ($product["status"] === "inactive") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Produk sudah tidak aktif."
        ]);
        exit;
    }

    // Nonaktifkan produk, bukan menghapus permanen
    $stmt = $pdo->prepare(
        "UPDATE products
         SET status = 'inactive'
         WHERE product_id = :product_id
           AND owner_id = :owner_id"
    );

    $stmt->execute([
        "product_id" => $productId,
        "owner_id" => $authenticatedUserId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Produk berhasil dinonaktifkan."
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menonaktifkan produk."
    ]);
}
