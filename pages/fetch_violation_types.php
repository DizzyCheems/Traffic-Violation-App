<?php
// fetch_violation_types.php
header('Content-Type: application/json');
require_once '../config/conn.php';

try {
    $stmt = $pdo->query("
        SELECT id, violation_type, fine_amount, description, base_offense,
               CASE 
                   WHEN violation_type LIKE '%1st Offense%' THEN 1
                   WHEN violation_type LIKE '%2nd Offense%' THEN 2
                   WHEN violation_type LIKE '%3rd Offense%' THEN 3
                   ELSE 0 
               END AS offense_level
        FROM types 
        WHERE base_offense IS NOT NULL OR base_offense = ''
        ORDER BY base_offense, offense_level
    ");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($types as &$type) {
        $type['fine_amount'] = number_format((float)$type['fine_amount'], 2, '.', '');
        $type['base_offense'] = $type['base_offense'] ?: $type['violation_type'];
    }

    echo json_encode($types);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Error', 'details' => $e->getMessage()]);
}
?>