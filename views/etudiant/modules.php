<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$etudiant_id = $_SESSION['user_id'];

// ===== TOUS LES MODULES + statut d'inscription =====
$stmt = $pdo->prepare("
    SELECT m.*,
        (SELECT COUNT(*) FROM courses c WHERE c.module_id = m.id) AS nb_courses,
        (SELECT COUNT(*) FROM lessons l INNER JOIN courses c ON c.id = l.course_id WHERE c.module_id = m.id) AS nb_lecons,
        (SELECT id FROM enrollments e WHERE e.etudiant_id = ? AND e.module_id = m.id) AS enrollment_id
    FROM modules m
    ORDER BY m.created_at DESC
");
$stmt->execute([$etudiant_id]);
$modules = $stmt->fetchAll();

$pageTitle    = "Mes modules";
$pageSubtitle = "Parcourez les modules disponibles et inscrivez-vous";
$activeMenu   = "modules";

require __DIR__ . '/../layout/header.php';
?>


  <?php if (empty($modules)): ?>
    <div class="card" style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
      <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
      <p>Aucun module disponible pour le moment. Revenez plus tard !</p>
    </div>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($modules as $m): ?>
        <div class="card" id="module-card-<?= $m['id'] ?>">
          <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:.5rem;">
            <h3 style="font-size:1.05rem; font-weight:700;"><?= htmlspecialchars($m['titre']) ?></h3>
            <span class="badge badge-success module-badge-enrolled" style="<?= $m['enrollment_id'] ? '' : 'display:none;' ?>">✓ Inscrit</span>
          </div>
          <?php if ($m['description']): ?>
            <p style="font-size:.85rem; color:var(--muted); margin-bottom:.75rem;"><?= htmlspecialchars($m['description']) ?></p>
          <?php endif; ?>
          <div style="display:flex; gap:1rem; font-size:.78rem; color:var(--muted); margin-bottom:1rem;">
            <span>📚 <?= $m['nb_courses'] ?> cours</span>
            <span>🎬 <?= $m['nb_lecons'] ?> leçon(s)</span>
          </div>

          <div class="module-action" id="module-action-<?= $m['id'] ?>">
            <?php if ($m['enrollment_id']): ?>
              <a href="/lms-inf222/views/etudiant/lessons.php?module=<?= $m['id'] ?>" class="btn btn-primary">Continuer →</a>
            <?php else: ?>
              <button type="button" class="btn btn-primary enroll-btn" data-module-id="<?= $m['id'] ?>" data-module-titre="<?= htmlspecialchars($m['titre']) ?>" <?= $m['nb_lecons'] == 0 ? 'disabled' : '' ?>>
                <?= $m['nb_lecons'] == 0 ? 'Pas encore de contenu' : "S'inscrire" ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Zone de notification toast (AJAX) -->
  <div id="toast" style="
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    background: var(--ink); color: #fff;
    padding: .85rem 1.25rem; border-radius: 10px;
    font-size: .85rem; font-weight: 500;
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    transform: translateY(100px); opacity: 0;
    transition: all .3s ease;
    z-index: 100;
  "></div>

<script>
function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.style.background = isError ? 'var(--danger)' : 'var(--ink)';
  toast.style.transform = 'translateY(0)';
  toast.style.opacity = '1';
  setTimeout(() => {
    toast.style.transform = 'translateY(100px)';
    toast.style.opacity = '0';
  }, 3000);
}

document.querySelectorAll('.enroll-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const moduleId = this.dataset.moduleId;
    const moduleTitre = this.dataset.moduleTitre;
    const originalText = this.textContent;

    this.disabled = true;
    this.textContent = 'Inscription...';

    const formData = new FormData();
    formData.append('module_id', moduleId);

    fetch('ajax_enroll.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        showToast(data.error, true);
        this.disabled = false;
        this.textContent = originalText;
        return;
      }

      // Mettre à jour l'UI sans recharger la page
      const card = document.getElementById('module-card-' + moduleId);
      card.querySelector('.module-badge-enrolled').style.display = 'inline-flex';

      const actionZone = document.getElementById('module-action-' + moduleId);
      actionZone.innerHTML = '<a href="/lms-inf222/views/etudiant/lessons.php?module=' + moduleId + '" class="btn btn-primary">Continuer →</a>';

      showToast(data.already_enrolled
        ? 'Vous êtes déjà inscrit à "' + moduleTitre + '"'
        : 'Inscription réussie à "' + moduleTitre + '" ✅');
    })
    .catch(() => {
      showToast('Erreur réseau, veuillez réessayer.', true);
      this.disabled = false;
      this.textContent = originalText;
    });
  });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>