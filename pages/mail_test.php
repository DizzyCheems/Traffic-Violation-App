<?php
// File: mail_test.php
// Sends an email using PHPMailer with Gmail's SMTP server when the button is clicked.
// Assumes vendor folder is in C:\xampp\htdocs\Traffic-Violation-App.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Adjusted path to vendor folder

$status = ''; // To display success/error message

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $mail = new PHPMailer(true);

    try {
        // Server settings for Gmail SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'stine6595@gmail.com';
        $mail->Password   = 'qvkb ycan jdij yffz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom('stine6595@gmail.com', 'Your Name');
        $mail->addAddress('spiralsmoke903@gmail.com', 'Recipient Name');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Test Email via Gmail';
        $mail->Body    = 'Hello! This is a test email sent using PHPMailer and Gmail SMTP. It should appear in your Gmail inbox.';
        $mail->AltBody = 'This is the plain text version of the email.';

        $mail->send();
        $status = 'Email sent successfully! Check spiralsmoke903@gmail.com inbox or spam folder.';
    } catch (Exception $e) {
        $status = "Email could not be sent. Error: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        <h2>Send Test Email</h2>
        <form method="post">
            <button type="submit" name="send_email">Send Email</button>
        </form>
        <?php if ($status): ?>
            <p class="status"><?php echo htmlspecialchars($status); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>