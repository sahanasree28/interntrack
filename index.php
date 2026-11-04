<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
$user = null;
try {
    $user = current_user();
} catch (PDOException $exception) {
    error_log('InternTrack landing page database error: ' . $exception->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f6f7f4">
  <meta name="description" content="Find internships, complete meaningful work, and track your career progress with InternTrack.">
  <title>InternTrack — Manage Your Internship. Build Your Future.</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="site-header">
    <a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu</button>
    <nav id="main-nav" class="main-nav" aria-label="Main navigation">
      <a href="#home">Home</a><a href="#features">Features</a><a href="#about">About</a>
      <?php if ($user): ?>
        <a class="nav-login" href="<?= $user['role'] === 'admin' ? 'admin.php' : 'dashboard.php' ?>">Dashboard</a>
      <?php else: ?>
        <a class="nav-login" href="auth.php">Login</a>
        <a class="button button-small" href="auth.php?mode=register">Get started <span aria-hidden="true">↗</span></a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <section class="hero section-wrap" id="home">
      <div class="hero-copy">
        <div class="eyebrow"><span class="eyebrow-dot"></span> Your next chapter starts here</div>
        <h1>Manage Your Internship.<br><span>Build Your Future.</span></h1>
        <p>One place to find opportunities, stay on top of every task, and turn your internship into real-world momentum.</p>
        <div class="hero-actions">
          <a class="button" href="<?= $user ? ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php') : 'auth.php?mode=register' ?>">Start your journey <span aria-hidden="true">↗</span></a>
          <a class="text-link" href="#features">Explore the platform <span aria-hidden="true">↓</span></a>
        </div>
        <div class="hero-proof"><div class="avatar-stack" aria-hidden="true"><i>J</i><i>A</i><i>M</i><i>+</i></div><span>Built for students ready to grow</span></div>
      </div>
      <div class="hero-visual" aria-label="Preview of the student dashboard">
        <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
        <div class="dashboard-preview">
          <div class="preview-top"><div><span class="preview-label">YOUR SPACE</span><strong>Good morning, Alex <span>✦</span></strong></div><span class="preview-bell">♧</span></div>
          <div class="preview-metrics">
            <div class="metric-card"><span>Tasks completed</span><strong>8 <small>/ 12</small></strong><div class="mini-track"><i></i></div></div>
            <div class="metric-card metric-green"><span>On-time streak</span><strong>5 <small>days</small></strong><span class="metric-note">You're building great habits</span></div>
          </div>
          <div class="preview-task-heading"><strong>Up next</strong><span>View all <b>↗</b></span></div>
          <div class="preview-task"><span class="task-icon icon-lilac">✳</span><div><strong>Competitor landscape</strong><small>Product design · Due tomorrow</small></div><span class="task-arrow">↗</span></div>
          <div class="preview-task"><span class="task-icon icon-peach">◈</span><div><strong>Weekly reflection</strong><small>Growth journal · Due Friday</small></div><span class="task-arrow">↗</span></div>
          <div class="preview-foot"><span class="pulse-dot"></span> You're right on track <span class="foot-spark">✦</span></div>
        </div>
        <div class="floating-note"><span class="note-check">✓</span><span><b>One step at a time</b><small>Your progress adds up.</small></span></div>
      </div>
    </section>

    <section class="trust-strip" id="about">
      <div class="section-wrap trust-inner"><span>From first application to final review</span><strong>Opportunity <b>→</b> Experience <b>→</b> Growth</strong></div>
    </section>

    <section class="features section-wrap" id="features">
      <div class="section-heading">
        <div><div class="eyebrow"><span class="eyebrow-dot"></span> Everything in one place</div><h2>Make your internship<br>count for more.</h2></div>
        <p>Less chasing updates. More time doing the work that moves you forward.</p>
      </div>
      <div class="feature-grid">
        <article class="feature-card"><span class="feature-icon feature-lilac">⌕</span><span class="feature-number">01</span><h3>Find your fit</h3><p>Explore opportunities curated for your next step and apply with confidence.</p><a href="auth.php?mode=register" aria-label="Explore opportunities">Explore opportunities <b>↗</b></a></article>
        <article class="feature-card featured-card"><span class="feature-icon feature-green">✓</span><span class="feature-number">02</span><h3>Keep your momentum</h3><p>Know what is next, when it is due, and how far you have come.</p><a href="auth.php?mode=register" aria-label="Track your progress">Track your progress <b>↗</b></a></article>
        <article class="feature-card"><span class="feature-icon feature-peach">✳</span><span class="feature-number">03</span><h3>Grow with feedback</h3><p>Submit your work, get thoughtful feedback, and see your progress take shape.</p><a href="auth.php?mode=register" aria-label="Build your experience">Build your experience <b>↗</b></a></article>
      </div>
    </section>

    <section class="closing-cta section-wrap">
      <div><div class="eyebrow"><span class="eyebrow-dot"></span> Your future is a work in progress</div><h2>Start where you are.<br><span>Go further from here.</span></h2></div>
      <a class="button button-light" href="<?= $user ? ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php') : 'auth.php?mode=register' ?>">Get started <span aria-hidden="true">↗</span></a>
      <span class="cta-decoration" aria-hidden="true">✳</span>
    </section>
  </main>
  <footer class="site-footer section-wrap"><a class="brand" href="index.php"><span class="brand-mark">i</span> InternTrack</a><span>Make every opportunity count.</span><a href="auth.php?mode=admin">Administrator access</a></footer>
  <script src="assets/app.js" defer></script>
</body>
</html>
