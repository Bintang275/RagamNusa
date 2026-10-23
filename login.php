<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "config.php";

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

$email = strtolower(trim($input["email"] ?? ""));
$password = $input["password"] ?? "";

if (
    !is_string($email) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !is_string($password) ||
    $password === ""
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Email atau password tidak valid."
    ]);

    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, name, email, password, role, status
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute(["email" => $email]);

    $user = $stmt->fetch();

    if (
        !$user ||
        !password_verify($password, $user["password"])
    ) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Email atau password salah."
        ]);

        exit;
    }

    if ($user["status"] !== "active") {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Akun tidak aktif."
        ]);

        exit;
    }


    $accessToken = bin2hex(random_bytes(32));
    $sessionToken = bin2hex(random_bytes(32));

    $accessTokenHash = hash("sha256", $accessToken);
    $sessionTokenHash = hash("sha256", $sessionToken);

    // Menentukan masa berlaku token
    $accessExpiresAt = date("Y-m-d H:i:s", time() + (15 * 60));

    // Session berlaku 30 hari dan dapat diperpanjang
    $sessionExpiresAt = date("Y-m-d H:i:s", time() + (30 * 24 * 60 * 60));

    // Menyimpan kedua token ke database
    $tokenStmt = $pdo->prepare(
        "INSERT INTO user_tokens
            (user_id, token_hash, token_type, expires_at)
        VALUES
    (:access_user_id, :access_hash, 'access', :access_expires),
    (:session_user_id, :session_hash, 'session', :session_expires)"
    );

$tokenStmt->execute([
    "access_user_id" => $user["user_id"],
    "access_hash" => $accessTokenHash,
    "access_expires" => $accessExpiresAt,
    "session_user_id" => $user["user_id"],
    "session_hash" => $sessionTokenHash,
    "session_expires" => $sessionExpiresAt
]);

    unset($user["password"]);

    echo json_encode([
        "success" => true,
        "message" => "Login berhasil.",
        "data" => $user,
        "tokens" => [
            "access_token" => $accessToken,
            "access_expires_at" => $accessExpiresAt,
            "session_token" => $sessionToken,
            "session_expires_at" => $sessionExpiresAt
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