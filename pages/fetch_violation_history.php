<?php
header('Content-Type: application/json');
include '../config/conn.php';

try {
    $plate_number = $_POST['plate_number'] ?? '';

    if (empty($plate_number)) {
        echo json_encode(['success' => false, 'message' => 'Plate number is required']);
        exit;
    }

    $query = "
        SELECT t.id AS violation_type_id, t.violation_type, t.fine_amount, v.issued_date, v.status
        FROM violations v
        JOIN types t ON v.violation_type_id = t.id
        WHERE v.plate_number = :plate_number
        AND (
            t.violation_type LIKE '%1st Offense%'
            OR t.violation_type LIKE '%2nd Offense%'
            OR t.violation_type LIKE '%3rd Offense%'
        )
        ORDER BY v.issued_date DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['plate_number' => $plate_number]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'violations' => $violations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>  