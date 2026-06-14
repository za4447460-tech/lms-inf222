<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'promoteur') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

// --- Statistiques globales ---
$totalModules = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'etudiant'")->fetchColumn();
$totalCerts = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// --- Derniers modules créés ---
$stmt = $pdo->query("
    SELECT m.*,
        (SELECT COUNT(*) FROM courses c WHERE c.module_id = m.id) AS nb_courses,
        (SELECT COUNT(*) FROM enrollments e WHERE e.module_id = m.id) AS nb_etudiants
    FROM modules m
    ORDER BY m.created_at DESC
    LIMIT 5
");
$recentModules = $stmt->fetchAll();

$pageTitle    = "Tableau de bord";
$pageSubtitle = "Bienvenue, " . htmlspecialchars($_SESSION['prenom']) . " 👋";
$activeMenu   = "dashboard";

require __DIR__ . '/../layout/header.php';
?>

  <div class="grid grid-4" style="margin-bottom: 1.5rem;">
    <div class="card stat-card">
      <div class="stat-icon icon-bg-pulse">📦</div>
      <div class="stat-value"><?= $totalModules ?></div>
      <div class="stat-label">Modules</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-mint">📚</div>
      <div class="stat-value"><?= $totalCourses ?></div>
      <div class="stat-label">Cours</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-warn">👥</div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-label">Étudiants</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-pulse">🏆</div>
      <div class="stat-value"><?= $totalCerts ?></div>
      <div class="stat-label">Certificats émis</div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
      <h2 style="font-size:1.1rem; font-weight:700;">Modules récents</h2>
      <a href="/lms-inf222/views/promoteur/modules.php" class="btn btn-primary">+ Nouveau module</a>
    </div>

    <?php if (empty($recentModules)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Aucun module créé pour le moment.</p>
        <a href="/lms-inf222/views/promoteur/modules.php" class="btn btn-primary" style="margin-top:1rem;">Créer le premier module</a>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Module</th>
            <th>Cours</th>
            <th>Étudiants inscrits</th>
            <th>Créé le</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentModules as $m): ?>
            <tr>
              <td><strong><?= htmlspecialchars($m['titre']) ?></strong></td>
              <td><span class="badge badge-pulse"><?= $m['nb_courses'] ?> cours</span></td>
              <td><?= $m['nb_etudiants'] ?></td>
              <td><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/../layout/footer.php'; ?>