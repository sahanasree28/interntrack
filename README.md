# InternTrack — College Internship and Task Management Portal

InternTrack is a PHP and MySQL application for student internship applications, assigned work, submissions, feedback, and administrator review. It is designed to run locally with Apache and MySQL in XAMPP.

## Requirements

- XAMPP with Apache, MySQL, PHP 8.1+ and the PDO MySQL and Fileinfo extensions enabled
- A modern browser

## Run in XAMPP

1. Copy the `interntrack` folder into your XAMPP document root:
   - macOS: `/Applications/XAMPP/xamppfiles/htdocs/`
   - Windows: `C:\xampp\htdocs\`
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, choose **Import**, select `interntrack/schema.sql`, and run the import. The script creates the `interntrack` database and its tables.
4. Visit `http://localhost/interntrack/`.
5. Create a student account using **Get started**.
6. To enable the administrator workspace, create an account with the desired administrator email, then run this query in phpMyAdmin's SQL tab:

   ```sql
   USE interntrack;
   UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
   ```

   Replace the email with the account you registered. Then use **Administrator access** at the bottom of the landing page to sign in. This manual promotion avoids shipping a default administrator password.
7. Create and publish an opportunity in the administrator dashboard. Student applications, task assignment, file submissions, reviews, marks, and notifications are then available in the relevant dashboard sections.

The default connection is `127.0.0.1`, database `interntrack`, user `root`, and a blank password, which matches a standard local XAMPP install. To use different settings, set `INTERNTRACK_DB_HOST`, `INTERNTRACK_DB_NAME`, `INTERNTRACK_DB_USER`, and `INTERNTRACK_DB_PASSWORD` in the Apache/PHP environment before starting Apache.

## Included workflows

- Student registration and login with PHP password hashing and session protection
- Internship listings, duplicate-safe applications, and application status tracking
- Task assignment, deadline and progress statistics, text submissions, and optional attachments
- Administrator opportunity publishing and archiving, application triage, task assignment, and submission review
- Review feedback and marks; approval completes a task, while rejection returns it to pending for revision
- Per-user notifications and read status

Attachments are limited to 5 MB and PDF, DOC/DOCX, TXT, PNG, or JPG. They are stored under `storage/uploads`, which is denied direct web access; downloads pass through an authenticated PHP endpoint. Ensure the `storage/uploads` directory is writable by the Apache/PHP user.

To allow the full 5 MB upload limit in XAMPP, confirm `upload_max_filesize = 5M` and `post_max_size = 8M` in the active `php.ini`, then restart Apache. PHP may otherwise enforce a lower upload limit.

## Security and deployment notes

All state-changing forms use CSRF tokens, database operations use PDO prepared statements, roles are checked server-side, and displayed values are HTML-escaped. The default database connection is intended for local development only. For deployment, configure a dedicated database user with a strong password, HTTPS, production-safe PHP error display settings, and regular database/file backups.

No sample account credentials are included. Administrator access is granted only by the explicit local database step above.
