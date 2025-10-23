<?php
// Ensure no whitespace before this line
session_start();
ob_start(); // Start output buffering to catch unintended output

// Enable error logging, disable display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../debug.log');
error_reporting(E_ALL);

try {
    // Include database connection
    include '../config/conn.php';

    // Set JSON content type
    header('Content-Type: application/json');

    // Initialize response array
    $response = ['success' => false, 'message' => ''];

    // Check if this is a payment request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method. Expected POST.';
        echo json_encode($response);
        exit;
    }

    if (!isset($_POST['pay_violation']) || $_POST['pay_violation'] !== '1') {
        $response['message'] = 'Invalid request. Missing pay_violation parameter.';
        echo json_encode($response);
        exit;
    }

    $violation_id = $_POST['id'] ?? null;

    // Validate input
    if (!$violation_id || !is_numeric($violation_id)) {
        $response['message'] = 'Invalid or missing violation ID.';
        echo json_encode($response);
        exit;
    }

    // Verify PDO connection
    if (!$pdo) {
        $response['message'] = 'Database connection failed.';
        echo json_encode($response);
        exit;
    }

    // Verify the violation exists and is unpaid
    $stmt = $pdo->prepare("SELECT is_paid FROM violations WHERE id = ?");
    $stmt->execute([$violation_id]);
    $violation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$violation) {
        $response['message'] = 'Violation not found.';
        echo json_encode($response);
        exit;
    }

    if ($violation['is_paid']) {
        $response['message'] = 'This violation is already paid.';
        echo json_encode($response);
        exit;
    }

    // Update the is_paid column
    $stmt = $pdo->prepare("UPDATE violations SET is_paid = 2 WHERE id = ?");
    $stmt->execute([$violation_id]);

    // Check if the update was successful
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Payment processed successfully.';
    } else {
        $response['message'] = 'Failed to update payment status.';
    }
} catch (Exception $e) {
    // Catch any unexpected errors (e.g., database or PHP errors)
    $response['message'] = 'Server error: ' . $e->getMessage();
    file_put_contents('../debug.log', "Payment Error: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Clear output buffer to prevent unintended output
ob_end_clean();

// Output JSON response
echo json_encode($response);
exit;
?>