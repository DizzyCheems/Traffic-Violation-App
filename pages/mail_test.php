<?php
session_start();
require '../config/conn.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$toastr_messages = [];

// Function to send email for a specific violation
function sendViolationEmail($pdo, $violation_id) {
    try {
        // Fetch the specific violation
        $stmt = $pdo->prepare("
            SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, v.user_id, 
                   v.has_license, v.license_number, v.email_sent, v.issued_date, t.violation_type, t.fine_amount, u.email 
            FROM violations v 
            JOIN types t ON v.violation_type_id = t.id 
            LEFT JOIN users u ON v.user_id = u.id 
            WHERE v.id = ? AND v.email_sent = FALSE
        ");
        $stmt->execute([$violation_id]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$violation) {
            return ['success' => false, 'message' => "No unsent violation found for ID $violation_id or email already sent."];
        }

        // Extract violation data
        $violator_name = $violation['violator_name'];
        $plate_number = $violation['plate_number'];
        $reason = $violation['reason'];
        $violation_type_id = $violation['violation_type_id'];
        $user_id = $violation['user_id'];
        $email = $violation['email'];
        $violation_type_name = $violation['violation_type'];
        $fine_amount = $violation['fine_amount'];
        $issued_date = $violation['issued_date'];
        $license_number = $violation['license_number'] ?: 'N/A';

        // Validate required fields
        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id) || empty($user_id) || empty($email)) {
            return ['success' => false, 'message' => 'All required fields must be filled for violation ID ' . $violation_id . '.'];
        }

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'stine6595@gmail.com'; // Replace with your Gmail address
        $mail->Password = 'qvkb ycan jdij yffz'; // Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('stine6595@gmail.com', 'Traffic Violation System');
        $mail->addAddress($email, $violator_name);

        $mail->isHTML(true);
        $mail->Subject = 'Traffic Violation Recorded';
        $mail->Body = "
            <h3>Traffic Violation Notification</h3>
            <p>Dear " . htmlspecialchars($violator_name) . ",</p>
            <p>A traffic violation has been recorded with the following details:</p>
            <ul>
                <li><strong>Plate Number:</strong> " . htmlspecialchars($plate_number) . "</li>
                <li><strong>Reason:</strong> " . htmlspecialchars($reason) . "</li>
                <li><strong>Violation Type:</strong> " . htmlspecialchars($violation_type_name) . "</li>
                <li><strong>Fine Amount:</strong> ₱" . number_format($fine_amount, 2) . "</li>
                <li><strong>License Number:</strong> " . htmlspecialchars($license_number) . "</li>
                <li><strong>Issue Date:</strong> " . htmlspecialchars($issued_date) . "</li>
            </ul>
            <p>Please address this violation promptly.</p>
            <p>Regards,<br>Traffic Violation System</p>
        ";
        $mail->AltBody = "Traffic Violation Notification\n\nDear $violator_name,\n\nA traffic violation has been recorded:\n- Plate Number: $plate_number\n- Reason: $reason\n- Violation Type: $violation_type_name\n- Fine Amount: ₱" . number_format($fine_amount, 2) . "\n- License Number: $license_number\n- Issue Date: $issued_date\n\nPlease address this violation promptly.\n\nRegards,\nTraffic Violation System";

        $mail->send();

        // Update the email_sent status
        $stmt = $pdo->prepare("UPDATE violations SET email_sent = TRUE WHERE id = ?");
        $stmt->execute([$violation_id]);

        file_put_contents('../debug.log', "Email sent successfully for violation ID $violation_id to $email\n", FILE_APPEND);
        return ['success' => true, 'message' => "Violation email sent successfully to $email! Check the inbox or spam folder."];
    } catch (Exception $e) {
        file_put_contents('../debug.log', "Error sending email for violation ID $violation_id: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => "Error sending email for violation ID $violation_id: " . $e->getMessage()];
    }
}

try {
    // Check session
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
        !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
        header("Location: ../login.php");
        exit;
    }

    // Check for violation_id in query parameter for automatic email sending
    $violation_id = isset($_GET['violation_id']) ? (int)$_GET['violation_id'] : 0;
    if ($violation_id) {
        $result = sendViolationEmail($pdo, $violation_id);
        $_SESSION['email_status'] = $result;
        header("Location: manage_violations.php");
        exit;
    }

    // Fetch violations with email_sent = FALSE for the form, ordered by id DESC to get latest first
    $stmt = $pdo->prepare("
        SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, v.user_id, 
               v.has_license, v.license_number, v.email_sent, v.issued_date, t.violation_type, t.fine_amount, u.email 
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        LEFT JOIN users u ON v.user_id = u.id 
        WHERE v.email_sent = FALSE AND v.officer_id = ?
        ORDER BY v.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get the latest violation ID (first in the list due to ORDER BY id DESC)
    $latest_violation_id = !empty($violations) ? $violations[0]['id'] : 0;

    // Fetch violation types
    $stmt = $pdo->query("SELECT id, violation_type FROM types ORDER BY violation_type");
    $violation_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch users
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE officer_id = ? ORDER BY full_name");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle manual form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
        $violation_id = (int)$_POST['violation_id'];
        $result = sendViolationEmail($pdo, $violation_id);
        if ($result['success']) {
            $toastr_messages[] = "Swal.fire({
                title: 'Email Sent!',
                text: '" . addslashes($result['message']) . "',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'manage_violations.php';
            });";
        } else {
            $toastr_messages[] = "toastr.error('" . addslashes($result['message']) . "');";
        }
    }

    // Check for email status message from session (not used for Swal here, handled in manage_violations.php)
    if (isset($_SESSION['email_status'])) {
        // Session message will be handled in manage_violations.php
    }

} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Database error: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Database Error: " . $e->getMessage() . "\n", FILE_APPEND);
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
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/mail_test.php">
                                <i class="fas fa-envelope me-2"></i>
                                Send Email
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
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/mail_test.php">
                                <i class="fas fa-envelope me-2"></i>
                                Send Email
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
                    <h1 class="h2 text-primary">Send Violation Email</h1>
                    <div>
                        <a href="../pages/manage_violations.php" class="btn btn-outline-primary">Back to Violations</a>
                    </div>
                </div>

                <!-- Email Form -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Send Email for Unsent Violations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($violations)): ?>
                            <p class="text-muted">No unsent violations found.</p>
                        <?php else: ?>
                            <form method="POST" id="emailForm">
                                <input type="hidden" name="submit_violation" value="1">
                                <div class="mb-3">
                                    <label for="violation_id" class="form-label">Select Violation</label>
                                    <select class="form-select" name="violation_id" id="violation_id" required>
                                        <option value="" disabled>Select a violation</option>
                                        <?php foreach ($violations as $violation): ?>
                                            <option value="<?php echo htmlspecialchars($violation['id']); ?>" 
                                                <?php echo $violation['id'] == $latest_violation_id ? 'selected' : ''; ?>>
                                                ID: <?php echo htmlspecialchars($violation['id']); ?> - 
                                                <?php echo htmlspecialchars($violation['violator_name']); ?> - 
                                                Plate: <?php echo htmlspecialchars($violation['plate_number']); ?> - 
                                                <?php echo htmlspecialchars($violation['violation_type']); ?> - 
                                                Issued: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($violation['issued_date']))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a violation.</div>
                                </div>
                                <button type="submit" class="btn btn-primary">Send Email</button>
                            </form>
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

        // Display Toastr/Swal messages
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        // Client-side validation for Email Form
        document.getElementById('emailForm')?.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            const violationId = document.getElementById('violation_id').value;
            if (!violationId) {
                document.getElementById('violation_id').classList.add('is-invalid');
                toastr.error('Please select a violation.');
                return;
            }
            document.getElementById('violation_id').classList.remove('is-invalid');

            // Submit form via fetch
            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            }).then(data => {
                // PHP will handle Swal for success
            }).catch(error => {
                toastr.error('Error sending email: ' + error.message);
            });
        });
    </script>
</body>
</html>