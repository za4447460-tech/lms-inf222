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

// ===== CRÉATION D'UN COURS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $module_id = (int)($_POST['module_id'] ?? 0);

    if ($titre === '') {
        $errors[] = "Le titre du cours est obligatoire.";
    }
    if ($module_id <= 0) {
        $errors[] = "Veuillez sélectionner un module.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO courses (module_id, enseignant_id, titre, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$module_id, $enseignant_id, $titre, $description]);
        $success = "Cours créé avec succès ! Vous pouvez maintenant y ajouter des leçons.";
    }
}

// ===== MODIFICATION D'UN COURS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $module_id = (int)($_POST['module_id'] ?? 0);

    if ($titre === '') {
        $errors[] = "Le titre du cours est obligatoire.";
    }

    if (empty($errors) && $id > 0) {
        // Vérifier que le cours appartient bien à cet enseignant
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND enseignant_id = ?");
        $stmt->execute([$id, $enseignant_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE courses SET titre = ?, description = ?, module_id = ? WHERE id = ?");
            $stmt->execute([$titre, $description, $module_id, $id]);
            $success = "Cours mis à jour avec succès !";
        } else {
            $errors[] = "Action non autorisée.";
        }
    }
}

// ===== SUPPRESSION D'UN COURS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
        $stmt->execute([$id]);
        $nbLessons = $stmt->fetchColumn();

        if ($nbLessons > 0) {
            $errors[] = "Impossible de supprimer ce cours : il contient $nbLessons leçon(s). Supprimez-les d'abord.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ? AND enseignant_id = ?");
            $stmt->execute([$id, $enseignant_id]);
            $success = "Cours supprimé avec succès.";
        }
    }
}

// ===== LISTE DES MODULES (pour le select) =====
$modules = $pdo->query("SELECT id, titre FROM modules ORDER BY titre")->fetchAll();

// ===== LISTE DES COURS DE L'ENSEIGNANT =====
$stmt = $pdo->prepare("
    SELECT c.*, m.titre AS module_titre,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS nb_lecons
    FROM courses c
    INNER JOIN modules m ON m.id = c.module_id
    WHERE c.enseignant_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$enseignant_id]);
$courses = $stmt->fetchAll();

$pageTitle    = "Mes cours";
$pageSubtitle = "Créez et gérez vos cours, rattachés aux modules de la plateforme";
$activeMenu   = "courses";

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

  <?php if (empty($modules)): ?>
    <div class="card" style="border-color: #FEF3C7; background: #FFFBEB; margin-bottom: 1.5rem;">
      <p style="color: #B45309; font-size: .85rem;">⚠️ Aucun module n'a encore été créé par le promoteur. Demandez-lui d'en créer avant de pouvoir ajouter un cours.</p>
    </div>
  <?php endif; ?>

  <!-- ===== FORMULAIRE DE CRÉATION ===== -->
  <div class="card" style="margin-bottom: 1.5rem;">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">➕ Créer un nouveau cours</h2>
    <form action="courses.php" method="POST">
      <input type="hidden" name="action" value="create"/>

      <div style="margin-bottom: 1rem;">
        <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Module rattaché</label>
        <select name="module_id" required
          style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);">
          <option value="">-- Sélectionner un module --</option>
          <?php foreach ($modules as $mod): ?>
            <option value="<?= $mod['id'] ?>"><?= htmlspecialchars($mod['titre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Titre du cours</label>
          <input type="text" name="titre" required placeholder="Ex : Les bases du HTML"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
        <div>
          <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.45rem;">Description (optionnel)</label>
          <input type="text" name="description" placeholder="Brève description du cours"
            style="width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:.9rem; background:var(--paper);"/>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" <?= empty($modules) ? 'disabled' : '' ?>>Créer le cours</button>
    </form>
  </div>

  <!-- ===== LISTE DES COURS ===== -->
  <div class="card">
    <h2 style="font-size:1.1rem; font-weight:700; margin-bottom:1.25rem;">Mes cours (<?= count($courses) ?>)</h2>

    <?php if (empty($courses)): ?>
      <div style="text-align:center; padding: 2.5rem 1rem; color: var(--muted);">
        <div style="font-size: 2.5rem; margin-bottom: .5rem;">📭</div>
        <p>Vous n'avez créé aucun cours pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-2">
        <?php foreach ($courses as $c): ?>
          <div class="card" style="border: 1px solid var(--border);">
            <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:.5rem;">
              <h3 style="font-size:1.05rem; font-weight:700;"><?= htmlspecialchars($c['titre']) ?></h3>
              <span class="badge badge-pulse"><?= htmlspecialchars($c['module_titre']) ?></span>
            </div>
            <?php if ($c['description']): ?>
              <p style="font-size:.85rem; color:var(--muted); margin-bottom:.75rem;"><?= htmlspecialchars($c['description']) ?></p>
            <?php endif; ?>
            <div style="display:flex; gap:1rem; font-size:.78rem; color:var(--muted); margin-bottom:1rem;">
              <span>🎬 <?= $c['nb_lecons'] ?> leçon(s)</span>
              <span>📅 <?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
            </div>

            <div style="display:flex; gap:.5rem; margin-bottom:.75rem;">
              <a href="/lms-inf222/views/enseignant/lessons.php?course=<?= $c['id'] ?>" class="btn btn-primary" style="padding:.5rem 1rem; font-size:.8rem;">🎬 Gérer les leçons</a>
            </div>

            <details>
              <summary style="cursor:pointer; font-size:.82rem; font-weight:600; color:var(--pulse); list-style:none;">✏️ Modifier / Supprimer</summary>
              <form action="courses.php" method="POST" style="margin-top:.75rem;">
                <input type="hidden" name="action" value="update"/>
                <input type="hidden" name="id" value="<?= $c['id'] ?>"/>
                <div style="margin-bottom:.6rem;">
                  <select name="module_id" required
                    style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper); margin-bottom:.5rem;">
                    <?php foreach ($modules as $mod): ?>
                      <option value="<?= $mod['id'] ?>" <?= $mod['id'] == $c['module_id'] ? 'selected' : '' ?>><?= htmlspecialchars($mod['titre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="titre" value="<?= htmlspecialchars($c['titre']) ?>" required
                    style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper); margin-bottom:.5rem;"/>
                  <input type="text" name="description" value="<?= htmlspecialchars($c['description'] ?? '') ?>" placeholder="Description"
                    style="width:100%; padding:.6rem .9rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.85rem; background:var(--paper);"/>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:.5rem 1rem; font-size:.8rem;">Enregistrer</button>
              </form>

              <form action="courses.php" method="POST" onsubmit="return confirm('Supprimer ce cours ?');" style="margin-top:.5rem;">
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= $c['id'] ?>"/>
                <button type="submit" class="btn btn-outline" style="padding:.5rem 1rem; font-size:.8rem; color:var(--danger); border-color:#FECACA;">🗑️ Supprimer</button>
              </form>
            </details>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php require __DIR__ . '/../layout/footer.php'; ?>