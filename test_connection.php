<?php

header("Content-Type: application/json");

require_once "config.php";

echo json_encode([
    "success" => true,
    "message" => "Koneksi database RagamNusa berhasil!"
]);