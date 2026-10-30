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
