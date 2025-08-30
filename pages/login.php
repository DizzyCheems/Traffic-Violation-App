<?php
session_start();

// Debug: Log session start
file_put_contents('../debug.log', "Login.php accessed: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Check for file existence before including
$conn_file = '../config/conn.php';
$header_file = '../layout/header.php';
$navbar_file = '../layout/navbar.php';
$footer_file = '../layout/footer.php';

if (!file_exists($conn_file)) {
    die('Error: Database connection file not found at ' . $conn_file);
}
include $conn_file;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $error = '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $password === $user['password']) { // Replace with password_verify in production
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                
                // Debug: Log session after login
                file_put_contents('../debug.log', "Login successful for user_id: {$user['id']}, role: {$user['role']}\n", FILE_APPEND);

                // Redirect based on role
                switch (strtolower($user['role'])) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        exit;
                    case 'user':
                        header("Location: user_dashboard.php");
                        exit;
                    case 'officer':
                        header("Location: officer_dashboard.php");
                        exit;
                    default:
                        $error = 'Invalid user role.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php 
if (!file_exists($header_file)) {
    die('Error: Header file not found at ' . $header_file);
}
include $header_file; 
?>
<body>
    <?php 
    if (!file_exists($navbar_file)) {
        die('Error: Navbar file not found at ' . $navbar_file);
    }
    include $navbar_file; 
    ?>
    <div class="container mt-5">
        <h2>Login</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
    <?php 
    if (!file_exists($footer_file)) {
        die('Error: Footer file not found at ' . $footer_file);
    }
    include $footer_file; 
    ?>
</body>
</html>