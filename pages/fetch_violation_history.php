<?php
// fetch_violation_history.php
header('Content-Type: application/json');
include '../config/conn.php';

try {
    $plate_number   = trim($_POST['plate_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $officer_id     = $_POST['officer_id'] ?? '';

    if ($officer_id === '') {
        echo json_encode(['success' => false, 'message' => 'Officer ID is required']);
        exit;
    }

    if ($plate_number === '' && $license_number === '') {
        echo json_encode(['success' => false, 'message' => 'Plate number or license number is required']);
        exit;
    }

    // Primary search key: license if provided, otherwise plate
    $whereClause = $license_number !== '' ? 'v.license_number = :search_value' : 'v.plate_number = :search_value';
    $searchValue = $license_number !== '' ? $license_number : $plate_number;

    $sql = "
        SELECT
            v.plate_number,
            v.license_number,
            v.has_license,
            v.violation_type_id,
            t.violation_type,
            t.base_offense,
            t.fine_amount,
            v.issued_date,
            v.status
        FROM violations v
        JOIN types t ON v.violation_type_id = t.id
        WHERE v.officer_id = :officer_id
          AND $whereClause
        ORDER BY v.issued_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':officer_id' => $officer_id,
        ':search_value' => $searchValue
    ]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'violations' => $violations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>