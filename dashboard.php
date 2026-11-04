<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$user = require_role('student');
$pdo = db();

$statsQuery = $pdo->prepare(
    "SELECT COUNT(*) AS total,
        SUM(status = 'completed') AS completed,
        SUM(status <> 'completed') AS pending,
        SUM(status <> 'completed' AND due_date < CURRENT_DATE) AS overdue
     FROM tasks WHERE student_id = ?"
);
$statsQuery->execute([(int) $user['id']]);
$stats = $statsQuery->fetch() ?: ['total' => 0, 'completed' => 0, 'pending' => 0, 'overdue' => 0];
$stats = array_map(static fn($v): int => (int) ($v ?? 0), $stats);
$completion = $stats['total'] > 0 ? (int) round($stats['completed'] / $stats['total'] * 100) : 0;

$opportunitiesQuery = $pdo->prepare(
    "SELECT i.*, a.id AS application_id, a.status AS application_status
     FROM internships i
     LEFT JOIN applications a ON a.internship_id = i.id AND a.student_id = ?
     WHERE i.status = 'published'
     ORDER BY i.created_at DESC"
);
$opportunitiesQuery->execute([(int) $user['id']]);
$opportunities = $opportunitiesQuery->fetchAll();

$tasksQuery = $pdo->prepare(
    "SELECT t.*, i.title AS internship_title, s.id AS submission_id, s.review_status,
            s.response AS submission_response, s.feedback, s.marks, s.file_name, s.submitted_at
     FROM tasks t
     LEFT JOIN internships i ON i.id = t.internship_id
     LEFT JOIN submissions s ON s.id = (
       SELECT MAX(s2.id) FROM submissions s2 WHERE s2.task_id = t.id AND s2.student_id = t.student_id
     )
     WHERE t.student_id = ?
     ORDER BY (t.status = 'completed') ASC, t.due_date ASC, t.id DESC"
);
$tasksQuery->execute([(int) $user['id']]);
$tasks = $tasksQuery->fetchAll();
$applicationsQuery = $pdo->prepare(
    'SELECT a.*, i.title, i.organization FROM applications a JOIN internships i ON i.id = a.internship_id WHERE a.student_id = ? ORDER BY a.created_at DESC'
);
$applicationsQuery->execute([(int) $user['id']]);
$applications = $applicationsQuery->fetchAll();
$notificationQuery = $pdo->prepare('SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
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
  <title>My dashboard — InternTrack</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="portal-page">
  <header class="portal-header">
    <a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a>
    <nav class="portal-nav" aria-label="Dashboard navigation"><a href="#overview">Overview</a><a href="#opportunities">Opportunities</a><a href="#tasks">My tasks</a></nav>
    <div class="portal-user"><span class="user-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span><span><?= e($user['full_name']) ?></span>
      <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="logout-link" type="submit">Log out</button></form>
    </div>
  </header>
  <main class="portal-main">
    <?php if ($flashMessage): ?><div class="notice notice-<?= e($flashMessage['kind']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
    <section class="portal-welcome" id="overview">
      <div><div class="eyebrow"><span class="eyebrow-dot"></span> Student workspace</div><h1>You’re building something,<br><span><?= e(explode(' ', trim($user['full_name']))[0]) ?>.</span></h1><p>Keep your momentum. Your next opportunity is closer than you think.</p></div>
      <a class="button" href="#opportunities">Explore opportunities <span aria-hidden="true">↗</span></a>
    </section>

    <section class="stats-grid" aria-label="Task statistics">
      <article class="stat-card stat-progress"><div class="stat-top"><span>Overall progress</span><span class="stat-icon">↗</span></div><strong><?= $completion ?><small>%</small></strong><div class="progress-track"><span style="width: <?= $completion ?>%"></span></div><small><?= $stats['completed'] ?> of <?= $stats['total'] ?> tasks complete</small></article>
      <article class="stat-card"><div class="stat-top"><span>Total tasks</span><span class="stat-icon">▤</span></div><strong><?= $stats['total'] ?></strong><small>Assigned to you</small></article>
      <article class="stat-card"><div class="stat-top"><span>Pending tasks</span><span class="stat-icon icon-lilac">↗</span></div><strong><?= $stats['pending'] ?></strong><small>Not yet completed</small></article>
      <article class="stat-card stat-overdue"><div class="stat-top"><span>Overdue</span><span class="stat-icon icon-peach">!</span></div><strong><?= $stats['overdue'] ?></strong><small>Tasks past their deadline</small></article>
    </section>

    <section class="portal-section" id="opportunities">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Find your next step</div><h2>Open opportunities</h2></div><span class="section-count"><?= count($opportunities) ?> available</span></div>
      <?php if (!$opportunities): ?><div class="empty-state"><span class="empty-icon">✳</span><h3>New opportunities are on the way.</h3><p>Check back soon — your next great experience could be just around the corner.</p></div>
      <?php else: ?><div class="opportunity-grid">
        <?php foreach ($opportunities as $opportunity): ?>
        <article class="opportunity-card">
          <div class="opportunity-top"><span class="org-mark"><?= e(strtoupper(substr($opportunity['organization'], 0, 1))) ?></span><span class="tag"><?= e($opportunity['internship_type']) ?></span></div>
          <h3><?= e($opportunity['title']) ?></h3><p class="org-name"><?= e($opportunity['organization']) ?> <span>·</span> <?= e($opportunity['location']) ?></p>
          <p><?= e($opportunity['description']) ?></p>
          <?php if ($opportunity['requirements']): ?><p class="requirement-line"><b>Looking for:</b> <?= e($opportunity['requirements']) ?></p><?php endif; ?>
          <?php if ($opportunity['application_id']): ?><span class="status-badge status-<?= e($opportunity['application_status']) ?>"><?= e(ucwords(str_replace('_', ' ', $opportunity['application_status']))) ?></span>
          <?php else: ?><form class="apply-form" method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="internship_id" value="<?= (int) $opportunity['id'] ?>"><label for="cover-note-<?= (int) $opportunity['id'] ?>">A short note <span>(optional)</span></label><textarea id="cover-note-<?= (int) $opportunity['id'] ?>" name="cover_note" rows="2" maxlength="3000" placeholder="Why does this opportunity interest you?"></textarea><button class="button button-outline" type="submit">Apply now <span aria-hidden="true">↗</span></button></form><?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </section>

    <section class="portal-section" id="tasks">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Stay on track</div><h2>Your tasks</h2></div><span class="section-count"><?= count($tasks) ?> assigned</span></div>
      <?php if (!$tasks): ?><div class="empty-state"><span class="empty-icon">✓</span><h3>Your task list is clear.</h3><p>When an administrator assigns work, it will show up here.</p></div>
      <?php else: ?><div class="task-list">
        <?php foreach ($tasks as $task): $overdue = $task['status'] !== 'completed' && $task['due_date'] < date('Y-m-d'); ?>
        <article class="task-card">
          <div class="task-card-main"><span class="task-symbol <?= $task['status'] === 'completed' ? 'symbol-done' : ($overdue ? 'symbol-late' : '') ?>"><?= $task['status'] === 'completed' ? '✓' : '✳' ?></span><div class="task-copy"><div class="task-title-line"><h3><?= e($task['title']) ?></h3><span class="status-badge <?= $overdue ? 'status-overdue' : 'status-' . e($task['status']) ?>"><?= $overdue ? 'Overdue' : e(ucwords(str_replace('_', ' ', $task['status']))) ?></span></div><p><?= e($task['description']) ?></p><div class="task-meta"><span>Due <?= e(date('M j, Y', strtotime($task['due_date']))) ?></span><?php if ($task['internship_title']): ?><span><?= e($task['internship_title']) ?></span><?php endif; ?></div>
          <?php if ($task['submission_id']): ?><div class="submission-summary"><span class="status-badge status-<?= e($task['review_status']) ?>"><?= e(ucwords($task['review_status'])) ?></span><?php if ($task['marks'] !== null): ?><span class="mark-chip"><?= e((string) $task['marks']) ?>/100</span><?php endif; ?><?php if ($task['feedback']): ?><p><b>Administrator feedback:</b> <?= e($task['feedback']) ?></p><?php endif; ?><?php if ($task['file_name']): ?><a href="download.php?id=<?= (int) $task['submission_id'] ?>">Download your attachment</a><?php endif; ?></div><?php endif; ?>
          <?php if ($task['status'] !== 'completed'): ?>
            <?php if (!$task['submission_id'] || $task['review_status'] === 'rejected'): ?><details class="submit-details" <?= $task['review_status'] === 'rejected' ? 'open' : '' ?>><summary><?= $task['review_status'] === 'rejected' ? 'Revise and resubmit' : 'Submit your work' ?></summary><form class="stack-form compact-form" method="post" action="action.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_task"><input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>"><label for="response-<?= (int) $task['id'] ?>">Your work or response</label><textarea id="response-<?= (int) $task['id'] ?>" name="response" rows="4" maxlength="12000" required placeholder="Write your response or summarize your attached work."><?= $task['review_status'] === 'rejected' ? e($task['submission_response']) : '' ?></textarea><label for="attachment-<?= (int) $task['id'] ?>">Attachment <span>(optional, max 5 MB; PDF, DOC/DOCX, TXT, PNG or JPG)</span></label><input id="attachment-<?= (int) $task['id'] ?>" type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg"><button class="button button-small" type="submit">Send for review <span aria-hidden="true">↗</span></button></form></details>
            <?php elseif (in_array($task['review_status'], ['submitted', 'reviewed'], true)): ?><p class="review-waiting">Your latest work is with the administrator for review.</p><?php endif; ?>
          <?php endif; ?>
          </div></div>
        </article>
        <?php endforeach; ?>
      </div><?php endif; ?>
    </section>

    <section class="portal-section" id="applications">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Your next chapter</div><h2>Application status</h2></div><span class="section-count"><?= count($applications) ?> submitted</span></div>
      <?php if (!$applications): ?><div class="empty-state small-empty"><h3>No applications yet.</h3><p>Explore the opportunities above when you are ready.</p></div>
      <?php else: ?><div class="application-list"><?php foreach ($applications as $application): ?><article class="application-row"><div><h3><?= e($application['title']) ?></h3><p><?= e($application['organization']) ?> · Applied <?= e(date('M j, Y', strtotime($application['created_at']))) ?></p></div><span class="status-badge status-<?= e($application['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $application['status']))) ?></span></article><?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="portal-section notifications-section" id="notifications">
      <div class="section-heading compact-heading"><div><div class="eyebrow"><span class="eyebrow-dot"></span> Stay in the loop</div><h2>Notifications <?= $unreadCount ? '<span class="unread-count">' . $unreadCount . ' new</span>' : '' ?></h2></div>
        <?php if ($unreadCount): ?><form method="post" action="action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_notifications_read"><button class="text-button" type="submit">Mark all read</button></form><?php endif; ?>
      </div>
      <?php if (!$notifications): ?><div class="empty-state small-empty"><h3>You’re all caught up.</h3><p>Updates about your applications and submissions will appear here.</p></div>
      <?php else: ?><div class="notification-list"><?php foreach ($notifications as $notification): ?><a class="notification-row <?= !$notification['is_read'] ? 'notification-unread' : '' ?>" href="<?= e($notification['link']) ?>"><span class="notification-dot"></span><span><?= e($notification['message']) ?><small><?= e(date('M j · g:i a', strtotime($notification['created_at']))) ?></small></span><b>↗</b></a><?php endforeach; ?></div><?php endif; ?>
    </section>
  </main>
  <footer class="site-footer section-wrap"><a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a><span>Make every opportunity count.</span><a href="#overview">Back to top ↑</a></footer>
</body>
</html>
