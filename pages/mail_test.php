<?php
// File: mail_test.php
// Creates a violation record in the database and sends an email to the provided email address using PHPMailer with Gmail's SMTP server.
// Assumes vendor folder is in C:\xampp\htdocs\Traffic-Violation-App and MySQL database is configured in XAMPP.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Database connection settings (update these with your actual database details)
$host = 'localhost';
$dbname = 'traffic'; // Replace with your database name
$username = 'root'; // Default XAMPP MySQL user (update if different)
$password = ''; // Default XAMPP MySQL password (update if different)

$status = ''; // To display success/error message

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_violation'])) {
    try {
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

        // Insert into violations table
        $stmt = $pdo->prepare("
            INSERT INTO violations (
                violator_name, plate_number, reason, violation_type_id, user_id, 
                has_license, license_number, issue_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $violator_name,
            $plate_number,
            $reason,
            $violation_type_id,
            $user_id,
            $has_license,
            $license_number
        ]);

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
            <p>Dear $violator_name,</p>
            <p>A traffic violation has been recorded with the following details:</p>
            <ul>
                <li><strong>Plate Number:</strong> $plate_number</li>
                <li><strong>Reason:</strong> $reason</li>
                <li><strong>Violation Type ID:</strong> $violation_type_id</li>
                <li><strong>License Number:</strong> " . ($license_number ?: 'N/A') . "</li>
                <li><strong>Issue Date:</strong> " . date('Y-m-d H:i:s') . "</li>
            </ul>
            <p>Please address this violation promptly.</p>
            <p>Regards,<br>Traffic Violation System</p>
        ";
        $mail->AltBody = "Traffic Violation Notification\n\nDear $violator_name,\n\nA traffic violation has been recorded:\n- Plate Number: $plate_number\n- Reason: $reason\n- Violation Type ID: $violation_type_id\n- License Number: " . ($license_number ?: 'N/A') . "\n- Issue Date: " . date('Y-m-d H:i:s') . "\n\nPlease address this violation promptly.\n\nRegards,\nTraffic Violation System";

        $mail->send();
        $status = 'Violation recorded and email sent successfully to ' . htmlspecialchars($email) . '! Check the inbox or spam folder.';
    } catch (PDOException $e) {
        $status = "Failed to record violation. Database Error: " . $e->getMessage();
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
    <title>Create Violation</title>
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
        input[type="email"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
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
        <h2>Create Traffic Violation</h2>
        <form method="post">
            <div class="form-group">
                <label for="violator_name">Violator Name *</label>
                <input type="text" id="violator_name" name="violator_name" required>
            </div>
            <div class="form-group">
                <label for="plate_number">Plate Number *</label>
                <input type="text" id="plate_number" name="plate_number" required>
            </div>
            <div class="form-group">
                <label for="reason">Reason *</label>
                <input type="text" id="reason" name="reason" required>
            </div>
            <div class="form-group">
                <label for="violation_type_id">Violation Type ID *</label>
                <input type="text" id="violation_type_id" name="violation_type_id" required>
            </div>
            <div class="form-group">
                <label for="user_id">User ID *</label>
                <input type="text" id="user_id" name="user_id" required>
            </div>
            <div class="form-group">
                <label for="email">Recipient Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="has_license"> Has License
                </label>
            </div>
            <div class="form-group">
                <label for="license_number">License Number</label>
                <input type="text" id="license_number" name="license_number">
            </div>
            <button type="submit" name="submit_violation">Create Violation & Send Email</button>
        </form>
        <?php if ($status): ?>
            <p class="status"><?php echo htmlspecialchars($status); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>