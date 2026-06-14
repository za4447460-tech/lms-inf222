<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$enseignant_id = $_SESSION['user_id'];

// --- Statistiques de l'enseignant ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE enseignant_id = ?");
$stmt->execute([$enseignant_id]);
$totalCourses = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lessons l
    INNER JOIN courses c ON c.id = l.course_id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$enseignant_id]);
$totalLessons = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM evaluations ev
    INNER JOIN lessons l ON l.id = ev.lesson_id
    INNER JOIN courses c ON c.id = l.course_id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$enseignant_id]);
$totalEvals = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.etudiant_id) FROM enrollments e
    INNER JOIN courses c ON c.module_id = e.module_id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$enseignant_id]);
$totalStudents = $stmt->fetchColumn();

// --- Mes cours récents ---
$stmt = $pdo->prepare("
    SELECT c.*, m.titre AS module_titre,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS nb_lecons
    FROM courses c
    INNER JOIN modules m ON m.id = c.module_id
    WHERE c.enseignant_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$enseignant_id]);
$recentCourses = $stmt->fetchAll();

$pageTitle    = "Tableau de bord";
$pageSubtitle = "Bienvenue, " . htmlspecialchars($_SESSION['prenom']) . " 👋";
$activeMenu   = "dashboard";

require __DIR__ . '/../layout/header.php';
?>

  <div class="grid grid-4" style="margin-bottom: 1.5rem;">
    <div class="card stat-card">
      <div class="stat-icon icon-bg-pulse">📚</div>
      <div class="stat-value"><?= $totalCourses ?></div>
      <div class="stat-label">Mes cours</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-mint">🎬</div>
      <div class="stat-value"><?= $totalLessons ?></div>
      <div class="stat-label">Leçons publiées</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-warn">📝</div>
      <div class="stat-value"><?= $totalEvals ?></div>
      <div class="stat-label">Évaluations</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-pulse">👥</div>
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-label">Étudiants touchés</div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
      <h2 style="font-size:1.1rem; font-weight:700;">Mes cours récents</h2>
      <a href="/lms-inf222/views/enseignant/courses.php" class="btn btn-primary">+ Nouveau cours</a>
    </div>

    <?php if (empty($recentCourses)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Vous n'avez créé aucun cours pour le moment.</p>
        <a href="/lms-inf222/views/enseignant/courses.php" class="btn btn-primary" style="margin-top:1rem;">Créer mon premier cours</a>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Cours</th>
            <th>Module</th>
            <th>Leçons</th>
            <th>Créé le</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentCourses as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['titre']) ?></strong></td>
              <td><span class="badge badge-pulse"><?= htmlspecialchars($c['module_titre']) ?></span></td>
              <td><?= $c['nb_lecons'] ?></td>
              <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
              <td><a href="/lms-inf222/views/enseignant/lessons.php?course=<?= $c['id'] ?>" style="color:var(--pulse); font-weight:600; text-decoration:none; font-size:.82rem;">Gérer →</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/../layout/footer.php'; ?>