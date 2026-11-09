<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Gunakan metode POST."
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
$email = strtolower(trim($input["email"] ?? ""));
$password = $input["password"] ?? "";
$phone = trim($input["phone"] ?? "");

if (
    $name === "" ||
    strlen($name) > 100 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 150 ||
    !is_string($password) ||
    strlen($password) < 8 ||
    strlen($password) > 4096 ||
    ($phone !== "" && strlen($phone) > 20)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Data tidak valid. Periksa nama, email, nomor telepon, dan password minimal 8 karakter."
    ]);
    exit;
}

try {
    $check = $pdo->prepare(
        "SELECT user_id FROM users WHERE email = :email LIMIT 1"
    );
    $check->execute(["email" => $email]);

    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email sudah terdaftar."
        ]);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, phone, role, status)
         VALUES (:name, :email, :password, :phone, 'admin', 'active')"
    );

    $stmt->execute([
        "name" => $name,
        "email" => $email,
        "password" => $hashedPassword,
        "phone" => $phone !== "" ? $phone : null
    ]);

    $adminId = (int) $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Registrasi akun admin berhasil.",
        "data" => [
            "user_id" => $adminId,
            "name" => $name,
            "email" => $email,
            "role" => "admin"
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server."
    ]);
}