ALTER TABLE users
    ADD COLUMN IF NOT EXISTS portfolio_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_hour;

CREATE TABLE IF NOT EXISTS portfolio_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title_ar VARCHAR(190) NOT NULL,
    title_en VARCHAR(190) NULL,
    caption_ar TEXT NOT NULL,
    caption_en TEXT NULL,
    alt_text_ar VARCHAR(255) NOT NULL,
    alt_text_en VARCHAR(255) NULL,
    captured_on DATE NULL,
    project_name VARCHAR(190) NULL,
    organization_name VARCHAR(190) NULL,
    people_context VARCHAR(255) NULL,
    location VARCHAR(190) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    mime_type VARCHAR(40) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    image_width SMALLINT UNSIGNED NOT NULL,
    image_height SMALLINT UNSIGNED NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portfolio_media_stored_name(stored_name),
    INDEX idx_portfolio_media_owner(user_id,created_at),
    INDEX idx_portfolio_media_public(user_id,is_published,sort_order,captured_on),
    CONSTRAINT fk_portfolio_media_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
