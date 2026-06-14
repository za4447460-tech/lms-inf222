Login · PHP
<?php
session_start();
require __DIR__ . '/../config/db.php';
 
$error = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
 
    if ($email === '' || $password === '') {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
 
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = "E-mail ou mot de passe incorrect.";
        } elseif ($user['role'] !== $role) {
            $error = "Ce compte n'est pas enregistré comme " . htmlspecialchars($role) . ".";
        } else {
            // Connexion réussie : on crée la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nom']     = $user['nom'];
            $_SESSION['prenom']  = $user['prenom'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
 
            // Redirection selon le rôle
            switch ($user['role']) {
                case 'etudiant':
                    header("Location: ../views/etudiant/dashboard.php");
                    break;
                case 'enseignant':
                    header("Location: ../views/enseignant/dashboard.php");
                    break;
                case 'promoteur':
                    header("Location: ../views/promoteur/dashboard.php");
                    break;
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connexion — EduPulse INF222</title>
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
      background: var(--ink);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      align-items: stretch;
    }
 
    .hero-panel {
      width: 55%;
      background: var(--deep);
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem;
      overflow: hidden;
    }
    .hero-panel::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(108,99,255,.35) 0%, transparent 70%);
      top: -150px; left: -100px;
      pointer-events: none;
    }
    .hero-panel::after {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(0,212,170,.2) 0%, transparent 70%);
      bottom: -80px; right: -60px;
      pointer-events: none;
    }
 
    .brand { display: flex; align-items: center; gap: .75rem; position: relative; z-index: 1; }
    .brand-icon {
      width: 42px; height: 42px; background: var(--pulse); border-radius: 12px;
      display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
    }
    .brand-name { font-size: 1.5rem; font-weight: 700; color: #fff; letter-spacing: -.02em; }
    .brand-sub { font-size: .75rem; color: var(--pulse-lt); letter-spacing: .08em; text-transform: uppercase; }
 
    .hero-content { position: relative; z-index: 1; }
    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: .5rem;
      background: rgba(108,99,255,.18); border: 1px solid rgba(108,99,255,.4);
      border-radius: 100px; padding: .3rem .9rem;
      font-size: .75rem; font-weight: 500; color: var(--pulse-lt);
      margin-bottom: 1.5rem; letter-spacing: .04em;
    }
    .hero-eyebrow span { width: 6px; height: 6px; border-radius: 50%; background: var(--mint); display: block; animation: blink 2s infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
 
    .hero-title {
      font-family: 'Instrument Serif', serif;
      font-size: clamp(2.2rem, 4vw, 3.4rem);
      line-height: 1.1; color: #fff; margin-bottom: 1.25rem;
    }
    .hero-title em { font-style: italic; color: var(--pulse-lt); }
 
    .hero-desc { font-size: 1rem; line-height: 1.7; color: #9CA3AF; max-width: 420px; margin-bottom: 2.5rem; }
 
    .stats-row { display: flex; gap: 2.5rem; }
    .stat { display: flex; flex-direction: column; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #fff; line-height: 1; }
    .stat-label { font-size: .75rem; color: #6B7280; margin-top: .25rem; }
 
    .hero-footer { position: relative; z-index: 1; display: flex; align-items: center; gap: 1rem; }
    .avatar-stack { display: flex; }
    .avatar-stack .av {
      width: 32px; height: 32px; border-radius: 50%; border: 2px solid var(--deep);
      margin-left: -8px; background: var(--pulse);
      display: flex; align-items: center; justify-content: center;
      font-size: .65rem; font-weight: 600; color: #fff;
    }
    .avatar-stack .av:first-child { margin-left: 0; }
    .hero-footer p { font-size: .82rem; color: #6B7280; }
    .hero-footer strong { color: #fff; }
 
    .auth-panel {
      width: 45%; display: flex; flex-direction: column; justify-content: center;
      padding: 3rem 3.5rem; background: #fff; color: var(--ink);
    }
 
    .auth-header { margin-bottom: 2rem; }
    .auth-header h2 { font-size: 1.75rem; font-weight: 700; letter-spacing: -.03em; line-height: 1.2; margin-bottom: .4rem; }
    .auth-header p { font-size: .9rem; color: var(--muted); }
 
    .role-tabs {
      display: flex; gap: .5rem; background: var(--paper);
      border-radius: 12px; padding: .3rem; margin-bottom: 1.75rem;
    }
    .role-tab {
      flex: 1; padding: .55rem; border: none; background: transparent;
      border-radius: 9px; font-family: 'Space Grotesk', sans-serif;
      font-size: .82rem; font-weight: 500; color: var(--muted);
      cursor: pointer; transition: all .2s;
      display: flex; align-items: center; justify-content: center; gap: .4rem;
    }
    .role-tab.active { background: #fff; color: var(--pulse); box-shadow: 0 1px 4px rgba(0,0,0,.1); }
 
    .alert {
      padding: .85rem 1rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1.25rem;
      background: #FEF2F2; border: 1px solid #FECACA; color: var(--danger);
    }
 
    .form-group { margin-bottom: 1.1rem; }
    .form-group label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: .45rem; letter-spacing: .02em; }
    .input-wrap { position: relative; }
    .input-wrap .icon { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; pointer-events: none; }
    .form-group input {
      width: 100%; padding: .75rem 1rem .75rem 2.5rem;
      border: 1.5px solid var(--border); border-radius: 10px;
      font-family: 'Space Grotesk', sans-serif; font-size: .9rem; color: var(--ink);
      background: var(--paper); transition: border-color .2s, box-shadow .2s;
    }
    .form-group input:focus {
      outline: none; border-color: var(--pulse);
      box-shadow: 0 0 0 3px rgba(108,99,255,.12); background: #fff;
    }
 
    .form-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .remember { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: var(--muted); cursor: pointer; }
    .remember input[type=checkbox] { accent-color: var(--pulse); width: 15px; height: 15px; }
    .forgot { font-size: .82rem; color: var(--pulse); text-decoration: none; font-weight: 500; }
    .forgot:hover { text-decoration: underline; }
 
    .btn-primary {
      width: 100%; padding: .85rem; background: var(--pulse); color: #fff; border: none;
      border-radius: 10px; font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600;
      cursor: pointer; transition: background .2s, transform .1s, box-shadow .2s;
      letter-spacing: .01em; display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .btn-primary:hover { background: #5A52E0; box-shadow: 0 4px 16px rgba(108,99,255,.35); }
    .btn-primary:active { transform: scale(.98); }
 
    .auth-footer { text-align: center; margin-top: 1.5rem; font-size: .85rem; color: var(--muted); }
    .auth-footer a { color: var(--pulse); font-weight: 600; text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
 
    @media (max-width: 900px) {
      body { flex-direction: column; }
      .hero-panel { width: 100%; padding: 2rem; min-height: 280px; }
      .hero-title { font-size: 1.9rem; }
      .stats-row { gap: 1.5rem; }
      .hero-footer, .hero-desc { display: none; }
      .auth-panel { width: 100%; padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>
 
<div class="hero-panel">
  <div class="brand">
    <div class="brand-icon">⚡</div>
    <div>
      <div class="brand-name">EduPulse</div>
      <div class="brand-sub">INF222 · Développement Web</div>
    </div>
  </div>
 
  <div class="hero-content">
    <div class="hero-eyebrow"><span></span> Plateforme active · Semestre 2</div>
    <h1 class="hero-title">Apprenez.<br>Évaluez-vous.<br><em>Progressez.</em></h1>
    <p class="hero-desc">
      Un espace d'apprentissage structuré pour INF222 — suivez vos leçons, validez vos modules et obtenez vos certifications, tout en un seul endroit.
    </p>
    <div class="stats-row">
      <div class="stat"><span class="stat-value">12</span><span class="stat-label">Modules de cours</span></div>
      <div class="stat"><span class="stat-value">48</span><span class="stat-label">Leçons disponibles</span></div>
      <div class="stat"><span class="stat-value">94%</span><span class="stat-label">Taux de réussite</span></div>
    </div>
  </div>
 
  <div class="hero-footer">
    <div class="avatar-stack">
      <div class="av">AM</div><div class="av">KB</div><div class="av">FN</div><div class="av">+</div>
    </div>
    <p><strong>247 étudiants</strong> connectés cette semaine</p>
  </div>
</div>
 
<div class="auth-panel">
  <div class="auth-header">
    <h2>Bon retour 👋</h2>
    <p>Connectez-vous pour continuer votre apprentissage</p>
  </div>
 
  <div class="role-tabs">
    <button type="button" class="role-tab active" onclick="setRole(this,'etudiant')">🎓 Étudiant</button>
    <button type="button" class="role-tab" onclick="setRole(this,'enseignant')">📚 Enseignant</button>
    <button type="button" class="role-tab" onclick="setRole(this,'promoteur')">🏆 Promoteur</button>
  </div>
 
  <?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
 
  <form action="login.php" method="POST">
    <input type="hidden" name="role" id="role-input" value="etudiant"/>
 
    <div class="form-group">
      <label for="email">Adresse e-mail</label>
      <div class="input-wrap">
        <span class="icon">✉️</span>
        <input type="email" id="email" name="email" placeholder="vous@exemple.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
    </div>
 
    <div class="form-group">
      <label for="password">Mot de passe</label>
      <div class="input-wrap">
        <span class="icon">🔒</span>
        <input type="password" id="password" name="password" placeholder="••••••••" required/>
      </div>
    </div>
 
    <div class="form-row">
      <label class="remember"><input type="checkbox" name="remember"/> Se souvenir de moi</label>
      <a href="#" class="forgot">Mot de passe oublié ?</a>
    </div>
 
    <button type="submit" class="btn-primary">Se connecter ➔</button>
  </form>
 
  <div class="auth-footer">
    Pas encore de compte ? <a href="register.php">Créer un compte</a>
  </div>
</div>
 
<script>
  function setRole(btn, role) {
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('role-input').value = role;
  }
</script>
</body>
</html>
 
