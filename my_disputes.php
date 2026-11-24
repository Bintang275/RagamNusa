<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rentalResponse(false, 'Method harus GET.', null, 405);
}

$user = rentalUser();
$role = $user['role'];
$userId = (int)$user['user_id'];

$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['open', 'in_review', 'investigating', 'resolved', 'rejected'];

try {
    if ($role === 'admin') {
        $sql = "
            SELECT 
                d.dispute_id,
                d.rentals_id,
                d.reported_by,
                d.reported_by AS reporter_id,
                u.name AS reporter_name,
                u.email AS reporter_email,
                d.reason,
                d.description,
                d.evidence,
                d.evidence AS evidence_image,
                d.dispute_status,
                d.resolution,
                d.resolution AS admin_notes,
                d.create_at,
                d.updated_at,
                r.start_date,
                r.end_date,
                r.rental_status
            FROM disputes d
            INNER JOIN users u ON u.user_id = d.reported_by
            INNER JOIN rentals r ON r.rentals_id = d.rentals_id
        ";
        $params = [];

        if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
            $sql .= " WHERE d.dispute_status = :status";
            $params['status'] = $statusFilter;
        }

        $sql .= " ORDER BY d.create_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($role === 'owner') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                d.dispute_id,
                d.rentals_id,
                d.reported_by,
                d.reported_by AS reporter_id,
                u.name AS reporter_name,
                d.reason,
                d.description,
                d.evidence,
                d.evidence AS evidence_image,
                d.dispute_status,
                d.resolution,
                d.resolution AS admin_notes,
                d.create_at,
                d.updated_at,
                r.start_date,
                r.end_date,
                r.rental_status
            FROM disputes d
            INNER JOIN users u ON u.user_id = d.reported_by
            INNER JOIN rentals r ON r.rentals_id = d.rentals_id
            INNER JOIN order_details od ON od.rentals_id = r.rentals_id
            INNER JOIN products p ON p.product_id = od.product_id
            WHERE p.owner_id = :owner_id
            ORDER BY d.create_at DESC
        ");
        $stmt->execute(['owner_id' => $userId]);
        $disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt = $pdo->prepare("
            SELECT 
                d.dispute_id,
                d.rentals_id,
                d.reported_by,
                d.reported_by AS reporter_id,
                u.name AS reporter_name,
                d.reason,
                d.description,
                d.evidence,
                d.evidence AS evidence_image,
                d.dispute_status,
                d.resolution,
                d.resolution AS admin_notes,
                d.create_at,
                d.updated_at,
                r.start_date,
                r.end_date,
                r.rental_status
            FROM disputes d
            INNER JOIN users u ON u.user_id = d.reported_by
            INNER JOIN rentals r ON r.rentals_id = d.rentals_id
            WHERE r.renter_id = :renter_id OR d.reported_by = :user_id
            ORDER BY d.create_at DESC
        ");
        $stmt->execute([
            'renter_id' => $userId,
            'user_id' => $userId
        ]);
        $disputes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    rentalResponse(true, 'Data sengketa berhasil diambil.', $disputes);

} catch (PDOException $e) {
    error_log($e->getMessage());
    rentalResponse(false, 'Gagal mengambil data sengketa: ' . $e->getMessage(), null, 500);
}
