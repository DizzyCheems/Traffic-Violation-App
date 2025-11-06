<?php
session_start();
require '../config/conn.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
        $mail->Subject = 'Traffic Violation Notice';
        $mail->Body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body, table, td, p, div { 
                        font-family: Arial, sans-serif; 
                        color: #444444; 
                        font-size: 13px; 
                        line-height: 20px; 
                    }
                    .container { 
                        width: 100%; 
                        max-width: 450px; 
                        margin: 0 auto; 
                        background-color: #ffffff; 
                        padding: 20px; 
                    }
                    .header { 
                        color: #003087; 
                        font-size: 18px; 
                        font-weight: normal; 
                        padding: 15px 0; 
                    }
                    .footer { 
                        background-color: #f5f5f5; 
                        padding: 10px; 
                        text-align: center; 
                        border-top: 1px solid #e3e3e3; 
                        margin-top: 20px; 
                    }
                    .footer a, .view-details { 
                        color: #003087; 
                        text-decoration: none; 
                    }
                    .view-details {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #003087;
                        color: #ffffff !important;
                        border-radius: 5px;
                        margin-top: 10px;
                        font-weight: 500;
                    }
                    .view-details:hover {
                        background-color: #002060;
                        color: #ffffff !important;
                    }
                </style>
            </head>
            <body style="background-color: #E4E6E9; margin: 0; padding: 20px;">
                <table class="container" bgcolor="#FFFFFF">
                    <tbody>
                        <tr>
                            <td>
                                <div class="header">Traffic Violation Notice</div>
                                <div>
                                    <b style="color: #777777;">Dear ' . htmlspecialchars($violator_name) . ',</b>
                                    <p>We regret to inform you that a traffic violation has been recorded under your name. The complete details of the violation are provided below. Please address this matter promptly.</p>
                                </div>
                                <div style="margin-top: 12px;">
                                    <b style="color: #003087;">Violation Details:</b><br>
                                    <b>Plate Number:</b> ' . htmlspecialchars($plate_number) . '<br>
                                    <b>Violation Type:</b> ' . htmlspecialchars($violation_type_name) . '<br>
                                    <b>Reason:</b> ' . htmlspecialchars($reason) . '<br>
                                    <b>Fine Amount:</b> ₱' . number_format($fine_amount, 2) . '<br>
                                    <b>License Number:</b> ' . htmlspecialchars($license_number) . '<br>
                                    <b>Issue Date:</b> ' . htmlspecialchars(date('F j, Y, g:i A', strtotime($issued_date))) . '
                                </div>
                                <div style="margin-top: 16px;">
                                    <p>Please review the full details of your violation or contact our support team for further information.</p>
                                    <a href="http://178.128.93.220:74/pages/user_violations_portal.php?plate_number=' . urlencode($plate_number) . '" class="view-details">View Details</a>
                                </div>
                                <div class="footer">
                                    <p>
                                        Traffic Violation System &copy; ' . date('Y') . '<br>
                                        <a href="https://example.com/support">Contact Support</a> | 
                                        <a href="mailto:support@trafficviolationsystem.com">support@trafficviolationsystem.com</a> | 
                                        <a href="tel:1234567890">(123) 456-7890</a>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        ';
        $mail->AltBody = "Traffic Violation Notice\n\nDear $violator_name,\n\nA traffic violation has been recorded:\n- Plate Number: $plate_number\n- Violation Type: $violation_type_name\n- Reason: $reason\n- Fine Amount: ₱" . number_format($fine_amount, 2) . "\n- License Number: $license_number\n- Issue Date: " . date('F j, Y, g:i A', strtotime($issued_date)) . "\n\nPlease review the full details at: http://178.128.93.220:74/pages/user_violations_portal.php?plate_number=" . urlencode($plate_number) . "\n\nContact our support team for further information.\n\nTraffic Violation System\nsupport@trafficviolationsystem.com\n(123) 456-7890";

        $mail->send();

        // Update the email_sent status
        $stmt = $pdo->prepare("UPDATE violations SET email_sent = TRUE WHERE id = ?");
        $stmt->execute([$violation_id]);

        file_put_contents('../debug.log', "Email sent successfully for violation ID $violation_id to $email\n", FILE_APPEND);
        return ['success' => true, 'message' => "Violation email sent successfully to $email!"];
    } catch (Exception $e) {
        file_put_contents('../debug.log', "Error sending email for violation ID $violation_id: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => "Error sending email: " . $e->getMessage()];
    }
}

try {
    // Check session
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
        !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
        header("Location: ../login.php");
        exit;
    }

    // Handle AJAX form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
        $violation_id = (int)$_POST['violation_id'];
        $result = sendViolationEmail($pdo, $violation_id);
        header('Content-Type: application/json');
        echo json_encode($result);
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

} catch (PDOException $e) {
    echo "<script>Swal.fire({
        title: 'Database Error!',
        text: '" . addslashes(htmlspecialchars($e->getMessage())) . "',
        icon: 'error',
        confirmButtonText: 'OK'
    });</script>";
    file_put_contents('../debug.log', "Database Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
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
        document.getElementById('emailForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const violationId = document.getElementById('violation_id').value;
            if (!violationId) {
                document.getElementById('violation_id').classList.add('is-invalid');
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a violation.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            document.getElementById('violation_id').classList.remove('is-invalid');

            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            }).then(response => response.json())
            .then(data => {
                Swal.fire({
                    title: data.success ? 'Email Sent!' : 'Error!',
                    text: data.message,
                    icon: data.success ? 'success' : 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
            }).catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error sending email: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
    </script>
</body>
</html>