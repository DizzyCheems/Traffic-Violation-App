<?php
session_start();
require '../config/conn.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send email using violator_email from violations table
function sendViolationEmail($pdo, $violation_id) {
    try {
        // Fetch violation + type info, including violator_email from violations table
        $stmt = $pdo->prepare("
            SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id,
                   v.license_number, v.issued_date, v.violator_email,
                   t.violation_type, t.fine_amount
            FROM violations v 
            JOIN types t ON v.violation_type_id = t.id 
            WHERE v.id = ? AND v.email_sent = FALSE
        ");
        $stmt->execute([$violation_id]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$violation) {
            return ['success' => false, 'message' => "Violation not found or email already sent."];
        }

        $email = trim($violation['violator_email']);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "No valid email address found for this violator."];
        }

        // Extract data
        $violator_name       = $violation['violator_name'];
        $plate_number        = $violation['plate_number'];
        $reason              = $violation['reason'];
        $violation_type_name = $violation['violation_type'];
        $fine_amount         = $violation['fine_amount'];
        $issued_date         = $violation['issued_date'];
        $license_number      = $violation['license_number'] ?: 'N/A';

        // PHPMailer setup
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'stine6595@gmail.com';
        $mail->Password   = 'qvkb ycan jdij yffz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('stine6595@gmail.com', 'Traffic Violation System');
        $mail->addAddress($email, $violator_name);
        $mail->isHTML(true);
        $mail->Subject = 'Traffic Violation Notice';

        $viewUrl = "http://178.128.93.220:74/pages/user_violations_portal.php?plate_number=" . urlencode($plate_number);

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { max-width: 500px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: #003087; color: white; padding: 20px; text-align: center; font-size: 20px; }
                .content { padding: 25px; color: #333; }
                .details { background: #f9f9f9; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .btn { display: inline-block; padding: 12px 25px; background: #003087; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
                .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">Traffic Violation Notice</div>
                <div class="content">
                    <p><strong>Dear ' . htmlspecialchars($violator_name) . ',</strong></p>
                    <p>A traffic violation has been recorded under your name. Please review the details below:</p>
                    <div class="details">
                        <strong>Plate Number:</strong> ' . htmlspecialchars($plate_number) . '<br>
                        <strong>Violation:</strong> ' . htmlspecialchars($violation_type_name) . '<br>
                        <strong>Reason:</strong> ' . htmlspecialchars($reason) . '<br>
                        <strong>Fine Amount:</strong> ₱' . number_format($fine_amount, 2) . '<br>
                        <strong>License:</strong> ' . htmlspecialchars($license_number) . '<br>
                        <strong>Issued:</strong> ' . date('F j, Y, g:i A', strtotime($issued_date)) . '
                    </div>
                    <p>Click below to view full details and settle your violation:</p>
                        <a href="' . $viewUrl . '" class="btn" style="color: white;">View Violation Details</a>
                    </div>
                <div class="footer">
                    Traffic Violation System &copy; ' . date('Y') . '<br>
                    <a href="mailto:support@trafficviolationsystem.com">support@trafficviolationsystem.com</a> | (123) 456-7890
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Traffic Violation Notice\n\nDear $violator_name,\n\nPlate: $plate_number\nViolation: $violation_type_name\nReason: $reason\nFine: ₱" . number_format($fine_amount, 2) . "\nLicense: $license_number\nIssued: " . date('F j, Y, g:i A', strtotime($issued_date)) . "\n\nView details: $viewUrl\n\nSupport: support@trafficviolationsystem.com";

        $mail->send();

        // Mark as sent
        $update = $pdo->prepare("UPDATE violations SET email_sent = TRUE WHERE id = ?");
        $update->execute([$violation_id]);

        file_put_contents('../debug.log', "Email sent to $email (Violation ID: $violation_id)\n", FILE_APPEND);

        return ['success' => true, 'message' => "Email sent successfully to $email!"];
    } catch (Exception $e) {
        file_put_contents('../debug.log', "Mail Error (ID $violation_id): " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()];
    }
}

// === MAIN LOGIC ===
try {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
        !in_array(strtolower($_SESSION['role']), ['officer', 'admin'])) {
        header("Location: ../login.php");
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
        $violation_id = (int)$_POST['violation_id'];
        $result = sendViolationEmail($pdo, $violation_id);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // Fetch unsent violations for this officer
    $stmt = $pdo->prepare("
        SELECT v.id, v.violator_name, v.plate_number, v.violation_type_id,
               v.issued_date, v.violator_email, t.violation_type, t.fine_amount
        FROM violations v
        JOIN types t ON v.violation_type_id = t.id
        WHERE v.email_sent = FALSE 
          AND v.officer_id = ?
          AND v.violator_email IS NOT NULL 
          AND v.violator_email != ''
        ORDER BY v.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $latest_violation_id = !empty($violations) ? $violations[0]['id'] : 0;

} catch (PDOException $e) {
    echo "<script>Swal.fire('Database Error!', '" . addslashes($e->getMessage()) . "', 'error');</script>";
    file_put_contents('../debug.log', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
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
        <button class="btn btn-primary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
            <i class="fas fa-bars"></i> Menu
        </button>

        <div class="row">
            <!-- Desktop Sidebar -->
            <nav class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="../pages/officer_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/manage_violations.php"><i class="fas fa-list-alt me-2"></i> Manage Violations</a></li>
                        <li class="nav-item"><a class="nav-link active" href="../pages/mail_test.php"><i class="fas fa-envelope me-2"></i> Send Email</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <!-- Mobile Menu -->
            <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarMenu">
                <div class="offcanvas-header">
                    <h5>Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="../pages/officer_dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/manage_violations.php">Manage Violations</a></li>
                        <li class="nav-item"><a class="nav-link active" href="#">Send Email</a></li>
                        <li class="nav-item"><a class="nav-link" href="../pages/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-12 col-md-9 col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Send Violation Email</h1>
                    <a href="../pages/manage_violations.php" class="btn btn-outline-primary">Back</a>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Select Violation to Email</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($violations)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pending violations with email addresses found.
                            </div>
                        <?php else: ?>
                            <form id="emailForm" method="POST">
                                <input type="hidden" name="submit_violation" value="1">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Select Violation</strong></label>
                                    <select class="form-select form-select-lg" name="violation_id" id="violation_id" required>
                                        <option value="" disabled selected>Choose a violation to send email...</option>
                                        <?php foreach ($violations as $v): ?>
                                            <option value="<?= $v['id'] ?>" <?= $v['id'] == $latest_violation_id ? 'selected' : '' ?>>
                                                #<?= $v['id'] ?> | <?= htmlspecialchars($v['violator_name']) ?> 
                                                | <?= htmlspecialchars($v['plate_number']) ?> 
                                                | <?= htmlspecialchars($v['violation_type']) ?> 
                                                | Email: <?= htmlspecialchars($v['violator_email']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-paper-plane"></i> Send Email Now
                                </button>
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
            const id = document.getElementById('violation_id').value;
            if (!id) {
                Swal.fire('Error', 'Please select a violation', 'error');
                return;
            }

            Swal.fire({
                title: 'Sending...',
                text: 'Please wait while we send the email',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Sent!' : 'Failed',
                    text: data.message,
                    timer: data.success ? 2000 : 5000,
                    showConfirmButton: !data.success
                }).then(() => data.success && location.reload());
            })
            .catch(() => Swal.fire('Error', 'Network error. Check console.', 'error'));
        });
    </script>
</body>
</html>