<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: /lms-inf222/auth/login.php");
    exit;
}

$etudiant_id = $_SESSION['user_id'];

// ===== Liste des certificats de l'étudiant =====
$stmt = $pdo->prepare("
    SELECT cert.*, m.titre AS module_titre, m.description AS module_description
    FROM certificates cert
    INNER JOIN modules m ON m.id = cert.module_id
    WHERE cert.etudiant_id = ?
    ORDER BY cert.issued_at DESC
");
$stmt->execute([$etudiant_id]);
$certificates = $stmt->fetchAll();

$pageTitle    = "Mes certificats";
$pageSubtitle = "Vos modules validés et certifiés";
$activeMenu   = "certificates";

require __DIR__ . '/../layout/header.php';
?>

  <?php if (empty($certificates)): ?>
    <div class="card" style="text-align:center; padding: 3rem 1.5rem; color: var(--muted);">
      <div style="font-size: 3rem; margin-bottom: .75rem;">🏆</div>
      <h2 style="font-size:1.1rem; font-weight:700; color:var(--ink); margin-bottom:.5rem;">Aucun certificat pour le moment</h2>
      <p style="margin-bottom:1.25rem;">Validez toutes les leçons d'un module (score ≥ 50% à chaque évaluation) pour obtenir votre certificat.</p>
      <a href="modules.php" class="btn btn-primary">Voir mes modules</a>
    </div>
  <?php else: ?>
    <div class="grid grid-2">
      <?php foreach ($certificates as $cert): ?>
        <div class="card">
          <!-- ===== CERTIFICAT VISUEL ===== -->
          <div style="
            background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
            border-radius: 14px;
            padding: 2rem 1.5rem;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
          ">
            <div style="position:absolute; width:200px; height:200px; border-radius:50%; background:radial-gradient(circle, rgba(108,99,255,.35) 0%, transparent 70%); top:-60px; left:-60px;"></div>
            <div style="position:absolute; width:150px; height:150px; border-radius:50%; background:radial-gradient(circle, rgba(0,212,170,.25) 0%, transparent 70%); bottom:-40px; right:-40px;"></div>

            <div style="position:relative; z-index:1;">
              <div style="font-size:2.5rem; margin-bottom:.5rem;">🏆</div>
              <p style="font-size:.7rem; letter-spacing:.15em; text-transform:uppercase; color:#A89CFF; margin-bottom:.5rem;">Certificat de réussite</p>
              <h3 style="font-family:'Instrument Serif', serif; font-size:1.4rem; margin-bottom:.75rem;"><?= htmlspecialchars($cert['module_titre']) ?></h3>
              <p style="font-size:.8rem; color:#9CA3AF; margin-bottom:.25rem;">Décerné à</p>
              <p style="font-size:1.1rem; font-weight:700; margin-bottom:1rem;"><?= htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) ?></p>
              <div style="display:flex; justify-content:space-between; font-size:.7rem; color:#9CA3AF; border-top:1px solid rgba(255,255,255,.15); padding-top:.75rem;">
                <span>Délivré le <?= date('d/m/Y', strtotime($cert['issued_at'])) ?></span>
                <span><?= htmlspecialchars($cert['code_unique']) ?></span>
              </div>
            </div>
          </div>

          <?php if ($cert['module_description']): ?>
            <p style="font-size:.82rem; color:var(--muted); margin-bottom:.75rem;"><?= htmlspecialchars($cert['module_description']) ?></p>
          <?php endif; ?>

          <button onclick="window.print()" class="btn btn-outline" style="width:100%;">🖨️ Imprimer / Télécharger en PDF</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>