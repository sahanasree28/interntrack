CREATE DATABASE IF NOT EXISTS interntrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE interntrack;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_idx (role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS internships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  organization VARCHAR(160) NOT NULL,
  location VARCHAR(160) NOT NULL DEFAULT 'Remote',
  internship_type VARCHAR(80) NOT NULL DEFAULT 'Internship',
  description TEXT NOT NULL,
  requirements TEXT NULL,
  status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY internships_status_created_idx (status, created_at),
  CONSTRAINT internships_creator_fk FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  internship_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  cover_note TEXT NULL,
  status ENUM('submitted', 'under_review', 'accepted', 'rejected') NOT NULL DEFAULT 'submitted',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY applications_student_internship_unique (student_id, internship_id),
  KEY applications_status_created_idx (status, created_at),
  CONSTRAINT applications_internship_fk FOREIGN KEY (internship_id) REFERENCES internships(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT applications_student_fk FOREIGN KEY (student_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  internship_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('pending', 'in_progress', 'completed') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tasks_student_status_due_idx (student_id, status, due_date),
  KEY tasks_due_date_idx (due_date),
  CONSTRAINT tasks_student_fk FOREIGN KEY (student_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT tasks_internship_fk FOREIGN KEY (internship_id) REFERENCES internships(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT tasks_creator_fk FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  response TEXT NOT NULL,
  file_name VARCHAR(255) NULL,
  stored_name VARCHAR(100) NULL,
  file_mime VARCHAR(100) NULL,
  file_size INT UNSIGNED NULL,
  review_status ENUM('submitted', 'reviewed', 'approved', 'rejected') NOT NULL DEFAULT 'submitted',
  feedback TEXT NULL,
  marks DECIMAL(5,2) NULL,
  reviewed_by INT UNSIGNED NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY submissions_task_submitted_idx (task_id, submitted_at),
  KEY submissions_student_status_idx (student_id, review_status),
  CONSTRAINT submissions_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT submissions_student_fk FOREIGN KEY (student_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT submissions_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  message VARCHAR(255) NOT NULL,
  link VARCHAR(255) NOT NULL DEFAULT 'dashboard.php',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY notifications_user_read_created_idx (user_id, is_read, created_at),
  CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
