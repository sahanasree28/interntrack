<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$user = require_role('admin');
$pdo = db();

$counts = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'internships' => (int) $pdo->query('SELECT COUNT(*) FROM internships')->fetchColumn(),
    'applications' => (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
    'tasks' => (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn(),
];
$internships = $pdo->query('SELECT * FROM internships ORDER BY created_at DESC')->fetchAll();
$students = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'student' ORDER BY full_name")->fetchAll();
$publishedInternships = $pdo->query("SELECT id, title, organization FROM internships WHERE status <> 'archived' ORDER BY title")->fetchAll();

$applicationSearch = trim((string) ($_GET['application_q'] ?? ''));
$applicationStatus = (string) ($_GET['application_status'] ?? '');
$applicationSql = 'SELECT a.*, u.full_name, u.email, i.title AS internship_title, i.organization FROM applications a JOIN users u ON u.id = a.student_id JOIN internships i ON i.id = a.internship_id WHERE 1=1';
$applicationParams = [];
if ($applicationSearch !== '') {
    $applicationSql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR i.title LIKE ? OR i.organization LIKE ?)';
    $term = '%' . $applicationSearch . '%';
    array_push($applicationParams, $term, $term, $term, $term);
}
if (in_array($applicationStatus, ['submitted', 'under_review', 'accepted', 'rejected'], true)) {
    $applicationSql .= ' AND a.status = ?';
    $applicationParams[] = $applicationStatus;
}
$applicationSql .= ' ORDER BY a.created_at DESC LIMIT 100';
$applicationsQuery = $pdo->prepare($applicationSql);
$applicationsQuery->execute($applicationParams);
$applications = $applicationsQuery->fetchAll();

$taskSearch = trim((string) ($_GET['task_q'] ?? ''));
$taskStatus = (string) ($_GET['task_status'] ?? '');
$taskSql = 'SELECT t.*, u.full_name, u.email, i.title AS internship_title FROM tasks t JOIN users u ON u.id = t.student_id LEFT JOIN internships i ON i.id = t.internship_id WHERE 1=1';
$taskParams = [];
if ($taskSearch !== '') {
    $taskSql .= ' AND (t.title LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $term = '%' . $taskSearch . '%';
    array_push($taskParams, $term, $term, $term);
}
if (in_array($taskStatus, ['pending', 'in_progress', 'completed'], true)) {
    $taskSql .= ' AND t.status = ?';
    $taskParams[] = $taskStatus;
}
$taskSql .= ' ORDER BY t.due_date ASC, t.created_at DESC LIMIT 100';
$tasksQuery = $pdo->prepare($taskSql);
$tasksQuery->execute($taskParams);
$tasks = $tasksQuery->fetchAll();

$submissionSearch = trim((string) ($_GET['submission_q'] ?? ''));
$submissionStatus = (string) ($_GET['submission_status'] ?? '');
$submissionSql = 'SELECT s.*, t.title AS task_title, t.due_date, u.full_name, u.email FROM submissions s JOIN tasks t ON t.id = s.task_id JOIN users u ON u.id = s.student_id WHERE s.id = (SELECT MAX(s2.id) FROM submissions s2 WHERE s2.task_id = s.task_id)';
$submissionParams = [];
if ($submissionSearch !== '') {
    $submissionSql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR t.title LIKE ?)';
    $term = '%' . $submissionSearch . '%';
    array_push($submissionParams, $term, $term, $term);
}
if (in_array($submissionStatus, ['submitted', 'reviewed', 'approved', 'rejected'], true)) {
    $submissionSql .= ' AND s.review_status = ?';
    $submissionParams[] = $submissionStatus;
}
$submissionSql .= " ORDER BY CASE WHEN s.review_status = 'submitted' THEN 0 ELSE 1 END, s.submitted_at DESC LIMIT 100";
$submissionsQuery = $pdo->prepare($submissionSql);
$submissionsQuery->execute($submissionParams);
$submissions = $submissionsQuery->fetchAll();

$notificationQuery = $pdo->prepare('SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
$notificationQuery->execute([(int) $user['id']]);
$notifications = $notificationQuery->fetchAll();
$unreadQuery = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadQuery->execute([(int) $user['id']]);
$unreadCount = (int) $unreadQuery->fetchColumn();
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administration — InternTrack</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="portal-page admin-portal">
  <header class="portal-header">
    <a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack <span class="admin-label">ADMIN</span></a>
    <nav class="portal-nav" aria-label="Admin navigation"><a href="#overview">Overview</a><a href="#opportunities">Opportunities</a><a href="#applications">Applications</a><a href="#tasks">Tasks</a><a href="#submissions">Reviews</a></nav>
    <div class="portal-user"><span class="user-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span><span><?= e($user['full_name']) ?></span><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-link" type="submit">Log out</button></form></div>
  </header>
  <main class="portal-main admin-main">
    <?php if ($flashMessage): ?><div class="notice notice-<?= e($flashMessage['kind']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
    <section class="portal-welcome admin-welcome" id="overview"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Administrator workspace</div><h1>Make room for<br><span>what’s next.</span></h1><p>Manage opportunities, support student progress, and turn great work into growth.</p></div><a class="button" href="#opportunities">Manage opportunities <span aria-hidden="true">↗</span></a></section>
    <section class="stats-grid admin-stats" aria-label="Portal summary">
      <article class="stat-card"><div class="stat-top"><span>Students</span><span class="stat-icon">♙</span></div><strong><?= $counts['students'] ?></strong><small>Active student accounts</small></article>
      <article class="stat-card"><div class="stat-top"><span>Opportunities</span><span class="stat-icon icon-lilac">⌕</span></div><strong><?= $counts['internships'] ?></strong><small>Across every status</small></article>
      <article class="stat-card"><div class="stat-top"><span>Applications</span><span class="stat-icon icon-peach">↗</span></div><strong><?= $counts['applications'] ?></strong><small>Student applications</small></article>
      <article class="stat-card"><div class="stat-top"><span>Assigned tasks</span><span class="stat-icon icon-green">✓</span></div><strong><?= $counts['tasks'] ?></strong><small>Across all students</small></article>
    </section>

    <section class="portal-section" id="opportunities">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Shape the next opportunity</div><h2>Internship opportunities</h2></div><span class="section-count"><?= count($internships) ?> total</span></div>
      <details class="management-create" open><summary><span class="create-plus">+</span> Create an opportunity</summary>
        <form class="management-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_internship"><div class="form-grid">
          <div><label for="new-title">Opportunity title</label><input id="new-title" name="title" maxlength="160" required></div><div><label for="new-org">Organization</label><input id="new-org" name="organization" maxlength="160" required></div>
          <div><label for="new-location">Location</label><input id="new-location" name="location" maxlength="160" value="Remote" required></div><div><label for="new-type">Type / duration</label><input id="new-type" name="internship_type" maxlength="80" placeholder="e.g. 12 weeks" required></div>
          <div class="form-span"><label for="new-description">Description</label><textarea id="new-description" name="description" rows="3" maxlength="10000" required></textarea></div>
          <div class="form-span"><label for="new-requirements">Requirements <span>(optional)</span></label><textarea id="new-requirements" name="requirements" rows="2" maxlength="5000"></textarea></div>
          <div><label for="new-status">Publication status</label><select id="new-status" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div>
        </div><button class="button button-small" type="submit">Create opportunity <span aria-hidden="true">↗</span></button></form>
      </details>
      <?php if (!$internships): ?><div class="empty-state"><h3>No opportunities to manage yet.</h3><p>Create your first opportunity using the form above.</p></div>
      <?php else: ?><div class="management-list"><?php foreach ($internships as $internship): ?>
        <details class="management-item"><summary><span><strong><?= e($internship['title']) ?></strong><small><?= e($internship['organization']) ?> · <?= e($internship['location']) ?></small></span><span class="status-badge status-<?= e($internship['status']) ?>"><?= e(ucfirst($internship['status'])) ?></span></summary>
          <form class="management-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_internship"><input type="hidden" name="internship_id" value="<?= (int) $internship['id'] ?>"><div class="form-grid">
            <div><label for="title-<?= (int) $internship['id'] ?>">Opportunity title</label><input id="title-<?= (int) $internship['id'] ?>" name="title" value="<?= e($internship['title']) ?>" maxlength="160" required></div><div><label for="org-<?= (int) $internship['id'] ?>">Organization</label><input id="org-<?= (int) $internship['id'] ?>" name="organization" value="<?= e($internship['organization']) ?>" maxlength="160" required></div>
            <div><label for="loc-<?= (int) $internship['id'] ?>">Location</label><input id="loc-<?= (int) $internship['id'] ?>" name="location" value="<?= e($internship['location']) ?>" maxlength="160" required></div><div><label for="type-<?= (int) $internship['id'] ?>">Type / duration</label><input id="type-<?= (int) $internship['id'] ?>" name="internship_type" value="<?= e($internship['internship_type']) ?>" maxlength="80" required></div>
            <div class="form-span"><label for="desc-<?= (int) $internship['id'] ?>">Description</label><textarea id="desc-<?= (int) $internship['id'] ?>" name="description" rows="3" maxlength="10000" required><?= e($internship['description']) ?></textarea></div><div class="form-span"><label for="req-<?= (int) $internship['id'] ?>">Requirements</label><textarea id="req-<?= (int) $internship['id'] ?>" name="requirements" rows="2" maxlength="5000"><?= e($internship['requirements']) ?></textarea></div>
            <div><label for="status-<?= (int) $internship['id'] ?>">Status</label><select id="status-<?= (int) $internship['id'] ?>" name="status"><?php foreach (['draft', 'published', 'archived'] as $status): ?><option value="<?= $status ?>" <?= $internship['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
          </div><button class="button button-small" type="submit">Save changes</button></form>
        </details>
      <?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="portal-section" id="applications">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Meet the applicants</div><h2>Applications</h2></div><span class="section-count"><?= count($applications) ?> shown</span></div>
      <form class="filter-form" method="get" action="admin.php#applications"><label for="application-q">Search applicants</label><input id="application-q" name="application_q" value="<?= e($applicationSearch) ?>" placeholder="Name, email, organization or role"><label for="application-status">Status</label><select id="application-status" name="application_status"><option value="">All statuses</option><?php foreach (['submitted', 'under_review', 'accepted', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= $applicationStatus === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Filter</button><a class="text-button" href="admin.php#applications">Clear</a></form>
      <?php if (!$applications): ?><div class="empty-state small-empty"><h3>No applications match this view.</h3><p>Try clearing the filters or check again later.</p></div>
      <?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Opportunity</th><th>Applied</th><th>Status</th><th>Update status</th></tr></thead><tbody><?php foreach ($applications as $application): ?><tr><td><strong><?= e($application['full_name']) ?></strong><small><?= e($application['email']) ?></small><?php if ($application['cover_note']): ?><small>Note: <?= e($application['cover_note']) ?></small><?php endif; ?></td><td><?= e($application['internship_title']) ?><small><?= e($application['organization']) ?></small></td><td><?= e(date('M j, Y', strtotime($application['created_at']))) ?></td><td><span class="status-badge status-<?= e($application['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $application['status']))) ?></span></td><td><form class="inline-update" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_application"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><label class="sr-only" for="app-status-<?= (int) $application['id'] ?>">New status for <?= e($application['full_name']) ?></label><select id="app-status-<?= (int) $application['id'] ?>" name="status"><?php foreach (['under_review', 'accepted', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= $application['status'] === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select><button type="submit" class="button button-tiny">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

    <section class="portal-section" id="tasks">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Support student growth</div><h2>Assign a task</h2></div></div>
      <?php if (!$students): ?><div class="empty-state small-empty"><h3>No student accounts yet.</h3><p>Students need to create an account before you can assign work.</p></div>
      <?php else: ?><form class="management-form task-assignment-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="assign_task"><div class="form-grid">
        <div><label for="task-student">Assign to student</label><select id="task-student" name="student_id" required><option value="">Choose a student</option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['full_name']) ?> · <?= e($student['email']) ?></option><?php endforeach; ?></select></div>
        <div><label for="task-internship">Related opportunity <span>(optional)</span></label><select id="task-internship" name="internship_id"><option value="">Not linked to an opportunity</option><?php foreach ($publishedInternships as $internship): ?><option value="<?= (int) $internship['id'] ?>"><?= e($internship['title']) ?> · <?= e($internship['organization']) ?></option><?php endforeach; ?></select></div>
        <div class="form-span"><label for="task-title">Task title</label><input id="task-title" name="title" maxlength="180" required></div>
        <div class="form-span"><label for="task-description">Description and instructions</label><textarea id="task-description" name="description" rows="3" maxlength="10000" required></textarea></div>
        <div><label for="task-due">Deadline</label><input id="task-due" name="due_date" type="date" min="<?= e(date('Y-m-d')) ?>" required></div>
      </div><button class="button button-small" type="submit">Assign task <span aria-hidden="true">↗</span></button></form><?php endif; ?>
      <div class="section-heading compact-heading subheading"><div><h2>Recently assigned</h2></div><span class="section-count"><?= count($tasks) ?> shown</span></div>
      <form class="filter-form" method="get" action="admin.php#tasks"><label for="task-q">Search tasks</label><input id="task-q" name="task_q" value="<?= e($taskSearch) ?>" placeholder="Task title or student"><label for="task-status-filter">Task status</label><select id="task-status-filter" name="task_status"><option value="">All statuses</option><?php foreach (['pending', 'in_progress', 'completed'] as $status): ?><option value="<?= $status ?>" <?= $taskStatus === $status ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Filter</button><a class="text-button" href="admin.php#tasks">Clear</a></form>
      <?php if (!$tasks): ?><div class="empty-state small-empty"><h3>No tasks assigned yet.</h3><p>Assigned tasks will be listed here for easy tracking.</p></div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Task</th><th>Student</th><th>Deadline</th><th>Status</th></tr></thead><tbody><?php foreach ($tasks as $task): ?><tr><td><strong><?= e($task['title']) ?></strong><small><?= e($task['internship_title'] ?: 'General task') ?></small></td><td><?= e($task['full_name']) ?><small><?= e($task['email']) ?></small></td><td><?= e(date('M j, Y', strtotime($task['due_date']))) ?></td><td><span class="status-badge status-<?= e($task['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $task['status']))) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

    <section class="portal-section" id="submissions">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Feedback that moves work forward</div><h2>Submission reviews</h2></div><span class="section-count"><?= count($submissions) ?> shown</span></div>
      <form class="filter-form" method="get" action="admin.php#submissions"><label for="submission-q">Search submissions</label><input id="submission-q" name="submission_q" value="<?= e($submissionSearch) ?>" placeholder="Student name, email or task"><label for="submission-status">Review status</label><select id="submission-status" name="submission_status"><option value="">All statuses</option><?php foreach (['submitted', 'reviewed', 'approved', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= $submissionStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Filter</button><a class="text-button" href="admin.php#submissions">Clear</a></form>
      <?php if (!$submissions): ?><div class="empty-state small-empty"><h3>No submissions to review.</h3><p>When students send in their work, it will appear here.</p></div>
      <?php else: ?><div class="review-list"><?php foreach ($submissions as $submission): ?><article class="review-card"><div class="review-header"><div><span class="eyebrow">From <?= e($submission['full_name']) ?> · <?= e($submission['email']) ?></span><h3><?= e($submission['task_title']) ?></h3><p>Submitted <?= e(date('M j, Y · g:i a', strtotime($submission['submitted_at']))) ?> · Deadline <?= e(date('M j, Y', strtotime($submission['due_date']))) ?></p></div><span class="status-badge status-<?= e($submission['review_status']) ?>"><?= e(ucfirst($submission['review_status'])) ?></span></div>
        <div class="student-response"><?= nl2br(e($submission['response'])) ?></div><?php if ($submission['file_name']): ?><a class="attachment-link" href="download.php?id=<?= (int) $submission['id'] ?>">↳ <?= e($submission['file_name']) ?> <span>(<?= number_format((int) $submission['file_size'] / 1024) ?> KB)</span> · Download</a><?php endif; ?>
        <form class="review-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_submission"><input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>"><div class="form-grid"><div><label for="review-status-<?= (int) $submission['id'] ?>">Review status</label><select id="review-status-<?= (int) $submission['id'] ?>" name="review_status"><?php foreach (['reviewed', 'approved', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= $submission['review_status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div><div><label for="marks-<?= (int) $submission['id'] ?>">Marks <span>(optional, out of 100)</span></label><input id="marks-<?= (int) $submission['id'] ?>" name="marks" type="number" min="0" max="100" step="0.5" value="<?= $submission['marks'] !== null ? e((string) $submission['marks']) : '' ?>"></div><div class="form-span"><label for="feedback-<?= (int) $submission['id'] ?>">Feedback for student</label><textarea id="feedback-<?= (int) $submission['id'] ?>" name="feedback" rows="3" maxlength="5000"><?= e($submission['feedback']) ?></textarea></div></div><button class="button button-small" type="submit">Save review <span aria-hidden="true">↗</span></button></form>
      </article><?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="portal-section notifications-section" id="notifications">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Recent updates</div><h2>Notifications <?= $unreadCount ? '<span class="unread-count">' . $unreadCount . ' new</span>' : '' ?></h2></div><?php if ($unreadCount): ?><form method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_notifications_read"><button class="text-button" type="submit">Mark all read</button></form><?php endif; ?></div>
      <?php if (!$notifications): ?><div class="empty-state small-empty"><h3>You’re all caught up.</h3><p>Application and submission activity will appear here.</p></div><?php else: ?><div class="notification-list"><?php foreach ($notifications as $notification): ?><a class="notification-row <?= !$notification['is_read'] ? 'notification-unread' : '' ?>" href="<?= e($notification['link']) ?>"><span class="notification-dot"></span><span><?= e($notification['message']) ?><small><?= e(date('M j · g:i a', strtotime($notification['created_at']))) ?></small></span><b>↗</b></a><?php endforeach; ?></div><?php endif; ?>
    </section>
  </main>
  <footer class="site-footer section-wrap"><a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a><span>Administration · InternTrack</span><a href="#overview">Back to top ↑</a></footer>
</body>
</html>
