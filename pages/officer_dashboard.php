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

// Function to check if violation type already exists (case-insensitive)
function violationTypeExists($pdo, $violation_type, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM types WHERE LOWER(violation_type) = LOWER(?) AND id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$violation_type, $exclude_id ?? 0]);
    return $stmt->fetchColumn() > 0;
}

// Handle user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    try {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = 'user';
        $created_at = date('Y-m-d H:i:s');

        if (empty($username) || empty($full_name) || empty($password)) {
            $toastr_messages[] = "toastr.error('All fields are required for user registration.');";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $toastr_messages[] = "toastr.error('Username already exists.');";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $password, $role, $created_at]); // Use password_hash in production
                $toastr_messages[] = "toastr.success('User registered successfully.');";
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error registering user: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "User Registration Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
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
            $toastr_messages[] = "toastr.error('Violation type ID is required.');";
        } else {
            $stmt = $pdo->prepare("DELETE FROM types WHERE id = ?");
            $params = [$id];
            file_put_contents('../debug.log', "Delete Violation Type Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Violation type deleted successfully.');";
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

// Handle violation issuance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_violation'])) {
    try {
        $user_id = $_SESSION['user_id']; // Officer issuing the violation
        $violator_name = trim($_POST['violator_name'] ?? '');
        $plate_number = trim($_POST['plate_number'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $violation_type_id = trim($_POST['violation_type_id'] ?? '');
        $has_license = isset($_POST['has_license']) ? 1 : 0;
        $license_number = trim($_POST['license_number'] ?? '') ?: null;
        $is_impounded = isset($_POST['is_impounded']) ? 1 : 0;
        $is_paid = isset($_POST['is_paid']) ? 1 : 0;
        $or_number = trim($_POST['or_number'] ?? '') ?: null;
        $issued_date = trim($_POST['issued_date'] ?? '') ?: date('Y-m-d H:i:s');
        $status = trim($_POST['status'] ?? 'Pending');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        // Log received input
        file_put_contents('../debug.log', "Issue Violation Input: user_id='$user_id', violator_name='$violator_name', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id'\n", FILE_APPEND);

        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id)) {
            $toastr_messages[] = "toastr.error('Violator Name, Plate Number, Reason, and Violation Type are required.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO violations (user_id, violator_name, plate_number, reason, violation_type_id, has_license, license_number, is_impounded, is_paid, or_number, issued_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $params = [$user_id, $violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes];
            file_put_contents('../debug.log', "Issue Violation Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Violation issued successfully.');";
                // Update officer earnings
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $stmt = $pdo->prepare("SELECT fine_amount FROM types WHERE id = ?");
                $stmt->execute([$violation_type_id]);
                $fine = $stmt->fetch(PDO::FETCH_ASSOC)['fine_amount'] ?? 0;
                $stmt = $pdo->prepare("INSERT INTO officer_earnings (officer_id, week_start, total_fines) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total_fines = total_fines + ?");
                $stmt->execute([$user_id, $week_start, $fine, $fine]);
            } else {
                $toastr_messages[] = "toastr.error('Failed to issue violation.');";
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error issuing violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Issue Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
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

// Fetch today's issued violations count
$today = date('Y-m-d');
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND DATE(issued_date) = ?");
    $stmt->execute([$officer_id, $today]);
    $issued_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching today\'s stats: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $issued_today = 0;
}

// Fetch pending tickets (unpaid violations issued by the officer)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND is_paid = 0");
    $stmt->execute([$officer_id]);
    $pending_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching pending tickets: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $pending_tickets = 0;
}

// Fetch assigned concerns
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM concerns WHERE user_id = ? AND status IN ('OPEN', 'IN_PROGRESS')");
    $stmt->execute([$officer_id]);
    $assigned_concerns = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching assigned concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $assigned_concerns = 0;
}

// Fetch weekly earnings
$week_start = date('Y-m-d', strtotime('monday this week'));
try {
    $stmt = $pdo->prepare("SELECT total_fines FROM officer_earnings WHERE officer_id = ? AND week_start = ?");
    $stmt->execute([$officer_id, $week_start]);
    $earnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $wtd_earnings = $earnings['total_fines'] ? '$' . number_format($earnings['total_fines'], 2) : '$0.00';
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching earnings: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $wtd_earnings = '$0.00';
}

// Fetch urgent items (top 2 concerns or violations marked as urgent)
try {
    $stmt = $pdo->prepare("
        (SELECT 'Concern' as type, id, description as title, status FROM concerns WHERE user_id = ? AND status = 'OPEN' LIMIT 1)
        UNION
        (SELECT 'Violation' as type, id, reason as title, status FROM violations WHERE user_id = ? AND status = 'OPEN' LIMIT 1)
    ");
    $stmt->execute([$officer_id, $officer_id]);
    $urgent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching urgent items: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $urgent_items = [];
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

// Fetch users with violations
try {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, COUNT(v.id) as violation_count 
                          FROM users u 
                          LEFT JOIN violations v ON u.id = v.user_id 
                          WHERE u.role = 'user' 
                          GROUP BY u.id 
                          ORDER BY violation_count DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<style>
    body {
        padding-top: 56px; /* Adjust for fixed navbar height */
    }
    .sidebar {
        position: fixed;
        top: 56px; /* Start below navbar */
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        overflow-y: auto;
    }
    .sidebar-sticky {
        position: relative;
        top: 0;
        height: calc(100vh - 56px); /* Full height minus navbar */
        padding-top: .5rem;
        overflow-x: hidden;
        overflow-y: auto;
    }
    @media (max-width: 767.98px) {
        .sidebar {
            position: static;
            height: auto;
            padding: 0;
        }
        .main-content {
            padding-top: 0;
        }
    }
</style>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Officer Dashboard - <?php echo htmlspecialchars($officer['full_name']); ?></h1>
                    <div>
                        <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                            <i class="fas fa-bars"></i>
                        </button>
                        <a href="../index.php" class="btn btn-outline-primary">Back to Home</a>
                    </div>
                </div>

                <!-- Dashboard Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Today's Stats</h5>
                                <p class="card-text">Issued: <?php echo htmlspecialchars($issued_today); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Pending Tickets</h5>
                                <p class="card-text">To Print: <?php echo htmlspecialchars($pending_tickets); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Assigned Items</h5>
                                <p class="card-text">Concerns: <?php echo htmlspecialchars($assigned_concerns); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Earnings (WTD)</h5>
                                <p class="card-text"><?php echo htmlspecialchars($wtd_earnings); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issue Violation and Patrol Map -->
                <div class="row g-4 mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">Issue Violation (Quick)</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="form-outline issue-violation-form" enctype="multipart/form-data">
                                    <input type="hidden" name="issue_violation" value="1">
                                    <div class="mb-3">
                                        <label for="plate_number" class="form-label">License Plate</label>
                                        <input type="text" class="form-control" id="plate_number" name="plate_number" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="violation_type_id" class="form-label">Violation Type</label>
                                        <select class="form-select" id="violation_type_id" name="violation_type_id" required>
                                            <option value="">Select</option>
                                            <?php foreach ($types as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                                    <?php echo htmlspecialchars($type['violation_type']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="violator_name" class="form-label">Violator Name</label>
                                        <input type="text" class="form-control" id="violator_name" name="violator_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">Reason</label>
                                        <input type="text" class="form-control" id="reason" name="reason" required>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="has_license" id="has_license">
                                            <label class="form-check-label" for="has_license">Has License</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="license_number" class="form-label">License Number</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number">
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded">
                                            <label class="form-check-label" for="is_impounded">Is Impounded</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid">
                                            <label class="form-check-label" for="is_paid">Is Paid</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="or_number" class="form-label">OR Number</label>
                                        <input type="text" class="form-control" id="or_number" name="or_number">
                                    </div>
                                    <div class="mb-3">
                                        <label for="issued_date" class="form-label">Issued Date</label>
                                        <input type="datetime-local" class="form-control" id="issued_date" name="issued_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="Pending" selected>Pending</option>
                                            <option value="Resolved">Resolved</option>
                                            <option value="Disputed">Disputed</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <a href="#" class="text-decoration-none link-primary">Add Details & Photos</a>
                                    </div>
                                    <button type="submit" class="btn btn-primary">🚔 Submit Electronic Citation</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Patrol Map</h5>
                                <p class="card-text"><a href="#" class="text-decoration-none link-primary">🗺️ View Active Patrol Zone</a></p>
                                <p class="card-text">📍 Your location is being tracked</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Urgent Items -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Urgent Items</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <?php if (empty($urgent_items)): ?>
                                <li class="text-muted">No urgent items found</li>
                            <?php else: ?>
                                <?php foreach ($urgent_items as $item): ?>
                                    <li>
                                        #<?php echo htmlspecialchars($item['type'][0] . '-' . $item['id']); ?> - 
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
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
                                                <td><?php echo htmlspecialchars(number_format($type['fine_amount'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($type['description'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editViolationTypeModal<?php echo $type['id']; ?>">Edit</button>
                                                    <form method="POST" style="display:inline;" class="delete-violation-type-form" data-id="<?php echo $type['id']; ?>" data-violation-type="<?php echo htmlspecialchars($type['violation_type']); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                        <button type="submit" name="delete_violation_type" class="btn btn-sm btn-danger">Delete</button>
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
                                                                    <label class="form-label" for="fine_amount_<?php echo $type['id']; ?>">Fine Amount</label>
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
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Users with Violations Section -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Users with Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#registerUserModal">Register New User</button>
                            <button class="btn btn-info ms-2" data-bs-toggle="modal" data-bs-target="#issueViolationModal">Issue Violation</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Violation Count</th>
                                        <th>Action</th>
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
                                                <td><?php echo htmlspecialchars($user['violation_count']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#issueViolationModal<?php echo $user['id']; ?>">Issue Violation</button>
                                                </td>
                                            </tr>
                                            <!-- Per-User Issue Violation Modal -->
                                            <div class="modal fade" id="issueViolationModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="issueViolationModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="issueViolationModalLabel<?php echo $user['id']; ?>">Issue Violation for <?php echo htmlspecialchars($user['full_name']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline issue-violation-form">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="violator_name" id="violator_name_<?php echo $user['id']; ?>" required value="<?php echo htmlspecialchars($user['full_name']); ?>" />
                                                                    <label class="form-label" for="violator_name_<?php echo $user['id']; ?>">Violator Name</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="plate_number" id="plate_number_<?php echo $user['id']; ?>" required />
                                                                    <label class="form-label" for="plate_number_<?php echo $user['id']; ?>">Plate Number</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="reason" id="reason_<?php echo $user['id']; ?>" required />
                                                                    <label class="form-label" for="reason_<?php echo $user['id']; ?>">Reason</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="violation_type_id" id="violation_type_id_<?php echo $user['id']; ?>" required>
                                                                        <option value="">Select Violation Type</option>
                                                                        <?php if (empty($types)): ?>
                                                                            <option value="">No violation types available</option>
                                                                        <?php else: ?>
                                                                            <?php foreach ($types as $type): ?>
                                                                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['violation_type']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        <?php endif; ?>
                                                                    </select>
                                                                    <label class="form-label" for="violation_type_id_<?php echo $user['id']; ?>">Violation Type</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <div class="form-check">
                                                                        <input type="checkbox" class="form-check-input" name="has_license" id="has_license_<?php echo $user['id']; ?>" />
                                                                        <label class="form-check-label" for="has_license_<?php echo $user['id']; ?>">Has License</label>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="license_number" id="license_number_<?php echo $user['id']; ?>" />
                                                                    <label class="form-label" for="license_number_<?php echo $user['id']; ?>">License Number</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <div class="form-check">
                                                                        <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded_<?php echo $user['id']; ?>" />
                                                                        <label class="form-check-label" for="is_impounded_<?php echo $user['id']; ?>">Is Impounded</label>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <div class="form-check">
                                                                        <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid_<?php echo $user['id']; ?>" />
                                                                        <label class="form-check-label" for="is_paid_<?php echo $user['id']; ?>">Is Paid</label>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="or_number" id="or_number_<?php echo $user['id']; ?>" />
                                                                    <label class="form-label" for="or_number_<?php echo $user['id']; ?>">OR Number</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="datetime-local" class="form-control" name="issued_date" id="issued_date_<?php echo $user['id']; ?>" value="<?php echo date('Y-m-d\TH:i'); ?>" />
                                                                    <label class="form-label" for="issued_date_<?php echo $user['id']; ?>">Issued Date</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="status" id="status_<?php echo $user['id']; ?>">
                                                                        <option value="Pending" selected>Pending</option>
                                                                        <option value="Resolved">Resolved</option>
                                                                        <option value="Disputed">Disputed</option>
                                                                    </select>
                                                                    <label class="form-label" for="status_<?php echo $user['id']; ?>">Status</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="notes" id="notes_<?php echo $user['id']; ?>" rows="4"></textarea>
                                                                    <label class="form-label" for="notes_<?php echo $user['id']; ?>">Notes</label>
                                                                </div>
                                                                <button type="submit" name="issue_violation" class="btn btn-primary">Issue Violation</button>
                                                            </form>
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

                <!-- Register User Modal -->
                <div class="modal fade" id="registerUserModal" tabindex="-1" aria-labelledby="registerUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="registerUserModalLabel">Register New User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline" id="registerUserForm">
                                    <input type="hidden" name="register_user" value="1">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="username" id="username" required />
                                        <label class="form-label" for="username">Username</label>
                                        <div class="invalid-feedback">Please enter a valid username.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="full_name" id="full_name" required />
                                        <label class="form-label" for="full_name">Full Name</label>
                                        <div class="invalid-feedback">Please enter a valid full name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="password" class="form-control" name="password" id="password" required />
                                        <label class="form-label" for="password">Password</label>
                                        <div class="invalid-feedback">Please enter a password.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Register User</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Issue Violation Modal -->
                <div class="modal fade" id="issueViolationModal" tabindex="-1" aria-labelledby="issueViolationModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="issueViolationModalLabel">Issue New Violation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline issue-violation-form">
                                    <div class="mb-3">
                                        <select class="form-select" name="user_id" id="user_id" required>
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label" for="user_id">User</label>
                                        <div class="invalid-feedback">Please select a user.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="violator_name" id="violator_name" required />
                                        <label class="form-label" for="violator_name">Violator Name</label>
                                        <div class="invalid-feedback">Please enter a violator name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="plate_number" id="plate_number" required />
                                        <label class="form-label" for="plate_number">Plate Number</label>
                                        <div class="invalid-feedback">Please enter a plate number.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="reason" id="reason" required />
                                        <label class="form-label" for="reason">Reason</label>
                                        <div class="invalid-feedback">Please enter a reason.</div>
                                    </div>
                                    <div class="mb-3">
                                        <select class="form-select" name="violation_type_id" id="violation_type_id" required>
                                            <option value="">Select Violation Type</option>
                                            <?php if (empty($types)): ?>
                                                <option value="">No violation types available</option>
                                            <?php else: ?>
                                                <?php foreach ($types as $type): ?>
                                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['violation_type']); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <label class="form-label" for="violation_type_id">Violation Type</label>
                                        <div class="invalid-feedback">Please select a violation type.</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="has_license" id="has_license" />
                                            <label class="form-check-label" for="has_license">Has License</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="license_number" id="license_number" />
                                        <label class="form-label" for="license_number">License Number</label>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded" />
                                            <label class="form-check-label" for="is_impounded">Is Impounded</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid" />
                                            <label class="form-check-label" for="is_paid">Is Paid</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="or_number" id="or_number" />
                                        <label class="form-label" for="or_number">OR Number</label>
                                    </div>
                                    <div class="mb-3">
                                        <input type="datetime-local" class="form-control" name="issued_date" id="issued_date" value="<?php echo date('Y-m-d\TH:i'); ?>" />
                                        <label class="form-label" for="issued_date">Issued Date</label>
                                    </div>
                                    <div class="mb-3">
                                        <select class="form-select" name="status" id="status">
                                            <option value="Pending" selected>Pending</option>
                                            <option value="Resolved">Resolved</option>
                                            <option value="Disputed">Disputed</option>
                                        </select>
                                        <label class="form-label" for="status">Status</label>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="notes" id="notes" rows="4"></textarea>
                                        <label class="form-label" for="notes">Notes</label>
                                    </div>
                                    <button type="submit" name="issue_violation" class="btn btn-primary">Issue Violation</button>
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

        // SweetAlert2 for Delete Violation Type Forms
        document.querySelectorAll('.delete-violation-type-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const violationType = this.getAttribute('data-violation-type');
                console.log(`Delete violation type form submission attempted for ID: ${this.getAttribute('data-id')}, Violation Type: ${violationType}`);
                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you want to delete the violation type "${violationType}"? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log(`Delete confirmed for violation type: ${violationType}`);
                        this.submit();
                    } else {
                        console.log(`Delete canceled for violation type: ${violationType}`);
                    }
                });
            });
        });

        // SweetAlert2 for Register User Form
        document.getElementById('registerUserForm').addEventListener('submit', function(e) {
            console.log('Register user form submission attempted');
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to register this user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, register!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Register user form submission confirmed');
                    this.submit();
                } else {
                    console.log('Register user form submission canceled');
                }
            });
        });

        // SweetAlert2 for Issue Violation Forms
        document.querySelectorAll('.issue-violation-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Issue violation form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to issue this violation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, issue it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Issue violation form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Issue violation form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>