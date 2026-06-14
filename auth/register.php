
<?php
session_start();
require __DIR__ . '/../config/db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Validation
    if ($nom === '' || $prenom === '' || $email === '' || $password === '') {
        $errors[] = "Tous les champs sont obligatoires.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse e-mail invalide.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($password !== $confirm) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (!in_array($role, ['etudiant', 'enseignant', 'promoteur'])) {
        $errors[] = "Rôle invalide.";
    }

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Cet e-mail est déjà utilisé.";
        }
    }

    // Insertion en base
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (nom, prenom, email, password_hash, role) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nom, $prenom, $email, $hash, $role]);

        $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inscription — EduPulse INF222</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --ink:      #0D1117;
      --deep:     #111827;
      --pulse:    #6C63FF;
      --pulse-lt: #A89CFF;
      --mint:     #00D4AA;
      --paper:    #F8F7FF;
      --muted:    #6B7280;
      --border:   #E5E7EB;
      --danger:   #EF4444;
    }

    body {
      font-family: 'Space Grotesk', sans-serif;
      background: var(--paper);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .card {
      width: 100%;
      max-width: 460px;
      background: #fff;
      border-radius: 18px;
      padding: 2.5rem;
      box-shadow: 0 10px 40px rgba(108,99,255,.08);
      border: 1px solid var(--border);
    }

    .brand {
      display: flex; align-items: center; gap: .6rem;
      margin-bottom: 1.75rem;
    }
    .brand-icon {
      width: 38px; height: 38px;
      background: var(--pulse);
      border-radius: 11px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.25rem;
    }
    .brand-name { font-size: 1.25rem; font-weight: 700; letter-spacing: -.02em; }
    .brand-sub { font-size: .7rem; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; }

    h1 {
      font-family: 'Instrument Serif', serif;
      font-size: 2rem;
      margin-bottom: .35rem;
    }
    .subtitle { font-size: .9rem; color: var(--muted); margin-bottom: 1.75rem; }

    .alert {
      padding: .85rem 1rem;
      border-radius: 10px;
      font-size: .85rem;
      margin-bottom: 1.25rem;
    }
    .alert-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      color: var(--danger);
    }
    .alert-success {
      background: #ECFDF5;
      border: 1px solid #A7F3D0;
      color: #047857;
    }
    .alert ul { margin: 0; padding-left: 1.2rem; }

    .form-row-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-group { margin-bottom: 1.1rem; }
    .form-group label {
      display: block;
      font-size: .8rem; font-weight: 600;
      margin-bottom: .45rem;
      letter-spacing: .02em;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: .75rem 1rem;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: 'Space Grotesk', sans-serif;
      font-size: .9rem;
      background: var(--paper);
      transition: border-color .2s, box-shadow .2s;
      appearance: none;
    }
    .form-group input:focus, .form-group select:focus {
      outline: none;
      border-color: var(--pulse);
      box-shadow: 0 0 0 3px rgba(108,99,255,.12);
      background: #fff;
    }

    /* Role selector */
    .role-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: .6rem;
      margin-bottom: 1.25rem;
    }
    .role-option {
      position: relative;
    }
    .role-option input { position: absolute; opacity: 0; pointer-events: none; }
    .role-option label {
      display: flex; flex-direction: column; align-items: center; gap: .35rem;
      padding: .9rem .5rem;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      cursor: pointer;
      font-size: .78rem; font-weight: 500;
      text-align: center;
      color: var(--muted);
      transition: all .15s;
    }
    .role-option label .emoji { font-size: 1.4rem; }
    .role-option input:checked + label {
      border-color: var(--pulse);
      background: rgba(108,99,255,.06);
      color: var(--pulse);
      font-weight: 600;
    }

    .btn-primary {
      width: 100%;
      padding: .85rem;
      background: var(--pulse);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Space Grotesk', sans-serif;
      font-size: .95rem; font-weight: 600;
      cursor: pointer;
      transition: background .2s, box-shadow .2s;
      margin-top: .25rem;
    }
    .btn-primary:hover { background: #5A52E0; box-shadow: 0 4px 16px rgba(108,99,255,.35); }

    .footer-link {
      text-align: center; margin-top: 1.5rem;
      font-size: .85rem; color: var(--muted);
    }
    .footer-link a { color: var(--pulse); font-weight: 600; text-decoration: none; }
    .footer-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <div class="brand-icon">⚡</div>
      <div>
        <div class="brand-name">EduPulse</div>
        <div class="brand-sub">INF222 · Développement Web</div>
      </div>
    </div>

    <h1>Créer un compte</h1>
    <p class="subtitle">Rejoignez la plateforme du cours INF222</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <?= htmlspecialchars($success) ?> <a href="login.php" style="color:#047857; font-weight:600;">Se connecter →</a>
      </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
      <div class="form-row-2">
        <div class="form-group">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" placeholder="Marc" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"/>
        </div>
        <div class="form-group">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" placeholder="Dupont" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"/>
        </div>
      </div>

      <div class="form-group">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" placeholder="vous@exemple.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" placeholder="6 caractères min." required/>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirmer</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required/>
        </div>
      </div>

      <label style="display:block; font-size:.8rem; font-weight:600; margin-bottom:.6rem;">Je suis...</label>
      <div class="role-grid">
        <div class="role-option">
          <input type="radio" id="role-etudiant" name="role" value="etudiant" checked/>
          <label for="role-etudiant"><span class="emoji">🎓</span>Étudiant</label>
        </div>
        <div class="role-option">
          <input type="radio" id="role-enseignant" name="role" value="enseignant"/>
          <label for="role-enseignant"><span class="emoji">📚</span>Enseignant</label>
        </div>
        <div class="role-option">
          <input type="radio" id="role-promoteur" name="role" value="promoteur"/>
          <label for="role-promoteur"><span class="emoji">🏆</span>Promoteur</label>
        </div>
      </div>

      <button type="submit" class="btn-primary">Créer mon compte ➔</button>
    </form>

    <div class="footer-link">
      Déjà un compte ? <a href="login.php">Se connecter</a>
    </div>
  </div>
</body>
</html>
