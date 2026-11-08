
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, name, email, profile_picture, role, status
         FROM users
         WHERE user_id = :user_id
         LIMIT 1"
    );

    $stmt->execute([
        "user_id" => $authenticatedUserId
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Pengguna tidak ditemukan."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Profil berhasil diambil.",
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
