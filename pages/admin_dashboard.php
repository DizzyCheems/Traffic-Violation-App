<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

// Initialize toastr messages
$toastr_messages = [];

// Check database connection
if (!$pdo) {
    $toastr_messages[] = "toastr.error('Database connection failed.');";
    file_put_contents('../debug.log', "Database connection failed.\n", FILE_APPEND);
}

// Function to check if violation type already exists (case-insensitive)
function violationTypeExists($pdo, $violation_type, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM types WHERE LOWER(violation_type) = LOWER(?) AND id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$violation_type, $exclude_id ?? 0]);
    return $stmt->fetchColumn() > 0;
}

// Function to check if violation type is in use
function isViolationTypeInUse($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM violations WHERE violation_type_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() > 0;
}

// Handle create violation type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_violation_type'])) {
    try {
        $violation_type = trim($_POST['violation_type'] ?? '');
        $fine_amount = trim($_POST['fine_amount'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;

        // Log received input
        file_put_contents('../debug.log', "Create Violation Type Input: violation_type='$violation_type', fine_amount='$fine_amount', description='$description'\n", FILE_APPEND);

        if (empty($violation_type) || empty($fine_amount)) {
            $toastr_messages[] = "toastr.error('Violation Type and Fine Amount are required.');";
        } elseif (strlen($violation_type) > 100) {
            $toastr_messages[] = "toastr.error('Violation Type must not exceed 100 characters.');";
        } elseif (!is_numeric($fine_amount) || $fine_amount < 0) {
            $toastr_messages[] = "toastr.error('Fine Amount must be a non-negative number.');";
        } elseif (violationTypeExists($pdo, $violation_type)) {
            $toastr_messages[] = "toastr.error('Violation Type already exists (case-insensitive).');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO types (violation_type, fine_amount, description) VALUES (?, ?, ?)");
            $params = [htmlspecialchars($violation_type), (float)$fine_amount, $description];
            file_put_contents('../debug.log', "Create Violation Type Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Violation type created successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to create violation type. No rows affected.');";
                file_put_contents('../debug.log', "Create Violation Type Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error creating violation type: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Create Violation Type Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle edit violation type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_violation_type'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $violation_type = trim($_POST['violation_type'] ?? '');
        $fine_amount = trim($_POST['fine_amount'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;

        // Log received input
        file_put_contents('../debug.log', "Edit Violation Type Input: id='$id', violation_type='$violation_type', fine_amount='$fine_amount', description='$description'\n", FILE_APPEND);

        if (empty($id) || empty($violation_type) || empty($fine_amount)) {
            $toastr_messages[] = "toastr.error('ID, Violation Type, and Fine Amount are required.');";
        } elseif (strlen($violation_type) > 100) {
            $toastr_messages[] = "toastr.error('Violation Type must not exceed 100 characters.');";
        } elseif (!is_numeric($fine_amount) || $fine_amount < 0) {
            $toastr_messages[] = "toastr.error('Fine Amount must be a non-negative number.');";
        } elseif (violationTypeExists($pdo, $violation_type, $id)) {
            $toastr_messages[] = "toastr.error('Violation Type already exists (case-insensitive).');";
        } else {
            $stmt = $pdo->prepare("UPDATE types SET violation_type = ?, fine_amount = ?, description = ? WHERE id = ?");
            $params = [htmlspecialchars($violation_type), (float)$fine_amount, $description, $id];
            file_put_contents('../debug.log', "Edit Violation Type Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Violation type updated successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to update violation type. No rows affected.');";
                file_put_contents('../debug.log', "Edit Violation Type Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating violation type: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Edit Violation Type Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle delete violation type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_violation_type'])) {
    try {
        $id = trim($_POST['id'] ?? '');

        // Log received input
        file_put_contents('../debug.log', "Delete Violation Type Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            $toastr_messages[] = "toastr.error('Violation Type ID is required.');";
        } elseif (isViolationTypeInUse($pdo, $id)) {
            $toastr_messages[] = "toastr.error('Cannot delete violation type: it is referenced in existing violations.');";
        } else {
            $stmt = $pdo->prepare("DELETE FROM types WHERE id = ?");
            $success = $stmt->execute([$id]);
            if ($success && $stmt->rowCount() > 0) {
                $toastr_messages[] = "Swal.fire({
                    title: 'Success!',
                    text: 'Violation type deleted successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => { window.location.reload(); });";
            } else {
                $toastr_messages[] = "toastr.error('Failed to delete violation type. No rows affected.');";
                file_put_contents('../debug.log', "Delete Violation Type Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error deleting violation type: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Delete Violation Type Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch users
try {
    $stmt = $pdo->query("SELECT id, username, full_name, role, created_at FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $users = [];
}

// Fetch violation types
try {
    $stmt = $pdo->query("SELECT id, violation_type, fine_amount, description FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
}

// Fetch all violations
try {
    $stmt = $pdo->query("SELECT v.id, v.violator_name, v.plate_number, v.reason, t.violation_type, 
                         t.fine_amount, v.issued_date, v.has_license, v.is_impounded, v.is_paid, 
                         v.or_number, v.status, v.notes, u.full_name as user_name 
                         FROM violations v 
                         JOIN types t ON v.violation_type_id = t.id 
                         JOIN users u ON v.user_id = u.id 
                         ORDER BY v.issued_date DESC");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
}

// Fetch system health
try {
    $stmt = $pdo->query("SELECT uptime, api_status FROM system_health ORDER BY last_updated DESC LIMIT 1");
    $system_health = $stmt->fetch(PDO::FETCH_ASSOC);
    $system_uptime = $system_health['uptime'] ?? 'N/A';
    $api_status = $system_health['api_status'] ?? 'N/A';
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching system health: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $system_uptime = 'N/A';
    $api_status = 'N/A';
}

// Fetch total revenue for current month
$current_month = date('Y-m');
try {
    $stmt = $pdo->prepare("SELECT total_revenue FROM revenue_metrics WHERE month_year = ?");
    $stmt->execute([$current_month]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $revenue['total_revenue'] ? '₱' . number_format($revenue['total_revenue'], 2) : '₱0.00';
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching revenue: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $total_revenue = '₱0.00';
}

// Fetch open concerns
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM concerns WHERE status = 'OPEN'");
    $open_concerns = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching open concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $open_concerns = 0;
}

// Fetch officer status
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM officer_status GROUP BY status");
    $officer_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $active_officers = 0;
    $offline_officers = 0;
    foreach ($officer_counts as $count) {
        if ($count['status'] === 'ONLINE') {
            $active_officers = $count['count'];
        } elseif ($count['status'] === 'OFFLINE') {
            $offline_officers = $count['count'];
        }
    }
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officer status: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $active_officers = 0;
    $offline_officers = 0;
}

// Fetch violation analytics for top violation
try {
    $stmt = $pdo->prepare("SELECT t.violation_type, va.percentage 
                           FROM violation_analytics va 
                           JOIN types t ON va.violation_type_id = t.id 
                           WHERE va.month_year = ? 
                           ORDER BY va.percentage DESC LIMIT 1");
    $stmt->execute([$current_month]);
    $top_violation_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $top_violation = $top_violation_data ? "{$top_violation_data['violation_type']} ({$top_violation_data['percentage']}%)" : 'N/A';
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation analytics: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $top_violation = 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <!-- Toggle button for offcanvas sidebar (mobile only) -->
        <button class="btn btn-primary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="fas fa-bars"></i> Menu
        </button>
        <div class="row">
            <!-- Sidebar (visible on desktop, offcanvas on mobile) -->
            <nav class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/monitor_violations.php">
                                <i class="fas fa-list me-2"></i>
                                Monitor Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/violation_report.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Violation Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/violation_heatmap.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Violation Heatmap
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/set_fines.php">
                                <i class="fas fa-dollar-sign me-2"></i>
                                Set Fines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_concerns.php">
                                <i class="fas fa-comment-dots me-2"></i>
                                Manage Complaints
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/officer_performance.php">
                                <i class="fas fa-star me-2"></i>
                                Officer Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/audit_log.php">
                                <i class="fas fa-file-alt me-2"></i>
                                Audit Log
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/holiday_rules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Holiday Rules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/appeal_workflow.php">
                                <i class="fas fa-gavel me-2"></i>
                                Appeal Workflow
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/database_backup.php">
                                <i class="fas fa-database me-2"></i>
                                Database Backup
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="offcanvas offcanvas-start sidebar d-lg-none" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="sidebarMenuLabel">Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/monitor_violations.php">
                                <i class="fas fa-list me-2"></i>
                                Monitor Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/violation_report.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Violation Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/violation_heatmap.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Violation Heatmap
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/set_fines.php">
                                <i class="fas fa-dollar-sign me-2"></i>
                                Set Fines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_concerns.php">
                                <i class="fas fa-comment-dots me-2"></i>
                                Manage Complaints
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/officer_performance.php">
                                <i class="fas fa-star me-2"></i>
                                Officer Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/audit_log.php">
                                <i class="fas fa-file-alt me-2"></i>
                                Audit Log
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/holiday_rules.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Holiday Rules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/appeal_workflow.php">
                                <i class="fas fa-gavel me-2"></i>
                                Appeal Workflow
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/database_backup.php">
                                <i class="fas fa-database me-2"></i>
                                Database Backup
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- Main content -->
            <main class="col-12 col-md-9 col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Admin Dashboard</h1>
                    <div>
                        <a href="../index.php" class="btn btn-outline-primary">Back to Home</a>
                    </div>
                </div>

                <!-- Dashboard Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">System Health</h5>
                                <p class="card-text">Uptime: <?php echo htmlspecialchars($system_uptime); ?> <span class="text-success">✅</span></p>
                                <p class="card-text">API Status: <?php echo htmlspecialchars($api_status); ?> <span class="text-success">✅</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Violation Collection</h5>
                                <p class="card-text">MTD: <?php echo htmlspecialchars($total_revenue); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Open Concerns</h5>
                                <p class="card-text"><?php echo htmlspecialchars($open_concerns); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Active Officers</h5>
                                <p class="card-text"><?php echo htmlspecialchars($active_officers); ?> Online <span class="text-success">✅</span></p>
                                <p class="card-text"><?php echo htmlspecialchars($offline_officers); ?> Offline <span class="text-danger">🔴</span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Section -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Revenue Collection Analytics</h5>
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo htmlspecialchars($top_violation_data['percentage'] ?? 0); ?>%;" aria-valuenow="<?php echo htmlspecialchars($top_violation_data['percentage'] ?? 0); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <p class="card-text">Top Violation: <?php echo htmlspecialchars($top_violation); ?></p>
                                <a href="../pages/violation_report.php" class="btn btn-outline-primary btn-sm">📊 Generate Full Report</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Violation Heatmap</h5>
                                <p class="card-text">Shows high-frequency violation zones for patrol allocation.</p>
                                <a href="../pages/violation_heatmap.php" class="btn btn-outline-primary btn-sm">📍 View Interactive Map</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions and Rule Management -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Admin Actions</h5>
                                <ul class="list-unstyled">
                                    <li><a href="../pages/manage_users.php" class="text-decoration-none link-primary">▪ Manage All Users</a></li>
                                    <li><a href="../pages/audit_log.php" class="text-decoration-none link-primary">▪ View Audit Log</a></li>
                                    <li><a href="../pages/officer_performance.php" class="text-decoration-none link-primary">▪ Officer Performance</a></li>
                                    <li><a href="../pages/database_backup.php" class="text-decoration-none link-primary">▪ Database Backup</a></li>
                                    <li><a href="../pages/issue_violation.php" class="text-decoration-none link-primary">▪ Issue Traffic Violation</a></li>
                                    <li><a href="../pages/manage_concerns.php" class="text-decoration-none link-primary">▪ Manage Complaints</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Rule Management</h5>
                                <ul class="list-unstyled">
                                    <li><a href="#" class="text-decoration-none link-primary" data-bs-toggle="modal" data-bs-target="#createViolationTypeModal">▪ Configure Violation Types</a></li>
                                    <li><a href="../pages/set_fines.php" class="text-decoration-none link-primary">▪ Set Fine Amounts</a></li>
                                    <li><a href="../pages/holiday_rules.php" class="text-decoration-none link-primary">▪ Holiday Rules</a></li>
                                    <li><a href="../pages/appeal_workflow.php" class="text-decoration-none link-primary">▪ Edit Appeal Workflow</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Violations Section -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="../pages/monitor_violations.php" class="btn btn-info">Monitor Violations</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Violator Name</th>
                                        <th>Plate Number</th>
                                        <th>Violation Type</th>
                                        <th>Reason</th>
                                        <th>Fine Amount</th>
                                        <th>Issue Date</th>
                                        <th>License</th>
                                        <th>Impounded</th>
                                        <th>Paid</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($violations)): ?>
                                        <tr><td colspan="12" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['reason']); ?></td>
                                                <td>₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($violation['issued_date']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $violation['has_license'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $violation['has_license'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $violation['is_impounded'] ? 'bg-warning' : 'bg-success'; ?>">
                                                        <?php echo $violation['is_impounded'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $violation['is_paid'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $violation['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($violation['status'] ?: 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No users found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Violation Types Section -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violation Types</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createViolationTypeModal">Add Violation Type</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Violation Type</th>
                                        <th>Fine Amount</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($types)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No violation types found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($types as $type): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($type['id']); ?></td>
                                                <td><?php echo htmlspecialchars($type['violation_type']); ?></td>
                                                <td>₱<?php echo htmlspecialchars(number_format($type['fine_amount'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($type['description'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editViolationTypeModal<?php echo $type['id']; ?>">Edit</button>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteViolationTypeModal<?php echo $type['id']; ?>">Delete</button>
                                                    <form method="POST" style="display: none;" class="delete-violation-type-form" id="deleteViolationTypeForm<?php echo $type['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                        <input type="hidden" name="delete_violation_type" value="1">
                                                    </form>
                                                </td>
                                            </tr>
                                            <!-- Edit Violation Type Modal -->
                                            <div class="modal fade" id="editViolationTypeModal<?php echo $type['id']; ?>" tabindex="-1" aria-labelledby="editViolationTypeModalLabel<?php echo $type['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editViolationTypeModalLabel<?php echo $type['id']; ?>">Edit Violation Type: <?php echo htmlspecialchars($type['violation_type']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-violation-type-form">
                                                                <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="violation_type" id="violation_type_<?php echo $type['id']; ?>" required value="<?php echo htmlspecialchars($type['violation_type']); ?>" maxlength="100" />
                                                                    <label class="form-label" for="violation_type_<?php echo $type['id']; ?>">Violation Type (max 100 characters)</label>
                                                                    <div class="invalid-feedback">Please enter a valid violation type (1-100 characters).</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="fine_amount" id="fine_amount_<?php echo $type['id']; ?>" required value="<?php echo htmlspecialchars($type['fine_amount']); ?>" />
                                                                    <label class="form-label" for="fine_amount_<?php echo $type['id']; ?>">Fine Amount (₱)</label>
                                                                    <div class="invalid-feedback">Please enter a valid non-negative number.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="description" id="description_<?php echo $type['id']; ?>" rows="4"><?php echo htmlspecialchars($type['description'] ?: ''); ?></textarea>
                                                                    <label class="form-label" for="description_<?php echo $type['id']; ?>">Description</label>
                                                                </div>
                                                                <button type="submit" name="edit_violation_type" class="btn btn-primary">Update Violation Type</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Delete Violation Type Modal -->
                                            <div class="modal fade" id="deleteViolationTypeModal<?php echo $type['id']; ?>" tabindex="-1" aria-labelledby="deleteViolationTypeModalLabel<?php echo $type['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteViolationTypeModalLabel<?php echo $type['id']; ?>">Confirm Delete: <?php echo htmlspecialchars($type['violation_type']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the violation type "<strong><?php echo htmlspecialchars($type['violation_type']); ?></strong>"? This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteViolationTypeForm<?php echo $type['id']; ?>').submit();">Delete</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Create Violation Type Modal -->
                <div class="modal fade" id="createViolationTypeModal" tabindex="-1" aria-labelledby="createViolationTypeModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createViolationTypeModalLabel">Add New Violation Type</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline create-violation-type-form" id="createViolationTypeForm">
                                    <input type="hidden" name="create_violation_type" value="1">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="violation_type" id="violation_type" required maxlength="100" />
                                        <label class="form-label" for="violation_type">Violation Type (max 100 characters)</label>
                                        <div class="invalid-feedback">Please enter a valid violation type (1-100 characters).</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="number" step="0.01" min="0" class="form-control" name="fine_amount" id="fine_amount" required />
                                        <label class="form-label" for="fine_amount">Fine Amount (₱)</label>
                                        <div class="invalid-feedback">Please enter a valid non-negative number.</div>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                                        <label class="form-label" for="description">Description</label>
                                    </div>
                                    <button type="submit" name="create_violation_type" class="btn btn-primary">Create Violation Type</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
    <script>
        // Initialize Toastr
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };

        // Display Toastr messages
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        // Client-side validation for Create Violation Type Form
        document.getElementById('createViolationTypeForm').addEventListener('submit', function(e) {
            console.log('Create violation type form submission attempted');
            const violationType = document.getElementById('violation_type').value.trim();
            const fineAmount = document.getElementById('fine_amount').value.trim();

            let isValid = true;

            // Reset validation states
            document.getElementById('violation_type').classList.remove('is-invalid');
            document.getElementById('fine_amount').classList.remove('is-invalid');

            if (!violationType || violationType.length > 100) {
                document.getElementById('violation_type').classList.add('is-invalid');
                isValid = false;
            }

            if (!fineAmount || isNaN(fineAmount) || parseFloat(fineAmount) < 0) {
                document.getElementById('fine_amount').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for create violation type');
                e.preventDefault();
                return;
            }

            // SweetAlert2 confirmation
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to create this violation type?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Create violation type form submission confirmed');
                    this.submit();
                } else {
                    console.log('Create violation type form submission canceled');
                }
            });
        });

        // Client-side validation for Edit Violation Type Forms
        document.querySelectorAll('.edit-violation-type-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit violation type form submission attempted');
                const violationType = this.querySelector('input[name="violation_type"]').value.trim();
                const fineAmount = this.querySelector('input[name="fine_amount"]').value.trim();

                let isValid = true;

                // Reset validation states
                this.querySelector('input[name="violation_type"]').classList.remove('is-invalid');
                this.querySelector('input[name="fine_amount"]').classList.remove('is-invalid');

                if (!violationType || violationType.length > 100) {
                    this.querySelector('input[name="violation_type"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!fineAmount || isNaN(fineAmount) || parseFloat(fineAmount) < 0) {
                    this.querySelector('input[name="fine_amount"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit violation type');
                    e.preventDefault();
                    return;
                }

                // SweetAlert2 confirmation
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this violation type?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit violation type form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit violation type form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>