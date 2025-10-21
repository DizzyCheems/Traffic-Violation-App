<?php
session_start();
include '../config/conn.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Debug: Log session data
file_put_contents('../debug.log', "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
    
    $reason = "Redirecting to login.php. ";
    
    if (!isset($_SESSION['user_id'])) {
        $reason .= "user_id not set. ";
    }
    
    if (!isset($_SESSION['role'])) {
        $reason .= "role not set. ";
    }
    
    if (isset($_SESSION['role']) && !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
        $reason .= "role is '" . $_SESSION['role'] . "' instead of 'officer' or 'admin'.";
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

// Ensure uploads directory exists
$upload_dir = '../Uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle create violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_violation'])) {
    try {
        $violator_name = trim($_POST['violator_name'] ?? '');
        $user_id = trim($_POST['user_id'] ?? '') ?: null;
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;
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
        $plate_image = null;

        // Handle file upload
        if (isset($_FILES['plate_image']) && $_FILES['plate_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['plate_image']['tmp_name'];
            $file_name = uniqid() . '_' . basename($_FILES['plate_image']['name']);
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $file_path)) {
                $plate_image = $file_path;
            } else {
                $toastr_messages[] = "toastr.error('Failed to upload plate image.');";
                file_put_contents('../debug.log', "File Upload Failed: Unable to move file to $file_path\n", FILE_APPEND);
            }
        }

        file_put_contents('../debug.log', "Create Violation Input: violator_name='$violator_name', user_id='$user_id', contact_number='$contact_number', email='$email', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id', plate_image='$plate_image'\n", FILE_APPEND);

        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id) || empty($contact_number)) {
            $toastr_messages[] = "toastr.error('Violator Name, Plate Number, Reason, Violation Type, and Contact Number are required.');";
            file_put_contents('../debug.log', "Create Violation Failed: Missing required fields.\n", FILE_APPEND);
        } else {
            // Check if user_id is provided and valid
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND officer_id = ?");
                $stmt->execute([$user_id, $_SESSION['user_id']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing_user || strtolower(trim($existing_user['full_name'])) !== strtolower(trim($violator_name))) {
                    $toastr_messages[] = "toastr.error('Selected user is invalid or does not match the provided name.');";
                    file_put_contents('../debug.log', "Create Violation Failed: Invalid user_id='$user_id' or name mismatch.\n", FILE_APPEND);
                    $user_id = null;
                } else {
                    // Use email from users table if not provided in form
                    $email = $email ?: $existing_user['email'];
                }
            }

            // If no valid user_id, check if violator_name matches an existing user or create a new one
            if (!$user_id) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(full_name) = LOWER(?) AND officer_id = ?");
                $stmt->execute([$violator_name, $_SESSION['user_id']]);
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_user) {
                    $user_id = $existing_user['id'];
                    // Update existing user's contact number and email
                    $stmt = $pdo->prepare("UPDATE users SET contact_number = ?, email = ? WHERE id = ?");
                    $success = $stmt->execute([$contact_number, $email, $user_id]);
                    if (!$success) {
                        $toastr_messages[] = "toastr.error('Failed to update user contact information.');";
                        file_put_contents('../debug.log', "Update User Contact Failed: No rows affected.\n", FILE_APPEND);
                    }
                } else {
                    // Create new user
                    $username = substr(strtolower(str_replace(' ', '_', $violator_name)), 0, 50);
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $username .= '_' . rand(1000, 9999); // Append random number if username exists
                    }
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, officer_id, contact_number, email) VALUES (?, 'x', ?, 'user', ?, ?, ?)");
                    $success = $stmt->execute([$username, $violator_name, $_SESSION['user_id'], $contact_number, $email]);
                    if ($success) {
                        $user_id = $pdo->lastInsertId();
                        file_put_contents('../debug.log', "Created new user: username='$username', user_id='$user_id', contact_number='$contact_number', email='$email'\n", FILE_APPEND);
                    } else {
                        $toastr_messages[] = "toastr.error('Failed to create new user.');";
                        file_put_contents('../debug.log', "Create User Failed: No rows affected.\n", FILE_APPEND);
                    }
                }
            }

            // Calculate offense_freq
            $offense_freq = 1; // Default to 1 for the first offense
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND officer_id = ?");
                $stmt->execute([$user_id, $_SESSION['user_id']]);
                $offense_freq = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
                file_put_contents('../debug.log', "Offense Frequency for user_id='$user_id': $offense_freq\n", FILE_APPEND);
            } else {
                // Fallback: count violations by violator_name if user_id is not set
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE LOWER(violator_name) = LOWER(?) AND officer_id = ?");
                $stmt->execute([$violator_name, $_SESSION['user_id']]);
                $offense_freq = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
                file_put_contents('../debug.log', "Offense Frequency for violator_name='$violator_name': $offense_freq\n", FILE_APPEND);
            }

            // Fetch violation type details for email
            $stmt = $pdo->prepare("SELECT violation_type, fine_amount FROM types WHERE id = ?");
            $stmt->execute([$violation_type_id]);
            $violation_type = $stmt->fetch(PDO::FETCH_ASSOC);
            $violation_type_name = $violation_type['violation_type'] ?? 'Unknown';
            $fine_amount = $violation_type['fine_amount'] ?? 0;

            // Insert violation with user_id, offense_freq, plate_image, and email_sent = FALSE
            $stmt = $pdo->prepare("INSERT INTO violations (officer_id, user_id, violator_name, plate_number, reason, violation_type_id, has_license, license_number, is_impounded, is_paid, or_number, issued_date, status, notes, offense_freq, plate_image, email_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)");
            $params = [$_SESSION['user_id'], $user_id, $violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes, $offense_freq, $plate_image];
            file_put_contents('../debug.log', "Executing INSERT query with params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                // Store the new violation ID
                $violation_id = $pdo->lastInsertId();

                // Update officer earnings
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $stmt = $pdo->prepare("INSERT INTO officer_earnings (officer_id, week_start, total_fines) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE total_fines = total_fines + ?");
                $success_earnings = $stmt->execute([$_SESSION['user_id'], $week_start, $fine_amount, $fine_amount]);
                if (!$success_earnings) {
                    $toastr_messages[] = "toastr.error('Failed to update officer earnings.');";
                    file_put_contents('../debug.log', "Update Officer Earnings Failed: No rows affected.\n", FILE_APPEND);
                }

                // Log redirect and redirect to mail_test.php to trigger email sending
                file_put_contents('../debug.log', "Violation created successfully, redirecting to send_mail.php?violation_id=$violation_id\n", FILE_APPEND);
                $_SESSION['create_success'] = true;
                header("Location: send_mail.php?violation_id=$violation_id");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to create violation.');";
                file_put_contents('../debug.log', "Create Violation Failed: No rows affected.\n", FILE_APPEND);
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
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;
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
        $plate_image = null;

        // Handle file upload for edit
        if (isset($_FILES['plate_image']) && $_FILES['plate_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['plate_image']['tmp_name'];
            $file_name = uniqid() . '_' . basename($_FILES['plate_image']['name']);
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $file_path)) {
                $plate_image = $file_path;
            } else {
                $toastr_messages[] = "toastr.error('Failed to upload plate image.');";
                file_put_contents('../debug.log', "File Upload Failed: Unable to move file to $file_path\n", FILE_APPEND);
            }
        }

        file_put_contents('../debug.log', "Edit Violation Input: id='$id', violator_name='$violator_name', contact_number='$contact_number', email='$email', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id', plate_image='$plate_image'\n", FILE_APPEND);

        if (empty($id) || empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id) || empty($contact_number)) {
            $toastr_messages[] = "toastr.error('ID, Violator Name, Plate Number, Reason, Violation Type, and Contact Number are required.');";
        } else {
            // Verify violation belongs to the officer
            $stmt = $pdo->prepare("SELECT user_id, plate_image FROM violations WHERE id = ? AND officer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $violation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$violation) {
                $toastr_messages[] = "toastr.error('Violation not found or you lack permission.');";
                file_put_contents('../debug.log', "Edit Violation Failed: Violation ID='$id' not found or unauthorized.\n", FILE_APPEND);
            } else {
                // Update user contact information if user_id exists and is supervised by the officer
                if ($violation['user_id']) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND officer_id = ?");
                    $stmt->execute([$violation['user_id'], $_SESSION['user_id']]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, contact_number = ?, email = ? WHERE id = ?");
                        $success = $stmt->execute([$violator_name, $contact_number, $email, $violation['user_id']]);
                        if (!$success) {
                            $toastr_messages[] = "toastr.error('Failed to update user contact information.');";
                            file_put_contents('../debug.log', "Update User Contact Failed: No rows affected.\n", FILE_APPEND);
                        }
                    } else {
                        $toastr_messages[] = "toastr.error('User is not under your supervision.');";
                        file_put_contents('../debug.log', "Edit Violation Failed: user_id='$violation[user_id]' not supervised by officer_id='$_SESSION[user_id]'.\n", FILE_APPEND);
                    }
                }

                // If a new image is uploaded, delete the old one
                if ($plate_image && $violation['plate_image'] && file_exists($violation['plate_image'])) {
                    unlink($violation['plate_image']);
                }

                // Prepare update query
                $query = "UPDATE violations SET violator_name = ?, plate_number = ?, reason = ?, violation_type_id = ?, has_license = ?, license_number = ?, is_impounded = ?, is_paid = ?, or_number = ?, issued_date = ?, status = ?, notes = ?";
                $params = [$violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes];
                if ($plate_image) {
                    $query .= ", plate_image = ?";
                    $params[] = $plate_image;
                }
                $query .= " WHERE id = ? AND officer_id = ?";
                $params[] = $id;
                $params[] = $_SESSION['user_id'];

                $stmt = $pdo->prepare($query);
                $success = $stmt->execute($params);
                if ($success) {
                    $_SESSION['edit_success'] = true;
                    // Update officer earnings if violation type changed
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $stmt = $pdo->prepare("SELECT fine_amount FROM types WHERE id = ?");
                    $stmt->execute([$violation_type_id]);
                    $fine = $stmt->fetch(PDO::FETCH_ASSOC)['fine_amount'] ?? 0;
                    $stmt = $pdo->prepare("INSERT INTO officer_earnings (officer_id, plate_number, week_start, total_fines) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_fines = total_fines + ?");
                    $stmt->execute([$_SESSION['user_id'], $plate_number, $week_start, $fine, $fine]);
                } else {
                    $toastr_messages[] = "toastr.error('Failed to update violation or you lack permission.');";
                    file_put_contents('../debug.log', "Edit Violation Failed: No rows affected.\n", FILE_APPEND);
                }
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
            // Verify violation belongs to the officer
            $stmt = $pdo->prepare("SELECT plate_image FROM violations WHERE id = ? AND officer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $violation = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($violation) {
                // Delete associated image
                if ($violation['plate_image'] && file_exists($violation['plate_image'])) {
                    unlink($violation['plate_image']);
                }

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
            } else {
                $toastr_messages[] = "toastr.error('Violation not found or you lack permission.');";
                file_put_contents('../debug.log', "Delete Violation Failed: Violation ID='$id' not found or unauthorized.\n", FILE_APPEND);
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

// Fetch all violations issued by the officer
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.officer_id, v.user_id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, v.has_license, v.license_number, v.is_impounded, v.is_paid, v.or_number, v.issued_date, v.status, v.notes, v.offense_freq, v.plate_image, t.violation_type, t.fine_amount 
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

<style>
    <style>
    #violation_type_id option:disabled {
        color: #ccc !important;
        font-style: italic;
    }
</style>

<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">

<!--        <div class="px-3 py-2">
            <img src="../public/images/PRVN.png" alt="PRVN Logo" class="img-fluid" style="max-width: 150px; margin-bottom: 10px;">
        </div>-->

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
<?php if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin'): ?>
    <a class="nav-link" href="../pages/admin_dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i>
        Home
    </a>
<?php else: ?>
    <a class="nav-link" href="../pages/officer_dashboard.php">
        <i class="fas fa-tachometer-alt me-2"></i>
        Officer Dashboard
    </a>
<?php endif; ?>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
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
                                        <th>Plate Image</th>
                                        <th>Type</th>
                                        <th>Fine</th>
                                        <th>Reason</th>
                                        <th>License</th>
                                        <th>Impounded</th>
                                        <th>Paid</th>
                                        <th>CR Number</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Offense Frequency</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($violations)): ?>
                                        <tr><td colspan="17" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <?php
                                                // Fetch user contact info for edit modal
                                                $user_contact = ['contact_number' => '', 'email' => ''];
                                                if ($violation['user_id']) {
                                                    $stmt = $pdo->prepare("SELECT contact_number, email FROM users WHERE id = ? AND officer_id = ?");
                                                    $stmt->execute([$violation['user_id'], $_SESSION['user_id']]);
                                                    $user_contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['contact_number' => '', 'email' => ''];
                                                }
                                            ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['officer_id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                <td>
                                                    <?php if ($violation['plate_image'] && file_exists($violation['plate_image'])): ?>
                                                        <a href="<?php echo htmlspecialchars($violation['plate_image']); ?>" target="_blank">
                                                            <img src="<?php echo htmlspecialchars($violation['plate_image']); ?>" alt="Plate Image" style="max-width: 100px; max-height: 100px;">
                                                        </a>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
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
                                                    <span class="badge <?php echo ($violation['offense_freq'] == 1) ? 'bg-warning text-dark' : ($violation['offense_freq'] >= 2 ? 'bg-danger text-white' : 'bg-light text-dark'); ?>">
                                                        <?php echo ($violation['offense_freq'] == 0) ? 'No Recurring Offense' : htmlspecialchars($violation['offense_freq']); ?>
                                                    </span>
                                                </td>
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
                                                            <form method="POST" class="form-outline edit-violation-form" enctype="multipart/form-data">
                                                                <input type="hidden" name="id" value="<?php echo $violation['id']; ?>">
                                                                <input type="hidden" name="edit_violation" value="1">
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="violator_name_<?php echo $violation['id']; ?>" class="form-label">Violator Name</label>
                                                                        <input type="text" class="form-control" name="violator_name" id="violator_name_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['violator_name']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid violator name.</div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="contact_number_<?php echo $violation['id']; ?>" class="form-label">Contact Number</label>
                                                                        <input type="text" class="form-control" name="contact_number" id="contact_number_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($user_contact['contact_number']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid contact number.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="email_<?php echo $violation['id']; ?>" class="form-label">Email (Optional)</label>
                                                                        <input type="email" class="form-control" name="email" id="email_<?php echo $violation['id']; ?>" value="<?php echo htmlspecialchars($user_contact['email'] ?: ''); ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="plate_number_<?php echo $violation['id']; ?>" class="form-label">Plate Number</label>
                                                                        <input type="text" class="form-control" name="plate_number" id="plate_number_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['plate_number']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid license plate.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="plate_image_<?php echo $violation['id']; ?>" class="form-label">Plate Image (Optional)</label>
                                                                        <input type="file" class="form-control" name="plate_image" id="plate_image_<?php echo $violation['id']; ?>" accept="image/*">
                                                                        <?php if ($violation['plate_image'] && file_exists($violation['plate_image'])): ?>
                                                                            <a href="<?php echo htmlspecialchars($violation['plate_image']); ?>" target="_blank">View Current Image</a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="violation_type_id_<?php echo $violation['id']; ?>" class="form-label">Violation Type</label>
<select class="form-select" name="violation_type_id" id="violation_type_id" required>
    <option value="" disabled selected>Select</option>
    <?php foreach ($types as $type): ?>
        <option value="<?php echo htmlspecialchars($type['id']); ?>" 
                data-original-text="<?php echo htmlspecialchars($type['violation_type']); ?> (₱<?php echo number_format($type['fine_amount'], 2); ?>)">
            <?php echo htmlspecialchars($type['violation_type']); ?> (₱<?php echo number_format($type['fine_amount'], 2); ?>)
        </option>
    <?php endforeach; ?>
</select>
                                                                        <div class="invalid-feedback">Please select a violation type.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="reason_<?php echo $violation['id']; ?>" class="form-label">Reason</label>
                                                                        <input type="text" class="form-control" name="reason" id="reason_<?php echo $violation['id']; ?>" required value="<?php echo htmlspecialchars($violation['reason']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid reason.</div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="has_license" id="has_license_<?php echo $violation['id']; ?>" <?php echo $violation['has_license'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="has_license_<?php echo $violation['id']; ?>">Has License</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="license_number_<?php echo $violation['id']; ?>" class="form-label">License Number</label>
                                                                        <input type="text" class="form-control" name="license_number" id="license_number_<?php echo $violation['id']; ?>" value="<?php echo htmlspecialchars($violation['license_number'] ?: ''); ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded_<?php echo $violation['id']; ?>" <?php echo $violation['is_impounded'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_impounded_<?php echo $violation['id']; ?>">Is Impounded</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <div class="form-check">
                                                                            <input type="checkbox" class="form-check-input" name="is_paid" id="is_paid_<?php echo $violation['id']; ?>" <?php echo $violation['is_paid'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="is_paid_<?php echo $violation['id']; ?>">Is Paid</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="or_number_<?php echo $violation['id']; ?>" class="form-label">CR Number</label>
                                                                        <input type="text" class="form-control" name="or_number" id="or_number_<?php echo $violation['id']; ?>" value="<?php echo htmlspecialchars($violation['or_number'] ?: ''); ?>">
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="issued_date_<?php echo $violation['id']; ?>" class="form-label">Issued Date</label>
                                                                        <input type="datetime-local" class="form-control" name="issued_date" id="issued_date_<?php echo $violation['id']; ?>" value="<?php echo date('Y-m-d\TH:i', strtotime($violation['issued_date'])); ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="status_<?php echo $violation['id']; ?>" class="form-label">Status</label>
                                                                        <select class="form-select" name="status" id="status_<?php echo $violation['id']; ?>">
                                                                            <option value="Pending" <?php echo $violation['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="Resolved" <?php echo $violation['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                            <option value="Disputed" <?php echo $violation['status'] === 'Disputed' ? 'selected' : ''; ?>>Disputed</option>
                                                                        </select>
                                                                    </div>
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
                <form method="POST" class="form-outline create-violation-form" id="createViolationForm" enctype="multipart/form-data">
                    <input type="hidden" name="create_violation" value="1">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="violation_type_id" id="selected_violation_type_id">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="violator_name" class="form-label">Violator Name</label>
                                <input type="text" class="form-control" name="violator_name" id="violator_name" required>
                                <div class="invalid-feedback">Please enter a valid violator name.</div>
                            </div>
                            <div class="mb-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" id="contact_number" required>
                                <div class="invalid-feedback">Please enter a valid contact number.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email (Optional)</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="plate_image" class="form-label">Upload Plate Number</label>
                                    <input type="file" class="form-control" name="plate_image" id="plate_image" accept="image/*">
                                    <div id="ocr_status" class="form-text"></div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="plate_number" class="form-label">License Plate</label>
                                    <input type="text" class="form-control" name="plate_number" id="plate_number" required>
                                    <div class="invalid-feedback">Please enter a valid license plate.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="reason" class="form-label">Reason</label>
                                    <input type="text" class="form-control" name="reason" id="reason">
                                    <div class="invalid-feedback">Please enter a valid reason.</div>
                                </div>
                            </div>
                            <!-- Violation Type Selection Table -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h6>Available Violation Types</h6>
                                    <div id="violationTypeTable" class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Select</th>
                                                    <th>Violation Type</th>
                                                    <th>Fine Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody id="violationTypeBody">
                                                <tr>
                                                    <td colspan="3" class="text-center">Enter a plate number to view available violation types</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Violation History Table -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h6>Violation History for Plate Number</h6>
                                    <div id="violationHistoryTable" class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Violation Type</th>
                                                    <th>Fine Amount</th>
                                                    <th>Issued Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="violationHistoryBody">
                                                <tr>
                                                    <td colspan="4" class="text-center">Enter a plate number to view violation history</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
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
                                    <label for="or_number" class="form-label">CR Number</label>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const plateNumberInput = document.getElementById('plate_number');
    const violationHistoryBody = document.getElementById('violationHistoryBody');
    const violationTypeBody = document.getElementById('violationTypeBody');
    const selectedViolationTypeId = document.getElementById('selected_violation_type_id');

    if (!plateNumberInput || !violationHistoryBody || !violationTypeBody || !selectedViolationTypeId) {
        console.error('Required elements not found:', { plateNumberInput, violationHistoryBody, violationTypeBody, selectedViolationTypeId });
        return;
    }

    // Store original types from PHP (simulated client-side for now)
    const originalTypes = <?php echo json_encode($types); ?> || [];

    plateNumberInput.addEventListener('input', function() {
        const plateNumber = this.value.trim();
        console.log('Plate number changed to:', plateNumber);

        if (plateNumber.length > 0) {
            fetchViolationHistory(plateNumber);
        } else {
            clearHistoryAndResetTables();
        }
    });

    function fetchViolationHistory(plateNumber) {
        console.log('Fetching violation history for:', plateNumber);
        fetch('fetch_violation_history.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'plate_number=' + encodeURIComponent(plateNumber)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) throw new Error('Network error: ' + response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            violationHistoryBody.innerHTML = '';
            violationTypeBody.innerHTML = '';

            const usedTypeIds = new Set();
            if (data.success && Array.isArray(data.violations) && data.violations.length > 0) {
                data.violations.forEach(violation => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${violation.violation_type || 'N/A'}</td>
                        <td>₱${parseFloat(violation.fine_amount || 0).toFixed(2)}</td>
                        <td>${new Date(violation.issued_date || '').toLocaleString() || 'N/A'}</td>
                        <td><span class="badge bg-warning text-dark">${violation.status || 'N/A'}</span></td>
                    `;
                    violationHistoryBody.appendChild(row);
                    if (violation.violation_type_id) usedTypeIds.add(violation.violation_type_id);
                });

                // Populate available violation types
                populateAvailableTypes(usedTypeIds);
            } else {
                violationHistoryBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No violations found for this plate number</td></tr>';
                populateAvailableTypes(usedTypeIds);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            violationHistoryBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading history: ' + error.message + '</td></tr>';
            violationTypeBody.innerHTML = '<tr><td colspan="3" class="text-center">Enter a plate number to view available violation types</td></tr>';
        });
    }

    function populateAvailableTypes(usedTypeIds) {
        violationTypeBody.innerHTML = '';
        const availableTypes = originalTypes.filter(type => !usedTypeIds.has(type.id.toString()));
        if (availableTypes.length > 0) {
            availableTypes.forEach(type => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><button type="button" class="btn btn-sm btn-outline-primary select-violation" data-id="${type.id}">Select</button></td>
                    <td>${type.violation_type}</td>
                    <td>₱${parseFloat(type.fine_amount || 0).toFixed(2)}</td>
                `;
                violationTypeBody.appendChild(row);
            });
        } else {
            violationTypeBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No available violation types</td></tr>';
        }

        // Add click event to select buttons
        document.querySelectorAll('.select-violation').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                selectedViolationTypeId.value = id;
                console.log('Selected violation type ID:', id);
                toastr.success('Violation type selected: ' + originalTypes.find(t => t.id == id).violation_type);
            });
        });
    }

    function clearHistoryAndResetTables() {
        violationHistoryBody.innerHTML = '<tr><td colspan="4" class="text-center">Enter a plate number to view violation history</td></tr>';
        violationTypeBody.innerHTML = '<tr><td colspan="3" class="text-center">Enter a plate number to view available violation types</td></tr>';
        selectedViolationTypeId.value = '';
    }

    // Initialize table with all types if no plate number
    if (plateNumberInput.value.trim().length > 0) {
        fetchViolationHistory(plateNumberInput.value.trim());
    }
});
</script>
</main>
</div>
</div>
<?php include '../layout/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.0/dist/tesseract.min.js"></script>
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

    // Function to perform OCR on image and populate plate number
    function performOCR(file, inputId) {
        const ocrStatus = document.getElementById('ocr_status');
        ocrStatus.textContent = 'Processing image...';
        Tesseract.recognize(
            file,
            'eng',
            { logger: m => console.log('OCR Progress:', m) }
        ).then(({ data: { text } }) => {
            const cleanedText = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            const input = document.getElementById(inputId);
            input.value = cleanedText;
            ocrStatus.textContent = 'Text extracted successfully!';
            console.log('OCR Result:', cleanedText);
            input.dispatchEvent(new Event('input'));
            fetchUserByPlateNumber(cleanedText);
        }).catch(error => {
            ocrStatus.textContent = 'Error extracting text from image.';
            console.error('OCR Error:', error);
        });
    }

    // Function to fetch user details by plate number
    function fetchUserByPlateNumber(plateNumber) {
        if (!plateNumber) {
            console.log('No plate number provided');
            document.getElementById('violator_name').value = '';
            document.getElementById('contact_number').value = '';
            document.getElementById('email').value = '';
            document.getElementById('user_id').value = '';
            document.getElementById('has_license').checked = false;
            document.getElementById('license_number').value = '';
            return;
        }
        console.log('Fetching user data for plate:', plateNumber);
        fetch('get_user_by_plate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'plate_number=' + encodeURIComponent(plateNumber)
        })
        .then(response => {
            console.log('Fetch response status:', response.status);
            if (!response.ok) throw new Error('Network response was not ok: ' + response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('Fetch response data:', data);
            if (data.success) {
                document.getElementById('violator_name').value = data.violator_name || '';
                document.getElementById('contact_number').value = data.contact_number || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('user_id').value = data.user_id || '';
                document.getElementById('has_license').checked = data.has_license == 1;
                document.getElementById('license_number').value = data.license_number || '';
                toastr.success('User details populated successfully.');
            } else {
                document.getElementById('violator_name').value = '';
                document.getElementById('contact_number').value = '';
                document.getElementById('email').value = '';
                document.getElementById('user_id').value = '';
                document.getElementById('has_license').checked = false;
                document.getElementById('license_number').value = '';
                console.log('No user data found:', data.message);
                toastr.info(data.message || 'No previous violation found for this plate number.');
            }
        })
        .catch(error => {
            console.error('Error fetching user by plate:', error);
            toastr.error('Error fetching user details: ' + error.message);
        });
    }

    // Client-side validation for Create Violation Form
    document.getElementById('createViolationForm').addEventListener('submit', function(e) {
        console.log('Create violation form submission attempted');
        const violatorName = document.getElementById('violator_name').value.trim();
        const contactNumber = document.getElementById('contact_number').value.trim();
        const plateNumber = document.getElementById('plate_number').value.trim();
        const reason = document.getElementById('reason').value.trim();
        const violationTypeId = document.getElementById('selected_violation_type_id').value;

        let isValid = true;

        document.getElementById('violator_name').classList.remove('is-invalid');
        document.getElementById('contact_number').classList.remove('is-invalid');
        document.getElementById('plate_number').classList.remove('is-invalid');
        document.getElementById('reason').classList.remove('is-invalid');

        if (!violatorName) { document.getElementById('violator_name').classList.add('is-invalid'); isValid = false; }
        if (!contactNumber) { document.getElementById('contact_number').classList.add('is-invalid'); isValid = false; }
        if (!plateNumber) { document.getElementById('plate_number').classList.add('is-invalid'); isValid = false; }
        if (!reason) { document.getElementById('reason').classList.add('is-invalid'); isValid = false; }
        if (!violationTypeId) {
            toastr.error('Please select a violation type from the table.');
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
                fetch(this.action, { method: 'POST', body: new FormData(this) })
                .then(response => {
                    if (response.ok) {
                        Swal.fire({
                            title: 'Created!',
                            text: 'Violation has been created successfully.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to create violation.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred while creating the violation.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
            } else console.log('Create violation form submission canceled');
        });
    });

    // Handle image upload for OCR in Create Violation Form
    document.getElementById('plate_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            console.log('Image selected for OCR:', file.name);
            performOCR(file, 'plate_number');
        }
    });

    // Handle plate number input for auto-population
    document.getElementById('plate_number').addEventListener('input', function() {
        const plateNumber = this.value.trim();
        console.log('Plate number input changed:', plateNumber);
        fetchUserByPlateNumber(plateNumber);
    });

    // Handle image upload for OCR in Edit Violation Forms
    document.querySelectorAll('input[name="plate_image"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const inputId = this.id.replace('plate_image_', 'plate_number_');
                console.log('Image selected for OCR in edit form:', file.name);
                performOCR(file, inputId);
            }
        });
    });

    // Client-side validation for Edit Violation Forms
    document.querySelectorAll('.edit-violation-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Edit violation form submission attempted');
            const violatorName = this.querySelector('input[name="violator_name"]').value.trim();
            const contactNumber = this.querySelector('input[name="contact_number"]').value.trim();
            const plateNumber = this.querySelector('input[name="plate_number"]').value.trim();
            const reason = this.querySelector('input[name="reason"]').value.trim();
            const violationTypeId = this.querySelector('select[name="violation_type_id"]').value;

            let isValid = true;

            this.querySelector('input[name="violator_name"]').classList.remove('is-invalid');
            this.querySelector('input[name="contact_number"]').classList.remove('is-invalid');
            this.querySelector('input[name="plate_number"]').classList.remove('is-invalid');
            this.querySelector('input[name="reason"]').classList.remove('is-invalid');
            this.querySelector('select[name="violation_type_id"]').classList.remove('is-invalid');

            if (!violatorName) { this.querySelector('input[name="violator_name"]').classList.add('is-invalid'); isValid = false; }
            if (!contactNumber) { this.querySelector('input[name="contact_number"]').classList.add('is-invalid'); isValid = false; }
            if (!plateNumber) { this.querySelector('input[name="plate_number"]').classList.add('is-invalid'); isValid = false; }
            if (!reason) { this.querySelector('input[name="reason"]').classList.add('is-invalid'); isValid = false; }
            if (!violationTypeId) { this.querySelector('select[name="violation_type_id"]').classList.add('is-invalid'); isValid = false; }

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
                    fetch(this.action, { method: 'POST', body: new FormData(this) })
                    .then(response => {
                        if (response.ok) {
                            Swal.fire({
                                title: 'Updated!',
                                text: 'Violation has been updated successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => window.location.reload());
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to update violation.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    }).catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while updating the violation.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                } else console.log('Edit violation form submission canceled');
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
                } else console.log('Delete violation form submission canceled');
            });
        });
    });
</script>
</body>
</html>