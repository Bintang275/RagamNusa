
<?php

require_once __DIR__ . "/config.php";

header("Content-Type: application/json; charset=UTF-8");

// Ambil access token dari header Authorization
$headers = getallheaders();

$authorization = $headers["Authorization"]
    ?? $headers["authorization"]
    ?? "";

if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Access token tidak ditemukan."
    ]);

    exit;
}

$accessToken = $matches[1];

// Hash token untuk mencocokkan dengan database
$tokenHash = hash("sha256", $accessToken);

try {
    $stmt = $pdo->prepare(
        "SELECT user_id
         FROM user_tokens
         WHERE token_hash = :token_hash
           AND token_type = 'access'
           AND revoked_at IS NULL
           AND expires_at > NOW()
         LIMIT 1"
    );

    $stmt->execute([
        "token_hash" => $tokenHash
    ]);

    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Access token tidak valid atau sudah kedaluwarsa."
        ]);

        exit;
    }

    // Identitas pengguna yang sudah terautentikasi
    $authenticatedUserId = (int) $tokenData["user_id"];

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat memeriksa token."
    ]);

    exit;
}
