<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /pages/admin_dashboard.php");
    } else {
        header("Location: /pages/user_dashboard.php");
    }
    exit;
} else {
    // Always redirect to /pages/login using absolute path
    header("Location: /pages/login");
    exit;
}
?>