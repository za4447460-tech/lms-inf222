<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$etudiant_id = $_SESSION['user_id'];
$lesson_id = (int)($_GET['lesson'] ?? 0);
$module_id = (int)($_GET['module'] ?? 0);

// ===== Vérifier l'inscription au module =====
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE etudiant_id = ? AND module_id = ?");
$stmt->execute([$etudiant_id, $module_id]);
if (!$stmt->fetch()) {
    header("Location: modules.php");
    exit;
}

// ===== Récupérer la leçon + son évaluation =====
$stmt = $pdo->prepare("
    SELECT l.*, c.titre AS course_titre, c.module_id
    FROM lessons l
    INNER JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.module_id = ?
");
$stmt->execute([$lesson_id, $module_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    header("Location: lessons.php?module=$module_id");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    header("Location: lessons.php?module=$module_id");
    exit;
}

// ===== Récupérer les questions + choix =====
$stmt = $pdo->prepare("SELECT * FROM questions WHERE evaluation_id = ? ORDER BY id ASC");
$stmt->execute([$evaluation['id']]);
$questions = $stmt->fetchAll();

foreach ($questions as &$q) {
    $stmt2 = $pdo->prepare("SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC");
    $stmt2->execute([$q['id']]);
    $q['choices'] = $stmt2->fetchAll();
}
unset($q);

$errors = [];

$pageTitle    = "Évaluation — " . $lesson['titre'];
$pageSubtitle = $evaluation['titre'];
$activeMenu   = "modules";

require __DIR__ . '/../layout/header.php';
?>

  <a href="lessons.php?module=<?= $module_id ?>" style="display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--muted); text-decoration:none; margin-bottom:1.25rem;">← Retour aux leçons</a>

  <?php if (!empty($errors)): ?>
    <div class="card" style="border-color: #FECACA; background: #FEF2F2; margin-bottom: 1.25rem;">
      <?php foreach ($errors as $err): ?>
        <p style="color: var(--danger); font-size: .85rem;"><?= htmlspecialchars($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($questions)): ?>
    <div class="card" style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
      <div style="font-size: 2.5rem; margin-bottom: .5rem;">❓</div>
      <p>Cette évaluation ne contient pas encore de questions.</p>
    </div>
  <?php else: ?>
    <!-- ===== ZONE DE RÉSULTAT (remplie dynamiquement par AJAX) ===== -->
    <div id="quiz-result" style="display:none;"></div>

    <div id="quiz-form-wrapper">
      <div class="card" style="margin-bottom: 1.25rem;">
        <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:.25rem;"><?= htmlspecialchars($evaluation['titre']) ?></h2>
        <?php if ($evaluation['description']): ?>
          <p style="font-size:.85rem; color:var(--muted);"><?= htmlspecialchars($evaluation['description']) ?></p>
        <?php endif; ?>
        <p style="font-size:.78rem; color:var(--muted); margin-top:.5rem;"><?= count($questions) ?> question(s) · Seuil de validation : 50%</p>
      </div>

      <form id="quiz-form">
        <?php foreach ($questions as $i => $q): ?>
          <div class="card" style="margin-bottom: 1rem;" data-question-card="<?= $q['id'] ?>">
            <h3 style="font-size:.95rem; font-weight:700; margin-bottom:.85rem;">Q<?= $i + 1 ?>. <?= htmlspecialchars($q['enonce']) ?></h3>
            <div style="display:flex; flex-direction:column; gap:.5rem;">
              <?php foreach ($q['choices'] as $c): ?>
                <label class="quiz-choice" data-choice-id="<?= $c['id'] ?>" style="display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; font-size:.85rem; transition: border-color .15s, background .15s;">
                  <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $c['id'] ?>" required/>
                  <?= htmlspecialchars($c['texte']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <button type="submit" id="quiz-submit-btn" class="btn btn-primary" style="width:100%; padding:.9rem; font-size:.95rem;">Valider mes réponses</button>
      </form>
    </div>

    <script>
    document.getElementById('quiz-form').addEventListener('submit', function(e) {
      e.preventDefault();

      const btn = document.getElementById('quiz-submit-btn');
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Correction en cours...';

      const formData = new FormData();
      formData.append('lesson_id', <?= $lesson_id ?>);
      formData.append('module_id', <?= $module_id ?>);

      // Construire answers[question_id] = choice_id
      document.querySelectorAll('#quiz-form input[type=radio]:checked').forEach(input => {
        const match = input.name.match(/answers\[(\d+)\]/);
        if (match) formData.append('answers[' + match[1] + ']', input.value);
      });

      fetch('ajax_submit_quiz.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          alert(data.error);
          btn.disabled = false;
          btn.textContent = originalText;
          return;
        }

        // Coloration de la correction sur chaque question
        data.detail.forEach(d => {
          const card = document.querySelector('[data-question-card="' + d.question_id + '"]');
          if (!card) return;
          card.querySelectorAll('.quiz-choice').forEach(label => {
            const choiceId = parseInt(label.dataset.choiceId);
            const radio = label.querySelector('input[type=radio]');
            radio.disabled = true;
            if (choiceId === d.correct_choice_id) {
              label.style.borderColor = '#A7F3D0';
              label.style.background = 'var(--mint-bg)';
            }
            if (choiceId === d.given_choice_id && !d.is_correct) {
              label.style.borderColor = '#FECACA';
              label.style.background = '#FEF2F2';
            }
          });
        });

        // Construire l'écran de résultat
        const passed = data.passed;
        let html = '<div class="card" style="text-align:center; padding: 2.5rem 1.5rem; margin-bottom: 1.5rem;">';
        html += '<div style="font-size: 3rem; margin-bottom: .75rem;">' + (passed ? '🎉' : '😕') + '</div>';
        html += '<h2 style="font-size:1.5rem; font-weight:700; margin-bottom:.5rem;">' + (passed ? 'Bien joué !' : 'Pas encore validé') + '</h2>';
        html += '<p style="font-size:1rem; color:var(--muted); margin-bottom:1.25rem;">Vous avez obtenu <strong>' + data.correct + '/' + data.total + '</strong> bonnes réponses</p>';
        html += '<div style="display:inline-flex; align-items:center; justify-content:center; width:120px; height:120px; border-radius:50%; background:' + (passed ? 'var(--mint-bg)' : '#FEF2F2') + '; margin-bottom:1.25rem;">';
        html += '<span style="font-size:2rem; font-weight:700; color:' + (passed ? '#047857' : 'var(--danger)') + ';">' + data.score + '%</span>';
        html += '</div>';
        html += '<p style="font-size:.85rem; color:var(--muted); margin-bottom:1.5rem;">' + (passed
          ? 'Score ≥ 50% : cette leçon est validée et compte dans votre progression.'
          : 'Le seuil de validation est de 50%. Vous pouvez repasser le quiz.') + '</p>';

        if (data.module_validated) {
          html += '<div class="card" style="background: var(--pulse-bg); border: none; margin-bottom: 1.25rem;">';
          html += '<p style="font-weight:700; color:var(--pulse);">🏆 Félicitations ! Vous avez validé l\\'ensemble du module.</p>';
          html += '<a href="certificates.php" style="color:var(--pulse); font-weight:600; text-decoration:none;">Voir mon certificat →</a>';
          html += '</div>';
        }

        html += '<div style="display:flex; gap:.75rem; justify-content:center;">';
        html += '<a href="lessons.php?module=<?= $module_id ?>" class="btn btn-outline">← Retour aux leçons (progression : ' + data.module_progress + '%)</a>';
        if (!passed) {
          html += '<a href="evaluations.php?lesson=<?= $lesson_id ?>&module=<?= $module_id ?>" class="btn btn-primary">🔄 Réessayer</a>';
        }
        html += '</div></div>';

        const resultDiv = document.getElementById('quiz-result');
        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';
        btn.style.display = 'none';

        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(() => {
        alert('Erreur réseau, veuillez réessayer.');
        btn.disabled = false;
        btn.textContent = originalText;
      });
    });
    </script>
  <?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>