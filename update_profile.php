
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Metode request tidak diizinkan."
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

$name = trim($input["name"] ?? "");
$phone = trim($input["phone"] ?? "");

if ($name === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Nama tidak boleh kosong."
    ]);
    exit;
}

if (strlen($name) > 100) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Nama maksimal 100 karakter."
    ]);
    exit;
}

if ($phone !== "" && strlen($phone) > 20) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Nomor telepon maksimal 20 karakter."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET name = :name,
             phone = :phone
         WHERE user_id = :user_id"
    );

    $stmt->execute([
        "name" => $name,
        "phone" => $phone === "" ? null : $phone,
        "user_id" => $authenticatedUserId
    ]);

    $stmt = $pdo->prepare(
        "SELECT user_id, name, email, phone,
                profile_picture, role, status
         FROM users
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        "user_id" => $authenticatedUserId
    ]);

    $user = $stmt->fetch();

    echo json_encode([
        "success" => true,
        "message" => "Profil berhasil diperbarui.",
        "data" => $user
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}
