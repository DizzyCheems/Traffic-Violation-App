<?php
// File: mail_test.php
// Sends an email for the latest registered violation using PHPMailer with Gmail's SMTP server.
// Assumes vendor folder is in C:\xampp\htdocs\Traffic-Violation-App and MySQL database is configured in XAMPP.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Database connection settings
$host = 'localhost';
$dbname = 'traffic';
$username = 'root';
$password = '';

$status = ''; // To display success/error message
$latest_violation_id = 0; // To store the ID of the latest violation

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all violations
    $stmt = $pdo->query("
        SELECT v.id, v.violator_name, v.plate_number, v.reason, v.violation_type_id, v.user_id, 
               v.has_license, v.license_number, t.violation_type, u.email 
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        LEFT JOIN users u ON v.user_id = u.id 
        ORDER BY v.violator_name, v.plate_number
    ");
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the latest violation ID
    $stmt = $pdo->query("
        SELECT id 
        FROM violations 
        ORDER BY issue_date DESC, id DESC 
        LIMIT 1
    ");
    $latest_violation = $stmt->fetch(PDO::FETCH_ASSOC);
    $latest_violation_id = $latest_violation ? (int)$latest_violation['id'] : 0;

    // Fetch violation types
    $stmt = $pdo->query("SELECT id, violation_type FROM types ORDER BY violation_type");
    $violation_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch users
    $stmt = $pdo->query("SELECT id, full_name, email FROM users ORDER BY full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $status = "Failed to fetch data. Database Error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
    try {
        // Get form data
        $violator_name = $_POST['violator_name'] ?? '';
        $plate_number = $_POST['plate_number'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $violation_type_id = $_POST['violation_type_id'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        $has_license = isset($_POST['has_license']) ? 1 : 0;
        $license_number = $_POST['license_number'] ?? null;
        $email = $_POST['email'] ?? '';

        // Validate required fields
        if (empty($violator_name) || empty($plate_number) || empty($reason) || empty($violation_type_id) || empty($user_id) || empty($email)) {
            throw new Exception('All required fields must be filled.');
        }

        // Fetch violation type name for email
        $stmt = $pdo->prepare("SELECT violation_type FROM types WHERE id = ?");
        $stmt->execute([$violation_type_id]);
        $violation_type = $stmt->fetch(PDO::FETCH_ASSOC);
        $violation_type_name = $violation_type['violation_type'] ?? 'Unknown';

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'stine6595@gmail.com';
        $mail->Password = 'qvkb ycan jdij yffz';
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
                <li><strong>License Number:</strong> " . ($license_number ? htmlspecialchars($license_number) : 'N/A') . "</li>
                <li><strong>Issue Date:</strong> " . date('Y-m-d H:i:s') . "</li>
            </ul>
            <p>Please address this violation promptly.</p>
            <p>Regards,<br>Traffic Violation System</p>
        ";
        $mail->AltBody = "Traffic Violation Notification\n\nDear $violator_name,\n\nA traffic violation has been recorded:\n- Plate Number: $plate_number\n- Reason: $reason\n- Violation Type: $violation_type_name\n- License Number: " . ($license_number ?: 'N/A') . "\n- Issue Date: " . date('Y-m-d H:i:s') . "\n\nPlease address this violation promptly.\n\nRegards,\nTraffic Violation System";

        $mail->send();
        $status = 'Violation email sent successfully to ' . htmlspecialchars($email) . '! Check the inbox or spam folder.';
    } catch (PDOException $e) {
        $status = "Failed to send email. Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $status = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Violation Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        h2 {
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .status {
            margin-top: 20px;
            color: <?php echo strpos($status, 'successfully') !== false ? 'green' : 'red'; ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Send Traffic Violation Email</h2>
        <form method="post" id="violationForm">
            <input type="hidden" name="submit_violation" value="1">
            <div class="form-group">
                <label for="violation_id">Select Existing Violation *</label>
                <select id="violation_id" name="violation_id" onchange="updateViolationFields()">
                    <option value="">Select a Violation</option>
                    <?php foreach ($violations as $violation): ?>
                        <option value="<?php echo htmlspecialchars($violation['id']); ?>" 
                                data-violator-name="<?php echo htmlspecialchars($violation['violator_name']); ?>"
                                data-plate-number="<?php echo htmlspecialchars($violation['plate_number']); ?>"
                                data-reason="<?php echo htmlspecialchars($violation['reason']); ?>"
                                data-violation-type-id="<?php echo htmlspecialchars($violation['violation_type_id']); ?>"
                                data-violation-type="<?php echo htmlspecialchars($violation['violation_type']); ?>"
                                data-user-id="<?php echo htmlspecialchars($violation['user_id']); ?>"
                                data-email="<?php echo htmlspecialchars($violation['email'] ?: ''); ?>"
                                data-has-license="<?php echo htmlspecialchars($violation['has_license']); ?>"
                                data-license-number="<?php echo htmlspecialchars($violation['license_number'] ?: ''); ?>"
                                <?php echo ($violation['id'] == $latest_violation_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($violation['violator_name'] . ' - ' . $violation['plate_number'] . ' - ' . $violation['violation_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="violator_name">Violator Name *</label>
                <input type="text" id="violator_name" name="violator_name" readonly>
            </div>
            <div class="form-group">
                <label for="plate_number">Plate Number *</label>
                <input type="text" id="plate_number" name="plate_number" readonly>
            </div>
            <div class="form-group">
                <label for="reason">Reason *</label>
                <input type="text" id="reason" name="reason" readonly>
            </div>
            <div class="form-group">
                <label for="violation_type_id">Violation Type *</label>
                <select id="violation_type_id" name="violation_type_id" readonly>
                    <option value="">Select Violation Type</option>
                    <?php foreach ($violation_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['id']); ?>">
                            <?php echo htmlspecialchars($type['violation_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="user_id">User ID *</label>
                <input type="text" id="user_id" name="user_id" readonly>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" readonly>
            </div>
            <div class="form-group">
                <label for="has_license">Has License</label>
                <input type="checkbox" id="has_license" name="has_license" disabled>
            </div>
            <div class="form-group">
                <label for="license_number">License Number</label>
                <input type="text" id="license_number" name="license_number" readonly>
            </div>
            <button type="submit">Send Violation Email</button>
            <?php if ($status): ?>
                <div class="status"><?php echo htmlspecialchars($status); ?></div>
            <?php endif; ?>
        </form>
        <div>
            <a href="manage_violations.php" style="margin-top: 20px; display: inline-block;">Back to Manage Violations</a>
        </div>
    </div>

    <script>
        function updateViolationFields() {
            const violationSelect = document.getElementById('violation_id');
            const selectedOption = violationSelect.options[violationSelect.selectedIndex];

            // Get form fields
            const violatorName = document.getElementById('violator_name');
            const plateNumber = document.getElementById('plate_number');
            const reason = document.getElementById('reason');
            const violationTypeId = document.getElementById('violation_type_id');
            const userId = document.getElementById('user_id');
            const email = document.getElementById('email');
            const hasLicense = document.getElementById('has_license');
            const licenseNumber = document.getElementById('license_number');

            // Clear fields if no violation is selected
            if (!selectedOption.value) {
                violatorName.value = '';
                plateNumber.value = '';
                reason.value = '';
                violationTypeId.value = '';
                userId.value = '';
                email.value = '';
                hasLicense.checked = false;
                licenseNumber.value = '';
                return;
            }

            // Populate fields with selected violation data
            violatorName.value = selectedOption.getAttribute('data-violator-name') || '';
            plateNumber.value = selectedOption.getAttribute('data-plate-number') || '';
            reason.value = selectedOption.getAttribute('data-reason') || '';
            violationTypeId.value = selectedOption.getAttribute('data-violation-type-id') || '';
            userId.value = selectedOption.getAttribute('data-user-id') || '';
            email.value = selectedOption.getAttribute('data-email') || '';
            hasLicense.checked = selectedOption.getAttribute('data-has-license') == '1';
            licenseNumber.value = selectedOption.getAttribute('data-license-number') || '';
        }

        // Run updateViolationFields on page load to populate fields with the latest violation
        window.onload = function() {
            updateViolationFields();
            // Uncomment the following line to enable auto-submit for the latest violation
            // if (document.getElementById('violation_id').value) {
            //     document.getElementById('violationForm').submit();
            // }
        };
    </script>
</body>
</html>