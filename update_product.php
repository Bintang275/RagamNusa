
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
            "message" => "Hanya owner yang dapat mengubah produk."
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

// Periksa tipe data teks
foreach (["name", "description", "location", "condition_status"] as $field) {
    if (
        isset($input[$field]) &&
        !is_string($input[$field])
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Format data produk tidak valid."
        ]);
        exit;
    }
}

$productId = (int)$input["product_id"];

$categoryId = filter_var(
    $input["category_id"] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim($input["name"] ?? "");
$description = $input["description"] ?? null;
$price = $input["price_per_day"] ?? null;
$deposit = $input["deposit"] ?? 0;
$stock = filter_var(
    $input["stock"] ?? null,
    FILTER_VALIDATE_INT
);
$condition = $input["condition_status"] ?? "good";
$location = $input["location"] ?? null;

// Validasi data wajib
if (
    $categoryId === false ||
    $categoryId <= 0 ||
    $name === "" ||
    !is_numeric($price) ||
    !is_numeric($deposit) ||
    $stock === false ||
    $stock === null ||
    $stock < 1
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Data produk wajib belum lengkap atau tidak valid."
    ]);
    exit;
}

// Validasi kondisi produk
$allowedConditions = ["new", "good", "fair", "poor"];

if (!in_array($condition, $allowedConditions, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kondisi produk tidak valid."
    ]);
    exit;
}

// Validasi panjang teks
if (
    strlen($name) > 150 ||
    ($description !== null && strlen($description) > 65535) ||
    ($location !== null && strlen($location) > 255)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Panjang data produk melebihi batas."
    ]);
    exit;
}

// Validasi harga dan deposit
if ((float)$price <= 0 || (float)$deposit < 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Harga harus lebih dari 0 dan deposit tidak boleh negatif."
    ]);
    exit;
}

try {

    // Pastikan produk benar-benar milik owner yang login
    $stmt = $pdo->prepare(
        "SELECT product_id
         FROM products
         WHERE product_id = :product_id
           AND owner_id = :owner_id
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
            "message" => "Produk tidak ditemukan atau bukan milik Anda."
        ]);
        exit;
    }

    // Pastikan kategori tersedia
    $stmt = $pdo->prepare(
        "SELECT category_id
         FROM categories
         WHERE category_id = :category_id
         LIMIT 1"
    );

    $stmt->execute([
        "category_id" => $categoryId
    ]);

    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Kategori produk tidak ditemukan."
        ]);
        exit;
    }

    // Perbarui produk
    $stmt = $pdo->prepare(
        "UPDATE products
         SET
            category_id = :category_id,
            name = :name,
            description = :description,
            price_per_day = :price,
            deposit = :deposit,
            stock = :stock,
            condition_status = :condition_status,
            location = :location
         WHERE product_id = :product_id
           AND owner_id = :owner_id"
    );

    $stmt->execute([
        "category_id" => $categoryId,
        "name" => $name,
        "description" => $description,
        "price" => $price,
        "deposit" => $deposit,
        "stock" => $stock,
        "condition_status" => $condition,
        "location" => $location,
        "product_id" => $productId,
        "owner_id" => $authenticatedUserId
    ]);

    // Ambil data terbaru
    $stmt = $pdo->prepare(
        "SELECT
            product_id, owner_id, category_id, name, description,
            product_images, price_per_day, deposit, stock,
            condition_status, location, status, create_at, update_at
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

    echo json_encode([
        "success" => true,
        "message" => "Produk berhasil diperbarui.",
        "data" => $product
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memperbarui produk."
    ]);
}
