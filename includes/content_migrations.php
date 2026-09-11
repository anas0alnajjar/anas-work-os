<?php
declare(strict_types=1);

function content_required_tables(): array {
    return ['schema_migrations','content_topics','content_posts','content_reminder_logs'];
}

function content_table_exists(PDO $pdo, string $table): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() > 0;
}

function content_column_exists(PDO $pdo, string $table, string $column): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $statement->execute([$table,$column]);
    return (int)$statement->fetchColumn() > 0;
}

function content_index_exists(PDO $pdo, string $table, string $index): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $statement->execute([$table,$index]);
    return (int)$statement->fetchColumn() > 0;
}

function content_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool {
    $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
    $statement->execute([$table,$constraint]);
    return (int)$statement->fetchColumn() > 0;
}

function content_existing_tables(PDO $pdo): array {
    $statement = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME');
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function content_parse_sql_file(string $path): array {
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Migration file is unavailable.');
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    return array_values(array_filter(array_map('trim', $statements ?: [])));
}

function content_seed_brand_plan(PDO $pdo, ?int $userId=null): void {
    $topics = require __DIR__ . '/content_plan_seed.php';
    $owned = content_column_exists($pdo,'content_topics','user_id');
    if ($owned && !$userId) $userId = (int)(current_user()['id'] ?? $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn());
    if ($owned && !$userId) throw new RuntimeException('A content-plan owner is required.');
    $topicStatement = $pdo->prepare($owned
        ? 'INSERT INTO content_topics(user_id,seed_key,title_ar,title_en,pillar,month_number,week_number,objective,core_message,evidence_source,truthfulness_note,topic_status,internal_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE seed_key=VALUES(seed_key)'
        : 'INSERT INTO content_topics(seed_key,title_ar,title_en,pillar,month_number,week_number,objective,core_message,evidence_source,truthfulness_note,topic_status,internal_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE seed_key=VALUES(seed_key)');
    $topicIdStatement = $pdo->prepare($owned ? 'SELECT id FROM content_topics WHERE user_id=? AND seed_key=?' : 'SELECT id FROM content_topics WHERE seed_key=?');
    $postStatement = $pdo->prepare(
        'INSERT INTO content_posts(topic_id,platform,language,content_format,platform_angle,post_title,draft_text,visual_note,call_to_action,post_status)
         VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id'
    );

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        foreach ($topics as $topic) {
            $values = [
                $topic['seed_key'],$topic['title_ar'],$topic['title_en'],$topic['pillar'],
                $topic['month_number'],$topic['week_number'],$topic['objective'] ?? null,null,
                $topic['evidence_source'] ?? null,$topic['truthfulness_note'] ?? null,'planned',null,
            ];
            $topicStatement->execute($owned ? [$userId,...$values] : $values);
            $topicIdStatement->execute($owned ? [$userId,$topic['seed_key']] : [$topic['seed_key']]);
            $topicId = (int)$topicIdStatement->fetchColumn();
            if ($topicId < 1) throw new RuntimeException('Seed topic could not be resolved.');

            foreach ($topic['posts'] as $platform => $post) {
                $postStatement->execute([
                    $topicId,$platform,$post['language'],$post['format'],$post['angle'] ?? null,
                    null,null,$post['visual'] ?? null,null,'planned',
                ]);
            }
        }
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function content_migration_definitions(): array {
    return [
        '20260721_001_content_module_tables' => static function(PDO $pdo): void {
            $path = dirname(__DIR__) . '/sql/migrations/20260721_001_content_module_tables.sql';
            foreach (content_parse_sql_file($path) as $statement) $pdo->exec($statement);
        },
        '20260721_002_seed_three_month_brand_plan' => static function(PDO $pdo): void {
            content_seed_brand_plan($pdo);
        },
        '20260721_003_sustainable_content_timeline' => static function(PDO $pdo): void {
            if (!content_column_exists($pdo, 'content_topics', 'planned_for')) {
                $pdo->exec('ALTER TABLE content_topics ADD COLUMN planned_for DATE NULL AFTER week_number');
            }
            if (!content_index_exists($pdo, 'content_topics', 'idx_content_topic_planned_for')) {
                $pdo->exec('ALTER TABLE content_topics ADD INDEX idx_content_topic_planned_for(planned_for)');
            }
        },
        '20260721_004_multi_user_content_ownership' => static function(PDO $pdo): void {
            $ownerUserId = (int)(current_user()['id'] ?? $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn());
            if ($ownerUserId < 1) throw new RuntimeException('A content owner account is required.');
            foreach (['content_topics','content_reminder_logs'] as $table) {
                if (!content_column_exists($pdo,$table,'user_id')) $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN user_id INT UNSIGNED NULL AFTER id");
                $statement = $pdo->prepare("UPDATE `{$table}` SET user_id=? WHERE user_id IS NULL");
                $statement->execute([$ownerUserId]);
                $pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN user_id INT UNSIGNED NOT NULL");
                $index = 'idx_'.$table.'_user';
                if (!content_index_exists($pdo,$table,$index)) $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`user_id`)");
                $constraint = 'fk_'.$table.'_user';
                if (!content_foreign_key_exists($pdo,$table,$constraint)) $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`user_id`) REFERENCES users(id) ON DELETE RESTRICT");
            }
            if (content_index_exists($pdo,'content_topics','seed_key')) $pdo->exec('ALTER TABLE content_topics DROP INDEX `seed_key`');
            if (!content_index_exists($pdo,'content_topics','uq_content_topic_user_seed')) $pdo->exec('ALTER TABLE content_topics ADD UNIQUE INDEX uq_content_topic_user_seed (`user_id`,`seed_key`)');
        },
    ];
}

function content_completed_migrations(PDO $pdo): array {
    if (!content_table_exists($pdo, 'schema_migrations')) return [];
    $statement = $pdo->query('SELECT migration_name FROM schema_migrations ORDER BY id');
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function content_migration_state(PDO $pdo): array {
    $definitions = content_migration_definitions();
    $completed = content_completed_migrations($pdo);
    return [
        'completed' => array_values(array_intersect(array_keys($definitions), $completed)),
        'pending' => array_values(array_diff(array_keys($definitions), $completed)),
    ];
}

function content_apply_pending_migrations(PDO $pdo): array {
    $definitions = content_migration_definitions();
    $completed = content_completed_migrations($pdo);
    $results = [];

    foreach ($definitions as $name => $migration) {
        if (in_array($name, $completed, true)) {
            $results[] = ['name'=>$name,'status'=>'skipped'];
            continue;
        }
        try {
            $migration($pdo);
            if (!content_table_exists($pdo, 'schema_migrations')) throw new RuntimeException('Migration registry was not created.');
            $statement = $pdo->prepare('INSERT INTO schema_migrations(migration_name) VALUES(?)');
            $statement->execute([$name]);
            $results[] = ['name'=>$name,'status'=>'completed'];
            $completed[] = $name;
        } catch (Throwable $exception) {
            error_log('[content-migration] ' . $name . ': ' . $exception->getMessage());
            $results[] = ['name'=>$name,'status'=>'failed'];
            break;
        }
    }
    return $results;
}

function content_setup_authorized(array $config): bool {
    $secret = (string)($config['setup_secret'] ?? '');
    $storedHash = (string)($_SESSION['content_setup_secret_hash'] ?? '');
    return $secret !== '' && $storedHash !== '' && hash_equals(hash('sha256', $secret), $storedHash);
}
