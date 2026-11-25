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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format JSON tidak valid."
    ]);
    exit;
}

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

// Parse photos / product_images jika dikirim
$hasPhotosUpdate = array_key_exists("photos", $input) || array_key_exists("product_images", $input);
$productImages = null;
if ($hasPhotosUpdate) {
    $photosInput = $input["photos"] ?? $input["product_images"] ?? null;
    if (is_array($photosInput)) {
        $cleanPhotos = array_values(array_filter($photosInput, function($p) {
            return is_string($p) && trim($p) !== "";
        }));
        $productImages = !empty($cleanPhotos) ? json_encode($cleanPhotos, JSON_UNESCAPED_SLASHES) : null;
    } elseif (is_string($photosInput) && trim($photosInput) !== "") {
        $trimmed = trim($photosInput);
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $productImages = $trimmed;
        } else {
            $productImages = json_encode([$trimmed], JSON_UNESCAPED_SLASHES);
        }
    }
}

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

$allowedConditions = ["new", "good", "fair", "poor"];

if (!in_array($condition, $allowedConditions, true)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Kondisi produk tidak valid."
    ]);
    exit;
}

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

if ((float)$price <= 0 || (float)$deposit < 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Harga harus lebih dari 0 dan deposit tidak boleh negatif."
    ]);
    exit;
}

try {

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

    if ($hasPhotosUpdate) {
        $stmt = $pdo->prepare(
            "UPDATE products
             SET
                category_id = :category_id,
                name = :name,
                description = :description,
                product_images = :product_images,
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
            "product_images" => $productImages,
            "price" => $price,
            "deposit" => $deposit,
            "stock" => $stock,
            "condition_status" => $condition,
            "location" => $location,
            "product_id" => $productId,
            "owner_id" => $authenticatedUserId
        ]);
    } else {
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
    }

    $stmt = $pdo->prepare(
        "SELECT
            p.product_id, p.owner_id, p.category_id, p.name, p.description,
            p.product_images, p.price_per_day, p.deposit, p.stock,
            p.condition_status, p.location, p.status, p.create_at, p.update_at,
            c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.category_id = p.category_id
         WHERE p.product_id = :product_id
           AND p.owner_id = :owner_id
         LIMIT 1"
    );

    $stmt->execute([
        "product_id" => $productId,
        "owner_id" => $authenticatedUserId
    ]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $decoded = null;
        if (!empty($product['product_images'])) {
            $decoded = json_decode($product['product_images'], true);
            if (!is_array($decoded)) {
                $decoded = [$product['product_images']];
            }
        }
        $product['photos'] = $decoded ?? [];
    }

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
