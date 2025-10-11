<?php
session_start();
include '../config/conn.php';

header('Content-Type: application/json');

file_put_contents('../debug.log', "get_user_by_plate.php accessed. Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
    $error = "Unauthorized access. Session: " . print_r($_SESSION, true);
    file_put_contents('../debug.log', $error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate_number'])) {
    try {
        $plate_number = trim($_POST['plate_number']);
        file_put_contents('../debug.log', "Fetching data for plate_number: '$plate_number'\n", FILE_APPEND);
        
        if (empty($plate_number)) {
            file_put_contents('../debug.log', "Plate number is empty\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Plate number is required']);
            exit;
        }

        // Fetch the most recent violation for the plate number
        $stmt = $pdo->prepare("
            SELECT v.user_id, v.violator_name, v.has_license, v.license_number, u.contact_number, u.email
            FROM violations v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE v.plate_number = ? AND v.officer_id = ?
            ORDER BY v.issued_date DESC
            LIMIT 1
        ");
        $stmt->execute([$plate_number, $_SESSION['user_id']]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);

        file_put_contents('../debug.log', "Query result: " . print_r($violation, true) . "\n", FILE_APPEND);

        if ($violation) {
            echo json_encode([
                'success' => true,
                'user_id' => $violation['user_id'] ?: '',
                'violator_name' => $violation['violator_name'] ?: '',
                'contact_number' => $violation['contact_number'] ?: '',
                'email' => $violation['email'] ?: '',
                'has_license' => $violation['has_license'] ?: 0,
                'license_number' => $violation['license_number'] ?: ''
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No violation found for this plate number']);
        }
    } catch (PDOException $e) {
        file_put_contents('../debug.log', "Fetch User by Plate Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $e->getMessage()]);
    }
} else {
    file_put_contents('../debug.log', "Invalid request. Method: {$_SERVER['REQUEST_METHOD']}, POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>