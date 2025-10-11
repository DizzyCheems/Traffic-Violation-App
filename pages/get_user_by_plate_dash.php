<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
include '../config/conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate_number'])) {
    try {
        $plate_number = trim($_POST['plate_number']);
        if (empty($plate_number)) {
            echo json_encode(['success' => false, 'message' => 'Plate number is required']);
            file_put_contents('../debug.log', "Get User by Plate Failed: Plate number is empty\n", FILE_APPEND);
            exit;
        }

        // Query the violations table for the latest violation with this plate number
        $stmt = $pdo->prepare("
            SELECT v.user_id, v.violator_name, v.has_license, v.license_number, u.contact_number, u.email
            FROM violations v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE v.plate_number = ? AND v.officer_id = ?
            ORDER BY v.issued_date DESC
            LIMIT 1
        ");
        $stmt->execute([$plate_number, $_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        file_put_contents('../debug.log', "Get User by Plate Query: plate_number='$plate_number', officer_id='{$_SESSION['user_id']}', result=" . print_r($result, true) . "\n", FILE_APPEND);

        if ($result) {
            echo json_encode([
                'success' => true,
                'user_id' => $result['user_id'],
                'violator_name' => $result['violator_name'],
                'contact_number' => $result['contact_number'],
                'email' => $result['email'],
                'has_license' => $result['has_license'],
                'license_number' => $result['license_number']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No previous violation found for this plate number']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        file_put_contents('../debug.log', "Get User by Plate Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    file_put_contents('../debug.log', "Get User by Plate Failed: Invalid request\n", FILE_APPEND);
}
?>