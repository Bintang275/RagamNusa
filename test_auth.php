
<?php

require_once __DIR__ . "/auth.php";

echo json_encode([
    "success" => true,
    "message" => "Autentikasi berhasil.",
    "data" => [
        "user_id" => $authenticatedUserId
    ]
]);
