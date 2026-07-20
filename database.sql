CREATE TABLE IF NOT EXISTS settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 office_city VARCHAR(100) NOT NULL DEFAULT 'Jakarta',
 boss_name VARCHAR(200) NOT NULL DEFAULT 'Yusrizal Kurniawan',
 boss_nip VARCHAR(30) NOT NULL DEFAULT '197903032005021003',
 boss_position VARCHAR(255) NOT NULL DEFAULT 'Kepala Subdirektorat Pemantauan dan Evaluasi',
 boss_signature_path VARCHAR(255) NULL,
 director_name VARCHAR(200) NOT NULL DEFAULT 'Erna Wijayanti, S.T., M.Sc.',
 director_nip VARCHAR(30) NOT NULL DEFAULT '198005082005022001',
 director_position VARCHAR(255) NOT NULL DEFAULT 'PLT. Direktur Sistem dan Strategi Penyelenggaraan Jalan dan Jembatan',
 director_signature_path VARCHAR(255) NULL,
 max_absences TINYINT UNSIGNED NOT NULL DEFAULT 4,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO settings(id) VALUES(1);
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id INT UNSIGNED NULL,
 name VARCHAR(150) NOT NULL,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','operator') NOT NULL DEFAULT 'operator',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_users_employee_id(employee_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS employees (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(220) NOT NULL,
 nip VARCHAR(30) NULL,
 grade VARCHAR(100) NULL,
 position VARCHAR(255) NULL,
 signature_path VARCHAR(255) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_employee_name(name), INDEX idx_employee_active(active)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS attendance_events (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 employee_id INT UNSIGNED NOT NULL,
 event_date DATE NOT NULL,
 letter_date DATE NOT NULL,
 category ENUM('late','early_leave','missing_in','missing_out') NOT NULL,
 event_time TIME NOT NULL,
 app_name VARCHAR(120) NOT NULL DEFAULT 'Satu Bravo',
 reason TEXT NOT NULL,
 letter_number VARCHAR(120) NULL,
 approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 rejection_note TEXT NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_event_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON UPDATE CASCADE,
 CONSTRAINT fk_event_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_event_date(event_date), INDEX idx_event_employee_month(employee_id,event_date), INDEX idx_event_approval(approval_status)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action VARCHAR(50) NOT NULL,
 entity VARCHAR(50) NOT NULL,
 entity_id INT UNSIGNED NULL,
 detail TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_log_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_log_created(created_at)
) ENGINE=InnoDB;
