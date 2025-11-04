<?php
// get_user_by_license.php
session_start();
include '../config/conn.php';

header('Content-Type: application/json');

file_put_contents('../debug.log', "get_user_by_license.php accessed. Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
    $error = "Unauthorized access. Session: " . print_r($_SESSION, true);
    file_put_contents('../debug.log', $error . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_number'])) {
    try {
        $license_number = trim($_POST['license_number']);
        file_put_contents('../debug.log', "Fetching data for license_number: '$license_number'\n", FILE_APPEND);
        
        if (empty($license_number)) {
            file_put_contents('../debug.log', "License number is empty\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'License number is required']);
            exit;
        }

        // Clean license number
        $clean_license = preg_replace('/[^A-Z0-9]/i', '', strtoupper($license_number));
        if (strlen($clean_license) !== 12) {
            file_put_contents('../debug.log', "Invalid license format: '$clean_license'\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Invalid license format']);
            exit;
        }

        // Fetch most recent violation with this license number
        $stmt = $pdo->prepare("
            SELECT v.user_id, v.violator_name, v.has_license, v.plate_number, u.contact_number, u.email
            FROM violations v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE v.license_number = ? 
              AND v.officer_id = ?
              AND v.has_license = 1
            ORDER BY v.issued_date DESC
            LIMIT 1
        ");
        $stmt->execute([$clean_license, $_SESSION['user_id']]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);

        file_put_contents('../debug.log', "Query result: " . print_r($violation, true) . "\n", FILE_APPEND);

        if ($violation) {
            echo json_encode([
                'success' => true,
                'user_id' => $violation['user_id'] ?: '',
                'violator_name' => $violation['violator_name'] ?: '',
                'contact_number' => $violation['contact_number'] ?: '',
                'email' => $violation['email'] ?: '',
                'has_license' => 1,
                'plate_number' => $violation['plate_number'] ?: '',
                'license_number' => $clean_license
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No violation found for this license number']);
        }
    } catch (PDOException $e) {
        file_put_contents('../debug.log', "Fetch User by License Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $e->getMessage()]);
    }
} else {
    file_put_contents('../debug.log', "Invalid request. Method: {$_SERVER['REQUEST_METHOD']}, POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>