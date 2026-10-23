
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/auth.php";

// Hanya menerima metode POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Gunakan metode POST."
    ]);

    exit;
}

// memastikan file gambar dikirim
if (!isset($_FILES["profile_picture"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Foto profil belum dikirim."
    ]);

    exit;
}

// mengambil informasi file
$file = $_FILES["profile_picture"];

// memeriksa error upload
if ($file["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Upload foto gagal."
    ]);

    exit;
}

// membatasi ukuran maksimal 2 MB
$maxSize = 2 * 1024 * 1024;

if ($file["size"] > $maxSize) {
    http_response_code(413);

    echo json_encode([
        "success" => false,
        "message" => "Ukuran foto maksimal 2 MB."
    ]);

    exit;
}

// memastikan file benar-benar gambar
if (!is_uploaded_file($file["tmp_name"])) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "File upload tidak valid."
    ]);

    exit;
}

// memeriksa tipe gambar berdasarkan isi file
$imageInfo = getimagesize($file["tmp_name"]);

$allowedTypes = [
    IMAGETYPE_JPEG => "jpg",
    IMAGETYPE_PNG  => "png",
    IMAGETYPE_WEBP => "webp"
];

if (
    $imageInfo === false ||
    !isset($allowedTypes[$imageInfo[2]])
) {
    http_response_code(415);

    echo json_encode([
        "success" => false,
        "message" => "Format foto harus JPEG, PNG, atau WebP."
    ]);

    exit;
}

$uploadDir = __DIR__ . "/uploads/profiles/";

// Pastikan folder penyimpanan tersedia
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Folder penyimpanan foto tidak tersedia."
    ]);

    exit;
}

// Buat nama file acak agar tidak bentrok
$extension = $allowedTypes[$imageInfo[2]];
$fileName = bin2hex(random_bytes(16)) . "." . $extension;

$destination = $uploadDir . $fileName;

// Pindahkan gambar ke folder penyimpanan
if (!move_uploaded_file($file["tmp_name"], $destination)) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Foto gagal disimpan."
    ]);

    exit;
}

// Siapkan path relatif untuk database
$profilePicture = "uploads/profiles/" . $fileName;

try {
    // Ambil foto lama milik pengguna yang login
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
        throw new RuntimeException("Pengguna tidak ditemukan.");
    }

    $oldPicture = $user["profile_picture"];

    // Perbarui foto profil pengguna
    $stmt = $pdo->prepare(
        "UPDATE users
         SET profile_picture = :profile_picture
         WHERE user_id = :user_id"
    );

    $stmt->execute([
        "profile_picture" => $profilePicture,
        "user_id" => $authenticatedUserId
    ]);

} catch (Throwable $e) {
    // Hapus file baru jika database gagal diperbarui
    if (is_file($destination)) {
        unlink($destination);
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Foto gagal diperbarui di database."
    ]);

    exit;
}

// Hapus foto lama hanya jika berada di folder profil
// dan bukan file yang baru saja disimpan.
if (
    !empty($oldPicture) &&
    $oldPicture !== $profilePicture &&
    strpos($oldPicture, "uploads/profiles/") === 0 &&
    basename($oldPicture) === $oldPicture
) {
    $oldPath = $uploadDir . $oldPicture;

    if (is_file($oldPath)) {
        unlink($oldPath);
    }
}

// Respons berhasil
echo json_encode([
    "success" => true,
    "message" => "Foto profil berhasil diperbarui.",
    "data" => [
        "user_id" => $authenticatedUserId,
        "profile_picture" => $profilePicture
    ]
]);
