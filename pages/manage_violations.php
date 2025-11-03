<?php
session_start();
include '../config/conn.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Debug: Log session data
file_put_contents('../debug.log', "Session Data at start: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
    $reason = "Redirecting to login.php. ";
    if (!isset($_SESSION['user_id'])) $reason .= "user_id not set. ";
    if (!isset($_SESSION['role'])) $reason .= "role not set. ";
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

// Success messages handling
if (isset($_SESSION['create_success']) && $_SESSION['create_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Created!',
        text: 'Violation has been created successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    file_put_contents('../debug.log', "Create success message triggered.\n", FILE_APPEND);
    unset($_SESSION['create_success']);
}
if (isset($_SESSION['edit_success']) && $_SESSION['edit_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Updated!',
        text: 'Violation has been updated successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    file_put_contents('../debug.log', "Edit success message triggered.\n", FILE_APPEND);
    unset($_SESSION['edit_success']);
}
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Deleted!',
        text: 'Violation has been deleted successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    file_put_contents('../debug.log', "Delete success message triggered.\n", FILE_APPEND);
    unset($_SESSION['delete_success']);
}

// Ensure uploads directory exists
$upload_dir = '../Uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle create violation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_violation'])) {
    header('Content-Type: application/json'); // Set JSON response
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
        $impound_pic = null;

        // Handle plate_image upload
        if (isset($_FILES['plate_image']) && $_FILES['plate_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['plate_image']['tmp_name'];
            $file_name = uniqid('plate_') . '_' . basename($_FILES['plate_image']['name']);
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($file_tmp, $file_path)) {
                file_put_contents('../debug.log', "Plate Image Upload Failed: Unable to move file to $file_path\n", FILE_APPEND);
                echo json_encode(['success' => false, 'message' => 'Failed to upload plate image.']);
                exit;
            }
            $plate_image = $file_path;
        }

        // Handle impound_pic upload (only if is_impounded is checked)
        if ($is_impounded && isset($_FILES['impound_pic']) && $_FILES['impound_pic']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['impound_pic']['tmp_name'];
            $file_name = uniqid('impound_') . '_' . basename($_FILES['impound_pic']['name']);
            $file_path = $upload_dir . $file_name;
            if (!move_uploaded_file($file_tmp, $file_path)) {
                file_put_contents('../debug.log', "Impound Image Upload Failed: Unable to move file to $file_path\n", FILE_APPEND);
                echo json_encode(['success' => false, 'message' => 'Failed to upload impound image.']);
                exit;
            }
            $impound_pic = $file_path;
        }

        file_put_contents('../debug.log', "Create Violation Input: violator_name='$violator_name', user_id='$user_id', contact_number='$contact_number', email='$email', plate_number='$plate_number', reason='$reason', violation_type_id='$violation_type_id', plate_image='$plate_image', impound_pic='$impound_pic'\n", FILE_APPEND);

        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id) || empty($contact_number)) {
            file_put_contents('../debug.log', "Create Violation Failed: Missing required fields.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Violator Name, Plate Number, Reason, Violation Type, and Contact Number are required.']);
            exit;
        }

        // Check if user_id is provided and valid
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND officer_id = ?");
            $stmt->execute([$user_id, $_SESSION['user_id']]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
//            if (!$existing_user || strtolower(trim($existing_user['full_name'])) !== strtolower(trim($violator_name))) {
  //              file_put_contents('../debug.log', "Create Violation Failed: Invalid user_id='$user_id' or name mismatch.\n", FILE_APPEND);
    //            echo json_encode(['success' => false, 'message' => 'Selected user is invalid or does not match the provided name.']);
      //          exit;
      //      } else {
          //      $email = $email ?: $existing_user['email'];
        //    }
        }

        // If no valid user_id, check if violator_name matches an existing user or create a new one
        if (!$user_id) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(full_name) = LOWER(?) AND officer_id = ?");
            $stmt->execute([$violator_name, $_SESSION['user_id']]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing_user) {
                $user_id = $existing_user['id'];
                $stmt = $pdo->prepare("UPDATE users SET contact_number = ?, email = ? WHERE id = ?");
                $success = $stmt->execute([$contact_number, $email, $user_id]);
                if (!$success) {
                    file_put_contents('../debug.log', "Update User Contact Failed: No rows affected.\n", FILE_APPEND);
                    echo json_encode(['success' => false, 'message' => 'Failed to update user contact information.']);
                    exit;
                }
            } else {
                $username = substr(strtolower(str_replace(' ', '_', $violator_name)), 0, 50);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?)");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    $username .= '_' . rand(1000, 9999);
                }
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, officer_id, contact_number, email) VALUES (?, 'x', ?, 'user', ?, ?, ?)");
                $success = $stmt->execute([$username, $violator_name, $_SESSION['user_id'], $contact_number, $email]);
                if ($success) {
                    $user_id = $pdo->lastInsertId();
                    file_put_contents('../debug.log', "Created new user: username='$username', user_id='$user_id', contact_number='$contact_number', email='$email'\n", FILE_APPEND);
                } else {
                    file_put_contents('../debug.log', "Create User Failed: No rows affected.\n", FILE_APPEND);
                    echo json_encode(['success' => false, 'message' => 'Failed to create new user.']);
                    exit;
                }
            }
        }

        // Calculate offense_freq
        $offense_freq = 1;
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ? AND officer_id = ?");
            $stmt->execute([$user_id, $_SESSION['user_id']]);
            $offense_freq = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            file_put_contents('../debug.log', "Offense Frequency for user_id='$user_id': $offense_freq\n", FILE_APPEND);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE LOWER(violator_name) = LOWER(?) AND officer_id = ?");
            $stmt->execute([$violator_name, $_SESSION['user_id']]);
            $offense_freq = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            file_put_contents('../debug.log', "Offense Frequency for violator_name='$violator_name': $offense_freq\n", FILE_APPEND);
        }

        // Fetch violation type details
        $stmt = $pdo->prepare("SELECT violation_type, fine_amount, base_offense FROM types WHERE id = ?");
        $stmt->execute([$violation_type_id]);
        $violation_type = $stmt->fetch(PDO::FETCH_ASSOC);
        $violation_type_name = $violation_type['violation_type'] ?? 'Unknown';
        $fine_amount = $violation_type['fine_amount'] ?? 0;
        $base_offense = $violation_type['base_offense'] ?? 'N/A';

        // Insert violation
        $stmt = $pdo->prepare("
            INSERT INTO violations (
                officer_id, user_id, violator_name, plate_number, reason, violation_type_id, 
                has_license, license_number, is_impounded, is_paid, or_number, issued_date, 
                status, notes, offense_freq, plate_image, impound_pic, email_sent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)
        ");
        $params = [
            $_SESSION['user_id'], $user_id, $violator_name, $plate_number, $reason, $violation_type_id,
            $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date,
            $status, $notes, $offense_freq, $plate_image, $impound_pic
        ];
        file_put_contents('../debug.log', "Executing INSERT query with params: " . print_r($params, true) . "\n", FILE_APPEND);
        $success = $stmt->execute($params);
        if ($success) {
            $violation_id = $pdo->lastInsertId();
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $stmt = $pdo->prepare("
                INSERT INTO officer_earnings (officer_id, week_start, total_fines) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE total_fines = total_fines + ?
            ");
            $success_earnings = $stmt->execute([$_SESSION['user_id'], $week_start, $fine_amount, $fine_amount]);
            if (!$success_earnings) {
                file_put_contents('../debug.log', "Update Officer Earnings Failed: No rows affected.\n", FILE_APPEND);
                echo json_encode(['success' => false, 'message' => 'Failed to update officer earnings.']);
                exit;
            }
            file_put_contents('../debug.log', "Violation created successfully, violation_id='$violation_id'\n", FILE_APPEND);
            echo json_encode(['success' => true, 'message' => 'Violation has been created successfully.']);
            exit;
        } else {
            file_put_contents('../debug.log', "Create Violation Failed: No rows affected.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Failed to create violation.']);
            exit;
        }
    } catch (PDOException $e) {
        file_put_contents('../debug.log', "Create Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Violation Created Successfully']);
        exit;
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
                // Update user contact information
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

                // Delete old image if new one is uploaded
                if ($plate_image && $violation['plate_image'] && file_exists($violation['plate_image'])) {
                    unlink($violation['plate_image']);
                }

                // Prepare update query
                $query = "
                    UPDATE violations SET 
                    violator_name = ?, plate_number = ?, reason = ?, violation_type_id = ?, 
                    has_license = ?, license_number = ?, is_impounded = ?, is_paid = ?, 
                    or_number = ?, issued_date = ?, status = ?, notes = ?
                ";
                $params = [
                    $violator_name, $plate_number, $reason, $violation_type_id,
                    $has_license, $license_number, $is_impounded, $is_paid,
                    $or_number, $issued_date, $status, $notes
                ];
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
                    $week_start = date('Y-m-d', strtotime('monday this week'));
                    $stmt = $pdo->prepare("SELECT fine_amount FROM types WHERE id = ?");
                    $stmt->execute([$violation_type_id]);
                    $fine = $stmt->fetch(PDO::FETCH_ASSOC)['fine_amount'] ?? 0;
                    $stmt = $pdo->prepare("
                        INSERT INTO officer_earnings (officer_id, plate_number, week_start, total_fines) 
                        VALUES (?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE total_fines = total_fines + ?
                    ");
                    $success_earnings = $stmt->execute([$_SESSION['user_id'], $plate_number, $week_start, $fine, $fine]);
                    if (!$success_earnings) {
                        $toastr_messages[] = "toastr.error('Failed to update officer earnings.');";
                        file_put_contents('../debug.log', "Update Officer Earnings Failed: No rows affected.\n", FILE_APPEND);
                    }
                    file_put_contents('../debug.log', "Violation updated successfully, redirecting to manage_violations.php\n", FILE_APPEND);
                    $_SESSION['edit_success'] = true;
                    session_write_close(); // Ensure session data is saved
                    header("Location: manage_violations.php");
                    exit;
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

// Handle delete violation (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_violation'])) {
    header('Content-Type: application/json'); // Set JSON response
    try {
        $id = trim($_POST['id'] ?? '');
        file_put_contents('../debug.log', "Delete Violation Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            file_put_contents('../debug.log', "Delete Violation Failed: Violation ID is required.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Violation ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT plate_image FROM violations WHERE id = ? AND officer_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($violation) {
            if ($violation['plate_image'] && file_exists($violation['plate_image'])) {
                unlink($violation['plate_image']);
            }
            $stmt = $pdo->prepare("DELETE FROM violations WHERE id = ? AND officer_id = ?");
            $params = [$id, $_SESSION['user_id']];
            $success = $stmt->execute($params);
            if ($success) {
                file_put_contents('../debug.log', "Violation deleted successfully, violation_id='$id'\n", FILE_APPEND);
                echo json_encode(['success' => true, 'message' => 'Violation has been deleted successfully.']);
                exit;
            } else {
                file_put_contents('../debug.log', "Delete Violation Failed: No rows affected for id='$id'.\n", FILE_APPEND);
                echo json_encode(['success' => false, 'message' => 'Failed to delete violation or you lack permission.']);
                exit;
            }
        } else {
            file_put_contents('../debug.log', "Delete Violation Failed: Violation ID='$id' not found or unauthorized.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Violation not found or you lack permission.']);
            exit;
        }
    } catch (PDOException $e) {
        file_put_contents('../debug.log', "Delete Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Error deleting violation: ' . $e->getMessage()]);
        exit;
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
    $stmt = $pdo->query("SELECT id, violation_type, fine_amount, base_offense FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
}

// Fetch all violations issued by the officer
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.officer_id, v.user_id, v.violator_name, v.plate_number, v.reason, 
               v.violation_type_id, v.has_license, v.license_number, v.is_impounded, v.is_paid, 
               v.or_number, v.issued_date, v.status, v.notes, v.offense_freq, v.plate_image, 
               t.violation_type, t.fine_amount, t.base_offense 
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
            <!-- Sidebar (unchanged) -->
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

       <a href="../pages/send_mail.php" class="btn btn-outline-primary" style="margin-left: auto; display: block; width: fit-content;">Email Ticket</a>

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
                                        <th>Base Offense</th>
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
                                        <tr><td colspan="18" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <?php
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
                                                <td><?php echo htmlspecialchars($violation['base_offense'] ?: 'N/A'); ?></td>
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
                                <button type="button" class="btn btn-sm btn-danger delete-violation-btn" data-id="<?php echo $violation['id']; ?>">Delete</button>                                                
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
                                                                        <select class="form-select" name="violation_type_id" id="violation_type_id_<?php echo $violation['id']; ?>" required>
                                                                            <option value="" disabled>Select</option>
                                                                            <?php foreach ($types as $type): ?>
                                                                                <option value="<?php echo htmlspecialchars($type['id']); ?>" 
                                                                                        <?php echo $violation['violation_type_id'] == $type['id'] ? 'selected' : ''; ?>
                                                                                        data-original-text="<?php echo htmlspecialchars($type['violation_type'] . ' (' . ($type['base_offense'] ?: 'N/A') . ') - ₱' . number_format($type['fine_amount'], 2)); ?>">
                                                                                    <?php echo htmlspecialchars($type['violation_type'] . ' (' . ($type['base_offense'] ?: 'N/A') . ') - ₱' . number_format($type['fine_amount'], 2)); ?>
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
                        <input type="hidden" name="selected_violation_type_id" id="selected_violation_type_id">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="violator_name" class="form-label">Violator Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="violator_name" id="violator_name" required>
                                    <div class="invalid-feedback">Please enter violator name.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="contact_number" id="contact_number" required placeholder="09XX-XXX-XXXX">
                                    <div class="invalid-feedback">Please enter a valid contact number (e.g., 0917-123-4567).</div>
                                    <small class="form-text text-muted">Format: 09XX-XXX-XXXX</small>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email (Optional)</label>
                                    <input type="email" class="form-control" name="email" id="email" placeholder="example@email.com">
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
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
                                        <label for="plate_number" class="form-label">License Plate <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="plate_number" id="plate_number" required placeholder="ABC-1234" maxlength="8">
                                        <div class="invalid-feedback">Please enter a valid plate number (e.g., ABC-1234).</div>
                                        <small class="form-text text-muted">Format: XXX-XXXX (3 letters + 4 numbers)</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reason" id="reason" required>
                                        <div class="invalid-feedback">Please enter reason.</div>
                                    </div>
                                </div>
                                <!-- Selected Violation Display -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div id="selectedViolationDisplay" class="alert alert-success d-none" role="alert">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>Selected:</strong> <span id="selectedViolationText"></span>
                                        </div>
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
                                                        <th>Base Offense</th>
                                                        <th>Fine Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="violationTypeBody">
                                                    <tr>
                                                        <td colspan="4" class="text-center">Enter a plate number to view available violation types</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- Violation History Table -->
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <h6>Violation History</h6>
                                        <div id="violationHistoryTable" class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Violation Type</th>
                                                        <th>Base Offense</th>
                                                        <th>Fine Amount</th>
                                                        <th>Issued Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="violationHistoryBody">
                                                    <tr>
                                                        <td colspan="5" class="text-center">Enter a plate number to view violation history</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- Rest of the form fields -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="has_license" id="has_license">
                                            <label class="form-check-label" for="has_license">Has License</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="license_number" class="form-label">License Number</label>
                                        <input type="text" class="form-control" name="license_number" id="license_number" placeholder="NXX12C34567" maxlength="12">
                                        <div class="invalid-feedback">Please enter a valid license number (e.g., NXX12C34567).</div>
                                        <small class="form-text text-muted">Format: NXX12C34567 (3 letters + 2 numbers + 1 letter + 6 numbers)</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded">
                                            <label class="form-check-label" for="is_impounded">Is Impounded</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3" id="impound_pic_container">
                                        <label for="impound_pic" class="form-label">Upload Impound Image</label>
                                        <input type="file" class="form-control" name="impound_pic" id="impound_pic" accept="image/*">
                                        <div class="invalid-feedback">Please upload an impound image.</div>
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
                                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                            <i class="fas fa-exclamation-triangle me-2"></i>Please select a violation type
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.0/dist/tesseract.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debugging: Confirm Tesseract is available
        if (typeof Tesseract === 'undefined') {
            console.error('Tesseract.js is not loaded');
            toastr.error('Tesseract.js failed to load. OCR functionality unavailable.');
        } else {
            console.log('Tesseract.js loaded successfully');
        }

        // Initialize Toastr
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };

        // DOM Elements
        const plateNumberInput = document.getElementById('plate_number');
        const violationHistoryBody = document.getElementById('violationHistoryBody');
        const violationTypeBody = document.getElementById('violationTypeBody');
        const selectedViolationTypeId = document.getElementById('selected_violation_type_id');
        const selectedViolationDisplay = document.getElementById('selectedViolationDisplay');
        const selectedViolationText = document.getElementById('selectedViolationText');
        const submitBtn = document.getElementById('submitBtn');
        const isImpoundedCheckbox = document.getElementById('is_impounded');
        const impoundPicContainer = document.getElementById('impound_pic_container');
        const impoundPicInput = document.getElementById('impound_pic');
        const originalTypes = <?php echo json_encode($types ?? []); ?> || [];
        let currentlySelectedRow = null;

        // Toggle impound_pic input visibility
        isImpoundedCheckbox.addEventListener('change', function() {
            impoundPicContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                impoundPicInput.value = ''; // Clear file input when unchecked
            }
        });

        // Initialize input formatters and validators
        initializeInputFormatters();

        function initializeInputFormatters() {
            const contactNumberInput = document.getElementById('contact_number');
            const emailInput = document.getElementById('email');
            const licenseNumberInput = document.getElementById('license_number');

            // Contact Number (09XX-XXX-XXXX)
            contactNumberInput.addEventListener('input', formatContactNumber);
            contactNumberInput.addEventListener('blur', validateContactNumber);
            contactNumberInput.addEventListener('focus', clearFormatOnFocus);

            // Email
            emailInput.addEventListener('input', validateEmailOnInput);
            emailInput.addEventListener('blur', validateEmail);

            // Plate Number (XXX-XXXX)
            plateNumberInput.addEventListener('input', formatPlateNumber);
            plateNumberInput.addEventListener('blur', validatePlateNumber);
            plateNumberInput.addEventListener('focus', clearFormatOnFocus);

            // License Number (NXX12C34567)
            licenseNumberInput.addEventListener('input', formatLicenseNumber);
            licenseNumberInput.addEventListener('blur', validateLicenseNumber);
            licenseNumberInput.addEventListener('focus', clearFormatOnFocus);
        }

        // Contact Number Formatting and Validation
        function formatContactNumber(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            if (value.length > 0) {
                if (value.length <= 4) {
                    value = value;
                } else if (value.length <= 7) {
                    value = value.substring(0, 4) + '-' + value.substring(4);
                } else {
                    value = value.substring(0, 4) + '-' + value.substring(4, 7) + '-' + value.substring(7);
                }
            }
            e.target.value = value;
        }

        function validateContactNumber(e) {
            const value = e.target.value.replace(/\D/g, '');
            const isValid = value.length === 11 && value.startsWith('09');
            e.target.classList.remove('is-invalid', 'is-valid');
            if (value.length > 0) {
                e.target.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }

        // Email Validation
        function validateEmailOnInput(e) {
            const value = e.target.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = value === '' || emailRegex.test(value);
            e.target.classList.remove('is-invalid', 'is-valid');
            if (value.length > 0) {
                e.target.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }

        function validateEmail(e) {
            const value = e.target.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isValid = value === '' || emailRegex.test(value);
            e.target.classList.remove('is-invalid', 'is-valid');
            if (value.length > 0) {
                e.target.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }

        // Plate Number Formatting and Validation
//        function formatPlateNumber(e) {
  //          let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    //        if (value.length > 7) value = value.substring(0, 7);
      //      if (value.length > 0) {
        //        if (value.length <= 3) {
          //          value = value;
            //    } else {
              //      value = value.substring(0, 3) + value.substring(3);
               // }
           // }
           // e.target.value = value;
       // }

        function validatePlateNumber(e) {
            const value = e.target.value.replace(/[^A-Z0-9]/g, '');
            const isValid = value.length === 7 && /^[A-Z]{3}[0-9]{4}$/.test(value);
            e.target.classList.remove('is-invalid', 'is-valid');
            if (value.length > 0) {
                e.target.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }

        // License Number Formatting and Validation
        function formatLicenseNumber(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 12) value = value.substring(0, 12);
            if (value.length >= 1 && !/^[A-Z]/.test(value)) {
                value = 'N' + value.substring(1);
            }
            e.target.value = value;
        }

        function validateLicenseNumber(e) {
            const value = e.target.value.replace(/[^A-Z0-9]/g, '');
            const licenseRegex = /^[A-Z]{3}[0-9]{2}[A-Z][0-9]{6}$/;
            const isValid = value.length === 12 && licenseRegex.test(value);
            e.target.classList.remove('is-invalid', 'is-valid');
            if (value.length > 0) {
                e.target.classList.add(isValid ? 'is-valid' : 'is-invalid');
            }
        }

        // Clear formatting on focus
        function clearFormatOnFocus(e) {
            if (e.target.dataset.originalValue === undefined) {
                e.target.dataset.originalValue = e.target.value;
            }
            if (e.target.value.includes('-')) {
                const cleanValue = e.target.value.replace(/[^A-Za-z0-9]/g, '');
                e.target.value = cleanValue;
            }
        }

        // Plate Number Input Handler
        plateNumberInput.addEventListener('input', function() {
            const plateNumber = this.value.replace(/[^A-Z0-9]/g, '').toUpperCase();
            if (plateNumber.length >= 3) {
                fetchViolationHistory(plateNumber);
                fetchUserByPlateNumber(plateNumber);
            } else {
                clearHistoryAndResetTables();
            }
        });

        // Fetch Violation History
        function fetchViolationHistory(plateNumber) {
            console.log('Fetching violation history for:', plateNumber);
            fetch('fetch_violation_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'plate_number=' + encodeURIComponent(plateNumber)
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error: ' + response.statusText);
                return response.json();
            })
            .then(data => {
                console.log('Violation history response:', data);
                violationHistoryBody.innerHTML = '';
                populateAvailableTypes(data);

                const usedTypeIds = new Set();
                if (data.success && Array.isArray(data.violations) && data.violations.length > 0) {
                    data.violations.forEach(violation => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${violation.violation_type || 'N/A'}</td>
                            <td>${violation.base_offense || 'N/A'}</td>
                            <td>₱${parseFloat(violation.fine_amount || 0).toFixed(2)}</td>
                            <td>${new Date(violation.issued_date || '').toLocaleString() || 'N/A'}</td>
                            <td><span class="badge bg-${violation.status === 'Resolved' ? 'success' : violation.status === 'Pending' ? 'warning' : 'secondary'}">${violation.status || 'N/A'}</span></td>
                        `;
                        violationHistoryBody.appendChild(row);
                        if (violation.violation_type_id) usedTypeIds.add(violation.violation_type_id.toString());
                    });
                } else {
                    violationHistoryBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No violations found for this plate number</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching violation history:', error);
                violationHistoryBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading history: ' + error.message + '</td></tr>';
                violationTypeBody.innerHTML = '<tr><td colspan="4" class="text-center">Enter a plate number to view available violation types</td></tr>';
            });
        }

        // Populate Available Violation Types
        function populateAvailableTypes(data) {
            violationTypeBody.innerHTML = '';
            const usedTypeIds = new Set(data.violations ? data.violations.map(v => v.violation_type_id.toString()) : []);
            const availableTypes = originalTypes.filter(type => !usedTypeIds.has(type.id.toString()));

            if (availableTypes.length > 0) {
                availableTypes.forEach(type => {
                    const row = document.createElement('tr');
                    row.className = currentlySelectedRow === type.id.toString() ? 'selected-row' : '';
                    row.innerHTML = `
                        <td>
                            <button type="button" class="btn btn-sm ${currentlySelectedRow === type.id.toString() ? 'selected-btn' : 'btn-outline-primary'} select-violation" data-id="${type.id}">
                                ${currentlySelectedRow === type.id.toString() ? '✓ Selected' : 'Select'}
                            </button>
                        </td>
                        <td>${type.violation_type}</td>
                        <td>${type.base_offense || 'N/A'}</td>
                        <td>₱${parseFloat(type.fine_amount || 0).toFixed(2)}</td>
                    `;
                    violationTypeBody.appendChild(row);
                });

                document.querySelectorAll('.select-violation').forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const id = this.getAttribute('data-id');
                        
                        if (currentlySelectedRow) {
                            const prevRow = document.querySelector(`[data-id="${currentlySelectedRow}"]`).closest('tr');
                            if (prevRow) prevRow.classList.remove('selected-row');
                            const prevButton = document.querySelector(`button[data-id="${currentlySelectedRow}"]`);
                            if (prevButton) {
                                prevButton.className = 'btn btn-sm btn-outline-primary select-violation';
                                prevButton.textContent = 'Select';
                            }
                        }

                        currentlySelectedRow = id;
                        selectedViolationTypeId.value = id;
                        
                        const newRow = this.closest('tr');
                        newRow.classList.add('selected-row');
                        this.className = 'btn btn-sm selected-btn select-violation';
                        this.textContent = '✓ Selected';
                        
                        const selectedType = originalTypes.find(t => t.id.toString() === id);
                        selectedViolationText.textContent = `${selectedType.violation_type} (${selectedType.base_offense || 'N/A'}) - ₱${parseFloat(selectedType.fine_amount).toFixed(2)}`;
                        selectedViolationDisplay.classList.remove('d-none');
                        
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';
                        
                        toastr.success(`Selected: ${selectedType.violation_type}`);
                    });
                });
            } else {
                violationTypeBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No available violation types</td></tr>';
            }
        }

        // Clear History and Reset Tables
        function clearHistoryAndResetTables() {
            violationHistoryBody.innerHTML = '<tr><td colspan="5" class="text-center">Enter a plate number to view violation history</td></tr>';
            violationTypeBody.innerHTML = '<tr><td colspan="4" class="text-center">Enter a plate number to view available violation types</td></tr>';
            selectedViolationTypeId.value = '';
            currentlySelectedRow = null;
            selectedViolationDisplay.classList.add('d-none');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please select a violation type';
        }

        // Form Submission Handler
        document.getElementById('createViolationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const hiddenInput = document.getElementById('selected_violation_type_id');
            hiddenInput.name = 'violation_type_id';

            const requiredFields = {
                violator_name: document.getElementById('violator_name'),
                contact_number: document.getElementById('contact_number'),
                plate_number: document.getElementById('plate_number'),
                reason: document.getElementById('reason'),
                violation_type_id: hiddenInput
            };
            const emailInput = document.getElementById('email');

            let isValid = true;

            // Clear previous validation states
            Object.values(requiredFields).forEach(field => {
                field.classList.remove('is-invalid', 'is-valid');
            });
            emailInput.classList.remove('is-invalid', 'is-valid');

            // Validate required fields
            Object.entries(requiredFields).forEach(([key, field]) => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                    return;
                }

                if (key === 'contact_number') {
                    const phoneValue = field.value.replace(/\D/g, '');
                    if (phoneValue.length !== 11 || !phoneValue.startsWith('09')) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.add('is-valid');
                    }
                }

                if (key === 'plate_number') {
                    const plateValue = field.value.replace(/[^A-Z0-9]/g, '');
                    if (plateValue.length !== 7 || !/^[A-Z]{3}[0-9]{4}$/.test(plateValue)) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.add('is-valid');
                    }
                }
            });

            // Validate email if provided
            if (emailInput.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value.trim())) {
                    emailInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    emailInput.classList.add('is-valid');
                }
            }

            // Validate impound_pic if is_impounded is checked
            if (isImpoundedCheckbox.checked && !impoundPicInput.files.length) {
                impoundPicInput.classList.add('is-invalid');
                isValid = false;
            } else {
                impoundPicInput.classList.remove('is-invalid');
            }

            if (!isValid) {
                hiddenInput.name = 'selected_violation_type_id';
                Swal.fire({
                    title: 'Validation Error!',
                    text: 'Please correct the highlighted fields and ensure all data is in the correct format.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const selectedType = originalTypes.find(t => t.id.toString() === hiddenInput.value);
            const fineAmount = selectedType ? parseFloat(selectedType.fine_amount).toFixed(2) : '0.00';
            const emailValue = emailInput.value.trim() || 'N/A';

            Swal.fire({
                title: 'Confirm Violation',
                html: `
                    <div class="text-left">
                        <p><strong>Violator:</strong> ${requiredFields.violator_name.value}</p>
                        <p><strong>Contact:</strong> ${requiredFields.contact_number.value}</p>
                        <p><strong>Email:</strong> ${emailValue}</p>
                        <p><strong>Plate:</strong> ${requiredFields.plate_number.value}</p>
                        <p><strong>Reason:</strong> ${requiredFields.reason.value}</p>
                        <p><strong>Violation:</strong> ${selectedType ? `${selectedType.violation_type} (${selectedType.base_offense || 'N/A'})` : 'N/A'}</p>
                        <p><strong>Fine:</strong> ₱${fineAmount}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create violation!',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';

                    const formData = new FormData(document.getElementById('createViolationForm'));
                    fetch('manage_violations.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';

                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Violation has been created successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'manage_violations.php';
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: data.message || 'Failed to create violation.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred: ' + error.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                } else {
                    hiddenInput.name = 'selected_violation_type_id';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';
                }
            });
        });

        // OCR for Plate Image
        document.getElementById('plate_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                console.log('Plate image selected:', file.name);
                performOCR(file, 'plate_number');
            } else {
                console.warn('No file selected for plate_image');
            }
        });

        function performOCR(file, inputId) {
            const ocrStatus = document.getElementById('ocr_status');
            ocrStatus.textContent = 'Processing image...';
            console.log('Starting OCR for input:', inputId);
            Tesseract.recognize(file, 'eng', { logger: m => console.log('OCR Progress:', m) })
                .then(({ data: { text } }) => {
                    const cleanedText = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                    console.log('OCR Result:', cleanedText);
                    const input = document.getElementById(inputId);
                    input.value = cleanedText;
                    ocrStatus.textContent = `Plate detected: ${cleanedText}`;
                    input.dispatchEvent(new Event('input'));
                    fetchUserByPlateNumber(cleanedText);
                })
                .catch(error => {
                    console.error('OCR Error:', error);
                    ocrStatus.textContent = 'Error extracting text from image.';
                    toastr.error('Failed to process plate image: ' + error.message);
                });
        }

        // Fetch User by Plate Number
        function fetchUserByPlateNumber(plateNumber) {
            if (!plateNumber) {
                console.warn('No plate number provided for fetchUserByPlateNumber');
                return;
            }
            console.log('Fetching user by plate number:', plateNumber);
            fetch('get_user_by_plate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'plate_number=' + encodeURIComponent(plateNumber)
            })
                .then(response => {
                    if (!response.ok) throw new Error('Network error: ' + response.statusText);
                    return response.json();
                })
                .then(data => {
                    console.log('Fetch user response:', data);
                    if (data.success) {
                        document.getElementById('violator_name').value = data.violator_name || '';
                        document.getElementById('contact_number').value = formatContactNumberFromData(data.contact_number || '');
                        document.getElementById('email').value = data.email || '';
                        document.getElementById('user_id').value = data.user_id || '';
                        document.getElementById('has_license').checked = data.has_license == 1;
                        document.getElementById('license_number').value = data.license_number || '';
                        toastr.success('User details populated successfully.');
                        // Validate populated fields
                        validateContactNumber({ target: document.getElementById('contact_number') });
                        validateEmail({ target: document.getElementById('email') });
                        validateLicenseNumber({ target: document.getElementById('license_number') });
                    } else {
                        ['violator_name', 'contact_number', 'email', 'user_id', 'license_number'].forEach(id => 
                            document.getElementById(id).value = ''
                        );
                        document.getElementById('has_license').checked = false;
                        toastr.info(data.message || 'No previous violation found for this plate number.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching user details:', error);
                    toastr.error('Error fetching user details: ' + error.message);
                });
        }

        function formatContactNumberFromData(phone) {
            if (!phone) return '';
            let cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.length === 11 && cleanPhone.startsWith('09')) {
                return `${cleanPhone.substring(0, 4)}-${cleanPhone.substring(4, 7)}-${cleanPhone.substring(7)}`;
            }
            return phone;
        }

        // Initial table reset
        clearHistoryAndResetTables();
    
          // Delete Violation Handler
            document.querySelectorAll('.delete-violation-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const violationId = this.getAttribute('data-id');
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: `Do you want to delete violation ID ${violationId}? This action cannot be undone.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then(result => {
                        if (result.isConfirmed) {
                            const formData = new FormData();
                            formData.append('delete_violation', '1');
                            formData.append('id', violationId);

                            fetch('manage_violations.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Deleted!',
                                        text: 'Violation has been deleted successfully.',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    console.error('Error deleting violation:', data.message);
                                    toastr.error(data.message || 'Failed to delete violation.');
                                }
                            })
                            .catch(error => {
                                console.error('Fetch error:', error);
                                toastr.error('An error occurred while deleting the violation.');
                            });
                        }
                    });
                });
            });

            // Initial table reset
            clearHistoryAndResetTables();
        });
    


    
    </script>
</body>
</html>