<?php
session_start();
include '../config/conn.php';

// Debug: Log session data
file_put_contents('../debug.log', "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'officer') {
    $reason = "Redirecting to login.php. ";
    if (!isset($_SESSION['user_id'])) {
        $reason .= "user_id not set. ";
    }
    if (!isset($_SESSION['role'])) {
        $reason .= "role not set. ";
    }
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) !== 'officer') {
        $reason .= "role is '" . $_SESSION['role'] . "' instead of 'officer'.";
    }
    file_put_contents('../debug.log', $reason . "\n", FILE_APPEND);
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Check database connection
if (!$pdo) {
    $toastr_messages[] = "toastr.error('Database connection failed.');";
    file_put_contents('../debug.log', "Database connection failed.\n", FILE_APPEND);
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Log received input
        file_put_contents('../debug.log', "Edit User Input: id='$id', username='$username', full_name='$full_name'\n", FILE_APPEND);

        if (empty($id) || empty($username) || empty($full_name)) {
            $toastr_messages[] = "toastr.error('ID, Username, and Full Name are required.');";
        } else {
            // Check if username exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                $toastr_messages[] = "toastr.error('Username already exists.');";
            } else {
                if (!empty($password)) {
                    // Update with new password (use password_hash in production)
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, password = ? WHERE id = ? AND role = 'user'");
                    $params = [$username, $full_name, $password, $id];
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ? WHERE id = ? AND role = 'user'");
                    $params = [$username, $full_name, $id];
                }
                file_put_contents('../debug.log', "Edit User Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
                $success = $stmt->execute($params);
                if ($success) {
                    $toastr_messages[] = "toastr.success('User updated successfully.');";
                } else {
                    $toastr_messages[] = "toastr.error('Failed to update user. No rows affected or user is not a civilian.');";
                    file_put_contents('../debug.log', "Edit User Failed: No rows affected or user is not a civilian.\n", FILE_APPEND);
                }
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating user: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Edit User Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        // Log received input
        file_put_contents('../debug.log', "Delete User Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            $toastr_messages[] = "toastr.error('User ID is required.');";
        } else {
            // Check if user has violations
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE user_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $toastr_messages[] = "toastr.error('Cannot delete user with associated violations.');";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
                $params = [$id];
                file_put_contents('../debug.log', "Delete User Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
                $success = $stmt->execute($params);
                if ($success) {
                    $toastr_messages[] = "toastr.success('User deleted successfully.');";
                } else {
                    $toastr_messages[] = "toastr.error('Failed to delete user. No rows affected or user is not a civilian.');";
                    file_put_contents('../debug.log', "Delete User Failed: No rows affected or user is not a civilian.\n", FILE_APPEND);
                }
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error deleting user: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Delete User Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch officer details
$officer_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$officer_id]);
    $officer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officer details: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Officer Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $officer = ['full_name' => 'Unknown', 'username' => 'Unknown'];
}

// Fetch users with role 'user' and their violation counts
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, COUNT(v.id) as violation_count 
        FROM users u 
        LEFT JOIN violations v ON u.id = v.user_id 
        WHERE u.role = 'user' 
        GROUP BY u.id 
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching users: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Users Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $users = [];
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
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/all_violations.php">
                                <i class="fas fa-list me-2"></i>
                                All Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
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
                            <a class="nav-link" href="../pages/issue_violation.php">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Issue Violation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/all_violations.php">
                                <i class="fas fa-list me-2"></i>
                                All Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../pages/manage_violations.php">
                                <i class="fas fa-list-alt me-2"></i>
                                Manage Violations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/manage_users.php">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
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
                    <h1 class="h2 text-primary">Manage Users - <?php echo htmlspecialchars($officer['full_name']); ?></h1>
                    <div>
                        <a href="../pages/officer_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Violation Count</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No users found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['violation_count']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">Edit</button>
                                                    <form method="POST" style="display: inline;" class="delete-user-form">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="delete_user" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <!-- Edit User Modal -->
                                            <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-user-form">
                                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="edit_user" value="1">
                                                                <div class="mb-3">
                                                                    <label for="username_<?php echo $user['id']; ?>" class="form-label">Username</label>
                                                                    <input type="text" class="form-control" name="username" id="username_<?php echo $user['id']; ?>" required value="<?php echo htmlspecialchars($user['username']); ?>">
                                                                    <div class="invalid-feedback">Please enter a valid username.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="full_name_<?php echo $user['id']; ?>" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" name="full_name" id="full_name_<?php echo $user['id']; ?>" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                                    <div class="invalid-feedback">Please enter a valid full name.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="password_<?php echo $user['id']; ?>" class="form-label">New Password (optional)</label>
                                                                    <input type="password" class="form-control" name="password" id="password_<?php echo $user['id']; ?>">
                                                                    <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                                                </div>
                                                                <button type="submit" class="btn btn-primary">Update User</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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

        // Display Toastr messages
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        // Client-side validation for Edit User Forms
        document.querySelectorAll('.edit-user-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit user form submission attempted');
                const username = this.querySelector('input[name="username"]').value.trim();
                const fullName = this.querySelector('input[name="full_name"]').value.trim();
                const password = this.querySelector('input[name="password"]').value.trim();

                let isValid = true;

                // Reset validation states
                this.querySelector('input[name="username"]').classList.remove('is-invalid');
                this.querySelector('input[name="full_name"]').classList.remove('is-invalid');
                this.querySelector('input[name="password"]').classList.remove('is-invalid');

                if (!username) {
                    this.querySelector('input[name="username"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!fullName) {
                    this.querySelector('input[name="full_name"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (password && password.length < 6) {
                    this.querySelector('input[name="password"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit user');
                    e.preventDefault();
                    return;
                }

                // SweetAlert2 confirmation
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this user?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit user form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit user form submission canceled');
                    }
                });
            });
        });

        // Client-side validation and confirmation for Delete User Forms
        document.querySelectorAll('.delete-user-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Delete user form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to delete this user? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Delete user form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Delete user form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>