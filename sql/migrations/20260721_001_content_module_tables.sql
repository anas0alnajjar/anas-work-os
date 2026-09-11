CREATE TABLE IF NOT EXISTS schema_migrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 migration_name VARCHAR(190) NOT NULL UNIQUE,
 executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_topics (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seed_key VARCHAR(100) NULL UNIQUE,
 title_ar VARCHAR(255) NOT NULL,
 title_en VARCHAR(255) NOT NULL,
 pillar VARCHAR(100) NOT NULL,
 month_number TINYINT UNSIGNED NOT NULL,
 week_number TINYINT UNSIGNED NOT NULL,
 objective TEXT NULL,
 core_message TEXT NULL,
 evidence_source TEXT NULL,
 truthfulness_note TEXT NULL,
 topic_status VARCHAR(30) NOT NULL DEFAULT 'planned',
 internal_notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_content_topic_period(month_number,week_number),
 INDEX idx_content_topic_pillar(pillar),
 INDEX idx_content_topic_status(topic_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_posts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 topic_id BIGINT UNSIGNED NOT NULL,
 platform VARCHAR(30) NOT NULL,
 language VARCHAR(20) NOT NULL,
 content_format VARCHAR(30) NOT NULL,
 platform_angle TEXT NULL,
 post_title VARCHAR(255) NULL,
 draft_text LONGTEXT NULL,
 visual_note TEXT NULL,
 call_to_action TEXT NULL,
 post_status VARCHAR(30) NOT NULL DEFAULT 'planned',
 scheduled_at DATETIME NULL,
 custom_reminder_at DATETIME NULL,
 published_at DATETIME NULL,
 published_url VARCHAR(500) NULL,
 reminder_sent_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_content_posts_topic FOREIGN KEY (topic_id) REFERENCES content_topics(id) ON DELETE CASCADE,
 UNIQUE KEY uq_content_topic_platform(topic_id,platform),
 INDEX idx_content_post_status(post_status),
 INDEX idx_content_post_schedule(scheduled_at),
 INDEX idx_content_post_custom_reminder(custom_reminder_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_reminder_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reminder_date DATE NOT NULL,
 recipient_email VARCHAR(255) NOT NULL,
 content_summary TEXT NULL,
 send_status VARCHAR(20) NOT NULL,
 error_message TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_content_reminder_date(reminder_date),
 INDEX idx_content_reminder_status(send_status),
 INDEX idx_content_reminder_recipient(recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
