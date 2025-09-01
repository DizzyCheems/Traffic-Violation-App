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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Navigation link styles for sidebar and modules */
        .nav-link {
            transition: all 0.3s ease;
            border-radius: 5px;
            padding: 10px 15px;
            margin: 5px 0;
        }

        /* Hover effect: float, black background, white bold text */
        .nav-link:hover {
            background-color: #000000;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Active link style to maintain consistency */
        .nav-link.active {
            background-color: #007bff;
            color: #ffffff !important;
            font-weight: bold;
        }

        /* Ensure modal trigger links (e.g., Report Concern, Apply for Appeal) also get the style */
        a[data-bs-toggle="modal"] {
            transition: all 0.3s ease;
            border-radius: 5px;
            padding: 8px 12px;
            display: inline-block;
        }

        a[data-bs-toggle="modal"]:hover {
            background-color: #000000;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Sidebar specific adjustments */
        .sidebar .nav-link {
            color: #333;
        }

        .sidebar .nav-link:hover {
            color: #ffffff !important;
        }

        /* Quick Actions links in user_dashboard */
        .link-primary {
            transition: all 0.3s ease;
        }

        .link-primary:hover {
            background-color: #000000;
            color: #ffffff !important;
            font-weight: bold;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            padding: 8px 12px;
        }
    </style>
</head>