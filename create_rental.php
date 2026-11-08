
<?php

require_once __DIR__ . '/rental_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

$user = rentalRequireRole('renter');
$input = rentalInput();

$startDate = $input['start_date'] ?? '';
$endDate = $input['end_date'] ?? '';
$shippingCost = $input['shipping_cost'] ?? 0;
$items = $input['items'] ?? [];

if (
    !rentalValidDate($startDate) ||
    !rentalValidDate($endDate) ||
    $endDate < $startDate
) {
    rentalResponse(false, 'Tanggal penyewaan tidak valid.', null, 400);
}

if (
    !is_numeric($shippingCost) ||
    $shippingCost < 0 ||
    !is_array($items) ||
    count($items) === 0
) {
    rentalResponse(false, 'Data penyewaan tidak valid.', null, 400);
}

// Gabungkan produk yang sama agar tidak terjadi duplikasi.
$quantities = [];

foreach ($items as $item) {
    $productId = filter_var(
        $item['product_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantity = filter_var(
        $item['quantity'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$productId || !$quantity || $quantity < 1) {
        rentalResponse(false, 'Produk atau jumlah tidak valid.', null, 400);
    }

    $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
}

$days = (int) (
    (new DateTime($startDate))->diff(new DateTime($endDate))->days
) + 1;

try {
    $pdo->beginTransaction();

    $rentalPrice = 0;
    $depositTotal = 0;
    $orderItems = [];

    foreach ($quantities as $productId => $quantity) {

        // Mengunci baris produk selama transaksi berlangsung.
        $stmt = $pdo->prepare(
            "SELECT product_id, owner_id, name, price_per_day,
                    deposit, stock, status
             FROM products
             WHERE product_id = :product_id
             FOR UPDATE"
        );

        $stmt->execute(['product_id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || $product['status'] !== 'active') {
            throw new Exception("Produk ID $productId tidak tersedia.");
        }

        // Periksa ketersediaan tanggal.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM availability
             WHERE product_id = :product_id
               AND status = 'available'
               AND start_date <= :start_date
               AND end_date >= :end_date"
        );

        $stmt->execute([
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            throw new Exception(
                "Jadwal produk {$product['name']} tidak tersedia."
            );
        }

        // Pastikan tidak ada jadwal yang diblokir.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM availability
             WHERE product_id = :product_id
               AND status IN ('unavailable', 'booked')
               AND start_date <= :end_date
               AND end_date >= :start_date"
        );

        $stmt->execute([
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception(
                "Jadwal produk {$product['name']} sedang diblokir."
            );
        }

        // Hitung stok yang sudah digunakan transaksi lain.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(od.quantity), 0)
             FROM order_details od
             INNER JOIN rentals r
                ON r.rentals_id = od.rentals_id
             WHERE od.product_id = :product_id
               AND r.rental_status IN
                   ('pending', 'confirmed', 'ongoing', 'disputed')
               AND r.start_date <= :end_date
               AND r.end_date >= :start_date"
        );

        $stmt->execute([
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $usedStock = (int) $stmt->fetchColumn();
        $remainingStock = (int) $product['stock'] - $usedStock;

        if ($quantity > $remainingStock) {
            throw new Exception(
                "Stok produk {$product['name']} tidak mencukupi."
            );
        }

        $price = (float) $product['price_per_day'];
        $deposit = (float) $product['deposit'];

        $subtotal = $price * $quantity * $days;
        $itemDeposit = $deposit * $quantity;

        $rentalPrice += $subtotal;
        $depositTotal += $itemDeposit;

        $orderItems[] = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $subtotal,
            'deposit_amount' => $itemDeposit
        ];
    }

    // Buat transaksi utama.
    $stmt = $pdo->prepare(
        "INSERT INTO rentals
         (renter_id, start_date, end_date,
          rental_price, deposit_total, shipping_cost, rental_status)
         VALUES
         (:renter_id, :start_date, :end_date,
          :rental_price, :deposit_total, :shipping_cost, 'pending')"
    );

    $stmt->execute([
        'renter_id' => $user['user_id'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'rental_price' => $rentalPrice,
        'deposit_total' => $depositTotal,
        'shipping_cost' => $shippingCost
    ]);

    $rentalId = (int) $pdo->lastInsertId();

    // Simpan seluruh produk ke detail transaksi.
    $stmt = $pdo->prepare(
        "INSERT INTO order_details
         (rentals_id, product_id, quantity,
          price, subtotal, deposit_amount)
         VALUES
         (:rentals_id, :product_id, :quantity,
          :price, :subtotal, :deposit_amount)"
    );

    foreach ($orderItems as $item) {
        $stmt->execute([
            'rentals_id' => $rentalId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'subtotal' => $item['subtotal'],
            'deposit_amount' => $item['deposit_amount']
        ]);
    }

    $pdo->commit();

    rentalResponse(true, 'Transaksi penyewaan berhasil dibuat.', [
        'rentals_id' => $rentalId,
        'rental_price' => $rentalPrice,
        'deposit_total' => $depositTotal,
        'shipping_cost' => (float) $shippingCost,
        'total' => $rentalPrice + $depositTotal + $shippingCost,
        'rental_status' => 'pending',
        'items' => $orderItems
    ], 201);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    rentalResponse(false, $e->getMessage(), null, 400);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    rentalResponse(false, 'Terjadi kesalahan database.', null, 500);
}
