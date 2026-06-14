<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'promoteur') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$promoteur_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// ===== CRÉATION D'UN MODULE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($titre === '') {
        $errors[] = "Le titre du module est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO modules (titre, description, promoteur_id) VALUES (?, ?, ?)");
        $stmt->execute([$titre, $description, $promoteur_id]);
        $success = "Module créé avec succès !";
    }
}

// ===== MODIFICATION D'UN MODULE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($titre === '') {
        $errors[] = "Le titre du module est obligatoire.";
    }

    if (empty($errors) && $id > 0) {
        $stmt = $pdo->prepare("UPDATE modules SET titre = ?, description = ? WHERE id = ?");
        $stmt->execute([$titre, $description, $id]);
        $success = "Module mis à jour avec succès !";
    }
}

// ===== SUPPRESSION D'UN MODULE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Vérifier s'il y a des cours liés
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE module_id = ?");
        $stmt->execute([$id]);
        $nbCourses = $stmt->fetchColumn();

        if ($nbCourses > 0) {
            $errors[] = "Impossible de supprimer ce module : il contient $nbCourses cours. Supprimez d'abord les cours associés.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Module supprimé avec succès.";
        }
    }
}

// ===== LISTE DES MODULES =====
$stmt = $pdo->query("
    SELECT m.*,
        (SELECT COUNT(*) FROM courses c WHERE c.module_id = m.id) AS nb_courses,
        (SELECT COUNT(*) FROM enrollments e WHERE e.module_id = m.id) AS nb_etudiants,
        (SELECT COUNT(DISTINCT cert.etudiant_id) FROM certificates cert WHERE cert.module_id = m.id) AS nb_certifies
    FROM modules m
    ORDER BY m.created_at DESC
");
$modules = $stmt->fetchAll();

$pageTitle    = "Modules";
$pageSubtitle = "Gérez les modules de cours de la plateforme";
$activeMenu   = "modules";

require __DIR__ . '/../layout/header.php';
?>

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

  <!-- ===== FORMULAIRE DE CRÉATION ===== -->
  <div class="card" style="margin-bottom: 1.5rem;">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">➕ Créer un nouveau module</h2>
    <form action="modules.php" method="POST">
      <input type="hidden" name="action" value="create"/>
      <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Titre du module</label>
          <input type="text" name="titre" required placeholder="Ex : Introduction au développement Web"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Description (optionnel)</label>
          <input type="text" name="description" placeholder="Brève description du module"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Créer le module</button>
    </form>
  </div>

  <!-- ===== LISTE DES MODULES ===== -->
  <div class="card">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">Tous les modules (<?= count($modules) ?>)</h2>

    <?php if (empty($modules)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Aucun module créé pour le moment. Utilisez le formulaire ci-dessus.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-2">
        <?php foreach ($modules as $m): ?>
          <div class="card" style="border: 1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:.5rem;">
              <h3 style="font-size:1.05rem; font-weight:700;"><?= htmlspecialchars($m['titre']) ?></h3>
              <span class="badge badge-pulse"><?= $m['nb_courses'] ?> cours</span>
            </div>
            <?php if ($m['description']): ?>
              <p style="font-size:.85rem; color:var(--muted); margin-bottom:.75rem;"><?= htmlspecialchars($m['description']) ?></p>
            <?php endif; ?>
            <div style="display:flex; gap:1rem; font-size:.78rem; color:var(--muted); margin-bottom:1rem;">
              <span>👥 <?= $m['nb_etudiants'] ?> inscrit(s)</span>
              <span>🏆 <?= $m['nb_certifies'] ?> certifié(s)</span>
              <span>📅 <?= date('d/m/Y', strtotime($m['created_at'])) ?></span>
            </div>

            <details>
              <summary style="cursor:pointer; font-size:.82rem; font-weight:600; color:var(--pulse); list-style:none;">✏️ Modifier</summary>
              <form action="modules.php" method="POST" style="margin-top:.75rem;">
                <input type="hidden" name="action" value="update"/>
                <input type="hidden" name="id" value="<?= $m['id'] ?>"/>
                <div style="margin-bottom:.6rem;">
                  <input type="text" name="titre" value="<?= htmlspecialchars($m['titre']) ?>" required
                    style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper); margin-bottom:.5rem;"/>
                  <input type="text" name="description" value="<?= htmlspecialchars($m['description'] ?? '') ?>" placeholder="Description"
                    style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper);"/>
                </div>
                <div style="display:flex; gap:.5rem;">
                  <button type="submit" class="btn btn-primary" style="padding:.5rem 1rem; font-size:.8rem;">Enregistrer</button>
                </div>
              </form>

              <form action="modules.php" method="POST" onsubmit="return confirm('Supprimer ce module ?');" style="margin-top:.5rem;">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= $m['id'] ?>"/>
                <button type="submit" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem; color:var(--danger); border-color:#FECACA;">🗑️ Supprimer</button>
              </form>
            </details>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/../layout/footer.php'; ?>