<?php

require_once __DIR__ . '/rental_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rentalResponse(false, 'Method harus POST.', null, 405);
}

// Hanya Admin yang berhak memproses dan menyelesaikan sengketa
$admin = rentalRequireRole('admin');
$input = rentalInput();

$disputeId = filter_var($input['dispute_id'] ?? null, FILTER_VALIDATE_INT);
$newStatus = trim($input['dispute_status'] ?? '');
$adminNotes = trim($input['admin_notes'] ?? '');
$nextRentalStatus = trim($input['next_rental_status'] ?? '');

$allowedStatuses = ['in_review', 'resolved', 'rejected'];
if (!$disputeId || !in_array($newStatus, $allowedStatuses, true)) {
    rentalResponse(
        false,
        'Data tidak valid. Status sengketa yang diizinkan: in_review, resolved, rejected.',
        null,
        400
    );
}

$allowedNextRentalStatuses = ['confirmed', 'ongoing', 'completed', 'cancelled'];
if ($nextRentalStatus !== '' && !in_array($nextRentalStatus, $allowedNextRentalStatuses, true)) {
    rentalResponse(
        false,
        'Status rental lanjutan tidak valid. Pilihan: confirmed, ongoing, completed, cancelled.',
        null,
        400
    );
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT dispute_id, rentals_id, dispute_status
         FROM disputes
         WHERE dispute_id = :dispute_id
         FOR UPDATE"
    );
    $stmt->execute(['dispute_id' => $disputeId]);
    $dispute = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dispute) {
        $pdo->rollBack();
        rentalResponse(false, 'Data sengketa tidak ditemukan.', null, 404);
    }

    $isFinalStatus = in_array($newStatus, ['resolved', 'rejected'], true);

    $updateDispute = $pdo->prepare("
        UPDATE disputes
        SET dispute_status = :status,
            admin_notes = :notes,
            resolved_by = :resolved_by,
            resolved_at = CASE WHEN :is_final = 1 THEN NOW() ELSE resolved_at END
        WHERE dispute_id = :dispute_id
    ");

    $updateDispute->execute([
        'status' => $newStatus,
        'notes' => $adminNotes !== '' ? $adminNotes : null,
        'resolved_by' => $admin['user_id'],
        'is_final' => $isFinalStatus ? 1 : 0,
        'dispute_id' => $disputeId
    ]);

    // Jika admin menentukan kelanjutan status transaksi rental
    if ($nextRentalStatus !== '') {
        $updateRental = $pdo->prepare("
            UPDATE rentals
            SET rental_status = :next_status
            WHERE rentals_id = :rentals_id
        ");
        $updateRental->execute([
            'next_status' => $nextRentalStatus,
            'rentals_id' => $dispute['rentals_id']
        ]);
    }

    $pdo->commit();

    rentalResponse(true, 'Status sengketa berhasil diperbarui.', [
        'dispute_id' => $disputeId,
        'dispute_status' => $newStatus,
        'admin_notes' => $adminNotes,
        'next_rental_status' => $nextRentalStatus !== '' ? $nextRentalStatus : null
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rentalResponse(false, 'Terjadi kesalahan: ' . $e->getMessage(), null, 500);
}