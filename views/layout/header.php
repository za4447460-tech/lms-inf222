<?php
/**
 * Layout commun : sidebar + ouverture du <body>
 * Variables attendues (définies dans la page qui inclut ce fichier) :
 *   $pageTitle    -> titre affiché dans la topbar (optionnel)
 *   $pageSubtitle -> sous-titre affiché dans la topbar (optionnel)
 *   $activeMenu   -> clé du menu actif, ex: 'dashboard', 'cours', etc.
 *
 * Ce fichier suppose que session_start() et la vérification
 * d'authentification ont déjà été faites dans la page appelante.
 */

if (!isset($_SESSION['user_id'])) {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$role     = $_SESSION['role'];
$prenom   = $_SESSION['prenom'];
$nom      = $_SESSION['nom'];
$initials = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

// Définition des menus par rôle
$menus = [
    'etudiant' => [
        'dashboard'   => ['icon' => '🏠', 'label' => 'Tableau de bord', 'href' => '/lms-inf222/views/etudiant/dashboard.php'],
        'modules'     => ['icon' => '📦', 'label' => 'Mes modules',     'href' => '/lms-inf222/views/etudiant/modules.php'],
        'lessons'     => ['icon' => '🎬', 'label' => 'Leçons',          'href' => '/lms-inf222/views/etudiant/lessons.php'],
        'evaluations' => ['icon' => '📝', 'label' => 'Évaluations',     'href' => '/lms-inf222/views/etudiant/evaluations.php'],
        'progress'    => ['icon' => '📊', 'label' => 'Ma progression',  'href' => '/lms-inf222/views/etudiant/progress.php'],
        'certificates'=> ['icon' => '🏆', 'label' => 'Certificats',     'href' => '/lms-inf222/views/etudiant/certificates.php'],
    ],
    'enseignant' => [
        'dashboard'   => ['icon' => '🏠', 'label' => 'Tableau de bord', 'href' => '/lms-inf222/views/enseignant/dashboard.php'],
        'courses'     => ['icon' => '📚', 'label' => 'Mes cours',       'href' => '/lms-inf222/views/enseignant/courses.php'],
        'lessons'     => ['icon' => '🎬', 'label' => 'Leçons',          'href' => '/lms-inf222/views/enseignant/lessons.php'],
        'evaluations' => ['icon' => '📝', 'label' => 'Évaluations',     'href' => '/lms-inf222/views/enseignant/evaluations.php'],
        'students'    => ['icon' => '👥', 'label' => 'Étudiants',       'href' => '/lms-inf222/views/enseignant/students.php'],
    ],
    'promoteur' => [
        'dashboard'   => ['icon' => '🏠', 'label' => 'Tableau de bord', 'href' => '/lms-inf222/views/promoteur/dashboard.php'],
        'modules'     => ['icon' => '📦', 'label' => 'Modules',         'href' => '/lms-inf222/views/promoteur/modules.php'],
        'teachers'    => ['icon' => '📚', 'label' => 'Enseignants',     'href' => '/lms-inf222/views/promoteur/teachers.php'],
        'students'    => ['icon' => '👥', 'label' => 'Étudiants',       'href' => '/lms-inf222/views/promoteur/students.php'],
        'certificates'=> ['icon' => '🏆', 'label' => 'Certificats',     'href' => '/lms-inf222/views/promoteur/certificates.php'],
    ],
];

$currentMenu = $menus[$role] ?? [];
$activeMenu = $activeMenu ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>EduPulse INF222</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/lms-inf222/assets/css/style.css"/>
</head>
<body>

<!-- ============ SIDEBAR ============ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">⚡</div>
    <div>
      <div class="brand-name">EduPulse</div>
      <div class="brand-sub">INF222</div>
    </div>
  </div>

  <div class="sidebar-section-label">Navigation</div>
  <nav class="sidebar-nav">
    <?php foreach ($currentMenu as $key => $item): ?>
      <a href="<?= $item['href'] ?>" class="<?= $activeMenu === $key ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-spacer"></div>

  <div class="sidebar-user">
    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($prenom . ' ' . $nom) ?></div>
      <div class="user-role"><?= htmlspecialchars($role) ?></div>
    </div>
  </div>
  <a href="/lms-inf222/auth/logout.php" class="sidebar-logout">
    <span class="nav-icon">🚪</span> Déconnexion
  </a>
</aside>

<!-- ============ MAIN CONTENT ============ -->
<div class="main-content">
  <?php if (isset($pageTitle)): ?>
  <header class="topbar">
    <div>
      <h1><?= htmlspecialchars($pageTitle) ?></h1>
      <?php if (isset($pageSubtitle)): ?>
        <div class="topbar-sub"><?= htmlspecialchars($pageSubtitle) ?></div>
      <?php endif; ?>
    </div>
    <div class="topbar-actions">
      <?php if (isset($topbarPill)): ?>
        <span class="topbar-pill"><?= $topbarPill ?></span>
      <?php endif; ?>
    </div>
  </header>
  <?php endif; ?>

  <div class="page-body">