
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
            "message" => "Hanya owner yang dapat mengubah jadwal."
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

// Validasi ID
if (
    !isset($input["availability_id"]) ||
    filter_var($input["availability_id"], FILTER_VALIDATE_INT) === false ||
    (int)$input["availability_id"] <= 0
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID ketersediaan tidak valid."
    ]);
    exit;
}

$availabilityId = (int)$input["availability_id"];
$startDate = $input["start_date"] ?? "";
$endDate = $input["end_date"] ?? "";
$status = $input["status"] ?? "";

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

// Validasi tanggal
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

// Owner hanya boleh memilih available atau unavailable
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

    // Periksa kepemilikan jadwal
    $stmt = $pdo->prepare(
        "SELECT a.availability_id, a.status
         FROM availability a
         INNER JOIN products p
            ON a.product_id = p.product_id
         WHERE a.availability_id = :availability_id
           AND p.owner_id = :owner_id
         LIMIT 1"
    );

    $stmt->execute([
        "availability_id" => $availabilityId,
        "owner_id" => $authenticatedUserId
    ]);

    $availability = $stmt->fetch();

    if (!$availability) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Jadwal tidak ditemukan atau bukan milik Anda."
        ]);
        exit;
    }

    // Jadwal booked tidak boleh diubah oleh owner
    if ($availability["status"] === "booked") {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Jadwal yang sudah dipesan tidak dapat diubah."
        ]);
        exit;
    }

    // Periksa bentrokan dengan jadwal lain
    $stmt = $pdo->prepare(
        "SELECT availability_id
         FROM availability
         WHERE product_id = (
             SELECT product_id
             FROM availability
             WHERE availability_id = :availability_id
         )
         AND availability_id != :current_id
         AND start_date <= :end_date
         AND end_date >= :start_date
         LIMIT 1"
    );

    $stmt->execute([
        "availability_id" => $availabilityId,
        "current_id" => $availabilityId,
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Rentang tanggal bertabrakan dengan jadwal lain."
        ]);
        exit;
    }

    // Perbarui jadwal
    $stmt = $pdo->prepare(
        "UPDATE availability
         SET start_date = :start_date,
             end_date = :end_date,
             status = :status
         WHERE availability_id = :availability_id"
    );

    $stmt->execute([
        "start_date" => $startDate,
        "end_date" => $endDate,
        "status" => $status,
        "availability_id" => $availabilityId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Jadwal ketersediaan berhasil diperbarui.",
        "data" => [
            "availability_id" => $availabilityId,
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
        "message" => "Gagal memperbarui jadwal ketersediaan."
    ]);
}
