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

            // Insert violation with user_id, offense_freq, and plate_image
            $stmt = $pdo->prepare("INSERT INTO violations (officer_id, user_id, violator_name, plate_number, reason, violation_type_id, has_license, license_number, is_impounded, is_paid, or_number, issued_date, status, notes, offense_freq, plate_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $params = [$_SESSION['user_id'], $user_id, $violator_name, $plate_number, $reason, $violation_type_id, $has_license, $license_number, $is_impounded, $is_paid, $or_number, $issued_date, $status, $notes, $offense_freq, $plate_image];
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
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE officer_id = ? AND DATE(issued_date) = ?");
    $stmt->execute([$officer_id, $today]);
    $issued_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching today\'s stats: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $issued_today = 0;
}

// Fetch pending tickets (unpaid violations issued by the officer)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE officer_id = ? AND is_paid = 0");
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
    file_put_contents('../debug.log', "Fetch Assigned Concerns Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $assigned_concerns = 0;
}

// Fetch weekly earnings
$week_start = date('Y-m-d', strtotime('monday this week'));
try {
    $stmt = $pdo->prepare("SELECT total_fines FROM officer_earnings WHERE officer_id = ? AND week_start = ?");
    $stmt->execute([$officer_id, $week_start]);
    $earnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $wtd_earnings = $earnings['total_fines'] ? '₱' . number_format($earnings['total_fines'], 2) : '₱0.00';
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching earnings: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    $wtd_earnings = '₱0.00';
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

// Fetch users under the officer
try {
    $stmt = $pdo->prepare("SELECT id, username, full_name, contact_number, email FROM users WHERE officer_id = ? ORDER BY full_name");
    $stmt->execute([$_SESSION['user_id']]);
    $supervised_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching supervised users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Supervised Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $supervised_users = [];
}

// Fetch users with violations (only those with officer_id matching the logged-in officer)
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, COUNT(v.id) as violation_count 
        FROM users u 
        LEFT JOIN violations v ON u.id = v.user_id 
        WHERE u.role = 'user' AND u.officer_id = ? 
        GROUP BY u.id 
        ORDER BY violation_count DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $users = [];
}

// Fetch violations for each user
$user_violations = [];
foreach ($users as $user) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.id, v.officer_id, v.violator_name, v.plate_number, v.reason, v.issued_date, v.status, t.violation_type, t.fine_amount 
            FROM violations v 
            JOIN types t ON v.violation_type_id = t.id 
            WHERE v.user_id = ? 
            ORDER BY v.issued_date DESC
        ");
        $stmt->execute([$user['id']]);
        $user_violations[$user['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error fetching violations for user ID {$user['id']}: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Fetch User Violations Error (User ID {$user['id']}): " . $e->getMessage() . "\n", FILE_APPEND);
        $user_violations[$user['id']] = [];
    }
}

// Fetch all violations issued by the officer
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.officer_id, v.violator_name, v.plate_number, v.reason, v.issued_date, v.status, t.violation_type, t.fine_amount 
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

// Group violations by month
$violations_by_month = [];
foreach ($violations as $violation) {
    $month_year = date('F Y', strtotime($violation['issued_date']));
    $violations_by_month[$month_year][] = $violation;
}

// Fetch assigned patrol zones
try {
    $stmt = $pdo->prepare("SELECT zone_name, urgency, assigned_date FROM patrol_zones WHERE officer_id = ? ORDER BY assigned_date DESC");
    $stmt->execute([$officer_id]);
    $patrol_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                            <a class="nav-link active" href="../pages/officer_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Officer Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
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
                            <a class="nav-link active" href="../pages/officer_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Officer Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
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
                    <h1 class="h2 text-primary">Officer Dashboard - <?php echo htmlspecialchars($officer['full_name']); ?></h1>
                    <div>
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
                                <h5 class="card-title text-primary">Collection (WTD)</h5>
                                <p class="card-text"><?php echo htmlspecialchars($wtd_earnings); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Violations and Create Violation -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">Recent Violations</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($violations_by_month)): ?>
                                    <p class="text-center text-muted">No violations found</p>
                                <?php else: ?>
                                    <?php foreach ($violations_by_month as $month_year => $month_violations): ?>
                                        <span class="d-block mb-3 fw-bold text-primary"><?php echo htmlspecialchars($month_year); ?></span>
                                        <div class="table-responsive mb-4">
                                            <table class="table table-hover align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Officer ID</th>
                                                        <th>Violator</th>
                                                        <th>Plate</th>
                                                        <th>Type</th>
                                                        <th>Fine</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($month_violations as $violation): ?>
                                                        <tr class="table-row-hover">
                                                            <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                            <td><?php echo htmlspecialchars($violation['officer_id']); ?></td>
                                                            <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                            <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                            <td>₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($violation['issued_date']))); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $violation['status'] === 'Pending' ? 'bg-warning text-dark' : ($violation['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'); ?>">
                                                                    <?php echo htmlspecialchars($violation['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <p class="card-text"><a href="../pages/all_violations.php" class="text-decoration-none link-primary">View All Violations</a></p>
                            </div>
                        </div>
                    </div>
                 <!--<div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h3 class="mb-0">Create Violation</h3>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#createViolationModal">Open Create Violation Form</button>
                            </div>
                        </div>
                    </div> -->
    
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary">Patrol Zones</h5>
                                <?php if (empty($patrol_zones)): ?>
                                    <p class="card-text text-muted">No patrol zones assigned.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush mb-3">
                                        <?php foreach ($patrol_zones as $zone): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($zone['zone_name']); ?>
                                                <span class="badge <?php echo $zone['urgency'] === 'High' ? 'bg-danger' : ($zone['urgency'] === 'Medium' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                    <?php echo htmlspecialchars($zone['urgency']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <p class="card-text">
                                    <a href="../pages/patrol_zone_details.php" class="text-decoration-none link-primary">🗺️ View Active Patrol Zone</a>
                                </p>
                                <p style="display:none;" class="card-text">📍 Your location is being tracked</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Violation Types -->
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
                                                    <form method="POST" style="display: inline;" class="delete-violation-type-form">
                                                        <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                        <input type="hidden" name="delete_violation_type" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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
                                            <div class="mb-3">
                                                <h6>Users Under Your Supervision</h6>
                                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                                    <table class="table table-hover table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Username</th>
                                                                <th>Full Name</th>
                                                                <th>Contact</th>
                                                                <th>Email</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($supervised_users)): ?>
                                                                <tr><td colspan="4" class="text-center text-muted">No users found</td></tr>
                                                            <?php else: ?>
                                                                <?php foreach ($supervised_users as $user): ?>
                                                                    <tr class="user-row" data-user-id="<?php echo htmlspecialchars($user['id']); ?>" data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-contact-number="<?php echo htmlspecialchars($user['contact_number']); ?>" data-email="<?php echo htmlspecialchars($user['email'] ?: ''); ?>" style="cursor: pointer;">
                                                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                                        <td><?php echo htmlspecialchars($user['contact_number']); ?></td>
                                                                        <td><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></td>
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
                                                <div class="col-md-6 mb-3">
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

                <!-- Users with Violations -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Users with Violations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <p class="text-center text-muted">No users found</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($users as $user): ?>
                                    <div class="col">
                                        <div class="card h-100 shadow-sm">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                                                <p class="card-text">
                                                    <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?><br>
                                                    <strong>Violation Count:</strong> <?php echo htmlspecialchars($user['violation_count']); ?>
                                                </p>
                                                <button class="btn btn-primary btn-sm toggle-violations" data-bs-toggle="collapse" data-bs-target="#violations-<?php echo $user['id']; ?>" aria-expanded="false" aria-controls="violations-<?php echo $user['id']; ?>">
                                                    View Violations
                                                </button>
                                            </div>
                                            <div class="collapse" id="violations-<?php echo $user['id']; ?>">
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover align-middle">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>ID</th>
                                                                    <th>Officer ID</th>
                                                                    <th>Plate</th>
                                                                    <th>Type</th>
                                                                    <th>Fine</th>
                                                                    <th>Date</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (empty($user_violations[$user['id']])): ?>
                                                                    <tr><td colspan="7" class="text-center text-muted">No violations found for this user</td></tr>
                                                                <?php else: ?>
                                                                    <?php foreach ($user_violations[$user['id']] as $violation): ?>
                                                                        <tr class="table-row-hover">
                                                                            <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                                            <td><?php echo htmlspecialchars($violation['officer_id']); ?></td>
                                                                            <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                                            <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                                            <td>₱<?php echo htmlspecialchars(number_format($violation['fine_amount'], 2)); ?></td>
                                                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($violation['issued_date']))); ?></td>
                                                                            <td>
                                                                                <span class="badge <?php echo $violation['status'] === 'Pending' ? 'bg-warning text-dark' : ($violation['status'] === 'Resolved' ? 'bg-success' : 'bg-danger'); ?>">
                                                                                    <?php echo htmlspecialchars($violation['status']); ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

        // Function to perform OCR on image and populate license number
        function performOCR(file, inputId) {
            if (!file) return;
            const ocrStatus = document.getElementById('ocr_status');
            ocrStatus.textContent = 'Processing image...';
            Tesseract.recognize(
                file,
                'eng',
                {
                    logger: m => console.log(m)
                }
            ).then(({ data: { text } }) => {
                const cleanedText = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                document.getElementById(inputId).value = cleanedText;
                ocrStatus.textContent = 'Text extracted successfully!';
                console.log('OCR Result:', cleanedText);
            }).catch(error => {
                ocrStatus.textContent = 'Error extracting text from image.';
                console.error('OCR Error:', error);
            });
        }

        // Client-side validation for Create Violation Form
        document.getElementById('createViolationForm').addEventListener('submit', function(e) {
            console.log('Create violation form submission attempted');
            const violatorName = document.getElementById('violator_name').value.trim();
            const contactNumber = document.getElementById('contact_number').value.trim();
            const plateNumber = document.getElementById('plate_number').value.trim();
            const reason = document.getElementById('reason').value.trim();
            const violationTypeId = document.getElementById('violation_type_id').value;

            let isValid = true;

            document.getElementById('violator_name').classList.remove('is-invalid');
            document.getElementById('contact_number').classList.remove('is-invalid');
            document.getElementById('plate_number').classList.remove('is-invalid');
            document.getElementById('reason').classList.remove('is-invalid');
            document.getElementById('violation_type_id').classList.remove('is-invalid');

            if (!violatorName) {
                document.getElementById('violator_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!contactNumber) {
                document.getElementById('contact_number').classList.add('is-invalid');
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
                    fetch(this.action, {
                        method: 'POST',
                        body: new FormData(this)
                    }).then(response => {
                        if (response.ok) {
                            Swal.fire({
                                title: 'Created!',
                                text: 'Violation has been created successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.reload();
                            });
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
                } else {
                    console.log('Create violation form submission canceled');
                }
            });
        });

        // Handle image upload for OCR in Create Violation Form
        document.getElementById('plate_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                performOCR(file, 'license_number');
            }
        });

        // Handle user selection from mini-table
        document.querySelectorAll('.user-row').forEach(row => {
            row.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const fullName = this.getAttribute('data-full-name');
                const contactNumber = this.getAttribute('data-contact-number');
                const email = this.getAttribute('data-email');
                document.getElementById('violator_name').value = fullName;
                document.getElementById('contact_number').value = contactNumber;
                document.getElementById('email').value = email;
                document.getElementById('user_id').value = userId;
                document.getElementById('violator_name').classList.remove('is-invalid');
                document.getElementById('contact_number').classList.remove('is-invalid');
                console.log(`Selected user: ID=${userId}, Full Name=${fullName}, Contact=${contactNumber}, Email=${email}`);
                // Highlight selected row
                document.querySelectorAll('.user-row').forEach(r => r.classList.remove('table-primary'));
                this.classList.add('table-primary');
            });
        });

        // Clear user_id and fields if violator_name is manually changed
        document.getElementById('violator_name').addEventListener('input', function() {
            document.getElementById('user_id').value = '';
            document.getElementById('contact_number').value = '';
            document.getElementById('email').value = '';
            document.querySelectorAll('.user-row').forEach(row => row.classList.remove('table-primary'));
            console.log('Violator name manually changed, cleared user_id, contact_number, and email');
        });

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

        // Client-side validation and confirmation for Delete Violation Type Forms
        document.querySelectorAll('.delete-violation-type-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Delete violation type form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to delete this violation type? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Delete violation type form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Delete violation type form submission canceled');
                    }
                }); 
            });
        });
    </script>
</body>
</html>