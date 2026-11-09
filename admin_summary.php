<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rentalResponse(false, 'Method harus GET.', null, 405);
}

// Khusus diakses oleh Admin untuk keperluan website dashboard
$admin = rentalRequireRole('admin');

try {
    // 1. Ringkasan Pengguna
    $usersStmt = $pdo->query("
        SELECT 
            role, 
            COUNT(*) AS total
        FROM users
        GROUP BY role
    ");
    $usersByRole = [];
    while ($row = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
        $usersByRole[$row['role']] = (int)$row['total'];
    }

    // 2. Ringkasan Produk & Kategori
    $productsCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $categoriesCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

    // 3. Ringkasan Status Transaksi Rental
    $rentalsStmt = $pdo->query("
        SELECT 
            rental_status, 
            COUNT(*) AS total
        FROM rentals
        GROUP BY rental_status
    ");
    $rentalsByStatus = [
        'pending' => 0,
        'confirmed' => 0,
        'ongoing' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'disputed' => 0
    ];
    while ($row = $rentalsStmt->fetch(PDO::FETCH_ASSOC)) {
        $rentalsByStatus[$row['rental_status']] = (int)$row['total'];
    }

    // 4. Ringkasan Keuangan (Total Pendapatan Sewa & Deposit dari Pembayaran Lunas)
    $financeStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_paid_transactions,
            COALESCE(SUM(amount), 0) AS total_revenue
        FROM payments
        WHERE payment_status = 'paid'
    ");
    $finance = $financeStmt->fetch(PDO::FETCH_ASSOC);

    // 5. Antrean Operasional Admin yang Butuh Perhatian
    $pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn();
    $pendingRefunds = (int)$pdo->query("SELECT COUNT(*) FROM refunds WHERE refund_status IN ('pending', 'processing')")->fetchColumn();
    $openDisputes = (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE dispute_status IN ('open', 'in_review')")->fetchColumn();

    // 6. Transaksi Terbaru
    $recentStmt = $pdo->query("
        SELECT 
            r.rentals_id,
            u.name AS renter_name,
            r.rental_price,
            r.deposit_total,
            r.shipping_cost,
            r.rental_status,
            r.create_at
        FROM rentals r
        INNER JOIN users u ON u.user_id = r.renter_id
        ORDER BY r.create_at DESC
        LIMIT 5
    ");
    $recentRentals = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    rentalResponse(true, 'Data ringkasan dashboard admin berhasil diambil.', [
        'users' => [
            'total_renters' => $usersByRole['renter'] ?? 0,
            'total_owners' => $usersByRole['owner'] ?? 0,
            'total_admins' => $usersByRole['admin'] ?? 0,
            'total_all' => array_sum($usersByRole)
        ],
        'catalog' => [
            'total_active_products' => $productsCount,
            'total_categories' => $categoriesCount
        ],
        'rentals_by_status' => $rentalsByStatus,
        'financials' => [
            'paid_transactions_count' => (int)$finance['total_paid_transactions'],
            'total_revenue' => (float)$finance['total_revenue']
        ],
        'action_required' => [
            'pending_payments' => $pendingPayments,
            'pending_refunds' => $pendingRefunds,
            'open_disputes' => $openDisputes
        ],
        'recent_rentals' => $recentRentals
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    rentalResponse(false, 'Gagal mengambil data dashboard admin.', null, 500);
}