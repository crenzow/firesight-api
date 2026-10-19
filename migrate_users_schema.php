<?php
require_once __DIR__ . '/config/database.php';

echo "=== FireSight: Migrate user table to users schema ===\n";

try {
    $pdo = getDbConnection();

    // 1. Drop existing foreign keys pointing to user
    echo "1. Dropping foreign key constraints pointing to `user`...\n";
    $fkList = [
        ['auth_token', 'auth_token_ibfk_1'],
        ['password_reset', 'password_reset_ibfk_1'],
        ['report_evidence', 'report_evidence_ibfk_2'],
        ['report_status_history', 'report_status_history_ibfk_2'],
        ['resident_address', 'resident_address_ibfk_1'],
    ];

    foreach ($fkList as [$tbl, $fk]) {
        try {
            $pdo->exec("ALTER TABLE `{$tbl}` DROP FOREIGN KEY `{$fk}`");
            echo " - Dropped FK `{$fk}` on `{$tbl}`\n";
        } catch (Exception $e) {
            // Already dropped or doesn't exist
            echo " - Note on `{$tbl}`: " . $e->getMessage() . "\n";
        }
    }

    // 2. Update child table columns to bigint(20) UNSIGNED
    echo "2. Updating referencing columns to bigint(20) UNSIGNED...\n";
    $pdo->exec("ALTER TABLE auth_token MODIFY COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE password_reset MODIFY COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE report_evidence MODIFY COLUMN uploaded_by bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE report_status_history MODIFY COLUMN changed_by bigint(20) UNSIGNED DEFAULT NULL");
    $pdo->exec("ALTER TABLE resident_address MODIFY COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL");
    echo " - Child columns updated successfully.\n";

    // 3. Determine current table name (user or users)
    $tableExists = function ($name) use ($pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.tables 
            WHERE table_schema = :dbname AND table_name = :tname AND table_type = 'BASE TABLE'
        ");
        $stmt->execute(['dbname' => DB_NAME, 'tname' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $targetTable = 'user';
    if (!$tableExists('user') && $tableExists('users')) {
        $targetTable = 'users';
    }

    echo "3. Migrating table `{$targetTable}` data and columns...\n";

    // Map role column
    $pdo->exec("ALTER TABLE `{$targetTable}` MODIFY COLUMN `role` varchar(50) NOT NULL DEFAULT 'resident'");
    $pdo->exec("UPDATE `{$targetTable}` SET `role` = 'bfp_personnel' WHERE `role` = 'personnel'");
    $pdo->exec("UPDATE `{$targetTable}` SET `role` = 'bfp_admin' WHERE `role` = 'admin'");
    $pdo->exec("UPDATE `{$targetTable}` SET `role` = 'resident' WHERE `role` NOT IN ('bfp_personnel', 'bfp_admin')");

    // Map status column from is_active if is_active exists
    $colsStmt = $pdo->prepare("
        SELECT COLUMN_NAME FROM information_schema.columns 
        WHERE table_schema = :dbname AND table_name = :tname
    ");
    $colsStmt->execute(['dbname' => DB_NAME, 'tname' => $targetTable]);
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('is_active', $cols, true) && !in_array('status', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `status` enum('active','inactive') NOT NULL DEFAULT 'active' AFTER `role`");
        $pdo->exec("UPDATE `{$targetTable}` SET `status` = IF(is_active = 0, 'inactive', 'active')");
        $pdo->exec("ALTER TABLE `{$targetTable}` DROP COLUMN `is_active`");
        echo " - Converted `is_active` to `status`.\n";
    }

    // Add new columns if missing
    if (!in_array('email_verified_at', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `email_verified_at` timestamp NULL DEFAULT NULL AFTER `username`");
        echo " - Added `email_verified_at`.\n";
    }
    if (!in_array('two_factor_secret', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `two_factor_secret` text DEFAULT NULL AFTER `is_verified`");
        echo " - Added `two_factor_secret`.\n";
    }
    if (!in_array('two_factor_recovery_codes', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `two_factor_recovery_codes` text DEFAULT NULL AFTER `two_factor_secret`");
        echo " - Added `two_factor_recovery_codes`.\n";
    }
    if (!in_array('two_factor_confirmed_at', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `two_factor_confirmed_at` timestamp NULL DEFAULT NULL AFTER `two_factor_recovery_codes`");
        echo " - Added `two_factor_confirmed_at`.\n";
    }
    if (!in_array('remember_token', $cols, true)) {
        $pdo->exec("ALTER TABLE `{$targetTable}` ADD COLUMN `remember_token` varchar(100) DEFAULT NULL AFTER `two_factor_confirmed_at`");
        echo " - Added `remember_token`.\n";
    }

    // 4. Alter column types to match requested schema exactly
    echo "4. Altering columns to match exact requested specification...\n";
    $pdo->exec("
        ALTER TABLE `{$targetTable}`
        MODIFY COLUMN `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        MODIFY COLUMN `role` enum('resident','bfp_personnel','bfp_admin') NOT NULL DEFAULT 'resident',
        MODIFY COLUMN `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        MODIFY COLUMN `first_name` varchar(100) NOT NULL,
        MODIFY COLUMN `middle_name` varchar(100) DEFAULT NULL,
        MODIFY COLUMN `last_name` varchar(100) NOT NULL,
        MODIFY COLUMN `suffix` varchar(10) DEFAULT NULL,
        MODIFY COLUMN `email` varchar(255) NOT NULL,
        MODIFY COLUMN `contact_number` varchar(20) DEFAULT NULL,
        MODIFY COLUMN `username` varchar(50) DEFAULT NULL,
        MODIFY COLUMN `email_verified_at` timestamp NULL DEFAULT NULL,
        MODIFY COLUMN `password` varchar(255) NOT NULL,
        MODIFY COLUMN `google_id` varchar(255) DEFAULT NULL,
        MODIFY COLUMN `profile_image` varchar(255) DEFAULT NULL,
        MODIFY COLUMN `is_verified` tinyint(1) NOT NULL DEFAULT 0,
        MODIFY COLUMN `two_factor_secret` text DEFAULT NULL,
        MODIFY COLUMN `two_factor_recovery_codes` text DEFAULT NULL,
        MODIFY COLUMN `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
        MODIFY COLUMN `remember_token` varchar(100) DEFAULT NULL,
        MODIFY COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        MODIFY COLUMN `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo " - Column types altered successfully.\n";

    // 5. Rename table to `users` if currently named `user`
    if ($targetTable === 'user') {
        echo "5. Renaming `user` to `users` and creating backward-compatible `user` view...\n";
        $pdo->exec("RENAME TABLE `user` TO `users`");
        $pdo->exec("CREATE OR REPLACE VIEW `user` AS SELECT * FROM `users`");
        echo " - Renamed to `users` and created view `user`.\n";
    }

    // 6. Re-apply foreign key constraints pointing to users(user_id)
    echo "6. Re-applying foreign key constraints to `users(user_id)`...\n";
    try {
        $pdo->exec("ALTER TABLE auth_token ADD CONSTRAINT auth_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE");
        echo " - Added auth_token FK.\n";
    } catch (Exception $e) { echo " - auth_token FK: " . $e->getMessage() . "\n"; }

    try {
        $pdo->exec("ALTER TABLE password_reset ADD CONSTRAINT password_reset_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE");
        echo " - Added password_reset FK.\n";
    } catch (Exception $e) { echo " - password_reset FK: " . $e->getMessage() . "\n"; }

    try {
        $pdo->exec("ALTER TABLE report_evidence ADD CONSTRAINT report_evidence_ibfk_2 FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL");
        echo " - Added report_evidence FK.\n";
    } catch (Exception $e) { echo " - report_evidence FK: " . $e->getMessage() . "\n"; }

    try {
        $pdo->exec("ALTER TABLE report_status_history ADD CONSTRAINT report_status_history_ibfk_2 FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL");
        echo " - Added report_status_history FK.\n";
    } catch (Exception $e) { echo " - report_status_history FK: " . $e->getMessage() . "\n"; }

    try {
        $pdo->exec("ALTER TABLE resident_address ADD CONSTRAINT resident_address_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE");
        echo " - Added resident_address FK.\n";
    } catch (Exception $e) { echo " - resident_address FK: " . $e->getMessage() . "\n"; }

    echo "\n=== Migration Validation ===\n";
    echo $pdo->query("SHOW CREATE TABLE users")->fetch(PDO::FETCH_ASSOC)['Create Table'] . "\n\n";

    $userRows = $pdo->query("SELECT user_id, role, status, email, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users in database (" . count($userRows) . " total):\n";
    foreach ($userRows as $u) {
        echo " - ID: {$u['user_id']} | Role: {$u['role']} | Status: {$u['status']} | Email: {$u['email']}\n";
    }

    echo "\nMigration finished successfully!\n";
} catch (Exception $e) {
    echo "ERROR during migration: " . $e->getMessage() . "\n";
    exit(1);
}
