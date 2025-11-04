<?php
// get_user_by_license.php
header('Content-Type: application/json');
include '../config/conn.php';

$license_number = trim($_POST['license_number'] ?? '');
$officer_id     = $_POST['officer_id'] ?? '';

if ($license_number === '' || $officer_id === '') {
    echo json_encode(['success' => false, 'message' => 'License number and officer ID required']);
    exit;
}

// Clean license
$clean_license = preg_replace('/[^A-Z0-9]/', '', strtoupper($license_number));

if (strlen($clean_license) !== 12) {
    echo json_encode(['success' => false, 'message' => 'Invalid license format']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id AS user_id, u.full_name AS violator_name, u.contact_number, u.email
        FROM users u
        JOIN violations v ON u.id = v.user_id
        WHERE v.license_number = ? 
          AND v.officer_id = ?
          AND v.has_license = 1
        LIMIT 1
    ");
    $stmt->execute([$clean_license, $officer_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'user_id' => $user['user_id'],
            'violator_name' => $user['violator_name'],
            'contact_number' => $user['contact_number'],
            'email' => $user['email'],
            'has_license' => 1,
            'license_number' => $clean_license
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No user found with this license number']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>