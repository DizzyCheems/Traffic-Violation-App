<?php
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$title = '';
switch ($role) {
    case 'admin':
        $title = 'Admin Panel - Violation Management System';
        break;
    case 'user':
        $title = 'User Panel - Violation Management System';
        break;
    case 'officer':
        $title = 'Officer Panel - Violation Management System';
        break;
    default:
        $title = 'Violation Management System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/dashboards.css">
</head>