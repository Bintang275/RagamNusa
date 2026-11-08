
<?php
header("Content-Type: application/json");

require_once "config.php";
require_once "auth.php";

// Hanya menerima POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ]);
    exit;
}

// Periksa akun owner
$stmt = $pdo->prepare(
    "SELECT role, status
     FROM users
     WHERE user_id = :user_id"
);

$stmt->execute([
    "user_id" => $authenticatedUserId
]);

$user = $stmt->fetch();

if (!$user || $user["role"] !== "owner" || $user["status"] !== "active") {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Akses hanya untuk owner aktif."
    ]);
    exit;
}

// Ambil data JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format JSON tidak valid."
    ]);
    exit;
}

// Ambil input
$categoryId = $data["category_id"] ?? null;
$name = trim($data["name"] ?? "");
$description = trim($data["description"] ?? "");
$pricePerDay = $data["price_per_day"] ?? null;
$deposit = $data["deposit"] ?? 0;
$conditionStatus = $data["condition_status"] ?? "";
$location = trim($data["location"] ?? "");
$stock = $data["stock"] ?? null;

// Validasi kategori
if (!filter_var($categoryId, FILTER_VALIDATE_INT) || $categoryId <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kategori tidak valid."
    ]);
    exit;
}

// Validasi nama
if ($name === "" || strlen($name) > 150) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nama produk wajib diisi dan maksimal 150 karakter."
    ]);
    exit;
}

// Validasi harga
if (!is_numeric($pricePerDay) || $pricePerDay <= 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Harga sewa per hari harus lebih dari 0."
    ]);
    exit;
}

// Validasi deposit
if (!is_numeric($deposit) || $deposit < 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Deposit tidak valid."
    ]);
    exit;
}

// Validasi kondisi produk
$allowedConditions = ["new", "good", "fair", "poor"];

if (!in_array($conditionStatus, $allowedConditions, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kondisi produk tidak valid."
    ]);
    exit;
}

// Validasi stok
if (
    filter_var($stock, FILTER_VALIDATE_INT) === false ||
    $stock < 1
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Stok harus berupa bilangan bulat minimal 1."
    ]);
    exit;
}

try {
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
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Kategori tidak ditemukan."
        ]);
        exit;
    }

    // Simpan produk
    $stmt = $pdo->prepare(
        "INSERT INTO products (
            owner_id,
            category_id,
            name,
            description,
            price_per_day,
            deposit,
            condition_status,
            location,
            stock
        ) VALUES (
            :owner_id,
            :category_id,
            :name,
            :description,
            :price_per_day,
            :deposit,
            :condition_status,
            :location,
            :stock
        )"
    );

    $stmt->execute([
        "owner_id" => $authenticatedUserId,
        "category_id" => $categoryId,
        "name" => $name,
        "description" => $description !== "" ? $description : null,
        "price_per_day" => $pricePerDay,
        "deposit" => $deposit,
        "condition_status" => $conditionStatus,
        "location" => $location !== "" ? $location : null,
        "stock" => $stock
    ]);

    $productId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Produk berhasil ditambahkan.",
        "data" => [
            "product_id" => (int) $productId,
            "owner_id" => (int) $authenticatedUserId,
            "category_id" => (int) $categoryId,
            "name" => $name,
            "price_per_day" => $pricePerDay,
            "deposit" => $deposit,
            "condition_status" => $conditionStatus,
            "location" => $location,
            "stock" => (int) $stock,
            "status" => "active"
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}
