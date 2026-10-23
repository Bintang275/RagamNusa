<?php

header("Content-Type: application/json; charset=UTF-8");

$response = [
    "success" => true,
    "app" => "RAGAMNUSA API",
    "message" => "Backend berhasil dijalankan!"
];

echo json_encode($response, JSON_PRETTY_PRINT);