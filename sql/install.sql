CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 category VARCHAR(120) NOT NULL DEFAULT 'أخرى',
 priority VARCHAR(40) NOT NULL DEFAULT 'بناء علاقة',
 status VARCHAR(60) NOT NULL DEFAULT 'هدف',
 website VARCHAR(500) NULL,
 fit_reason TEXT NULL,
 first_offer TEXT NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_org_name(name), INDEX idx_org_priority(priority), INDEX idx_org_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id INT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 job_title VARCHAR(180) NULL,
 email VARCHAR(180) NULL,
 phone VARCHAR(80) NULL,
 profile_url VARCHAR(500) NULL,
 relationship_type VARCHAR(80) NOT NULL DEFAULT 'شبكة مهنية',
 status VARCHAR(80) NOT NULL DEFAULT 'لم أتواصل',
 last_contact_date DATE NULL,
 next_followup_date DATE NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_contacts_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
 INDEX idx_contact_name(name), INDEX idx_contact_status(status), INDEX idx_contact_followup(next_followup_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_activities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 contact_id INT UNSIGNED NOT NULL,
 activity_type VARCHAR(80) NOT NULL,
 activity_date DATE NOT NULL,
 summary TEXT NOT NULL,
 next_action TEXT NULL,
 next_followup_date DATE NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_activities_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
 INDEX idx_activity_date(activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_applications (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 organization_id INT UNSIGNED NULL,
 company VARCHAR(180) NOT NULL,
 job_title VARCHAR(220) NOT NULL,
 job_url VARCHAR(500) NULL,
 source VARCHAR(120) NULL,
 location VARCHAR(160) NULL,
 employment_type VARCHAR(100) NULL,
 status VARCHAR(80) NOT NULL DEFAULT 'محفوظة',
 applied_date DATE NULL,
 deadline DATE NULL,
 next_followup_date DATE NULL,
 cv_version VARCHAR(180) NULL,
 cover_letter_status VARCHAR(120) NULL,
 contact_name VARCHAR(180) NULL,
 contact_url VARCHAR(500) NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_jobs_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
 INDEX idx_job_status(status), INDEX idx_job_followup(next_followup_date), INDEX idx_job_company(company)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
 INDEX idx_job_document_job(job_application_id), INDEX idx_job_document_type(document_type)
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
 INDEX idx_job_interview_job(job_application_id), INDEX idx_job_interview_schedule(scheduled_at), INDEX idx_job_interview_status(status)
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
 INDEX idx_job_activity_job(job_application_id), INDEX idx_job_activity_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reminder_dispatches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 dispatch_key VARCHAR(160) NOT NULL UNIQUE,
 recipient VARCHAR(180) NOT NULL,
 item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_reminder_sent_at(sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
