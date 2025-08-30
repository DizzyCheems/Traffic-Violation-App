<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: pages/admin_dashboard.php");
    } else {
        header("Location: pages/user_dashboard.php");
    }
    exit;
} else {
    header("Location: pages/login.php");
    exit;
}
?>