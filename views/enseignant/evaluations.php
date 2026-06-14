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

$lesson_id = (int)($_GET['lesson'] ?? 0);
$course_id = (int)($_GET['course'] ?? 0);

// ===== Vérifier que la leçon appartient bien à l'enseignant =====
$stmt = $pdo->prepare("
    SELECT l.*, c.titre AS course_titre, c.module_id
    FROM lessons l
    INNER JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.id = ? AND c.enseignant_id = ?
");
$stmt->execute([$lesson_id, $course_id, $enseignant_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header("Location: courses.php");
    exit;
}

// ===== Récupérer ou créer l'évaluation =====
$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$evaluation = $stmt->fetch();

// ===== CRÉATION DE L'ÉVALUATION (si elle n'existe pas) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_evaluation') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($titre === '') {
        $errors[] = "Le titre de l'évaluation est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO evaluations (lesson_id, titre, description) VALUES (?, ?, ?)");
        $stmt->execute([$lesson_id, $titre, $description]);
        $evaluation = ['id' => $pdo->lastInsertId(), 'titre' => $titre, 'description' => $description];
        $success = "Évaluation créée ! Ajoutez maintenant des questions.";
    }
}

// ===== AJOUT D'UNE QUESTION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question' && $evaluation) {
    $enonce = trim($_POST['enonce'] ?? '');
    $type   = $_POST['q_type'] ?? 'qcm';
    $choices = $_POST['choices'] ?? [];
    $correct = (int)($_POST['correct'] ?? -1);

    if ($enonce === '') {
        $errors[] = "L'énoncé de la question est obligatoire.";
    }

    if ($type === 'qcm') {
        $choices = array_filter(array_map('trim', $choices));
        if (count($choices) < 2) {
            $errors[] = "Ajoutez au moins 2 choix de réponse.";
        }
        if ($correct < 0 || !isset($choices[$correct])) {
            $errors[] = "Veuillez indiquer quelle réponse est correcte.";
        }
    } elseif ($type === 'vrai_faux') {
        $choices = ['Vrai', 'Faux'];
        if ($correct !== 0 && $correct !== 1) {
            $errors[] = "Veuillez indiquer la bonne réponse (Vrai ou Faux).";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO questions (evaluation_id, enonce, type) VALUES (?, ?, ?)");
        $stmt->execute([$evaluation['id'], $enonce, $type]);
        $question_id = $pdo->lastInsertId();

        foreach (array_values($choices) as $i => $choiceText) {
            $isCorrect = ($i === $correct) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO choices (question_id, texte, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, $choiceText, $isCorrect]);
        }
        $success = "Question ajoutée avec succès !";
    }
}

// ===== SUPPRESSION D'UNE QUESTION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_question') {
    $question_id = (int)($_POST['question_id'] ?? 0);
    if ($question_id > 0) {
        $pdo->prepare("DELETE FROM choices WHERE question_id = ?")->execute([$question_id]);
        $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$question_id]);
        $success = "Question supprimée.";
    }
}

// ===== LISTE DES QUESTIONS =====
$questions = [];
if ($evaluation) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE evaluation_id = ? ORDER BY id ASC");
    $stmt->execute([$evaluation['id']]);
    $questions = $stmt->fetchAll();

    foreach ($questions as &$q) {
        $stmt2 = $pdo->prepare("SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC");
        $stmt2->execute([$q['id']]);
        $q['choices'] = $stmt2->fetchAll();
    }
    unset($q);
}

$pageTitle    = "Évaluation — " . $lesson['titre'];
$pageSubtitle = "Cours : " . $lesson['course_titre'];
$activeMenu   = "courses";

require __DIR__ . '/../layout/header.php';
?>

  <a href="lessons.php?course=<?= $course_id ?>" style="display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted); text-decoration:none; margin-bottom:1.25rem;">← Retour aux leçons</a>

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

  <?php if (!$evaluation): ?>
    <!-- ===== CRÉER L'ÉVALUATION ===== -->
    <div class="card">
      <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:.5rem;">📝 Créer l'évaluation de cette leçon</h2>
      <p style="font-size:.85rem; color:var(--muted); margin-bottom:1.25rem;">
        Chaque leçon doit être suivie d'une évaluation. Donnez-lui un titre, puis vous pourrez ajouter des questions.
      </p>
      <form action="evaluations.php?lesson=<?= $lesson_id ?>&course=<?= $course_id ?>" method="POST">
        <input type="hidden" name="action" value="create_evaluation"/>
        <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
          <div>
            <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Titre de l'évaluation</label>
            <input type="text" name="titre" required placeholder="Ex : Quiz — Introduction au HTML"
              style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
          </div>
          <div>
            <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Description (optionnel)</label>
            <input type="text" name="description" placeholder="Consignes pour l'étudiant"
              style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Créer l'évaluation</button>
      </form>
    </div>

  <?php else: ?>
    <!-- ===== AJOUTER UNE QUESTION ===== -->
    <div class="card" style="margin-bottom: 1.5rem;">
      <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:.25rem;">➕ Ajouter une question</h2>
      <p style="font-size:.82rem; color:var(--muted); margin-bottom:1.25rem;">
        Évaluation : <strong><?= htmlspecialchars($evaluation['titre']) ?></strong>
      </p>

      <form action="evaluations.php?lesson=<?= $lesson_id ?>&course=<?= $course_id ?>" method="POST" id="question-form">
        <input type="hidden" name="action" value="add_question"/>

        <div style="margin-bottom: 1rem;">
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Énoncé de la question</label>
          <input type="text" name="enonce" required placeholder="Ex : Quelle balise permet de créer un lien ?"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Type de question</label>
          <div style="display:flex; gap:.6rem;">
            <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
              <input type="radio" name="q_type" value="qcm" checked onchange="toggleQuestionType()"/> 🔘 QCM (plusieurs choix)
            </label>
            <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
              <input type="radio" name="q_type" value="vrai_faux" onchange="toggleQuestionType()"/> ✅ Vrai / Faux
            </label>
          </div>
        </div>

        <!-- QCM choices -->
        <div id="qcm-choices" style="margin-bottom: 1rem;">
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Choix de réponses (cochez la bonne réponse)</label>
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.5rem;">
              <input type="radio" name="correct" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>/>
              <input type="text" name="choices[]" placeholder="Choix <?= $i + 1 ?><?= $i < 2 ? ' (obligatoire)' : ' (optionnel)' ?>"
                style="flex:1; padding:.6rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper);"/>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Vrai/Faux choices -->
        <div id="vf-choices" style="margin-bottom: 1rem; display:none;">
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Réponse correcte</label>
          <div style="display:flex; gap:.6rem;">
            <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
              <input type="radio" name="correct_vf" value="0"/> ✅ Vrai
            </label>
            <label style="flex:1; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem;">
              <input type="radio" name="correct_vf" value="1"/> ❌ Faux
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Ajouter la question</button>
      </form>
    </div>

    <!-- ===== LISTE DES QUESTIONS ===== -->
    <div class="card">
      <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">Questions (<?= count($questions) ?>)</h2>

      <?php if (empty($questions)): ?>
        <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
          <div style="font-size: 2.5rem; margin-bottom: .5rem;">❓</div>
          <p>Aucune question ajoutée pour le moment.</p>
        </div>
      <?php else: ?>
        <?php foreach ($questions as $i => $q): ?>
          <div class="card" style="border: 1px solid var(--border); margin-bottom: .85rem;">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:.6rem;">
              <h3 style="font-size:.95rem; font-weight:700;">Q<?= $i + 1 ?>. <?= htmlspecialchars($q['enonce']) ?></h3>
              <span class="badge badge-pulse"><?= $q['type'] === 'qcm' ? 'QCM' : 'Vrai/Faux' ?></span>
            </div>
            <div style="display:flex; flex-direction:column; gap:.35rem; margin-bottom:.75rem;">
              <?php foreach ($q['choices'] as $c): ?>
                <div style="display:flex; align-items:center; gap:.5rem; font-size:.83rem; padding:.4rem .7rem; border-radius:8px; background:<?= $c['is_correct'] ? 'var(--mint-bg)' : 'var(--paper)' ?>;">
                  <?= $c['is_correct'] ? '✅' : '⬜' ?> <?= htmlspecialchars($c['texte']) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <form action="evaluations.php?lesson=<?= $lesson_id ?>&course=<?= $course_id ?>" method="POST" onsubmit="return confirm('Supprimer cette question ?');">
              <input type="hidden" name="action" value="delete_question"/>
              <input type="hidden" name="question_id" value="<?= $q['id'] ?>"/>
              <button type="submit" class="btn btn-outline" style="padding:.4rem .9rem; font-size:.78rem; color:var(--danger); border-color:#FECACA;">🗑️ Supprimer</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<script>
function toggleQuestionType() {
  const type = document.querySelector('input[name="q_type"]:checked').value;
  document.getElementById('qcm-choices').style.display = type === 'qcm' ? 'block' : 'none';
  document.getElementById('vf-choices').style.display = type === 'vrai_faux' ? 'block' : 'none';
}

// Avant soumission, si vrai/faux, on remplit "correct" avec la valeur de correct_vf
document.getElementById('question-form')?.addEventListener('submit', function(e) {
  const type = document.querySelector('input[name="q_type"]:checked').value;
  if (type === 'vrai_faux') {
    const vf = document.querySelector('input[name="correct_vf"]:checked');
    if (!vf) {
      alert("Veuillez sélectionner Vrai ou Faux.");
      e.preventDefault();
      return;
    }
    // injecter un champ "correct" caché avec la bonne valeur
    let hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'correct';
    hidden.value = vf.value;
    this.appendChild(hidden);

    // injecter les choix Vrai/Faux comme choices[]
    ['Vrai', 'Faux'].forEach(function(txt) {
      let inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'choices[]';
      inp.value = txt;
      this.appendChild(inp);
    }, this);

    // désactiver les champs QCM pour qu'ils ne soient pas envoyés
    document.querySelectorAll('#qcm-choices input').forEach(el => el.disabled = true);
  }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>