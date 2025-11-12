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


// ---------- 3. MULTIPLE VIOLATOR PICTURES ----------
$violator_pic_paths = [];                     // will hold each saved path
$allowed_ext = ['jpg','jpeg','png','gif','webp'];

if (!empty($_FILES['violator_pic']['name'][0])) {   // at least one file selected
    $files = $_FILES['violator_pic'];

    foreach ($files['error'] as $i => $err) {
        if ($err !== UPLOAD_ERR_OK) {
            // optional: log non-OK errors
            continue;
        }

        $tmp   = $files['tmp_name'][$i];
        $name  = $files['name'][$i];
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            echo json_encode(['success'=>false,
                             'message'=>"Invalid file type: $name"]);
            exit;
        }

        $new_name = uniqid('violator_') . '_' . basename($name);
        $dest     = $upload_dir . $new_name;

        if (!move_uploaded_file($tmp, $dest)) {
            file_put_contents('../debug.log',
                "Violator Pic Upload Failed: $dest\n", FILE_APPEND);
            echo json_encode(['success'=>false,
                             'message'=>"Failed to save picture: $name"]);
            exit;
        }

        $violator_pic_paths[] = $dest;
    }
}
$violator_pic = !empty($violator_pic_paths) ? implode(',', $violator_pic_paths) : null;

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

        if (empty($violator_name) || empty($reason) || empty($violation_type_id) || empty($contact_number)) {
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
        officer_id, user_id, violator_name, violator_email, plate_number, reason, violation_type_id,
        has_license, license_number, is_impounded, is_paid, or_number, issued_date,
        status, notes, offense_freq, plate_image, impound_pic, violator_pic, email_sent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE)
");
$params = [
    $_SESSION['user_id'], $user_id, $violator_name, $email, $plate_number, $reason, $violation_type_id,
    $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date,
    $status, $notes, $offense_freq, $plate_image, $impound_pic, $violator_pic
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
        SELECT 
            v.id, 
            v.officer_id, 
            v.user_id, 
            v.violator_name, 
            v.plate_number, 
            v.reason, 
            v.violation_type_id, 
            v.has_license, 
            v.license_number, 
            v.is_impounded, 
            v.is_paid, 
            v.or_number, 
            v.issued_date, 
            v.status, 
            v.notes, 
            v.offense_freq, 
            v.plate_image,
            v.violator_pic,          -- INCLUDED HERE
            t.violation_type, 
            t.fine_amount, 
            t.base_offense 
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
.table img {
    transition: transform .2s ease;
    cursor: zoom-in;
}
.table img:hover {
    transform: scale(3);
    position: relative;
    z-index: 1000;
    box-shadow: 0 10px 20px rgba(0,0,0,0.3);
}
</style>


<style>
.violator-thumb {
    transition: transform .2s ease;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.violator-thumb:hover {
    transform: scale(1.1);
}

.carousel-control-prev,
.carousel-control-next {
    opacity: 0.9 !important;
    transition: opacity 0.2s;
}
.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 1 !important;
}

/* Optional: Add indicators (dots) at bottom */
.carousel-indicators {
    bottom: 10px;
}
.carousel-indicators [data-bs-target] {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: rgba(0,0,0,0.5);
}
</style>


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
                                        <th>Violator Image</th>
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
<?php
// ---------------------------------------------------
// 1. PREPARE VIOLATOR PICTURES (once per row)
// ---------------------------------------------------
$violatorPic = $violation['violator_pic'] ?? null;
$allPics     = [];
$picCount    = 0;

if ($violatorPic && trim($violatorPic) !== '') {
    $allPics = array_filter(
        explode(',', $violatorPic),
        function($p) { return file_exists(trim($p)); }
    );
    $picCount = count($allPics);
}
?>

<!-- ====================== VIOLATOR IMAGE CELL ====================== -->
<td>
    <?php if ($picCount > 0): ?>
        <?php $firstPic = htmlspecialchars(trim(reset($allPics))); ?>
        <div class="position-relative d-inline-block">
            <a href="javascript:void(0)"
               data-bs-toggle="modal"
               data-bs-target="#violatorModal<?php echo $violation['id']; ?>">
                <img src="<?php echo $firstPic; ?>"
                     alt="Violator"
                     class="img-thumbnail violator-thumb"
                     style="width:60px;height:60px;object-fit:cover;cursor:pointer;">
            </a>
            <?php if ($picCount > 1): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary"
                      style="font-size:0.65rem;">
                    +<?php echo $picCount - 1; ?>
                </span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <span class="text-muted">N/A</span>
    <?php endif; ?>
</td>

<!-- ====================== MODAL (inside loop) ====================== -->
<div class="modal fade" id="violatorModal<?php echo $violation['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Violator: <?php echo htmlspecialchars($violation['violator_name']); ?>
                    <small class="text-muted ms-2">(<?php echo $picCount; ?> image<?php echo $picCount !== 1 ? 's' : ''; ?>)</small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0 position-relative">
                <?php if ($picCount > 0): ?>
                    <div id="carouselViolator<?php echo $violation['id']; ?>" class="carousel slide">
                        <div class="carousel-inner">
                            <?php foreach ($allPics as $index => $pic):
                                $path   = htmlspecialchars(trim($pic));
                                $active = $index === 0 ? 'active' : '';
                            ?>
                                <div class="carousel-item <?php echo $active; ?>">
                                    <img src="<?php echo $path; ?>"
                                         class="d-block w-100"
                                         alt="Violator Picture <?php echo $index + 1; ?>"
                                         style="max-height:70vh; object-fit:contain;">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($picCount > 1): ?>
                            <!-- ENHANCED LEFT ARROW -->
                            <button class="carousel-control-prev" type="button"
                                    data-bs-target="#carouselViolator<?php echo $violation['id']; ?>"
                                    data-bs-slide="prev"
                                    style="width:60px; opacity:0.9;">
                                <span class="carousel-control-prev-icon" 
                                      style="width:40px; height:40px; background-color:rgba(0,0,0,0.6); border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 16 16">
                                        <path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                                    </svg>
                                </span>
                                <span class="visually-hidden">Previous</span>
                            </button>

                            <!-- ENHANCED RIGHT ARROW -->
                            <button class="carousel-control-next" type="button"
                                    data-bs-target="#carouselViolator<?php echo $violation['id']; ?>"
                                    data-bs-slide="next"
                                    style="width:60px; opacity:0.9;">
                                <span class="carousel-control-next-icon"
                                      style="width:40px; height:40px; background-color:rgba(0,0,0,0.6); border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 16 16">
                                        <path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                                    </svg>
                                </span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted p-5">No images available.</p>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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
                                                                        <div class="invalid-feedback">Please enter a valid plate number.</div>
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
                <form method="POST" class="create-violation-form" id="createViolationForm" enctype="multipart/form-data">
                    <input type="hidden" name="create_violation" value="1">
                    <input type="hidden" name="user_id" id="user_id">
                    <input type="hidden" name="selected_violation_type_id" id="selected_violation_type_id">

                    <div class="row">
                        <!-- ==== LEFT COLUMN ==== -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="violator_name" class="form-label">Violator Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="violator_name" id="violator_name" required>
                                <div class="invalid-feedback">Please enter violator name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="contact_number" id="contact_number"
                                       placeholder="09XX-XXX-XXXX" required>
                                <div class="invalid-feedback">Please enter a valid contact number (e.g., 0917-123-4567).</div>
                                <small class="form-text text-muted">Format: 09XX-XXX-XXXX</small>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email (Optional)</label>
                                <input type="email" class="form-control" name="email" id="email" placeholder="example@email.com">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <!-- ==== RIGHT COLUMN ==== -->
                        <div class="col-md-8">
                            <!-- Plate Image + Plate Number -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="plate_image" class="form-label">Upload Plate Number</label>
                                    <input type="file" class="form-control" name="plate_image" id="plate_image" accept="image/*">
                                    <div id="ocr_status" class="form-text"></div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="plate_number" class="form-label">Plate Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="plate_number" id="plate_number"
                                           placeholder="ABC-1234" maxlength="8" required>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reason" id="reason" required>
                                    <div class="invalid-feedback">Please enter reason.</div>
                                </div>
                            </div>

                            <!-- Selected Violation Alert -->
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div id="selectedViolationDisplay" class="alert alert-success d-none" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Selected:</strong> <span id="selectedViolationText"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Available Violation Types -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h6>Available Violation Types</h6>
                                    <div class="table-responsive" id="violationTypeTable">
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
                                                <tr><td colspan="4" class="text-center">Enter a plate number to view available violation types</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Violation History -->
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <h6>Violation History</h6>
                                    <div class="table-responsive" id="violationHistoryTable">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Plate</th>
                                                    <th>License</th>
                                                    <th>Violation Type</th>
                                                    <th>Base Offense</th>
                                                    <th>Fine</th>
                                                    <th>Issued Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="violationHistoryBody">
                                                <tr><td colspan="7" class="text-center">Enter a plate number to view violation history</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- License, Impound, Payment -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="has_license" id="has_license" checked>
                                        <label class="form-check-label" for="has_license">Has License</label>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="license_number" class="form-label">
                                        License Number <span id="license_required_indicator" class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="license_number" id="license_number"
                                           placeholder="NXX12C34567" maxlength="12" required>
                                    <div class="invalid-feedback">Please enter a valid license number (e.g., NXX12C34567).</div>
                                    <small class="form-text text-muted">Format: NXX12C34567</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_impounded" id="is_impounded">
                                        <label class="form-check-label" for="is_impounded">Is Impounded</label>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3" id="impound_pic_container" style="display:none;">
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

                                <div class="col-md-6 mb-3">
                                    <label for="or_number" class="form-label">CR Number</label>
                                    <input type="text" class="form-control" name="or_number" id="or_number">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="issued_date" class="form-label">Issued Date</label>
                                    <input type="datetime-local" class="form-control" name="issued_date" id="issued_date"
                                           value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>

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

                            <!-- Violator Pictures (multiple) -->
                            <div class="row">
                                <div class="col-md-6 mb-3" id="violator_pic_container">
                                    <label for="violator_pic" class="form-label">
                                        Violator Picture(s) <small class="text-muted">(optional – multiple allowed)</small>
                                    </label>
                                    <input type="file" class="form-control" name="violator_pic[]" id="violator_pic"
                                           accept="image/*" multiple>
                                    <div class="preview mt-2"></div>
                                </div>
                            </div>

                            <!-- Submit -->
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

<!-- ============================================================= -->
<!--  ALL EXTERNAL SCRIPTS (once)                                 -->
<!-- ============================================================= -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.0/dist/tesseract.min.js"></script>

<!-- ============================================================= -->
<!--  MAIN APPLICATION LOGIC (single <script> block)              -->
<!-- ============================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ------------------------------------------------------------------ */
    /*  INITIAL SETUP                                                     */
    /* ------------------------------------------------------------------ */
    if (typeof Tesseract === 'undefined') {
        console.error('Tesseract.js not loaded');
        toastr.error('OCR unavailable.');
    }

    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 5000
    };

    const officerId = <?php echo json_encode($_SESSION['user_id']); ?>;
    const originalTypes = <?php echo json_encode($types ?? []); ?> || [];

    /* ------------------------------------------------------------------ */
    /*  DOM REFERENCES                                                    */
    /* ------------------------------------------------------------------ */
    const els = {
        plateImg          : document.getElementById('plate_image'),
        plateNumber       : document.getElementById('plate_number'),
        licenseNumber     : document.getElementById('license_number'),
        hasLicense        : document.getElementById('has_license'),
        licenseReqInd     : document.getElementById('license_required_indicator'),

        contactNumber     : document.getElementById('contact_number'),
        email             : document.getElementById('email'),

        violationHistory  : document.getElementById('violationHistoryBody'),
        violationTypes    : document.getElementById('violationTypeBody'),

        selectedTypeId    : document.getElementById('selected_violation_type_id'),
        selectedDisplay   : document.getElementById('selectedViolationDisplay'),
        selectedText      : document.getElementById('selectedViolationText'),

        submitBtn         : document.getElementById('submitBtn'),

        isImpounded       : document.getElementById('is_impounded'),
        impoundContainer  : document.getElementById('impound_pic_container'),
        impoundInput      : document.getElementById('impound_pic'),

        violatorPicInput  : document.getElementById('violator_pic'),
        violatorPicPreview: document.querySelector('#violator_pic_container .preview')
    };

    let currentlySelectedRow = null;
    let lastValidKey = '';
    let isLoading = false;

    /* ------------------------------------------------------------------ */
    /*  UTILITIES                                                         */
    /* ------------------------------------------------------------------ */
    const escapeHtml = (txt) => txt ? (new DOMParser().parseFromString(txt, 'text/html').body.textContent) : 'N/A';

    const debounce = (fn, wait) => {
        let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), wait); };
    };

    /* ------------------------------------------------------------------ */
    /*  INPUT FORMATTERS & VALIDATORS                                      */
    /* ------------------------------------------------------------------ */
    const formatContact = (e) => {
        let v = e.target.value.replace(/\D/g, '').slice(0,11);
        if (v.length > 7) v = `${v.slice(0,4)}-${v.slice(4,7)}-${v.slice(7)}`;
        else if (v.length > 4) v = `${v.slice(0,4)}-${v.slice(4)}`;
        e.target.value = v;
    };
    const validateContact = (e) => {
        const clean = e.target.value.replace(/\D/g, '');
        const ok = clean.length === 11 && clean.startsWith('09');
        e.target.classList.toggle('is-valid', ok);
        e.target.classList.toggle('is-invalid', !ok && clean);
    };

    const formatPlate = (e) => {
        let v = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,7);
        if (v.length === 7) v = `${v.slice(0,3)}-${v.slice(3)}`;
        e.target.value = v;
    };

    const formatLicense = (e) => {
        let v = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0,12);
        if (v && !/^[A-Z]/.test(v)) v = 'N' + v.slice(1);
        e.target.value = v;
    };
    const validateLicense = (e) => {
        const clean = e.target.value.replace(/[^A-Z0-9]/g, '');
        const ok = clean.length === 12 && /^[A-Z]{3}[0-9]{2}[A-Z][0-9]{6}$/.test(clean);
        e.target.classList.toggle('is-valid', ok);
        e.target.classList.toggle('is-invalid', !ok && clean);
    };

    const validateEmail = (e) => {
        const v = e.target.value.trim();
        const ok = v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/i.test(v);
        e.target.classList.toggle('is-valid', ok && v);
        e.target.classList.toggle('is-invalid', !ok && v);
    };

    const clearFormatOnFocus = (e) => {
        if (e.target.value.includes('-')) e.target.value = e.target.value.replace(/[^A-Za-z0-9]/g, '');
    };

    /* ------------------------------------------------------------------ */
    /*  LICENSE REQUIRED TOGGLE                                            */
    /* ------------------------------------------------------------------ */
    const toggleLicense = () => {
        if (els.hasLicense.checked) {
            els.licenseNumber.setAttribute('required', '');
            els.licenseReqInd.style.display = 'inline';
        } else {
            els.licenseNumber.removeAttribute('required');
            els.licenseNumber.value = '';
            els.licenseReqInd.style.display = 'none';
        }
    };
    els.hasLicense.addEventListener('change', toggleLicense);
    toggleLicense();

    /* ------------------------------------------------------------------ */
    /*  IMPOUND PICTURE TOGGLE                                            */
    /* ------------------------------------------------------------------ */
    els.isImpounded.addEventListener('change', () => {
        els.impoundContainer.style.display = els.isImpounded.checked ? 'block' : 'none';
        if (!els.isImpounded.checked) els.impoundInput.value = '';
    });

    /* ------------------------------------------------------------------ */
    /*  VIOLATOR PICTURE PREVIEW                                          */
    /* ------------------------------------------------------------------ */
    els.violatorPicInput.addEventListener('change', (e) => {
        els.violatorPicPreview.innerHTML = '';
        for (const f of e.target.files) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;margin:0 5px 5px 0;border-radius:4px;';
            els.violatorPicPreview.appendChild(img);
        }
    });

    /* ------------------------------------------------------------------ */
    /*  CLEAR TABLES                                                       */
    /* ------------------------------------------------------------------ */
    const clearTables = () => {
        els.violationHistory.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Enter plate or license to view history</td></tr>';
        els.violationTypes.innerHTML   = '<tr><td colspan="4" class="text-center text-muted">Select violation type</td></tr>';
        els.selectedTypeId.value = '';
        currentlySelectedRow = null;
        els.selectedDisplay.classList.add('d-none');
        els.submitBtn.disabled = true;
        els.submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Please select a violation type';
        document.querySelectorAll('.table-success').forEach(r => r.classList.remove('table-success'));
        document.querySelectorAll('.select-violation').forEach(b => {
            b.classList.replace('btn-success','btn-outline-primary');
            b.textContent = 'Select';
        });
    };

    /* ------------------------------------------------------------------ */
    /*  AUTO-FILL USER FROM PLATE / LICENSE                               */
    /* ------------------------------------------------------------------ */
    const autoFillFromPlate = (plate) => {
        const fd = new FormData();
        fd.append('plate_number', plate);
        fd.append('officer_id', officerId);
        return fetch('get_user_by_plate.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('violator_name').value = d.violator_name || '';
                    document.getElementById('contact_number').value = formatContactNumberFromData(d.contact_number||'');
                    document.getElementById('email').value = d.email || '';
                    document.getElementById('user_id').value = d.user_id || '';
                    els.hasLicense.checked = d.has_license == 1;
                    if (!els.licenseNumber.value && d.license_number) {
                        els.licenseNumber.value = d.license_number;
                        els.licenseNumber.dispatchEvent(new Event('input'));
                        els.licenseNumber.dispatchEvent(new Event('blur'));
                    }
                    toastr.success('Profile loaded from plate!');
                }
            });
    };
    const autoFillFromLicense = (lic) => {
        const fd = new FormData();
        fd.append('license_number', lic);
        fd.append('officer_id', officerId);
        return fetch('get_user_by_license.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('violator_name').value = d.violator_name || '';
                    document.getElementById('contact_number').value = formatContactNumberFromData(d.contact_number||'');
                    document.getElementById('email').value = d.email || '';
                    document.getElementById('user_id').value = d.user_id || '';
                    els.hasLicense.checked = true;
                    const curPlate = els.plateNumber.value.replace(/[^A-Z0-9]/g,'').toUpperCase();
                    const newPlate = (d.plate_number||'').replace(/[^A-Z0-9]/g,'').toUpperCase();
                    if (curPlate.length < 6 && newPlate.length >= 6) {
                        els.plateNumber.value = newPlate;
                        els.plateNumber.dispatchEvent(new Event('input'));
                    }
                    toastr.success('Full profile loaded from license!');
                }
            });
    };
    const formatContactNumberFromData = (ph) => {
        if (!ph) return '';
        const c = ph.replace(/\D/g,'');
        return (c.length===11 && c.startsWith('09')) ? `${c.slice(0,4)}-${c.slice(4,7)}-${c.slice(7)}` : ph;
    };

    /* ------------------------------------------------------------------ */
    /*  SMART REFRESH (plate / license)                                   */
    /* ------------------------------------------------------------------ */
    const smartRefresh = debounce(() => {
        const plateRaw   = els.plateNumber.value.replace(/[^A-Z0-9]/g,'').toUpperCase();
        const licenseRaw = els.licenseNumber.value.replace(/[^A-Z0-9]/g,'').toUpperCase();
        const plateOk    = (plateRaw.length===6) || (plateRaw.length===7 && /^[A-Z]{3}[0-9]{4}$/.test(plateRaw));
        const licOk      = els.hasLicense.checked && licenseRaw.length===12 && /^[A-Z]{3}[0-9]{2}[A-Z][0-9]{6}$/.test(licenseRaw);
        const key        = plateOk ? plateRaw : (licOk ? licenseRaw : '');

        if (!key || key===lastValidKey || isLoading) return;
        lastValidKey = key; is_loading = true;

        els.violationTypes.innerHTML   = '<tr><td colspan="4" class="text-center"><small>Loading violation types...</small></td></tr>';
        els.violationHistory.innerHTML = '<tr><td colspan="7" class="text-center"><small>Loading history...</small></td></tr>';

        const fd = new FormData();
        if (plateOk) { fd.append('plate_number', plateRaw); autoFillFromPlate(plateRaw); }
        else if (licOk) { fd.append('license_number', licenseRaw); autoFillFromLicense(licenseRaw); }
        fd.append('officer_id', officerId);

        fetch('fetch_violation_history.php', {method:'POST', body:fd})
            .then(r=>r.json())
            .then(data => {
                isLoading = false;
                renderHistory(data);
                renderAvailableTypes(data);
            })
            .catch(() => {
                isLoading = false;
                els.violationTypes.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>';
                toastr.error('Failed to load violation data.');
            });
    }, 600);

    /* ------------------------------------------------------------------ */
    /*  RENDER HISTORY                                                    */
    /* ------------------------------------------------------------------ */
    const renderHistory = (data) => {
        els.violationHistory.innerHTML = '';
        if (!data.success || !data.violations?.length) {
            els.violationHistory.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No violation history found.</td></tr>';
            return;
        }
        const sorted = [...data.violations].sort((a,b)=>new Date(b.issued_date)-new Date(a.issued_date));
        sorted.forEach(v => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(v.plate_number||'N/A')}</td>
                <td>${v.has_license==1 ? escapeHtml(v.license_number||'N/A') : 'No License'}</td>
                <td>${escapeHtml(v.violation_type||'N/A')}</td>
                <td>${escapeHtml(v.base_offense||'N/A')}</td>
                <td>₱${parseFloat(v.fine_amount||0).toFixed(2)}</td>
                <td>${new Date(v.issued_date||'').toLocaleString()||'N/A'}</td>
                <td><span class="badge bg-${v.status==='Resolved'?'success':(v.status==='Pending'?'warning text-dark':'secondary')}">
                    ${escapeHtml(v.status||'N/A')}
                </span></td>
            `;
            els.violationHistory.appendChild(row);
        });
    };

    /* ------------------------------------------------------------------ */
    /*  RENDER AVAILABLE TYPES                                            */
    /* ------------------------------------------------------------------ */
    const renderAvailableTypes = (data) => {
        els.violationTypes.innerHTML = '';
        const usedIds = new Set((data.violations||[]).map(v=>v.violation_type_id?.toString()).filter(Boolean));
        const highestLevelByBase = {};

        (data.violations||[]).forEach(v => {
            const t = originalTypes.find(x=>x.id==v.violation_type_id);
            if (!t) return;
            const m = t.violation_type.match(/(1st|2nd|3rd)/i);
            if (!m) return;
            const lvl = { '1st':1, '2nd':2, '3rd':3 }[m[0].toLowerCase()];
            const base = t.base_offense || 'unknown';
            if (!highestLevelByBase[base] || lvl > highestLevelByBase[base]) highestLevelByBase[base] = lvl;
        });

        const show = new Set();
        originalTypes.forEach(t => {
            const name = t.violation_type || '';
            const base = t.base_offense || 'unknown';
            const id   = t.id.toString();
            const m    = name.match(/(1st|2nd|3rd)/i);
            const lvl  = m ? { '1st':1, '2nd':2, '3rd':3 }[m[0].toLowerCase()] : null;
            const high = highestLevelByBase[base] || 0;
            const issued = usedIds.has(id);

            if (!m || lvl===null) show.add(t);
            else if (issued && lvl===3) show.add(t);
            else if (!issued) {
                if ((high===0 && lvl===1) || (high===1 && lvl===2) || (high>=2 && lvl===3)) show.add(t);
            }
        });

        const sorted = Array.from(show).sort((a,b)=>a.violation_type.localeCompare(b.violation_type));
        if (!sorted.length) {
            els.violationTypes.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No available violation types</td></tr>';
            return;
        }

        sorted.forEach(t => {
            const selected = currentlySelectedRow===t.id.toString();
            const row = document.createElement('tr');
            row.className = selected ? 'table-success' : '';
            row.innerHTML = `
                <td><button type="button" class="btn btn-sm ${selected?'btn-success':'btn-outline-primary'} select-violation" data-id="${t.id}">
                    ${selected?'Selected':'Select'}
                </button></td>
                <td>${escapeHtml(t.violation_type)}</td>
                <td>${escapeHtml(t.base_offense||'N/A')}</td>
                <td>₱${parseFloat(t.fine_amount||0).toFixed(2)}</td>
            `;
            els.violationTypes.appendChild(row);
        });

        /* ---- SELECT BUTTONS ---- */
        document.querySelectorAll('.select-violation').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                // deselect previous
                if (currentlySelectedRow) {
                    const prevRow = els.violationTypes.querySelector('tr.table-success');
                    if (prevRow) prevRow.classList.remove('table-success');
                    const prevBtn = els.violationTypes.querySelector(`button[data-id="${currentlySelectedRow}"]`);
                    if (prevBtn) { prevBtn.classList.replace('btn-success','btn-outline-primary'); prevBtn.textContent='Select'; }
                }
                // select current
                currentlySelectedRow = id;
                els.selectedTypeId.value = id;
                this.closest('tr').classList.add('table-success');
                this.classList.replace('btn-outline-primary','btn-success');
                this.textContent = 'Selected';

                const type = originalTypes.find(x=>x.id.toString()===id);
                els.selectedText.textContent = `${type.violation_type} (${type.base_offense||'N/A'}) - ₱${parseFloat(type.fine_amount).toFixed(2)}`;
                els.selectedDisplay.classList.remove('d-none');
                els.submitBtn.disabled = false;
                els.submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';
                toastr.success(`Selected: ${type.violation_type}`);
            });
        });
    };

    /* ------------------------------------------------------------------ */
    /*  OCR – PLATE IMAGE                                                 */
    /* ------------------------------------------------------------------ */
    els.plateImg.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) performOCR(file, 'plate_number');
    });

    const performOCR = (file, targetId) => {
        const status = document.getElementById('ocr_status');
        status.textContent = 'Analyzing plate...';

        const img = new Image();
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        img.onload = () => {
            canvas.width = img.width; canvas.height = img.height;
            ctx.drawImage(img,0,0);

            // B&W high contrast
            const id = ctx.getImageData(0,0,canvas.width,canvas.height);
            const d = id.data;
            for (let i=0;i<d.length;i+=4) {
                const gray = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
                const bw = gray>135?255:0;
                d[i]=d[i+1]=d[i+2]=bw;
            }
            ctx.putImageData(id,0,0);

            // upscale 2×
            const up = document.createElement('canvas');
            const uc = up.getContext('2d');
            const scale = 2;
            up.width = canvas.width*scale; up.height = canvas.height*scale;
            uc.imageSmoothingEnabled = false;
            uc.drawImage(canvas,0,0,up.width,up.height);

            Tesseract.recognize(up.toDataURL(),'eng',{
                tessedit_char_whitelist:'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
                tessedit_pageseg_mode:'7'
            })
            .then(({data:{text}}) => {
                const raw = text.replace(/[^A-Za-z0-9]/g,'').toUpperCase();
                const patterns = [/\d{3}[A-Z]{3,4}/,/[A-Z]\d{3}[A-Z]{2,3}/,/[A-Z]{2}\d{3}[A-Z]{2}/,/[A-Z]{3}\d{3}/,/[A-Z]{4}\d{3}/];
                let plate = patterns.reduce((p,pat)=>p||text.match(pat)?.[0],null);

                if (!plate && raw.length>=5) {
                    const corr = raw.replace(/N/g,'M').replace(/S/g,'5').replace(/O/g,'0')
                                    .replace(/B/g,'8').replace(/I/g,'1').replace(/G/g,'6')
                                    .replace(/Z/g,'2').replace(/T/g,'7');
                    plate = patterns.reduce((p,pat)=>p||corr.match(pat)?.[0],null);
                }
                if (!plate && raw.length>=6 && raw.length<=10) {
                    for (let i=0;i<=raw.length-6;i++) {
                        const sub = raw.substring(i,i+6);
                        if (patterns.some(p=>p.test(sub))) { plate=sub; break; }
                        const sub7 = raw.substring(i,i+7);
                        if (i<=raw.length-7 && patterns.some(p=>p.test(sub7))) { plate=sub7; break; }
                    }
                }

                const input = document.getElementById(targetId);
                input.value = plate||'';
                status.textContent = plate ? `Detected: ${plate}` : 'No plate found';
                input.dispatchEvent(new Event('input'));
                input.dispatchEvent(new Event('blur'));
            })
            .catch(err=>{ status.textContent='OCR failed'; console.error(err); toastr.error('OCR failed.'); });
        };
        img.onerror = ()=>{ status.textContent='Image load error'; toastr.error('Failed to load image'); };
        img.src = URL.createObjectURL(file);
    };

    /* ------------------------------------------------------------------ */
    /*  FORM SUBMISSION                                                   */
    /* ------------------------------------------------------------------ */
    document.getElementById('createViolationForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const hidden = document.getElementById('selected_violation_type_id');
        hidden.name = 'violation_type_id';

        const fields = {
            violator_name: document.getElementById('violator_name'),
            contact_number: document.getElementById('contact_number'),
            plate_number: document.getElementById('plate_number'),
            reason: document.getElementById('reason'),
            violation_type_id: hidden
        };
        const email = document.getElementById('email');
        let valid = true;

        // reset
        Object.values(fields).forEach(f=>f.classList.remove('is-invalid','is-valid'));
        email.classList.remove('is-invalid','is-valid');

        // required
        Object.entries(fields).forEach(([k,f])=>{
            if (!f.value.trim()) { f.classList.add('is-invalid'); valid=false; return; }
            if (k==='contact_number') {
                const c = f.value.replace(/\D/g,'');
                if (c.length!==11 || !c.startsWith('09')) { f.classList.add('is-invalid'); valid=false; }
                else f.classList.add('is-valid');
            }
        });

        // license (if checked)
        if (els.hasLicense.checked) {
            const lic = els.licenseNumber.value.replace(/[^A-Z0-9]/g,'');
            const ok = lic.length===12 && /^[A-Z]{3}[0-9]{2}[A-Z][0-9]{6}$/.test(lic);
            els.licenseNumber.classList.toggle('is-invalid',!ok);
            els.licenseNumber.classList.toggle('is-valid',ok);
            valid = valid && ok;
        }

        // email (optional)
        if (email.value.trim()) {
            const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/i.test(email.value.trim());
            email.classList.toggle('is-invalid',!ok);
            email.classList.toggle('is-valid',ok);
            valid = valid && ok;
        }

        // impound picture
        if (els.isImpounded.checked && !els.impoundInput.files.length) {
            els.impoundInput.classList.add('is-invalid');
            valid = false;
        }

        if (!valid) {
            hidden.name = 'selected_violation_type_id';
            Swal.fire({title:'Validation Error!', text:'Fix highlighted fields.', icon:'error'});
            return;
        }

        const type = originalTypes.find(t=>t.id.toString()===hidden.value);
        const fine = type ? parseFloat(type.fine_amount).toFixed(2) : '0.00';

        Swal.fire({
            title:'Confirm Violation',
            html:`
                <div class="text-start">
                    <p><strong>Violator:</strong> ${fields.violator_name.value}</p>
                    <p><strong>Contact:</strong> ${fields.contact_number.value}</p>
                    <p><strong>Email:</strong> ${email.value||'N/A'}</p>
                    <p><strong>Plate:</strong> ${fields.plate_number.value}</p>
                    <p><strong>License:</strong> ${els.hasLicense.checked?els.licenseNumber.value:'N/A'}</p>
                    <p><strong>Reason:</strong> ${fields.reason.value}</p>
                    <p><strong>Violation:</strong> ${type?`${type.violation_type} (${type.base_offense||'N/A'})`:'N/A'}</p>
                    <p><strong>Fine:</strong> ₱${fine}</p>
                </div>
            `,
            icon:'question',
            showCancelButton:true,
            confirmButtonText:'Yes, create!',
            cancelButtonText:'Cancel'
        }).then(res=>{
            if (res.isConfirmed) {
                els.submitBtn.disabled = true;
                els.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
                const fd = new FormData(this);
                fd.append('create_violation','1');
                fetch('manage_violations.php',{method:'POST', body:fd})
                    .then(r=>r.json())
                    .then(d=>{
                        els.submitBtn.disabled = false;
                        els.submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Create Violation';
                        if (d.success) Swal.fire('Success!','Violation created.','success').then(()=>location.reload());
                        else Swal.fire('Error!',d.message||'Failed.','error');
                    })
                    .catch(()=>{ els.submitBtn.disabled=false; els.submitBtn.innerHTML='<i class="fas fa-plus me-2"></i>Create Violation'; Swal.fire('Error!','Network error.','error'); });
            } else hidden.name='selected_violation_type_id';
        });
    });

    /* ------------------------------------------------------------------ */
    /*  INPUT LISTENERS                                                   */
    /* ------------------------------------------------------------------ */
    els.contactNumber.addEventListener('input', formatContact);
    els.contactNumber.addEventListener('blur', validateContact);
    els.email.addEventListener('input', validateEmail);
    els.email.addEventListener('blur', validateEmail);

    els.plateNumber.addEventListener('input', formatPlate);
    els.plateNumber.addEventListener('input', smartRefresh);
    els.licenseNumber.addEventListener('input', formatLicense);
    els.licenseNumber.addEventListener('blur', validateLicense);
    els.licenseNumber.addEventListener('input', smartRefresh);
    els.hasLicense.addEventListener('change', ()=>{ toggleLicense(); smartRefresh(); });

    /* ------------------------------------------------------------------ */
    /*  INITIAL STATE                                                     */
    /* ------------------------------------------------------------------ */
    clearTables();
});
</script>
</body>
</html>