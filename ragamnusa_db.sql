-- ====================================================================
-- RAGAMNUSA DATABASE SCHEMA
-- Platform Digital Penyewaan Produk Budaya Indonesia
-- Database: ragamnusa_db
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `ragamnusa_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `ragamnusa_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------------------
-- 1. TABEL: users
-- Pengguna sistem (Renter, Owner, Admin)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('renter', 'owner', 'admin') NOT NULL DEFAULT 'renter',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 2. TABEL: user_tokens
-- Token autentikasi berbasis Bearer (access & session) dengan SHA-256
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `user_tokens`;
CREATE TABLE `user_tokens` (
  `token_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `token_type` ENUM('access', 'session') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tokens_hash` (`token_hash`),
  INDEX `idx_tokens_user` (`user_id`),
  CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 3. TABEL: categories
-- 5 Kategori Budaya Utama RagamNusa
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 4. TABEL: products
-- Produk budaya yang didaftarkan oleh Owner
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `product_images` TEXT DEFAULT NULL,
  `price_per_day` DECIMAL(12, 2) NOT NULL,
  `deposit` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `stock` INT UNSIGNED NOT NULL DEFAULT 1,
  `condition_status` VARCHAR(50) DEFAULT 'Baik',
  `location` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_products_owner` (`owner_id`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_status` (`status`),
  CONSTRAINT `fk_products_owner` FOREIGN KEY (`owner_id`) 
    REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) 
    REFERENCES `categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 5. TABEL: availability
-- Kalender ketersediaan dan booking produk
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `availability`;
CREATE TABLE `availability` (
  `availability_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('available', 'unavailable', 'booked') NOT NULL DEFAULT 'available',
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_avail_product` (`product_id`),
  INDEX `idx_avail_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_avail_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 6. TABEL: rentals
-- Transaksi penyewaan utama (Renter menyewa produk budaya)
-- Status: pending -> confirmed -> ongoing -> completed / cancelled / disputed
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `rentals`;
CREATE TABLE `rentals` (
  `rentals_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `renter_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `rental_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `deposit_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `shipping_cost` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `rental_status` ENUM('pending', 'confirmed', 'ongoing', 'completed', 'cancelled', 'disputed') NOT NULL DEFAULT 'pending',
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rentals_renter` (`renter_id`),
  INDEX `idx_rentals_status` (`rental_status`),
  CONSTRAINT `fk_rentals_renter` FOREIGN KEY (`renter_id`) 
    REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 7. TABEL: order_details
-- Rincian item produk dalam transaksi penyewaan (Multi-item & Multi-owner)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `order_details`;
CREATE TABLE `order_details` (
  `order_detail_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rentals_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `price` DECIMAL(12, 2) NOT NULL,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  `deposit_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  INDEX `idx_order_rentals` (`rentals_id`),
  INDEX `idx_order_product` (`product_id`),
  CONSTRAINT `fk_order_rentals` FOREIGN KEY (`rentals_id`) 
    REFERENCES `rentals` (`rentals_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 8. TABEL: payments
-- Pencatatan simulasi pembayaran penyewaan
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rentals_id` INT UNSIGNED NOT NULL,
  `payment_method` ENUM('qris', 'virtual_account', 'bank_transfer', 'e_wallet', 'cash') NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `transaction_reference` VARCHAR(100) DEFAULT NULL,
  `payment_status` ENUM('pending', 'paid', 'failed', 'expired') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payments_rental` (`rentals_id`),
  INDEX `idx_payments_status` (`payment_status`),
  CONSTRAINT `fk_payments_rental` FOREIGN KEY (`rentals_id`) 
    REFERENCES `rentals` (`rentals_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 9. TABEL: refunds
-- Pencatatan pengembalian dana uang jaminan (deposit) pasca rental selesai
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `refund_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `refund_reason` TEXT DEFAULT NULL,
  `refund_status` ENUM('pending', 'processing', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
  `refunded_at` DATETIME DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_refunds_payment` (`payment_id`),
  INDEX `idx_refunds_status` (`refund_status`),
  CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) 
    REFERENCES `payments` (`payment_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 10. TABEL: disputes
-- Penanganan sengketa / komplain transaksi antara Renter, Owner & Admin
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `disputes`;
CREATE TABLE `disputes` (
  `dispute_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rentals_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `evidence_image` VARCHAR(255) DEFAULT NULL,
  `dispute_status` ENUM('open', 'in_review', 'resolved', 'rejected') NOT NULL DEFAULT 'open',
  `admin_notes` TEXT DEFAULT NULL,
  `resolved_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_disputes_rental` (`rentals_id`),
  INDEX `idx_disputes_user` (`user_id`),
  INDEX `idx_disputes_status` (`dispute_status`),
  CONSTRAINT `fk_disputes_rental` FOREIGN KEY (`rentals_id`) 
    REFERENCES `rentals` (`rentals_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_disputes_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_disputes_admin` FOREIGN KEY (`resolved_by`) 
    REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- 11. TABEL: reviews
-- Ulasan & Rating produk budaya setelah rental selesai (completed)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `review_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rentals_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `comment` TEXT DEFAULT NULL,
  `owner_reply` TEXT DEFAULT NULL,
  `replied_at` DATETIME DEFAULT NULL,
  `create_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reviews_product` (`product_id`),
  INDEX `idx_reviews_rental` (`rentals_id`),
  INDEX `idx_reviews_user` (`user_id`),
  CONSTRAINT `fk_reviews_rental` FOREIGN KEY (`rentals_id`) 
    REFERENCES `rentals` (`rentals_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- DATA AWAL (SEED DATA): 5 Kategori Budaya Utama RagamNusa
-- --------------------------------------------------------------------
INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Pakaian Adat', 'Kebaya, beskap, pakaian adat daerah, dan busana tradisional Nusantara.'),
(2, 'Alat Musik Tradisional', 'Angklung, gamelan, sasando, kolintang, dan alat musik daerah lainnya.'),
(3, 'Properti Event & Wedding Tradisional', 'Dekorasi panggung adat, pelaminan, dan perlengkapan pernikahan tradisional.'),
(4, 'Properti Kuliner Tradisional', 'Peralatan penyajian, kendil, gerabah, dan perlengkapan kuliner khas daerah.'),
(5, 'Perlengkapan Workshop Kriya', 'Peralatan membatik (canting, wajan batik), alat tenun, dan kerajinan tradisional.')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);
