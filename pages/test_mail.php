<?php
require '../config/conn.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to send a test email
function sendTestEmail($recipientEmail) {
    try {
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'stine6595@gmail.com'; // Replace with your Gmail address
        $mail->Password = 'qvkb ycan jdij yffz'; // Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('stine6595@gmail.com', 'Test Email Sender');
        $mail->addAddress($recipientEmail, 'Recipient');

        $mail->isHTML(true);
        $mail->Subject = 'Test Email';
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
                </style>
            </head>
            <body style="background-color: #E4E6E9; margin: 0; padding: 20px;">
                <table class="container" bgcolor="#FFFFFF">
                    <tbody>
                        <tr>
                            <td>
                                <div class="header">Test Email</div>
                                <div>
                                    <p>This is a test email sent to verify the email sending functionality.</p>
                                    <p>If you received this email, the PHPMailer configuration is working correctly.</p>
                                </div>
                                <div class="footer">
                                    <p>Test Email System &copy; ' . date('Y') . '</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        ';
        $mail->AltBody = "Test Email\n\nThis is a test email sent to verify the email sending functionality.\nIf you received this email, the PHPMailer configuration is working correctly.\n\nTest Email System";

        $mail->send();
        
        file_put_contents('debug.log', "Test email sent successfully to $recipientEmail\n", FILE_APPEND);
        return ['success' => true, 'message' => "Test email sent successfully to $recipientEmail!"];
    } catch (Exception $e) {
        file_put_contents('debug.log', "Error sending test email to $recipientEmail: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => "Error sending test email: " . $e->getMessage()];
    }
}

// Send test email to je7570568@gmail.com
$result = sendTestEmail('joshuaedwardtupasmorante@gmail.com');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email Sender</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Test Email Sender</h3>
            </div>
            <div class="card-body">
                <p><?php echo htmlspecialchars($result['message']); ?></p>
                <a href="send_email.php" class="btn btn-primary">Send Another Test Email</a>
            </div>
        </div>
    </div>
    <script>
        Swal.fire({
            title: '<?php echo $result['success'] ? "Email Sent!" : "Error!"; ?>',
            text: '<?php echo htmlspecialchars($result['message']); ?>',
            icon: '<?php echo $result['success'] ? "success" : "error"; ?>',
            confirmButtonText: 'OK'
        });
    </script>
</body>
</html>