<?php
include 'config/conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: pages/login.php");
    exit;
}

$action = $_POST['action'];

if ($action === 'register_violation') {
    $violator_name = $_POST['violator_name'];
    $plate_number = $_POST['plate_number'];
    $remarks = $_POST['remarks'] ?: null;
    $reason = $_POST['reason'];
    $violation_type_id = $_POST['violation_type_id'];
    $user_id = $_SESSION['user_id'];
    $has_license = isset($_POST['has_license']) ? 1 : 0;
    $license_number = $_POST['license_number'] ?: null;
    $is_impounded = $has_license ? 0 : 1; // Impound if no license

    // Insert violation
    $stmt = $pdo->prepare("INSERT INTO violations (violator_name, plate_number, remarks, reason, violation_type_id, user_id, has_license, license_number, is_impounded, issue_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$violator_name, $plate_number, $remarks, $reason, $violation_type_id, $user_id, $has_license, $license_number, $is_impounded]);

    header("Location: pages/user_dashboard.php?success=Violation registered");
    exit;
}
?>