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
    <section class="vh-100">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6 text-black">
                    <div class="px-5 ms-xl-4">
                        <img src="../public/images/PRVN.png" alt="PRVN Logo" class="img-fluid" style="max-width: 150px; margin-bottom: 10px;">
                        <span class="h1 fw-bold mb-0">PRVN - Violation Management System</span>
                    </div>

                    <div class="d-flex align-items-center h-custom-2 px-5 ms-xl-4 mt-5 pt-5 pt-xl-0 mt-xl-n5">
                        <form style="width: 23rem;" method="POST">
                            <h3 class="fw-normal mb-3 pb-3" style="letter-spacing: 1px;">Log in</h3>

                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>

                            <div class="form-outline mb-4">
                                <input type="text" id="username" name="username" class="form-control form-control-lg" required />
                                <label class="form-label" for="username">Username</label>
                            </div>

                            <div class="form-outline mb-4">
                                <input type="password" id="password" name="password" class="form-control form-control-lg" required />
                                <label class="form-label" for="password">Password</label>
                            </div>

                            <div class="pt-1 mb-4">
                                <button class="btn btn-info btn-lg btn-block" type="submit">Login</button>
                            </div>

                        <!--    <p class="small mb-5 pb-lg-2"><a class="text-muted" href="#!">Forgot password?</a></p>-->
                        <!--<p>Don't have an account? <a href="#!" class="link-info">Register here</a></p>-->
                        </form>
                    </div>
                </div>
                <div class="col-sm-6 px-0 d-none d-sm-block">
                    <img src="../public/images/tf.jpg" alt="Login image" class="w-100 vh-100 login-image">
                </div>
            </div>
        </div>
    </section>
    <?php 
    if (!file_exists($footer_file)) {
        die('Error: Footer file not found at ' . $footer_file);
    }
    include $footer_file; 
    ?>
</body>
</html>