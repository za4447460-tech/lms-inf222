<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$enseignant_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// ===== Récupérer le cours sélectionné =====
$course_id = (int)($_GET['course'] ?? 0);

// Vérifier que ce cours appartient bien à l'enseignant
$stmt = $pdo->prepare("
    SELECT c.*, m.titre AS module_titre
    FROM courses c
    INNER JOIN modules m ON m.id = c.module_id
    WHERE c.id = ? AND c.enseignant_id = ?
");
$stmt->execute([$course_id, $enseignant_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: courses.php");
    exit;
}

// ===== CRÉATION D'UNE LEÇON =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_lesson') {
    $titre = trim($_POST['titre'] ?? '');
    $type  = $_POST['type'] ?? '';
    $ordre = (int)($_POST['ordre'] ?? 0);
    $video_url = trim($_POST['video_url'] ?? '');
    $fichier_path = null;

    if ($titre === '') {
        $errors[] = "Le titre de la leçon est obligatoire.";
    }
    if (!in_array($type, ['pdf', 'video'])) {
        $errors[] = "Type de leçon invalide.";
    }

    // Upload PDF
    if ($type === 'pdf') {
        if (!isset($_FILES['fichier_pdf']) || $_FILES['fichier_pdf']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Veuillez sélectionner un fichier PDF.";
        } else {
            $file = $_FILES['fichier_pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = "Le fichier doit être au format PDF.";
            } elseif ($file['size'] > 20 * 1024 * 1024) { // 20 Mo max
                $errors[] = "Le fichier PDF ne doit pas dépasser 20 Mo.";
            } else {
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newName = 'pdf_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    $fichier_path = 'assets/uploads/' . $newName;
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier.";
                }
            }
        }
    }

    // Vidéo (lien YouTube ou autre)
    if ($type === 'video' && $video_url === '') {
        $errors[] = "Veuillez fournir l'URL de la vidéo.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO lessons (course_id, titre, type, fichier_path, video_url, ordre)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$course_id, $titre, $type, $fichier_path, $type === 'video' ? $video_url : null, $ordre]);
        $success = "Leçon ajoutée avec succès ! Vous pouvez maintenant lui associer une évaluation.";
    }
}

// ===== SUPPRESSION D'UNE LEÇON =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lesson') {
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);
    if ($lesson_id > 0) {
        // Vérifier appartenance
        $stmt = $pdo->prepare("SELECT fichier_path FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([$lesson_id, $course_id]);
        $lesson = $stmt->fetch();
        if ($lesson) {
            // Supprimer le fichier physique si présent
            if ($lesson['fichier_path']) {
                $filePath = __DIR__ . '/../../' . $lesson['fichier_path'];
                if (file_exists($filePath)) unlink($filePath);
            }
            // Les évaluations / questions / choices liées seront supprimées via ON DELETE CASCADE si configuré,
            // sinon on les supprime manuellement ici
            $stmt = $pdo->prepare("SELECT id FROM evaluations WHERE lesson_id = ?");
            $stmt->execute([$lesson_id]);
            foreach ($stmt->fetchAll() as $ev) {
                $stmt2 = $pdo->prepare("SELECT id FROM questions WHERE evaluation_id = ?");
                $stmt2->execute([$ev['id']]);
                foreach ($stmt2->fetchAll() as $q) {
                    $pdo->prepare("DELETE FROM choices WHERE question_id = ?")->execute([$q['id']]);
                }
                $pdo->prepare("DELETE FROM questions WHERE evaluation_id = ?")->execute([$ev['id']]);
                $pdo->prepare("DELETE FROM attempts WHERE evaluation_id = ?")->execute([$ev['id']]);
            }
            $pdo->prepare("DELETE FROM evaluations WHERE lesson_id = ?")->execute([$lesson_id]);
            $pdo->prepare("DELETE FROM progress WHERE lesson_id = ?")->execute([$lesson_id]);
            $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$lesson_id]);
            $success = "Leçon supprimée avec succès.";
        }
    }
}

// ===== LISTE DES LEÇONS DU COURS =====
$stmt = $pdo->prepare("
    SELECT l.*,
        (SELECT id FROM evaluations ev WHERE ev.lesson_id = l.id) AS evaluation_id,
        (SELECT COUNT(*) FROM questions q WHERE q.evaluation_id = (SELECT id FROM evaluations ev WHERE ev.lesson_id = l.id)) AS nb_questions
    FROM lessons l
    WHERE l.course_id = ?
    ORDER BY l.ordre ASC, l.created_at ASC
");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

$pageTitle    = "Leçons — " . $course['titre'];
$pageSubtitle = "Module : " . $course['module_titre'];
$activeMenu   = "courses";

require __DIR__ . '/../layout/header.php';
?>

  <a href="courses.php" style="display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted); text-decoration:none; margin-bottom:1.25rem;">← Retour à mes cours</a>

  <?php if (!empty($errors)): ?>
    <div class="card" style="border-color: #FECACA; background: #FEF2F2; margin-bottom: 1.25rem;">
      <?php foreach ($errors as $err): ?>
        <p style="color: var(--danger); font-size: .85rem;"><?= htmlspecialchars($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="card" style="border-color: #A7F3D0; background: #ECFDF5; margin-bottom: 1.25rem;">
      <p style="color: #047857; font-size: .85rem; font-weight: 600;"><?= htmlspecialchars($success) ?></p>
    </div>
  <?php endif; ?>

  <!-- ===== FORMULAIRE AJOUT LEÇON ===== -->
  <div class="card" style="margin-bottom: 1.5rem;">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">➕ Ajouter une leçon</h2>
    <form action="lessons.php?course=<?= $course_id ?>" method="POST" enctype="multipart/form-data" id="lesson-form">
      <input type="hidden" name="action" value="create_lesson"/>

      <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Titre de la leçon</label>
          <input type="text" name="titre" required placeholder="Ex : Leçon 1 — Introduction au HTML"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Ordre d'affichage</label>
          <input type="number" name="ordre" min="0" value="<?= count($lessons) ?>"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
      </div>

      <div style="margin-bottom: 1rem;">
        <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Type de contenu</label>
        <div style="display:flex; gap:.6rem;">
          <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
            <input type="radio" name="type" value="pdf" checked onchange="toggleLessonType()"/> 📄 Document PDF
          </label>
          <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
            <input type="radio" name="type" value="video" onchange="toggleLessonType()"/> 🎬 Vidéo (lien)
          </label>
        </div>
      </div>

      <div id="pdf-field" style="margin-bottom: 1rem;">
        <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Fichier PDF (max 20 Mo)</label>
        <input type="file" name="fichier_pdf" accept="application/pdf"
          style="width:100%; padding:.6rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper);"/>
      </div>

      <div id="video-field" style="margin-bottom: 1rem; display:none;">
        <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">URL de la vidéo (YouTube, Vimeo, etc.)</label>
        <input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..."
          style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
      </div>

      <button type="submit" class="btn btn-primary">Ajouter la leçon</button>
    </form>
  </div>

  <!-- ===== LISTE DES LEÇONS ===== -->
  <div class="card">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">Leçons du cours (<?= count($lessons) ?>)</h2>

    <?php if (empty($lessons)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Aucune leçon ajoutée pour le moment.</p>
      </div>
    <?php else: ?>
      <?php foreach ($lessons as $i => $l): ?>
        <div class="card" style="border: 1px solid var(--border); margin-bottom: 1rem;">
          <div style="display:flex; justify-content:space-between; align-items:start;">
            <div style="display:flex; gap:.85rem; align-items:start;">
              <div style="width:36px; height:36px; border-radius:10px; background:var(--pulse-bg); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                <?= $l['type'] === 'pdf' ? '📄' : '🎬' ?>
              </div>
              <div>
                <h3 style="font-size:1rem; font-weight:700; margin-bottom:.2rem;">Leçon <?= $i + 1 ?> — <?= htmlspecialchars($l['titre']) ?></h3>
                <p style="font-size:.78rem; color:var(--muted);">
                  Type : <?= $l['type'] === 'pdf' ? 'Document PDF' : 'Vidéo' ?>
                  <?php if ($l['type'] === 'pdf' && $l['fichier_path']): ?>
                    · <a href="/lms-inf222/<?= htmlspecialchars($l['fichier_path']) ?>" target="_blank" style="color:var(--pulse);">Voir le fichier</a>
                  <?php elseif ($l['type'] === 'video' && $l['video_url']): ?>
                    · <a href="<?= htmlspecialchars($l['video_url']) ?>" target="_blank" style="color:var(--pulse);">Voir la vidéo</a>
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <div style="display:flex; gap:.5rem; align-items:center;">
              <?php if ($l['evaluation_id']): ?>
                <span class="badge badge-success">✓ <?= $l['nb_questions'] ?> question(s)</span>
              <?php else: ?>
                <span class="badge badge-pending">Pas d'évaluation</span>
              <?php endif; ?>
            </div>
          </div>

          <div style="display:flex; gap:.5rem; margin-top:.85rem;">
            <a href="evaluations.php?lesson=<?= $l['id'] ?>&course=<?= $course_id ?>" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem;">
              📝 <?= $l['evaluation_id'] ? 'Gérer l\'évaluation' : 'Ajouter une évaluation' ?>
            </a>
            <form action="lessons.php?course=<?= $course_id ?>" method="POST" onsubmit="return confirm('Supprimer cette leçon et son évaluation ?');">
              <input type="hidden" name="action" value="delete_lesson"/>
              <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>"/>
              <button type="submit" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem; color:var(--danger); border-color:#FECACA;">🗑️ Supprimer</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

<script>
function toggleLessonType() {
  const type = document.querySelector('input[name="type"]:checked').value;
  document.getElementById('pdf-field').style.display = type === 'pdf' ? 'block' : 'none';
  document.getElementById('video-field').style.display = type === 'video' ? 'block' : 'none';
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>