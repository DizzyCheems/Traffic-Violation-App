<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $dashboard = ($_SESSION['role'] === 'admin') 
        ? '/pages/admin_dashboard.php' 
        : '/pages/user_dashboard.php';
    header("Location: $dashboard");
    exit;
} else {
    // This will ALWAYS go to http://your-ip/pages/login
    header("Location: /pages/login");
    exit;
}
?>