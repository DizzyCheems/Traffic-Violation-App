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
        // Fetch violation + type, get email from violations table only
        $stmt = $pdo->prepare("
            SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, 
                   v.has_license, v.license_number, v.email_sent, v.issued_date, 
                   v.violator_email,  -- THIS IS THE NEW EMAIL SOURCE
                   t.violation_type, t.fine_amount
            FROM violations v 
            JOIN types t ON v.violation_type_id = t.id 
            WHERE v.id = ? AND v.email_sent = FALSE
        ");
        $stmt->execute([$violation_id]);
        $violation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$violation) {
            return ['success' => false, 'message' => "No unsent violation found for ID $violation_id or email already sent."];
        }

        // Use violator_email from violations table
        $email = $violation['violator_email'];
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "Invalid or missing email for this violator."];
        }

        // Extract data
        $violator_name    = $violation['violator_name'];
        $plate_number     = $violation['plate_number'];
        $reason           = $violation['reason'];
        $violation_type_name = $violation['violation_type'];
        $fine_amount      = $violation['fine_amount'];
        $issued_date      = $violation['issued_date'];
        $license_number   = $violation['license_number'] ?: 'N/A';

        // Initialize PHPMailer
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
                                    <p>We regret to inform you that a traffic violation has been recorded under your name. The complete details are provided below.</p>
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
                                    <p>Click below to view full details or contact support.</p>
                                    <a href="' . $viewUrl . '" class="view-details">View Details</a>
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

        $mail->AltBody = "Traffic Violation Notice\n\nDear $violator_name,\n\nA traffic violation has been recorded:\n- Plate Number: $plate_number\n- Violation Type: $violation_type_name\n- Reason: $reason\n- Fine Amount: ₱" . number_format($fine_amount, 2) . "\n- License Number: $license_number\n- Issue Date: " . date('F j, Y, g:i A', strtotime($issued_date)) . "\n\nView details: $viewUrl\n\nContact support@trafficviolationsystem.com\n(123) 456-7890";

        $mail->send();

        // Mark as sent
        $updateStmt = $pdo->prepare("UPDATE violations SET email_sent = TRUE WHERE id = ?");
        $updateStmt->execute([$violation_id]);

        file_put_contents('../debug.log', "Email sent successfully for violation ID $violation_id to $email\n", FILE_APPEND);
        return ['success' => true, 'message' => "Email sent successfully to $email!"];

    } catch (Exception $e) {
        file_put_contents('../debug.log', "Error sending email for violation ID $violation_id: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => "Error sending email: " . $e->getMessage()];
    }
}

try {
    // Session check
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
        !in_array(strtolower(trim($_SESSION['role'])), ['officer', 'admin'])) {
        header("Location: ../login.php");
        exit;
    }

    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
        $violation_id = (int)$_POST['violation_id'];
        $result = sendViolationEmail($pdo, $violation_id);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // Fetch unsent violations for current officer
    $stmt = $pdo->prepare("
        SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, 
               v.license_number, v.issued_date, v.violator_email,
               t.violation_type, t.fine_amount
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        WHERE v.email_sent = FALSE AND v.officer_id = ?
        ORDER BY v.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $latest_violation_id = !empty($violations) ? $violations[0]['id'] : 0;

} catch (PDOException $e) {
    echo "<script>Swal.fire({
        title: 'Database Error!',
        text: '" . addslashes(htmlspecialchars($e->getMessage())) . "',
        icon: 'error'
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
        <button class="btn btn-primary d-lg-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
            <i class="fas fa-bars"></i> Menu
        </button>
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-lg-2 d-none d-lg-block bg-light sidebar">
                <!-- sidebar content -->
            </nav>
            <div class="offcanvas offcanvas-start sidebar d-lg-none" tabindex="-1" id="sidebarMenu">
                <!-- mobile menu -->
            </div>

            <main class="col-12 col-md-9 col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Send Violation Email</h1>
                    <a href="../pages/manage_violations.php" class="btn btn-outline-primary">Back to Violations</a>
                </div>

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
                                        <?php foreach ($violations as $v): ?>
                                            <option value="<?= htmlspecialchars($v['id']) ?>" 
                                                <?= $v['id'] == $latest_violation_id ? 'selected' : '' ?>>
                                                ID: <?= $v['id'] ?> - 
                                                <?= htmlspecialchars($v['violator_name']) ?> - 
                                                Plate: <?= htmlspecialchars($v['plate_number']) ?> - 
                                                <?= htmlspecialchars($v['violation_type']) ?> - 
                                                Email: <?= htmlspecialchars($v['violator_email']) ?> - 
                                                <?= date('Y-m-d H:i', strtotime($v['issued_date'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                Swal.fire('Error!', 'Please select a violation.', 'error');
                return;
            }

            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    title: data.success ? 'Success!' : 'Error!',
                    text: data.message,
                    icon: data.success ? 'success' : 'error'
                }).then(() => data.success && location.reload());
            })
            .catch(err => Swal.fire('Error!', 'Network error: ' + err.message, 'error'));
        });
    </script>
</body>
</html>