<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$etudiant_id = $_SESSION['user_id'];
$module_id = (int)($_GET['module'] ?? 0);

// ===== Vérifier l'inscription au module =====
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE etudiant_id = ? AND module_id = ?");
$stmt->execute([$etudiant_id, $module_id]);
if (!$stmt->fetch()) {
    header("Location: modules.php");
    exit;
}

// ===== Infos du module =====
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$stmt->execute([$module_id]);
$module = $stmt->fetch();
if (!$module) {
    header("Location: modules.php");
    exit;
}

// ===== Toutes les leçons du module (via ses cours), avec progression et statut évaluation =====
$stmt = $pdo->prepare("
    SELECT l.*, c.titre AS course_titre,
        (SELECT id FROM evaluations ev WHERE ev.lesson_id = l.id) AS evaluation_id,
        (SELECT COUNT(*) FROM questions q WHERE q.evaluation_id = (SELECT id FROM evaluations ev WHERE ev.lesson_id = l.id)) AS nb_questions,
        p.progression_pct, p.completed,
        (SELECT score FROM attempts a
            INNER JOIN evaluations ev2 ON ev2.id = a.evaluation_id
            WHERE ev2.lesson_id = l.id AND a.etudiant_id = ?
            ORDER BY a.completed_at DESC LIMIT 1) AS last_score
    FROM lessons l
    INNER JOIN courses c ON c.id = l.course_id
    LEFT JOIN progress p ON p.lesson_id = l.id AND p.etudiant_id = ?
    WHERE c.module_id = ?
    ORDER BY c.id ASC, l.ordre ASC, l.created_at ASC
");
$stmt->execute([$etudiant_id, $etudiant_id, $module_id]);
$lessons = $stmt->fetchAll();

// ===== Progression globale du module =====
$totalLessons = count($lessons);
$sumProgress = 0;
foreach ($lessons as $l) {
    $sumProgress += $l['progression_pct'] ? (float)$l['progression_pct'] : 0;
}
$moduleProgress = $totalLessons > 0 ? round($sumProgress / $totalLessons, 1) : 0;

$pageTitle    = $module['titre'];
$pageSubtitle = count($lessons) . " leçon(s) — Progression : " . $moduleProgress . "%";
$activeMenu   = "modules";
$topbarPill   = $moduleProgress >= 100 ? "🏆 Module validé !" : "📊 " . $moduleProgress . "% complété";

require __DIR__ . '/../layout/header.php';
?>

  <a href="modules.php" style="display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted); text-decoration:none; margin-bottom:1.25rem;">← Retour aux modules</a>

  <!-- ===== BARRE DE PROGRESSION GLOBALE ===== -->
  <div class="card" style="margin-bottom: 1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem;">
      <h2 style="font-size:1.1rem; font-weight:700;">Progression du module</h2>
      <span style="font-size:1.3rem; font-weight:700; color:var(--pulse);"><?= $moduleProgress ?>%</span>
    </div>
    <div class="progress-bar">
      <div class="progress-bar-fill" style="width: <?= $moduleProgress ?>%;"></div>
    </div>
    <?php if ($moduleProgress >= 100): ?>
      <p style="font-size:.85rem; color:#047857; font-weight:600; margin-top:.75rem;">
        🎉 Félicitations ! Vous avez validé ce module.
        <a href="certificates.php" style="color:var(--pulse); text-decoration:none;">Voir mon certificat →</a>
      </p>
    <?php endif; ?>
  </div>

  <?php if (empty($lessons)): ?>
    <div class="card" style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
      <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
      <p>Ce module ne contient aucune leçon pour le moment.</p>
    </div>
  <?php else: ?>
    <?php foreach ($lessons as $i => $l):
      $progress = $l['progression_pct'] ? (float)$l['progression_pct'] : 0;
      $hasEval = $l['evaluation_id'] && $l['nb_questions'] > 0;
    ?>
      <div class="card" style="margin-bottom: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:start; gap:1rem;">
          <div style="display:flex; gap:.85rem; align-items:start; flex:1;">
            <div style="width:36px; height:36px; border-radius:10px; background:var(--pulse-bg); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
              <?= $l['type'] === 'pdf' ? '📄' : '🎬' ?>
            </div>
            <div style="flex:1;">
              <h3 style="font-size:1rem; font-weight:700; margin-bottom:.2rem;">Leçon <?= $i + 1 ?> — <?= htmlspecialchars($l['titre']) ?></h3>
              <p style="font-size:.78rem; color:var(--muted); margin-bottom:.6rem;"><?= htmlspecialchars($l['course_titre']) ?></p>

              <!-- Contenu PDF ou vidéo -->
              <?php if ($l['type'] === 'pdf' && $l['fichier_path']): ?>
                <a href="/lms-inf222/<?= htmlspecialchars($l['fichier_path']) ?>" target="_blank" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem; margin-bottom:.5rem;">
                  📄 Ouvrir le document PDF
                </a>
              <?php elseif ($l['type'] === 'video' && $l['video_url']): ?>
                <a href="<?= htmlspecialchars($l['video_url']) ?>" target="_blank" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem; margin-bottom:.5rem;">
                  🎬 Regarder la vidéo
                </a>
              <?php endif; ?>

              <div class="progress-bar" style="margin-top:.5rem; margin-bottom:.35rem;">
                <div class="progress-bar-fill" style="width: <?= $progress ?>%;"></div>
              </div>
              <p style="font-size:.75rem; color:var(--muted);">
                Progression : <?= $progress ?>%
                <?php if ($l['last_score'] !== null): ?>
                  · Dernier score : <strong><?= $l['last_score'] ?>%</strong>
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div style="text-align:right; flex-shrink:0;">
            <?php if ($progress >= 100): ?>
              <span class="badge badge-success">✓ Terminée</span>
            <?php elseif (!$hasEval): ?>
              <span class="badge badge-pending">Pas d'évaluation</span>
            <?php else: ?>
              <span class="badge badge-pulse">À faire</span>
            <?php endif; ?>

            <div style="margin-top:.6rem;">
              <?php if ($hasEval): ?>
                <a href="evaluations.php?lesson=<?= $l['id'] ?>&module=<?= $module_id ?>" class="btn btn-primary" style="padding:.5rem 1rem; font-size:.8rem;">
                  📝 <?= $progress >= 100 ? 'Repasser le quiz' : 'Passer le quiz' ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>