
<?php
header("Content-Type: application/json");

require_once "config.php";
require_once "auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method tidak diizinkan."
    ]);
    exit;
}

// Pastikan pengguna adalah owner aktif
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

$availabilityId = $data["availability_id"] ?? null;

if (!$availabilityId || !filter_var($availabilityId, FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Availability ID tidak valid."
    ]);
    exit;
}

try {
    // Pastikan jadwal milik produk owner yang sedang login
    $stmt = $pdo->prepare(
        "SELECT a.availability_id, a.status
         FROM availability a
         JOIN products p ON a.product_id = p.product_id
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
            "message" => "Jadwal tidak ditemukan."
        ]);
        exit;
    }

    // Jadwal yang sudah dipesan tidak boleh dihapus
    if ($availability["status"] === "booked") {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Jadwal yang sudah dipesan tidak dapat dihapus."
        ]);
        exit;
    }

    // Hapus jadwal
    $stmt = $pdo->prepare(
        "DELETE FROM availability
         WHERE availability_id = :availability_id"
    );

    $stmt->execute([
        "availability_id" => $availabilityId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Jadwal ketersediaan berhasil dihapus."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}
