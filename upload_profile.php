
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

if (!isset($_FILES["profile_picture"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Foto profil belum dipilih."
    ]);
    exit;
}

$file = $_FILES["profile_picture"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengunggah foto profil."
    ]);
    exit;
}

$maxSize = 2 * 1024 * 1024;

if ($file["size"] > $maxSize) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Ukuran foto maksimal 2 MB."
    ]);
    exit;
}

if (!is_uploaded_file($file["tmp_name"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "File unggahan tidak valid."
    ]);
    exit;
}

$imageInfo = getimagesize($file["tmp_name"]);

if ($imageInfo === false) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "File harus berupa gambar yang valid."
    ]);
    exit;
}

$allowedTypes = [
    IMAGETYPE_JPEG => "jpg",
    IMAGETYPE_PNG => "png",
    IMAGETYPE_WEBP => "webp"
];

$imageType = $imageInfo[2];

if (!isset($allowedTypes[$imageType])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Format foto harus JPG, PNG, atau WebP."
    ]);
    exit;
}

$extension = $allowedTypes[$imageType];

$uploadDir = __DIR__ . "/uploads/profiles/";

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Folder penyimpanan foto gagal dibuat."
        ]);
        exit;
    }
}

$newFileName = bin2hex(random_bytes(16)) . "." . $extension;
$destination = $uploadDir . $newFileName;
$relativePath = "uploads/profiles/" . $newFileName;

try {
    $stmt = $pdo->prepare(
        "SELECT profile_picture
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

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "message" => "Foto gagal disimpan."
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE users
         SET profile_picture = :profile_picture
         WHERE user_id = :user_id"
    );

    $stmt->execute([
        "profile_picture" => $relativePath,
        "user_id" => $authenticatedUserId
    ]);

    $oldPicture = $user["profile_picture"];

    if (
        !empty($oldPicture) &&
        str_starts_with($oldPicture, "uploads/profiles/") &&
        basename($oldPicture) === substr(
            $oldPicture,
            strlen("uploads/profiles/")
        )
    ) {
        $oldFile = __DIR__ . "/" . $oldPicture;

        if (is_file($oldFile) && $oldFile !== $destination) {
            unlink($oldFile);
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Foto profil berhasil diperbarui.",
        "data" => [
            "user_id" => $authenticatedUserId,
            "profile_picture" => $relativePath
        ]
    ]);

} catch (Throwable $e) {
    if (isset($destination) && is_file($destination)) {
        unlink($destination);
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan saat memperbarui foto profil."
    ]);
}