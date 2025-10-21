<?php
// search_violations_by_plate.php
ob_start();
session_start();
header('Content-Type: application/json');

include '../config/conn.php';

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['officer', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$plate = trim($_POST['plate_number'] ?? '');
if (empty($plate)) {
    echo json_encode(['success' => false, 'message' => 'Plate required']);
    exit;
}

try {
    // Get violations grouped by type with offense count
    $stmt = $pdo->prepare("
        SELECT 
            t.id, 
            t.violation_type, 
            t.fine_amount,
            COUNT(*) as offense_count,
            MAX(v.issued_date) as last_offense
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        WHERE UPPER(v.plate_number) = UPPER(?) 
          AND v.officer_id = ?
        GROUP BY t.id, t.violation_type, t.fine_amount
        ORDER BY offense_count DESC, last_offense DESC
    ");
    $stmt->execute([$plate, $_SESSION['user_id']]);
    $violationStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = array_sum(array_column($violationStats, 'offense_count'));

    echo json_encode([
        'success' => true,
        'count' => (int)$total,
        'plate' => strtoupper($plate),
        'stats' => $violationStats // Enhanced: includes count per type
    ], JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log("search_violations_by_plate error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
ob_end_flush();
exit;
?>