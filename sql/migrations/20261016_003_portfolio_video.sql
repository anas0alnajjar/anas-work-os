ALTER TABLE portfolio_media
    ADD COLUMN IF NOT EXISTS media_kind VARCHAR(12) NOT NULL DEFAULT 'image' AFTER user_id,
    ADD COLUMN IF NOT EXISTS poster_original_name VARCHAR(255) NULL AFTER stored_name,
    ADD COLUMN IF NOT EXISTS poster_stored_name VARCHAR(100) NULL AFTER poster_original_name,
    ADD COLUMN IF NOT EXISTS poster_mime_type VARCHAR(40) NULL AFTER poster_stored_name,
    ADD COLUMN IF NOT EXISTS poster_size_bytes INT UNSIGNED NULL AFTER poster_mime_type;
