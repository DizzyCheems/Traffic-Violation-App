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

// Check for success messages in session
if (isset($_SESSION['create_success']) && $_SESSION['create_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Created!',
        text: 'Violation has been created successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['create_success']);
}
if (isset($_SESSION['edit_success']) && $_SESSION['edit_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Updated!',
        text: 'Violation has been updated successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['edit_success']);
}
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Deleted!',
        text: 'Violation has been deleted successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['delete_success']);
}

// Handle create violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_violation'])) {
    try {
        $violator_name = trim($_POST['violator_name'] ?? '');
        $user_id = trim($_POST['user_id'] ?? '') ?: null;
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

        file_put_contents('../debug.log', "Create Violation Input: violator_name='$violator_name', user_id='$user_id', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id'\n", FILE_APPEND);

        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id)) {
            $toastr_messages[] = "toastr.error('Violator Name, Plate Number, Reason, and Violation Type are required.');";
        } else {
            // Check if user_id is provided and valid
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND officer_id = ?");
                $stmt->execute([$user_id, $_SESSION['user_id']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing_user || strtolower(trim($existing_user['full_name'])) !== strtolower(trim($violator_name))) {
                    $toastr_messages[] = "toastr.error('Selected user is invalid or does not match the provided name.');";
                    file_put_contents('../debug.log', "Create Violation Failed: Invalid user_id='$user_id' or name mismatch.\n", FILE_APPEND);
                    $user_id = null;
                }
            }

            // If no valid user_id, check if violator_name matches an existing user or create a new one
            if (!$user_id) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(full_name) = LOWER(?) AND officer_id = ?");
                $stmt->execute([$violator_name, $_SESSION['user_id']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_user) {
                    $user_id = $existing_user['id'];
                } else {
                    // Create new user
                    $username = substr(strtolower(str_replace(' ', '_', $violator_name)), 0, 50);
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $username .= '_' . rand(1000, 9999); // Append random number if username exists
                    }
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, officer_id) VALUES (?, 'x', ?, 'user', ?)");
                    $success = $stmt->execute([$username, $violator_name, $_SESSION['user_id']]);
                    if ($success) {
                        $user_id = $pdo->lastInsertId();
                        file_put_contents('../debug.log', "Created new user: username='$username', user_id='$user_id'\n", FILE_APPEND);
                    } else {
                        $toastr_messages[] = "toastr.error('Failed to create new user.');";
                        file_put_contents('../debug.log', "Create User Failed: No rows affected.\n", FILE_APPEND);
                    }
                }
            }

            // Insert violation with user_id
            if ($user_id || true) { // Proceed even if user_id is null (nullable column)
                $stmt = $pdo->prepare("INSERT INTO violations (officer_id, user_id, violator_name, plate_number, reason, violation_type_id, has_license, license_number, is_impounded, is_paid, or_number, issued_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $params = [$_SESSION['user_id'], $user_id, $violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes];
                $success = $stmt->execute($params);
                if ($success) {
                    $_SESSION['create_success'] = true;
                    // Update officer earnings
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $stmt = $pdo->prepare("SELECT fine_amount FROM types WHERE id = ?");
                    $stmt->execute([$violation_type_id]);
                    $fine = $stmt->fetch(PDO::FETCH_ASSOC)['fine_amount'] ?? 0;
                    $stmt = $pdo->prepare("INSERT INTO officer_earnings (officer_id, week_start, total_fines) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total_fines = total_fines + ?");
                    $stmt->execute([$_SESSION['user_id'], $week_start, $fine, $fine]);
                    header("Location: manage_violations.php");
                    exit;
                } else {
                    $toastr_messages[] = "toastr.error('Failed to create violation.');";
                    file_put_contents('../debug.log', "Create Violation Failed: No rows affected.\n", FILE_APPEND);
                }
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error creating violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Create Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle edit violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_violation'])) {
    try {
        $id = trim($_POST['id'] ?? '');
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

        file_put_contents('../debug.log', "Edit Violation Input: id='$id', violator_name='$violator_name', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id'\n", FILE_APPEND);

        if (empty($id) || empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id)) {
            $toastr_messages[] = "toastr.error('ID, Violator Name, Plate Number, Reason, and Violation Type are required.');";
        } else {
            $stmt = $pdo->prepare("UPDATE violations SET violator_name = ?, plate_number = ?, reason = ?, violation_type_id = ?, has_license = ?, license_number = ?, is_impounded = ?, is_paid = ?, or_number = ?, issued_date = ?, status = ?, notes = ? WHERE id = ? AND officer_id = ?");
            $params = [$violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes, $id, $_SESSION['user_id']];
            $success = $stmt->execute($params);
            if ($success) {
                $_SESSION['edit_success'] = true;
                // Update officer earnings if violation type changed
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $stmt = $pdo->prepare("SELECT fine_amount FROM types WHERE id = ?");
                $stmt->execute([$violation_type_id]);
                $fine = $stmt->fetch(PDO::FETCH_ASSOC)['fine_amount'] ?? 0;
                $stmt = $pdo->prepare("INSERT INTO officer_earnings (officer_id, week_start, total_fines) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total_fines = total_fines + ?");
                $stmt->execute([$_SESSION['user_id'], $week_start, $fine, $fine]);
                header("Location: manage_violations.php");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to update violation or you lack permission.');";
                file_put_contents('../debug.log', "Edit Violation Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Edit Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle delete violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_violation'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        file_put_contents('../debug.log', "Delete Violation Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            $toastr_messages[] = "toastr.error('Violation ID is required.');";
        } else {
            $stmt = $pdo->prepare("DELETE FROM violations WHERE id = ? AND officer_id = ?");
            $params = [$id, $_SESSION['user_id']];
            $success = $stmt->execute($params);
            if ($success) {
                $_SESSION['delete_success'] = true;
                header("Location: manage_violations.php");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to delete violation or you lack permission.');";
                file_put_contents('../debug.log', "Delete Violation Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error deleting violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Delete Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
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

// Fetch violation types
try {
    $stmt = $pdo->query("SELECT id, violation_type, fine_amount FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
}

// Fetch users under the officer
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE officer_id = ? ORDER BY full_name");
    $stmt->execute([$_SESSION['user_id']]);
    $supervised_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching supervised users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Supervised Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $supervised_users = [];
}

// Fetch all violations issued by the officer
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.officer_id, v.user_id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, v.has_license, v.license_number, v.is_impounded, v.is_paid, v.or_number, v.issued_date, v.status, v.notes, t.violation_type, t.fine_amount 
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        WHERE v.officer_id = ? 
        ORDER BY v.issued_date DESC
    ");
    $stmt->execute([$officer_id]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
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
                            <a class="nav-link active" href="../pages/manage_violations.php">
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
                            <a class="nav-link active" href="../pages/manage_violations.php">
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
                    <h1 class="h2 text-primary">Manage Violations - <?php echo htmlspecialchars($officer['full_name']); ?></h1>
                    <div>
                        <a href="../pages/officer_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <!-- Violations Table -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createViolationModal">Create Violation</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Officer ID</th>
                                        <th>Violator</th>
                                        <th>Plate</th>
                                        <th>Type</th>
                                        <th>Fine</th>
                                        <th>Reason</th>
                                        <th>License</th>
                                        <th>Impounded</th>
                                        <th>Paid</th>
                                        <th>OR Number</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($violations)): ?>
                                        <tr><td colspan="15" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['officer_id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                <td>₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                <td><?php echo htmlspecialchars($violation['reason']); ?></td>
                                                <td><?php echo $violation['has_license'] ? htmlspecialchars($violation['license_number'] ?: 'Yes') : 'No'; ?></td>
                                                <td><?php echo $violation['is_impounded'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo $violation['is_paid'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo htmlspecialchars($violation['or_number'] ?: 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($violation['issued_date']))); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $violation['status'] === 'Pending' ? 'bg-warning text-dark' : ($violation['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'); ?>">
                                                        <?php echo htmlspecialchars($violation['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($violation['notes'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editViolationModal<?php echo $violation['id']; ?>">Edit</button>
                                                    <form method="POST" style="display: inline;" class="delete-violation-form">
                                                        <input type="hidden" name="id" value="<?php echo $violation['id']; ?>">
                                                        <input type="hidden" name="delete_violation" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <!-- Edit Violation Modal -->
                                            <div class="modal fade" id="editViolationModal<?php echo $violation['id']; ?>" tabindex="-1" aria-labelledby="editViolationModalLabel<?php echo $violation['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editViolationModalLabel<?php echo $violation['id']; ?>">Edit Violation: <?php echo htmlspecialchars($violation['id']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-violation-form">
                                                                <input type="hidden" name="id" value="<?php echo $violation['id']; ?>">
                                                                <input type="hidden" name="edit_violation" value="1">
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="violator_name_<?php echo $violation['id']; ?>" class="form-label">Violator Name</label>
                                                                        <input type="text" class="form-control" name="violator_name" id="violator_name_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['violator_name']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid violator name.</div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="plate_number_<?php echo $violation['id']; ?>" class="form-label">License Plate</label>
                                                                        <input type="text" class="form-control" name="plate_number" id="plate_number_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['plate_number']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid license plate.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="violation_type_id_<?php echo $violation['id']; ?>" class="form-label">Violation Type</label>
                                                                        <select class="form-select" name="violation_type_id" id="violation_type_id_<?php echo $violation['id']; ?>" required>
                                                                            <option value="">Select</option>
                                                                            <?php foreach ($types as $type): ?>
                                                                                <option value="<?php echo htmlspecialchars($type['id']); ?>" <?php echo $type['id'] == $violation['violation_type_id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($type['violation_type']); ?> (₱<?php echo number_format($type['fine_amount'], 2); ?>)
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <div class="invalid-feedback">Please select a violation type.</div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="reason_<?php echo $violation['id']; ?>" class="form-label">Reason</label>
                                                                        <input type="text" class="form-control" name="reason" id="reason_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['reason']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid reason.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="has_license" id="has_license_<?php echo $violation['id']; ?>" <?php echo $violation['has_license'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="has_license_<?php echo $violation['id']; ?>">Has License</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="license_number_<?php echo $violation['id']; ?>" class="form-label">License Number</label>
                                                                        <input type="text" class="form-control" name="license_number" id="license_number_<?php echo $violation['id']; ?>" value="<?php echo htmlspecialchars($violation['license_number'] ?: ''); ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded_<?php echo $violation['id']; ?>" <?php echo $violation['is_impounded'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_impounded_<?php echo $violation['id']; ?>">Is Impounded</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid_<?php echo $violation['id']; ?>" <?php echo $violation['is_paid'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_paid_<?php echo $violation['id']; ?>">Is Paid</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="or_number_<?php echo $violation['id']; ?>" class="form-label">OR Number</label>
                                                                        <input type="text" class="form-control" name="or_number" id="or_number_<?php echo $violation['id']; ?>" value="<?php echo htmlspecialchars($violation['or_number'] ?: ''); ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="issued_date_<?php echo $violation['id']; ?>" class="form-label">Issued Date</label>
                                                                        <input type="datetime-local" class="form-control" name="issued_date" id="issued_date_<?php echo $violation['id']; ?>" value="<?php echo date('Y-m-d\TH:i', strtotime($violation['issued_date'])); ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="status_<?php echo $violation['id']; ?>" class="form-label">Status</label>
                                                                    <select class="form-select" name="status" id="status_<?php echo $violation['id']; ?>">
                                                                        <option value="Pending" <?php echo $violation['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="Resolved" <?php echo $violation['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                        <option value="Disputed" <?php echo $violation['status'] === 'Disputed' ? 'selected' : ''; ?>>Disputed</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="notes_<?php echo $violation['id']; ?>" class="form-label">Notes</label>
                                                                    <textarea class="form-control" name="notes" id="notes_<?php echo $violation['id']; ?>" rows="4"><?php echo htmlspecialchars($violation['notes'] ?: ''); ?></textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-primary">Update Violation</button>
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

                <!-- Create Violation Modal -->
                <div class="modal fade" id="createViolationModal" tabindex="-1" aria-labelledby="createViolationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createViolationModalLabel">Create Violation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline create-violation-form" id="createViolationForm">
                                    <input type="hidden" name="create_violation" value="1">
                                    <input type="hidden" name="user_id" id="user_id">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="violator_name" class="form-label">Violator Name</label>
                                                <input type="text" class="form-control" name="violator_name" id="violator_name" required>
                                                <div class="invalid-feedback">Please enter a valid violator name.</div>
                                            </div>
                                            <div class="mb-3">
                                                <h6>Users Under Your Supervision</h6>
                                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                                    <table class="table table-hover table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Username</th>
                                                                <th>Full Name</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($supervised_users)): ?>
                                                                <tr><td colspan="2" class="text-center text-muted">No users found</td></tr>
                                                            <?php else: ?>
                                                                <?php foreach ($supervised_users as $user): ?>
                                                                    <tr class="user-row" data-user-id="<?php echo htmlspecialchars($user['id']); ?>" data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>" style="cursor: pointer;">
                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="plate_number" class="form-label">License Plate</label>
                                                    <input type="text" class="form-control" name="plate_number" id="plate_number" required>
                                                    <div class="invalid-feedback">Please enter a valid license plate.</div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="violation_type_id" class="form-label">Violation Type</label>
                                                    <select class="form-select" name="violation_type_id" id="violation_type_id" required>
                                                        <option value="" disabled selected>Select</option>
                                                        <?php foreach ($types as $type): ?>
                                                            <option value="<?php echo htmlspecialchars($type['id']); ?>">
                                                                <?php echo htmlspecialchars($type['violation_type']); ?> (₱<?php echo number_format($type['fine_amount'], 2); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a violation type.</div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12 mb-3">
                                                    <label for="reason" class="form-label">Reason</label>
                                                    <input type="text" class="form-control" name="reason" id="reason" required>
                                                    <div class="invalid-feedback">Please enter a valid reason.</div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" name="has_license" id="has_license">
                                                        <label class="form-check-label" for="has_license">Has License</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="license_number" class="form-label">License Number</label>
                                                    <input type="text" class="form-control" name="license_number" id="license_number">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded">
                                                        <label class="form-check-label" for="is_impounded">Is Impounded</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid">
                                                        <label class="form-check-label" for="is_paid">Is Paid</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="or_number" class="form-label">OR Number</label>
                                                    <input type="text" class="form-control" name="or_number" id="or_number">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="issued_date" class="form-label">Issued Date</label>
                                                    <input type="datetime-local" class="form-control" name="issued_date" id="issued_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" name="status" id="status">
                                                        <option value="Pending" selected>Pending</option>
                                                        <option value="Resolved">Resolved</option>
                                                        <option value="Disputed">Disputed</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="notes" class="form-label">Notes</label>
                                                    <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <button type="submit" class="btn btn-primary">Create Violation</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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

        // Display Toastr/SweetAlert messages
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        // Client-side validation for Create Violation Form
        document.getElementById('createViolationForm').addEventListener('submit', function(e) {
            console.log('Create violation form submission attempted');
            const violatorName = document.getElementById('violator_name').value.trim();
            const plateNumber = document.getElementById('plate_number').value.trim();
            const reason = document.getElementById('reason').value.trim();
            const violationTypeId = document.getElementById('violation_type_id').value;

            let isValid = true;

            document.getElementById('violator_name').classList.remove('is-invalid');
            document.getElementById('plate_number').classList.remove('is-invalid');
            document.getElementById('reason').classList.remove('is-invalid');
            document.getElementById('violation_type_id').classList.remove('is-invalid');

            if (!violatorName) {
                document.getElementById('violator_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!plateNumber) {
                document.getElementById('plate_number').classList.add('is-invalid');
                isValid = false;
            }
            if (!reason) {
                document.getElementById('reason').classList.add('is-invalid');
                isValid = false;
            }
            if (!violationTypeId) {
                document.getElementById('violation_type_id').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for create violation');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to create this violation?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Create violation form submission confirmed');
                    this.submit();
                } else {
                    console.log('Create violation form submission canceled');
                }
            });
        });

        // Handle user selection from mini-table
        document.querySelectorAll('.user-row').forEach(row => {
            row.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const fullName = this.getAttribute('data-full-name');
                document.getElementById('violator_name').value = fullName;
                document.getElementById('user_id').value = userId;
                document.getElementById('violator_name').classList.remove('is-invalid');
                console.log(`Selected user: ID=${userId}, Full Name=${fullName}`);
                // Highlight selected row
                document.querySelectorAll('.user-row').forEach(r => r.classList.remove('table-primary'));
                this.classList.add('table-primary');
            });
        });

        // Clear user_id if violator_name is manually changed
        document.getElementById('violator_name').addEventListener('input', function() {
            document.getElementById('user_id').value = '';
            document.querySelectorAll('.user-row').forEach(row => row.classList.remove('table-primary'));
            console.log('Violator name manually changed, cleared user_id');
        });

        // Client-side validation for Edit Violation Forms
        document.querySelectorAll('.edit-violation-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit violation form submission attempted');
                const violatorName = this.querySelector('input[name="violator_name"]').value.trim();
                const plateNumber = this.querySelector('input[name="plate_number"]').value.trim();
                const reason = this.querySelector('input[name="reason"]').value.trim();
                const violationTypeId = this.querySelector('select[name="violation_type_id"]').value;

                let isValid = true;

                this.querySelector('input[name="violator_name"]').classList.remove('is-invalid');
                this.querySelector('input[name="plate_number"]').classList.remove('is-invalid');
                this.querySelector('input[name="reason"]').classList.remove('is-invalid');
                this.querySelector('select[name="violation_type_id"]').classList.remove('is-invalid');

                if (!violatorName) {
                    this.querySelector('input[name="violator_name"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!plateNumber) {
                    this.querySelector('input[name="plate_number"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!reason) {
                    this.querySelector('input[name="reason"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!violationTypeId) {
                    this.querySelector('select[name="violation_type_id"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit violation');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this violation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit violation form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit violation form submission canceled');
                    }
                });
            });
        });

        // Client-side validation and confirmation for Delete Violation Forms
        document.querySelectorAll('.delete-violation-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Delete violation form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to delete this violation? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Delete violation form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Delete violation form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>