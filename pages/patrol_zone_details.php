<?php
session_start();
include '../config/conn.php';

// Debug: Log session data
file_put_contents('../debug.log', "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'officer') {
    $reason = "Redirecting to login.php. ";
    if (!isset($_SESSION['user_id'])) {
        $reason .= "user_id not set. ";
    }
    if (!isset($_SESSION['role'])) {
        $reason .= "role not set. ";
    }
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) !== 'officer') {
        $reason .= "role is '" . $_SESSION['role'] . "' instead of 'officer'.";
    }
    file_put_contents('../debug.log', $reason . "\n", FILE_APPEND);
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Check database connection
if (!$pdo) {
    $toastr_messages[] = "toastr.error('Database connection failed.');";
    file_put_contents('../debug.log', "Database connection failed.\n", FILE_APPEND);
}

// Fetch officer details
$officer_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$officer_id]);
    $officer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officer details: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Officer Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $officer = ['full_name' => 'Unknown', 'username' => 'Unknown'];
}

// Fetch assigned patrol zones
try {
    $stmt = $pdo->prepare("
        SELECT id, zone_name, coordinates, hotspots, urgency, assigned_date 
        FROM patrol_zones 
        WHERE officer_id = ? 
        ORDER BY assigned_date DESC
    ");
    $stmt->execute([$officer_id]);
    $patrol_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON fields for display
    foreach ($patrol_zones as &$zone) {
        $zone['coordinates'] = json_decode($zone['coordinates'], true) ?: ($zone['coordinates'] ?: 'Not specified');
        $zone['hotspots'] = json_decode($zone['hotspots'], true) ?: ($zone['hotspots'] ?: 'Not specified');
    }
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching patrol zones: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Patrol Zones Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $patrol_zones = [];
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
                            <a class="nav-link" href="../pages/officer_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Officer Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/all_violations.php">
                                <i class="fas fa-list me-2"></i>
                                All Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/patrol_zone_details.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Patrol Zone Details
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
                            <a class="nav-link" href="../pages/officer_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Officer Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/all_violations.php">
                                <i class="fas fa-list me-2"></i>
                                All Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/patrol_zone_details.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Patrol Zone Details
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
                    <h1 class="h2 text-primary">Patrol Zone Details - <?php echo htmlspecialchars($officer['full_name']); ?></h1>
                    <div>
                        <a href="../pages/officer_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <!-- Patrol Zones -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Assigned Patrol Zones</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patrol_zones)): ?>
                            <p class="text-center text-muted">No patrol zones assigned.</p>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($patrol_zones as $zone): ?>
                                    <div class="col-md-6">
                                        <div class="card shadow-sm h-100">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($zone['zone_name']); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Urgency:</strong> 
                                                    <span class="badge <?php echo $zone['urgency'] === 'High' ? 'bg-danger' : ($zone['urgency'] === 'Medium' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                        <?php echo htmlspecialchars($zone['urgency']); ?>
                                                    </span>
                                                </p>
                                                <p><strong>Assigned Date:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($zone['assigned_date']))); ?></p>
                                                <p><strong>Coordinates:</strong></p>
                                                <?php if (is_array($zone['coordinates'])): ?>
                                                    <ul class="list-group list-group-flush mb-3">
                                                        <?php foreach ($zone['coordinates'] as $coord): ?>
                                                            <li class="list-group-item">Lat: <?php echo htmlspecialchars($coord['lat']); ?>, Lng: <?php echo htmlspecialchars($coord['lng']); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p><?php echo htmlspecialchars($zone['coordinates']); ?></p>
                                                <?php endif; ?>
                                                <p><strong>Hotspots:</strong></p>
                                                <?php if (is_array($zone['hotspots'])): ?>
                                                    <ul class="list-group list-group-flush mb-3">
                                                        <?php foreach ($zone['hotspots'] as $hotspot): ?>
                                                            <li class="list-group-item">
                                                                <strong>Lat:</strong> <?php echo htmlspecialchars($hotspot['lat']); ?>,
                                                                <strong>Lng:</strong> <?php echo htmlspecialchars($hotspot['lng']); ?><br>
                                                                <strong>Description:</strong> <?php echo htmlspecialchars($hotspot['desc']); ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p><?php echo htmlspecialchars($zone['hotspots']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
    </script>
</body>
</html>