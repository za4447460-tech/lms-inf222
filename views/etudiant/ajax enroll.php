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
$module_id = (int)($_POST['module_id'] ?? 0);

if ($module_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Module invalide.']);
    exit;
}

// Vérifier que le module existe
$stmt = $pdo->prepare("SELECT id, titre FROM modules WHERE id = ?");
$stmt->execute([$module_id]);
$module = $stmt->fetch();

if (!$module) {
    http_response_code(404);
    echo json_encode(['error' => 'Module introuvable.']);
    exit;
}

// Vérifier si déjà inscrit
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE etudiant_id = ? AND module_id = ?");
$stmt->execute([$etudiant_id, $module_id]);

if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'already_enrolled' => true]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO enrollments (etudiant_id, module_id) VALUES (?, ?)");
$stmt->execute([$etudiant_id, $module_id]);

echo json_encode([
    'success' => true,
    'already_enrolled' => false,
    'module_titre' => $module['titre'],
]);