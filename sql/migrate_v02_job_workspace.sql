CREATE TABLE IF NOT EXISTS job_documents (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_application_id INT UNSIGNED NOT NULL,
 document_type VARCHAR(40) NOT NULL,
 label VARCHAR(180) NULL,
 original_name VARCHAR(255) NOT NULL,
 stored_name VARCHAR(100) NOT NULL UNIQUE,
 mime_type VARCHAR(120) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_job_documents_job FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
 INDEX idx_job_document_job(job_application_id),
 INDEX idx_job_document_type(document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_interviews (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_application_id INT UNSIGNED NOT NULL,
 round_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
 interview_type VARCHAR(80) NOT NULL,
 scheduled_at DATETIME NOT NULL,
 interview_format VARCHAR(40) NOT NULL,
 interviewer_name VARCHAR(180) NULL,
 meeting_url VARCHAR(500) NULL,
 location VARCHAR(220) NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'مجدولة',
 preparation_notes TEXT NULL,
 questions TEXT NULL,
 outcome TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_job_interviews_job FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
 INDEX idx_job_interview_job(job_application_id),
 INDEX idx_job_interview_schedule(scheduled_at),
 INDEX idx_job_interview_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_activities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_application_id INT UNSIGNED NOT NULL,
 activity_type VARCHAR(60) NOT NULL,
 status_from VARCHAR(80) NULL,
 status_to VARCHAR(80) NULL,
 summary VARCHAR(500) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_job_activities_job FOREIGN KEY (job_application_id) REFERENCES job_applications(id) ON DELETE CASCADE,
 INDEX idx_job_activity_job(job_application_id),
 INDEX idx_job_activity_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
