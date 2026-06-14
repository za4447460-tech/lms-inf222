<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'etudiant') {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé.']);
    exit;
}

$etudiant_id = $_SESSION['user_id'];
$lesson_id   = (int)($_POST['lesson_id'] ?? 0);
$module_id   = (int)($_POST['module_id'] ?? 0);
$answers     = $_POST['answers'] ?? []; // [question_id => choice_id]

// ===== Vérifier l'inscription au module =====
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE etudiant_id = ? AND module_id = ?");
$stmt->execute([$etudiant_id, $module_id]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Vous n\'êtes pas inscrit à ce module.']);
    exit;
}

// ===== Récupérer la leçon + son évaluation =====
$stmt = $pdo->prepare("
    SELECT l.id FROM lessons l
    INNER JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.module_id = ?
");
$stmt->execute([$lesson_id, $module_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Leçon introuvable.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM evaluations WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    http_response_code(404);
    echo json_encode(['error' => 'Évaluation introuvable.']);
    exit;
}

// ===== Récupérer les questions + bonnes réponses =====
$stmt = $pdo->prepare("SELECT * FROM questions WHERE evaluation_id = ? ORDER BY id ASC");
$stmt->execute([$evaluation['id']]);
$questions = $stmt->fetchAll();

if (empty($questions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cette évaluation ne contient aucune question.']);
    exit;
}

foreach ($questions as &$q) {
    $stmt2 = $pdo->prepare("SELECT * FROM choices WHERE question_id = ? ORDER BY id ASC");
    $stmt2->execute([$q['id']]);
    $q['choices'] = $stmt2->fetchAll();
}
unset($q);

// ===== Correction =====
$totalQuestions = count($questions);
$correctCount = 0;
$detail = []; // pour afficher la correction question par question côté client

foreach ($questions as $q) {
    $given = (int)($answers[$q['id']] ?? 0);
    $isCorrect = false;
    $correctChoiceId = null;

    foreach ($q['choices'] as $c) {
        if ($c['is_correct']) $correctChoiceId = (int)$c['id'];
        if ($c['is_correct'] && (int)$c['id'] === $given) {
            $isCorrect = true;
        }
    }

    if ($isCorrect) $correctCount++;

    $detail[] = [
        'question_id'      => (int)$q['id'],
        'is_correct'       => $isCorrect,
        'given_choice_id'  => $given,
        'correct_choice_id'=> $correctChoiceId,
    ];
}

$score = round(($correctCount / $totalQuestions) * 100, 2);

// ===== Enregistrer la tentative =====
$stmt = $pdo->prepare("INSERT INTO attempts (evaluation_id, etudiant_id, score) VALUES (?, ?, ?)");
$stmt->execute([$evaluation['id'], $etudiant_id, $score]);

// ===== Mettre à jour la progression de la leçon =====
$stmt = $pdo->prepare("SELECT * FROM progress WHERE etudiant_id = ? AND lesson_id = ?");
$stmt->execute([$etudiant_id, $lesson_id]);
$existing = $stmt->fetch();

$newProgress = $existing ? max((float)$existing['progression_pct'], $score) : $score;
$completed = $newProgress >= 50 ? 1 : 0;

if ($existing) {
    $stmt = $pdo->prepare("UPDATE progress SET progression_pct = ?, completed = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([$newProgress, $completed, $existing['id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO progress (etudiant_id, lesson_id, completed, progression_pct, completed_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$etudiant_id, $lesson_id, $completed, $newProgress]);
}

// ===== Vérifier si le module entier est validé -> émettre un certificat =====
$stmt = $pdo->prepare("
    SELECT l2.id,
        COALESCE((SELECT progression_pct FROM progress p WHERE p.etudiant_id = ? AND p.lesson_id = l2.id), 0) AS prog
    FROM lessons l2
    INNER JOIN courses c2 ON c2.id = l2.course_id
    WHERE c2.module_id = ?
");
$stmt->execute([$etudiant_id, $module_id]);
$allLessons = $stmt->fetchAll();

$allValidated = count($allLessons) > 0;
foreach ($allLessons as $al) {
    if ((float)$al['prog'] < 50) { $allValidated = false; break; }
}

$certCode = null;
if ($allValidated) {
    $stmt = $pdo->prepare("SELECT code_unique FROM certificates WHERE etudiant_id = ? AND module_id = ?");
    $stmt->execute([$etudiant_id, $module_id]);
    $existingCert = $stmt->fetch();

    if ($existingCert) {
        $certCode = $existingCert['code_unique'];
    } else {
        $certCode = 'CERT-' . strtoupper(bin2hex(random_bytes(4))) . '-' . $module_id . $etudiant_id;
        $stmt = $pdo->prepare("INSERT INTO certificates (etudiant_id, module_id, code_unique) VALUES (?, ?, ?)");
        $stmt->execute([$etudiant_id, $module_id, $certCode]);
    }
}

// ===== Calcul de la progression globale du module (pour mise à jour live) =====
$totalLessons = count($allLessons);
$sumProgress = array_sum(array_column($allLessons, 'prog'));
// remplacer la valeur de la leçon courante par la nouvelle progression dans le calcul
foreach ($allLessons as &$al) {
    if ((int)$al['id'] === $lesson_id) $al['prog'] = $newProgress;
}
unset($al);
$sumProgress = array_sum(array_column($allLessons, 'prog'));
$moduleProgress = $totalLessons > 0 ? round($sumProgress / $totalLessons, 1) : 0;

// ===== Réponse JSON =====
echo json_encode([
    'success'          => true,
    'score'            => $score,
    'correct'          => $correctCount,
    'total'            => $totalQuestions,
    'passed'           => $score >= 50,
    'lesson_progress'  => $newProgress,
    'module_progress'  => $moduleProgress,
    'module_validated' => $allValidated,
    'certificate_code' => $certCode,
    'detail'           => $detail,
]);