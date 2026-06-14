<?php
session_start();
require __DIR__ . '/../../config/db.php';

// Sécurité : seul un étudiant peut voir cette page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$etudiant_id = $_SESSION['user_id'];

// --- Modules dans lesquels l'étudiant est inscrit ---
$stmt = $pdo->prepare("
    SELECT m.id, m.titre, m.description
    FROM modules m
    INNER JOIN enrollments e ON e.module_id = m.id
    WHERE e.etudiant_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$etudiant_id]);
$modules = $stmt->fetchAll();

// --- Pour chaque module, calculer la progression moyenne ---
foreach ($modules as &$module) {
    $stmt = $pdo->prepare("
        SELECT l.id
        FROM lessons l
        INNER JOIN courses c ON c.id = l.course_id
        WHERE c.module_id = ?
    ");
    $stmt->execute([$module['id']]);
    $lessons = $stmt->fetchAll();

    $totalLessons = count($lessons);
    $sumProgress  = 0;

    if ($totalLessons > 0) {
        foreach ($lessons as $lesson) {
            $stmt2 = $pdo->prepare("
                SELECT progression_pct FROM progress
                WHERE etudiant_id = ? AND lesson_id = ?
            ");
            $stmt2->execute([$etudiant_id, $lesson['id']]);
            $p = $stmt2->fetch();
            $sumProgress += $p ? (float)$p['progression_pct'] : 0;
        }
        $module['progress'] = round($sumProgress / $totalLessons, 1);
    } else {
        $module['progress'] = 0;
    }
    $module['total_lessons'] = $totalLessons;
}
unset($module);

// --- Statistiques globales ---
$totalModules = count($modules);
$avgProgress  = $totalModules > 0
    ? round(array_sum(array_column($modules, 'progress')) / $totalModules, 1)
    : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM certificates WHERE etudiant_id = ?");
$stmt->execute([$etudiant_id]);
$certCount = $stmt->fetch()['cnt'];

// --- Dernières évaluations passées ---
$stmt = $pdo->prepare("
    SELECT a.score, a.completed_at, ev.titre AS eval_titre, l.titre AS lesson_titre
    FROM attempts a
    INNER JOIN evaluations ev ON ev.id = a.evaluation_id
    INNER JOIN lessons l ON l.id = ev.lesson_id
    WHERE a.etudiant_id = ?
    ORDER BY a.completed_at DESC
    LIMIT 5
");
$stmt->execute([$etudiant_id]);
$recentAttempts = $stmt->fetchAll();

// --- Variables pour le layout ---
$pageTitle    = "Tableau de bord";
$pageSubtitle = "Bienvenue, " . htmlspecialchars($_SESSION['prenom']) . " 👋";
$activeMenu   = "dashboard";
$topbarPill   = "🔥 " . $avgProgress . "% de progression globale";

require __DIR__ . '/../layout/header.php';
?>

  <!-- ===== STAT CARDS ===== -->
  <div class="grid grid-3" style="margin-bottom: 1.5rem;">
    <div class="card stat-card">
      <div class="stat-icon icon-bg-pulse">📦</div>
      <div class="stat-value"><?= $totalModules ?></div>
      <div class="stat-label">Modules inscrits</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-mint">📊</div>
      <div class="stat-value"><?= $avgProgress ?>%</div>
      <div class="stat-label">Progression moyenne</div>
    </div>
    <div class="card stat-card">
      <div class="stat-icon icon-bg-warn">🏆</div>
      <div class="stat-value"><?= $certCount ?></div>
      <div class="stat-label">Certificats obtenus</div>
    </div>
  </div>

  <!-- ===== MES MODULES ===== -->
  <div class="card" style="margin-bottom: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
      <h2 style="font-size:1.1rem; font-weight:700;">Mes modules</h2>
      <a href="/lms-inf222/views/etudiant/modules.php" class="btn btn-outline">Voir tout</a>
    </div>

    <?php if (empty($modules)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Vous n'êtes inscrit à aucun module pour le moment.</p>
        <a href="/lms-inf222/views/etudiant/modules.php" class="btn btn-primary" style="margin-top:1rem;">Parcourir les modules</a>
      </div>
    <?php else: ?>
      <div class="grid grid-2">
        <?php foreach ($modules as $m): ?>
          <div class="card" style="border: 1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:.75rem;">
              <div>
                <h3 style="font-size:1rem; font-weight:700; margin-bottom:.25rem;"><?= htmlspecialchars($m['titre']) ?></h3>
                <p style="font-size:.8rem; color:var(--muted);"><?= $m['total_lessons'] ?> leçon(s)</p>
              </div>
              <?php if ($m['progress'] >= 100): ?>
                <span class="badge badge-success">✓ Validé</span>
              <?php elseif ($m['progress'] > 0): ?>
                <span class="badge badge-pending">En cours</span>
              <?php else: ?>
                <span class="badge badge-pulse">Non commencé</span>
              <?php endif; ?>
            </div>
            <div class="progress-bar" style="margin-bottom:.4rem;">
              <div class="progress-bar-fill" style="width: <?= $m['progress'] ?>%;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:.78rem; color:var(--muted);">
              <span><?= $m['progress'] ?>% complété</span>
              <a href="/lms-inf222/views/etudiant/lessons.php?module=<?= $m['id'] ?>" style="color:var(--pulse); font-weight:600; text-decoration:none;">Continuer →</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== DERNIÈRES ÉVALUATIONS ===== -->
  <div class="card">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1rem;">Dernières évaluations</h2>

    <?php if (empty($recentAttempts)): ?>
      <div style="text-align:center; padding: 2rem 1rem; color: var(--muted);">
        <div style="font-size: 2rem; margin-bottom: .5rem;">📝</div>
        <p>Vous n'avez pas encore passé d'évaluation.</p>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Leçon</th>
            <th>Évaluation</th>
            <th>Score</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentAttempts as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['lesson_titre']) ?></td>
              <td><?= htmlspecialchars($a['eval_titre']) ?></td>
              <td>
                <?php if ($a['score'] >= 50): ?>
                  <span class="badge badge-success"><?= $a['score'] ?>%</span>
                <?php else: ?>
                  <span class="badge" style="background:#FEF2F2; color:var(--danger);"><?= $a['score'] ?>%</span>
                <?php endif; ?>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($a['completed_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/../layout/footer.php'; ?>