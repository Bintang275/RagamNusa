<?php
require_once 'rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

function paymentResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentResponse(false, 'Method tidak diizinkan.', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$rentalsId = (int)($input['rentals_id'] ?? 0);
$method = strtolower(trim($input['payment_method'] ?? ''));
$provider = trim($input['payment_provider'] ?? '');

// Mendukung QRIS, Virtual Account, E-Wallet, Transfer Bank, dan Tunai
$allowedMethods = ['qris', 'virtual_account', 'bank_transfer', 'e_wallet', 'cash'];

if ($rentalsId <= 0 || !in_array($method, $allowedMethods, true)) {
    paymentResponse(
        false, 
        'Data pembayaran tidak valid. Pilihan metode: qris, virtual_account, bank_transfer, e_wallet, cash.', 
        null, 
        400
    );
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM rentals
        WHERE rentals_id = ?
          AND renter_id = ?
        FOR UPDATE
    ");

    $pdo->beginTransaction();

    $stmt->execute([$rentalsId, $authenticatedUserId]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rental) {
        $pdo->rollBack();
        paymentResponse(false, 'Transaksi penyewaan tidak ditemukan.', null, 404);
    }

    if (!in_array($rental['rental_status'], ['pending', 'confirmed'], true)) {
        $pdo->rollBack();
        paymentResponse(false, 'Transaksi tidak dalam status yang dapat dibayar.', null, 400);
    }

    $check = $pdo->prepare("
        SELECT payment_id
        FROM payments
        WHERE rentals_id = ?
          AND payment_status IN ('pending', 'paid')
        LIMIT 1
    ");

    $check->execute([$rentalsId]);

    if ($check->fetch()) {
        $pdo->rollBack();
        paymentResponse(false, 'Tagihan pembayaran untuk transaksi ini sudah dibuat.', null, 409);
    }

    $amount = (float)$rental['rental_price']
            + (float)$rental['deposit_total']
            + (float)$rental['shipping_cost'];

    if ($amount <= 0) {
        $pdo->rollBack();
        paymentResponse(false, 'Total pembayaran tidak valid.', null, 400);
    }

    // Nomor referensi transaksi unik (contoh: RN-QRIS-20261118-0012)
    $refPrefix = strtoupper(str_replace('_', '', $method));
    $trxRef = 'RN-' . $refPrefix . '-' . date('Ymd') . '-' . str_pad((string)$rentalsId, 4, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // Berlaku 24 jam

    // Generate detail simulasi pembayaran sesuai metode yang dipilih
    $simulationData = [];

    if ($method === 'qris') {
        $simulationData = [
            'type' => 'QRIS',
            'qr_string' => '00020101021226540014ID.RAGAMNUSA.QRIS0118936009110022340118520458125303360540' . str_pad((string)$rentalsId, 6, '0', STR_PAD_LEFT),
            'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=RAGAMNUSA_PAY_' . $trxRef,
            'instructions' => 'Buka aplikasi e-wallet atau mobile banking apa saja (GoPay, OVO, DANA, BCA, Livin, dll), lalu scan QR code ini.'
        ];
    } elseif ($method === 'virtual_account') {
        $bankName = $provider !== '' ? strtoupper($provider) : 'BCA';
        $bankCodes = [
            'BCA' => '88001',
            'MANDIRI' => '89001',
            'BNI' => '82771',
            'BRI' => '80121',
            'BSI' => '84111'
        ];
        $bankPrefix = $bankCodes[$bankName] ?? '88001';
        $vaNumber = $bankPrefix . date('ymd') . str_pad((string)$rentalsId, 4, '0', STR_PAD_LEFT);

        $simulationData = [
            'type' => 'Virtual Account',
            'bank' => $bankName,
            'va_number' => $vaNumber,
            'instructions' => "Pilih menu Transfer > Virtual Account di ATM atau Mobile Banking $bankName, lalu masukkan nomor VA di atas."
        ];
    } elseif ($method === 'e_wallet') {
        $walletName = $provider !== '' ? ucfirst($provider) : 'GoPay';
        $simulationData = [
            'type' => 'E-Wallet',
            'wallet' => $walletName,
            'checkout_url' => 'https://simulator.ragamnusa.id/pay/ewallet?ref=' . $trxRef,
            'instructions' => "Klik link pembayaran untuk dialihkan ke aplikasi $walletName dan konfirmasi pembayaran."
        ];
    } elseif ($method === 'bank_transfer') {
        $bankName = $provider !== '' ? strtoupper($provider) : 'BCA';
        $simulationData = [
            'type' => 'Transfer Bank Manual',
            'bank' => $bankName,
            'account_number' => '7820192831',
            'account_name' => 'PT RAGAM NUSA INDONESIA',
            'instructions' => 'Transfer tepat hingga 3 digit terakhir ke rekening di atas dan simpan bukti transfer.'
        ];
    } else {
        $simulationData = [
            'type' => 'Tunai / COD',
            'instructions' => 'Lakukan pembayaran secara tunai saat serah terima barang budaya.'
        ];
    }

    $insert = $pdo->prepare("
        INSERT INTO payments
        (rentals_id, payment_method, amount, transaction_reference, payment_status)
        VALUES (?, ?, ?, ?, 'pending')
    ");

    $insert->execute([$rentalsId, $method, $amount, $trxRef]);

    $paymentId = $pdo->lastInsertId();

    $pdo->commit();

    paymentResponse(true, 'Tagihan pembayaran berhasil dibuat.', [
        'payment_id' => (int)$paymentId,
        'rentals_id' => $rentalsId,
        'payment_method' => $method,
        'transaction_reference' => $trxRef,
        'amount' => $amount,
        'payment_status' => 'pending',
        'expires_at' => $expiresAt,
        'payment_detail' => $simulationData
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    paymentResponse(false, 'Terjadi kesalahan saat membuat pembayaran: ' . $e->getMessage(), null, 500);
}
