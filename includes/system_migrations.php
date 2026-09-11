<?php
declare(strict_types=1);

function system_table_exists(PDO $pdo, string $table): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() > 0;
}

function system_column_exists(PDO $pdo, string $table, string $column): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $statement->execute([$table,$column]);
    return (int)$statement->fetchColumn() > 0;
}

function system_index_exists(PDO $pdo, string $table, string $index): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $statement->execute([$table,$index]);
    return (int)$statement->fetchColumn() > 0;
}

function system_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE=\'FOREIGN KEY\'');
    $statement->execute([$table,$constraint]);
    return (int)$statement->fetchColumn() > 0;
}

function system_ensure_registry(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(190) NOT NULL UNIQUE,
        executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function system_add_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!system_column_exists($pdo,$table,$column)) $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}

function system_add_index(PDO $pdo, string $table, string $index, string $columns, bool $unique=false): void {
    if (!system_index_exists($pdo,$table,$index)) $pdo->exec('ALTER TABLE `'.$table.'` ADD '.($unique?'UNIQUE ':'').'INDEX `'.$index.'` ('.$columns.')');
}

function system_add_user_owner(PDO $pdo, string $table, int $ownerUserId): void {
    if (!system_table_exists($pdo,$table)) return;
    system_add_column($pdo,$table,'user_id','INT UNSIGNED NULL AFTER `id`');
    $statement = $pdo->prepare("UPDATE `{$table}` SET user_id=? WHERE user_id IS NULL");
    $statement->execute([$ownerUserId]);
    system_add_index($pdo,$table,'idx_'.$table.'_user','`user_id`');
    $constraint = 'fk_'.$table.'_user';
    if (!system_foreign_key_exists($pdo,$table,$constraint)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT");
    }
}

function system_migration_definitions(): array {
    return [
        '20260721_100_multi_user_accounts' => static function(PDO $pdo, int $ownerUserId, ?string $legacyReminderEmail): void {
            system_add_column($pdo,'users','email','VARCHAR(190) NULL AFTER `username`');
            system_add_column($pdo,'users','display_name','VARCHAR(120) NULL AFTER `email`');
            system_add_column($pdo,'users','reminder_email','VARCHAR(190) NULL AFTER `display_name`');
            system_add_column($pdo,'users','timezone',"VARCHAR(64) NOT NULL DEFAULT 'Asia/Damascus' AFTER `reminder_email`");
            system_add_column($pdo,'users','reminders_enabled','TINYINT(1) NOT NULL DEFAULT 1 AFTER `timezone`');
            system_add_column($pdo,'users','reminder_hour','TINYINT UNSIGNED NOT NULL DEFAULT 9 AFTER `reminders_enabled`');
            system_add_column($pdo,'users','account_status',"VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `reminders_enabled`");
            system_add_column($pdo,'users','email_verified_at','DATETIME NULL AFTER `account_status`');
            system_add_column($pdo,'users','last_login_at','DATETIME NULL AFTER `email_verified_at`');
            system_add_column($pdo,'users','updated_at','TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
            system_add_index($pdo,'users','uq_users_email','`email`',true);
            $pdo->exec('UPDATE users SET display_name=username WHERE display_name IS NULL OR display_name=\'\'');
            if ($legacyReminderEmail && filter_var($legacyReminderEmail,FILTER_VALIDATE_EMAIL)) {
                $statement = $pdo->prepare('UPDATE users SET reminder_email=?,email=COALESCE(NULLIF(email,\'\'),?),email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?');
                $statement->execute([$legacyReminderEmail,$legacyReminderEmail,$ownerUserId]);
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS user_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_type VARCHAR(30) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                requested_ip VARCHAR(45) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_tokens_hash(token_hash),
                INDEX idx_user_tokens_lookup(user_id,token_type,expires_at),
                CONSTRAINT fk_user_tokens_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        },
        '20260721_101_existing_data_ownership' => static function(PDO $pdo, int $ownerUserId): void {
            foreach (['organizations','job_applications','content_topics','reminder_dispatches','content_reminder_logs'] as $table) {
                system_add_user_owner($pdo,$table,$ownerUserId);
            }
        },
        '20260721_102_per_user_unique_keys' => static function(PDO $pdo): void {
            if (system_table_exists($pdo,'content_topics')) {
                if (system_index_exists($pdo,'content_topics','seed_key')) $pdo->exec('ALTER TABLE content_topics DROP INDEX `seed_key`');
                system_add_index($pdo,'content_topics','uq_content_topic_user_seed','`user_id`,`seed_key`',true);
            }
            if (system_table_exists($pdo,'reminder_dispatches')) {
                if (system_index_exists($pdo,'reminder_dispatches','dispatch_key')) $pdo->exec('ALTER TABLE reminder_dispatches DROP INDEX `dispatch_key`');
                system_add_index($pdo,'reminder_dispatches','uq_reminder_user_dispatch','`user_id`,`dispatch_key`',true);
            }
        },
        '20260721_103_enforce_record_ownership' => static function(PDO $pdo, int $ownerUserId): void {
            foreach (['organizations','job_applications','content_topics','reminder_dispatches','content_reminder_logs'] as $table) {
                if (!system_table_exists($pdo,$table) || !system_column_exists($pdo,$table,'user_id')) continue;
                $statement = $pdo->prepare("UPDATE `{$table}` SET user_id=? WHERE user_id IS NULL");
                $statement->execute([$ownerUserId]);
                $unowned = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE user_id IS NULL")->fetchColumn();
                if ($unowned) throw new RuntimeException("Unowned records remain in {$table}.");
                $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN user_id INT UNSIGNED NOT NULL");
            }
        },
        '20260721_104_per_user_reminder_hour' => static function(PDO $pdo): void {
            system_add_column($pdo,'users','reminder_hour','TINYINT UNSIGNED NOT NULL DEFAULT 9 AFTER `reminders_enabled`');
        },
    ];
}

function system_completed_migrations(PDO $pdo): array {
    system_ensure_registry($pdo);
    return $pdo->query('SELECT migration_name FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

function system_migration_state(PDO $pdo): array {
    $definitions = system_migration_definitions();
    $completed = system_completed_migrations($pdo);
    return [
        'completed'=>array_values(array_intersect(array_keys($definitions),$completed)),
        'pending'=>array_values(array_diff(array_keys($definitions),$completed)),
    ];
}

function system_apply_pending_migrations(PDO $pdo, int $ownerUserId, ?string $legacyReminderEmail=null): array {
    if ($ownerUserId < 1 || !(int)scalar('SELECT COUNT(*) FROM users WHERE id=?',[$ownerUserId])) {
        throw new RuntimeException('A valid legacy owner account is required.');
    }
    system_ensure_registry($pdo);
    $completed = system_completed_migrations($pdo);
    $results = [];
    foreach (system_migration_definitions() as $name=>$migration) {
        if (in_array($name,$completed,true)) { $results[]=['name'=>$name,'status'=>'skipped']; continue; }
        try {
            $migration($pdo,$ownerUserId,$legacyReminderEmail);
            $statement = $pdo->prepare('INSERT INTO schema_migrations(migration_name) VALUES(?)');
            $statement->execute([$name]);
            $completed[] = $name;
            $results[] = ['name'=>$name,'status'=>'completed'];
        } catch (Throwable $exception) {
            error_log('[system-migration] '.$name.': '.$exception->getMessage());
            $results[] = ['name'=>$name,'status'=>'failed'];
            break;
        }
    }
    return $results;
}

function system_ownership_summary(PDO $pdo, int $ownerUserId): array {
    $summary = [];
    foreach (['organizations','job_applications','content_topics','reminder_dispatches','content_reminder_logs'] as $table) {
        if (!system_table_exists($pdo,$table)) { $summary[$table]=['exists'=>false,'total'=>0,'owned'=>0,'unowned'=>0]; continue; }
        $total = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        if (!system_column_exists($pdo,$table,'user_id')) { $summary[$table]=['exists'=>true,'total'=>$total,'owned'=>0,'unowned'=>$total]; continue; }
        $statement = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE user_id=?");
        $statement->execute([$ownerUserId]);
        $owned = (int)$statement->fetchColumn();
        $unowned = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE user_id IS NULL")->fetchColumn();
        $summary[$table]=['exists'=>true,'total'=>$total,'owned'=>$owned,'unowned'=>$unowned];
    }
    return $summary;
}
