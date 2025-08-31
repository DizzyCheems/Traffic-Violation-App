<?php
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">Violation Management System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/admin_dashboard.php">Admin Dashboard</a>
                    </li>
                <?php elseif ($role === 'user'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/user_dashboard.php">User Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/violations.php">Violation History</a>
                    </li>
                <?php elseif ($role === 'officer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/officer_dashboard.php">Officer Dashboard</a>
                    </li>
                <?php endif; ?>
                <?php if ($role !== 'guest'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../login.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>