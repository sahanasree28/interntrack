<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('This action requires a form submission.');
}

$user = current_user();
if (!$user) {
    redirect('auth.php');
}
check_csrf();
$pdo = db();
$action = request_string($_POST, 'action');
$uploadedPath = null;
$destination = $user['role'] === 'admin' ? 'admin.php' : 'dashboard.php';

try {
    if ($action === 'apply' && $user['role'] === 'student') {
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        $coverNote = post_string('cover_note', 3000);
        if (!$internshipId || $internshipId < 1) {
            throw new InvalidArgumentException('Choose a valid internship.');
        }
        $pdo->beginTransaction();
        $opportunity = $pdo->prepare("SELECT id, title FROM internships WHERE id = ? AND status = 'published'");
        $opportunity->execute([$internshipId]);
        $internship = $opportunity->fetch();
        if (!$internship) {
            throw new InvalidArgumentException('This opportunity is no longer available.');
        }
        $insert = $pdo->prepare('INSERT INTO applications (internship_id, student_id, cover_note) VALUES (?, ?, ?)');
        $insert->execute([$internshipId, (int) $user['id'], $coverNote ?: null]);
        notify((int) $user['id'], 'Your application for ' . $internship['title'] . ' was submitted.');
        notify_admins($user['full_name'] . ' applied for ' . $internship['title'] . '.', 'admin.php#applications');
        $pdo->commit();
        flash('Your application has been submitted.');
        $destination = 'dashboard.php#opportunities';
    } elseif ($action === 'submit_task' && $user['role'] === 'student') {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $response = post_string('response', 12000);
        if (!$taskId || $taskId < 1 || $response === '') {
            throw new InvalidArgumentException('Add your response before submitting.');
        }
        $taskQuery = $pdo->prepare('SELECT id, title, status FROM tasks WHERE id = ? AND student_id = ?');
        $taskQuery->execute([$taskId, (int) $user['id']]);
        $task = $taskQuery->fetch();
        if (!$task || $task['status'] === 'completed') {
            throw new InvalidArgumentException('This task is not available for submission.');
        }
        $latestSubmission = $pdo->prepare(
            'SELECT review_status FROM submissions WHERE task_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1'
        );
        $latestSubmission->execute([$taskId, (int) $user['id']]);
        $latestStatus = $latestSubmission->fetchColumn();
        if (in_array($latestStatus, ['submitted', 'reviewed'], true)) {
            throw new InvalidArgumentException('Your latest work is still being reviewed. You can revise it after it has been rejected.');
        }
        $file = $_FILES['attachment'] ?? null;
        $fileData = [null, null, null, null];
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                throw new InvalidArgumentException('The attachment could not be uploaded. Try again.');
            }
            if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
                throw new InvalidArgumentException('Attachments must be no larger than 5 MB.');
            }
            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $allowed = [
                'pdf' => ['application/pdf'],
                'doc' => ['application/msword', 'application/x-ole-storage'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                'txt' => ['text/plain'],
                'png' => ['image/png'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
            ];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $file['tmp_name']);
            if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
                throw new InvalidArgumentException('Use a PDF, DOC/DOCX, TXT, PNG, or JPG attachment.');
            }
            $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
            $uploadDirectory = __DIR__ . '/storage/uploads';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('Attachments are temporarily unavailable.');
            }
            $uploadedPath = $uploadDirectory . '/' . $storedName;
            if (!move_uploaded_file((string) $file['tmp_name'], $uploadedPath)) {
                throw new RuntimeException('The attachment could not be saved.');
            }
            chmod($uploadedPath, 0640);
            $originalName = basename((string) $file['name']);
            $fileData = [$originalName, $storedName, (string) $mime, (int) $file['size']];
        }

        $pdo->beginTransaction();
        $lockedTaskQuery = $pdo->prepare(
            'SELECT id, title, status FROM tasks WHERE id = ? AND student_id = ? FOR UPDATE'
        );
        $lockedTaskQuery->execute([$taskId, (int) $user['id']]);
        $lockedTask = $lockedTaskQuery->fetch();
        if (!$lockedTask || $lockedTask['status'] === 'completed') {
            throw new InvalidArgumentException('This task is not available for submission.');
        }
        $latestSubmission->execute([$taskId, (int) $user['id']]);
        $latestStatus = $latestSubmission->fetchColumn();
        if (in_array($latestStatus, ['submitted', 'reviewed'], true)) {
            throw new InvalidArgumentException('Your latest work is still being reviewed. You can revise it after it has been rejected.');
        }
        $insert = $pdo->prepare(
            "INSERT INTO submissions (task_id, student_id, response, file_name, stored_name, file_mime, file_size, review_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')"
        );
        $insert->execute([$taskId, (int) $user['id'], $response, ...$fileData]);
        $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ? AND student_id = ?")
            ->execute([$taskId, (int) $user['id']]);
        notify((int) $user['id'], 'Your work for "' . $lockedTask['title'] . '" was submitted.');
        notify_admins($user['full_name'] . ' submitted work for "' . $lockedTask['title'] . '".', 'admin.php#submissions');
        $pdo->commit();
        flash('Your work was submitted for review.');
        $destination = 'dashboard.php#tasks';
    } elseif ($action === 'mark_notifications_read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
            ->execute([(int) $user['id']]);
        flash('Notifications marked as read.');
    } elseif ($action === 'save_internship' && $user['role'] === 'admin') {
        $id = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        $title = post_string('title', 160);
        $organization = post_string('organization', 160);
        $location = post_string('location', 160);
        $type = post_string('internship_type', 80);
        $description = post_string('description', 10000);
        $requirements = post_string('requirements', 5000);
        $status = (string) ($_POST['status'] ?? 'draft');
        if ($title === '' || $organization === '' || $location === '' || $type === '' || $description === '') {
            throw new InvalidArgumentException('Complete the required opportunity details.');
        }
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException('Choose a valid publication status.');
        }
        if ($id) {
            $existing = $pdo->prepare('SELECT id FROM internships WHERE id = ?');
            $existing->execute([$id]);
            if (!$existing->fetch()) {
                throw new InvalidArgumentException('Internship opportunity not found.');
            }
            $pdo->prepare(
                'UPDATE internships SET title = ?, organization = ?, location = ?, internship_type = ?, description = ?, requirements = ?, status = ? WHERE id = ?'
            )->execute([$title, $organization, $location, $type, $description, $requirements ?: null, $status, $id]);
            $message = 'Internship opportunity updated.';
        } else {
            $pdo->prepare(
                'INSERT INTO internships (title, organization, location, internship_type, description, requirements, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$title, $organization, $location, $type, $description, $requirements ?: null, $status, (int) $user['id']]);
            $message = 'Internship opportunity created.';
        }
        flash($message);
        $destination = 'admin.php#opportunities';
    } elseif ($action === 'update_application' && $user['role'] === 'admin') {
        $id = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if (!$id || !in_array($status, ['under_review', 'accepted', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid application status.');
        }
        $pdo->beginTransaction();
        $query = $pdo->prepare(
            'SELECT a.student_id, a.status, i.title FROM applications a JOIN internships i ON i.id = a.internship_id WHERE a.id = ?'
        );
        $query->execute([$id]);
        $application = $query->fetch();
        if (!$application) {
            throw new InvalidArgumentException('Application not found.');
        }
        $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?')->execute([$status, $id]);
        if ($application['status'] !== $status) {
            $label = str_replace('_', ' ', $status);
            notify((int) $application['student_id'], 'Your application for ' . $application['title'] . ' is now ' . $label . '.');
        }
        $pdo->commit();
        flash('Application status updated.');
        $destination = 'admin.php#applications';
    } elseif ($action === 'assign_task' && $user['role'] === 'admin') {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
        $internshipId = filter_var($_POST['internship_id'] ?? null, FILTER_VALIDATE_INT);
        $title = post_string('title', 180);
        $description = post_string('description', 10000);
        $dueDate = (string) ($_POST['due_date'] ?? '');
        $date = DateTime::createFromFormat('!Y-m-d', $dueDate);
        if (!$studentId || $title === '' || $description === '' || !$date || $date->format('Y-m-d') !== $dueDate || $dueDate < date('Y-m-d')) {
            throw new InvalidArgumentException('Complete the task details and choose a valid deadline.');
        }
        $studentQuery = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $studentQuery->execute([$studentId]);
        if (!$studentQuery->fetch()) {
            throw new InvalidArgumentException('Choose an existing student account.');
        }
        $internshipId = $internshipId ?: null;
        if ($internshipId !== null) {
            $check = $pdo->prepare('SELECT id FROM internships WHERE id = ?');
            $check->execute([$internshipId]);
            if (!$check->fetch()) {
                throw new InvalidArgumentException('Choose a valid internship for this task.');
            }
        }
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO tasks (student_id, internship_id, title, description, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$studentId, $internshipId, $title, $description, $dueDate, (int) $user['id']]);
        notify((int) $studentId, 'A new task was assigned to you: ' . $title . '.', 'dashboard.php#tasks');
        $pdo->commit();
        flash('Task assigned successfully.');
        $destination = 'admin.php#tasks';
    } elseif ($action === 'review_submission' && $user['role'] === 'admin') {
        $submissionId = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['review_status'] ?? '');
        $feedback = post_string('feedback', 5000);
        $marksText = trim((string) ($_POST['marks'] ?? ''));
        $marks = null;
        if ($marksText !== '') {
            if (!is_numeric($marksText) || (float) $marksText < 0 || (float) $marksText > 100) {
                throw new InvalidArgumentException('Marks must be between 0 and 100.');
            }
            $marks = number_format((float) $marksText, 2, '.', '');
        }
        if (!$submissionId || !in_array($status, ['reviewed', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose a valid review status.');
        }
        $pdo->beginTransaction();
        $query = $pdo->prepare(
            'SELECT s.student_id, s.task_id, t.title FROM submissions s JOIN tasks t ON t.id = s.task_id
             WHERE s.id = ? AND s.id = (SELECT MAX(s2.id) FROM submissions s2 WHERE s2.task_id = s.task_id)'
        );
        $query->execute([$submissionId]);
        $submission = $query->fetch();
        if (!$submission) {
            throw new InvalidArgumentException('Submission not found.');
        }
        $pdo->prepare(
            'UPDATE submissions SET review_status = ?, feedback = ?, marks = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$status, $feedback ?: null, $marks, (int) $user['id'], $submissionId]);
        $taskStatus = $status === 'approved' ? 'completed' : ($status === 'rejected' ? 'pending' : 'in_progress');
        $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?')->execute([$taskStatus, (int) $submission['task_id']]);
        notify((int) $submission['student_id'], 'Your submission for "' . $submission['title'] . '" was ' . $status . '.', 'dashboard.php#tasks');
        $pdo->commit();
        flash('Submission review saved.');
        $destination = 'admin.php#submissions';
    } else {
        throw new InvalidArgumentException('That action is not available for your account.');
    }
} catch (InvalidArgumentException | RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedPath !== null && is_file($uploadedPath)) {
        unlink($uploadedPath);
    }
    flash($exception->getMessage(), 'error');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($uploadedPath !== null && is_file($uploadedPath)) {
        unlink($uploadedPath);
    }
    error_log('InternTrack action database error: ' . $exception->getMessage());
    flash($exception->getCode() === '23000'
        ? 'This action could not be completed because the record already exists or is no longer available.'
        : 'We could not save that change right now. Please try again.', 'error');
}
redirect($destination);
