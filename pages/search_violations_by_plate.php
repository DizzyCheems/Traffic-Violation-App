<?php
// search_violations_by_plate.php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['officer', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$plate = trim($_POST['plate_number'] ?? '');
if (empty($plate)) {
    echo json_encode(['success' => false, 'message' => 'Plate number required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.violation_type, t.fine_amount 
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        WHERE v.plate_number = ? AND v.officer_id = ?
    ");
    $stmt->execute([$plate, $_SESSION['user_id']]);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = $pdo->prepare("SELECT COUNT(*) FROM violations WHERE plate_number = ? AND officer_id = ?");
    $count->execute([$plate, $_SESSION['user_id']]);
    $total = $count->fetchColumn();

    echo json_encode([
        'success' => true,
        'count' => $total,
        'types' => $types
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>