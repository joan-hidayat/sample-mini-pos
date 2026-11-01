-- Mini POS Express — database milik aplikasi client (terpisah dari central_saas LMD)
-- Sesuai ERD LMD level 9: users/roles adalah tanggung jawab aplikasi client.
-- File ini dijalankan otomatis oleh lib/bootstrap.php jika tabel belum ada.

CREATE TABLE IF NOT EXISTS `tenants` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `domain` VARCHAR(255) NOT NULL UNIQUE,
    `license_key` VARCHAR(150) NOT NULL,
    `instance_secret` CHAR(64) NULL COMMENT 'Secret dari LMD untuk menandatangani request (T-22)',
    `store_name` VARCHAR(150) NULL,
    `licensee_name` VARCHAR(150) NULL,
    `licensee_email` VARCHAR(150) NULL,
    `app_name` VARCHAR(150) NULL,
    `max_users` INT UNSIGNED NOT NULL DEFAULT 1,
    `modules` JSON NULL,
    `subscription` JSON NULL COMMENT 'No. pesanan, paket, masa aktif, link kuitansi dari LMD (T-42)',
    `expires_at` DATETIME NULL,
    `last_validated_at` DATETIME NULL,
    `activated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `unique_tenant_email` UNIQUE (`tenant_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- T-28: data kasir per tenant. Fitur yang memakainya dikunci sesuai modul lisensi LMD
-- (KASIR: products & sales · LAPORAN: membaca sales · STOK: products.stock dikurangi saat transaksi).
CREATE TABLE IF NOT EXISTS `products` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(80) NOT NULL DEFAULT 'Umum',
    `emoji` VARCHAR(16) NULL,
    `price` INT UNSIGNED NOT NULL COMMENT 'Rupiah, tanpa desimal',
    `stock` INT NOT NULL DEFAULT 0,
    `min_stock` INT UNSIGNED NOT NULL DEFAULT 5,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    INDEX `idx_products_tenant` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `subtotal` INT UNSIGNED NOT NULL,
    `tax` INT UNSIGNED NOT NULL,
    `total` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    INDEX `idx_sales_tenant_date` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `product_name` VARCHAR(150) NOT NULL COMMENT 'Salinan saat transaksi, laporan tidak berubah bila produk diganti',
    `price` INT UNSIGNED NOT NULL,
    `qty` INT UNSIGNED NOT NULL,
    `line_total` INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- T-43: notifikasi akun ke user toko (outbox). Kanal 'mailbox' = kotak surat lokal (dibaca admin di menu Notifikasi).
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `event` VARCHAR(50) NOT NULL,
    `channel` VARCHAR(20) NOT NULL,
    `recipient` VARCHAR(150) NOT NULL,
    `recipient_name` VARCHAR(150) NULL,
    `subject` VARCHAR(200) NOT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    `last_error` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_tenant` (`tenant_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
