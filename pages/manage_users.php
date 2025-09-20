<?php
session_start();
include '../config/conn.php';

// Check if user is logged in and has a valid role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'officer', 'user'])) {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Check for success messages in session
if (isset($_SESSION['create_success']) && $_SESSION['create_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Created!',
        text: 'User has been created successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['create_success']);
}
if (isset($_SESSION['edit_success']) && $_SESSION['edit_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Updated!',
        text: 'User has been updated successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['edit_success']);
}
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $toastr_messages[] = "Swal.fire({
        title: 'Deleted!',
        text: 'User has been deleted successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
    });";
    unset($_SESSION['delete_success']);
}

// Function to check if username exists (case-insensitive)
function usernameExists($pdo, $username, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(?) AND id != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $exclude_id ?? 0]);
    return $stmt->fetchColumn() > 0;
}

// Handle create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $officer_id = ($_SESSION['role'] === 'officer' && $role === 'user') ? $_SESSION['user_id'] : null;

        file_put_contents('../debug.log', "Create User Input: username='$username', full_name='$full_name', role='$role', officer_id='$officer_id'\n", FILE_APPEND);

        if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
            $toastr_messages[] = "toastr.error('All fields are required.');";
        } elseif (strlen($username) > 50 || strlen($full_name) > 100) {
            $toastr_messages[] = "toastr.error('Username or Full Name exceeds maximum length.');";
        } elseif (!in_array($role, ['admin', 'officer', 'user'])) {
            $toastr_messages[] = "toastr.error('Invalid role selected.');";
        } elseif (usernameExists($pdo, $username)) {
            $toastr_messages[] = "toastr.error('Username already exists (case-insensitive).');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, officer_id) VALUES (?, ?, ?, ?, ?)");
            $success = $stmt->execute([htmlspecialchars($username), password_hash($password, PASSWORD_DEFAULT), htmlspecialchars($full_name), $role, $officer_id]);
            if ($success) {
                $_SESSION['create_success'] = true;
                header("Location: manage_users.php");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to create user.');";
                file_put_contents('../debug.log', "Create User Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error creating user: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Create User Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $officer_id = ($_SESSION['role'] === 'officer' && $role === 'user') ? $_SESSION['user_id'] : null;

        file_put_contents('../debug.log', "Edit User Input: id='$id', username='$username', full_name='$full_name', role='$role', officer_id='$officer_id'\n", FILE_APPEND);

        if (empty($id) || empty($username) || empty($full_name) || empty($role)) {
            $toastr_messages[] = "toastr.error('ID, Username, Full Name, and Role are required.');";
        } elseif (strlen($username) > 50 || strlen($full_name) > 100) {
            $toastr_messages[] = "toastr.error('Username or Full Name exceeds maximum length.');";
        } elseif (!in_array($role, ['admin', 'officer', 'user'])) {
            $toastr_messages[] = "toastr.error('Invalid role selected.');";
        } elseif (usernameExists($pdo, $username, $id)) {
            $toastr_messages[] = "toastr.error('Username already exists (case-insensitive).');";
        } else {
            $sql = "UPDATE users SET username = ?, full_name = ?, role = ?, officer_id = ?";
            $params = [htmlspecialchars($username), htmlspecialchars($full_name), $role, $officer_id];
            if (!empty($password)) {
                $sql .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute($params);
            if ($success) {
                $_SESSION['edit_success'] = true;
                header("Location: manage_users.php");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to update user.');";
                file_put_contents('../debug.log', "Edit User Failed: No rows affected.\n", FILE_APPEND);
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

        file_put_contents('../debug.log', "Delete User Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            $toastr_messages[] = "toastr.error('User ID is required.');";
        } elseif ($id == $_SESSION['user_id']) {
            $toastr_messages[] = "toastr.error('Cannot delete your own account.');";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND (officer_id = ? OR officer_id IS NULL OR ? = 'admin')");
            $success = $stmt->execute([$id, $_SESSION['user_id'], $_SESSION['role']]);
            if ($success) {
                $_SESSION['delete_success'] = true;
                header("Location: manage_users.php");
                exit;
            } else {
                $toastr_messages[] = "toastr.error('Failed to delete user or you lack permission.');";
                file_put_contents('../debug.log', "Delete User Failed: No rows affected or permission denied.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error deleting user: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Delete User Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch users
try {
    $sql = "SELECT id, username, full_name, role, created_at FROM users";
    $params = [];
    if ($_SESSION['role'] === 'officer') {
        $sql .= " WHERE officer_id = ? OR id = ?";
        $params = [$_SESSION['user_id'], $_SESSION['user_id']];
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
        <div class="row">
            <?php include '../layout/officer_menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Manage Users</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createUserModal">Add User</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No users found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
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
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="username" id="username_<?php echo $user['id']; ?>" required value="<?php echo htmlspecialchars($user['username']); ?>" maxlength="50" />
                                                                    <label class="form-label" for="username_<?php echo $user['id']; ?>">Username (max 50 characters)</label>
                                                                    <div class="invalid-feedback">Please enter a valid username.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="password" class="form-control" name="password" id="password_<?php echo $user['id']; ?>" placeholder="Leave blank to keep unchanged" />
                                                                    <label class="form-label" for="password_<?php echo $user['id']; ?>">Password (optional)</label>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <input type="text" class="form-control" name="full_name" id="full_name_<?php echo $user['id']; ?>" required value="<?php echo htmlspecialchars($user['full_name']); ?>" maxlength="100" />
                                                                    <label class="form-label" for="full_name_<?php echo $user['id']; ?>">Full Name (max 100 characters)</label>
                                                                    <div class="invalid-feedback">Please enter a valid full name.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="role" id="role_<?php echo $user['id']; ?>" required>
                                                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                        <option value="officer" <?php echo $user['role'] === 'officer' ? 'selected' : ''; ?>>Officer</option>
                                                                        <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                                    </select>
                                                                    <label class="form-label" for="role_<?php echo $user['id']; ?>">Role</label>
                                                                    <div class="invalid-feedback">Please select a role.</div>
                                                                </div>
                                                                <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
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

                <!-- Create User Modal -->
                <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createUserModalLabel">Add New User</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline create-user-form" id="createUserForm">
                                    <input type="hidden" name="create_user" value="1">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="username" id="username" required maxlength="50" />
                                        <label class="form-label" for="username">Username (max 50 characters)</label>
                                        <div class="invalid-feedback">Please enter a valid username.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="password" class="form-control" name="password" id="password" required />
                                        <label class="form-label" for="password">Password</label>
                                        <div class="invalid-feedback">Please enter a password.</div>
                                    </div>
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="full_name" id="full_name" required maxlength="100" />
                                        <label class="form-label" for="full_name">Full Name (max 100 characters)</label>
                                        <div class="invalid-feedback">Please enter a valid full name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <select class="form-select" name="role" id="role" required>
                                            <option value="" disabled selected>Select Role</option>
                                            <option value="admin">Admin</option>
                                            <option value="officer">Officer</option>
                                            <option value="user">User</option>
                                        </select>
                                        <label class="form-label" for="role">Role</label>
                                        <div class="invalid-feedback">Please select a role.</div>
                                    </div>
                                    <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
    <script>
        // Initialize toastr options
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };

        // Output any toastr or SweetAlert messages from PHP
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        // Function to attach delete form event listeners
        function attachDeleteFormListeners() {
            console.log('Attaching delete form listeners');
            document.removeEventListener('submit', handleDeleteFormSubmit);
            document.addEventListener('submit', handleDeleteFormSubmit);
        }

        // Handler for delete form submissions
        function handleDeleteFormSubmit(e) {
            if (e.target && e.target.matches('.delete-user-form')) {
                e.preventDefault();
                const form = e.target;
                console.log('Delete form submission intercepted for user ID:', form.querySelector('input[name="id"]').value);
                Swal.fire({
                    title: 'Delete User?',
                    text: 'Are you sure you want to delete this user? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'No, cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Delete user form submission confirmed');
                        form.submit();
                    } else {
                        console.log('Delete user form submission canceled');
                    }
                });
            }
        }

        // Attach listeners on page load
        window.addEventListener('load', function() {
            console.log('Page loaded, initializing delete form listeners');
            attachDeleteFormListeners();
        });

        // Create user form submission
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            console.log('Create user form submission attempted');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const fullName = document.getElementById('full_name').value.trim();
            const role = document.getElementById('role').value;

            let isValid = true;

            document.getElementById('username').classList.remove('is-invalid');
            document.getElementById('password').classList.remove('is-invalid');
            document.getElementById('full_name').classList.remove('is-invalid');
            document.getElementById('role').classList.remove('is-invalid');

            if (!username || username.length > 50) {
                document.getElementById('username').classList.add('is-invalid');
                isValid = false;
            }
            if (!password) {
                document.getElementById('password').classList.add('is-invalid');
                isValid = false;
            }
            if (!fullName || fullName.length > 100) {
                document.getElementById('full_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!role) {
                document.getElementById('role').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for create user');
                e.preventDefault();
                return;
            }

            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to create this user?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Create user form submission confirmed');
                    this.submit();
                } else {
                    console.log('Create user form submission canceled');
                }
            });
        });

        // Edit user form submission
        document.querySelectorAll('.edit-user-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit user form submission attempted');
                const username = this.querySelector('input[name="username"]').value.trim();
                const fullName = this.querySelector('input[name="full_name"]').value.trim();
                const role = this.querySelector('select[name="role"]').value;

                let isValid = true;

                this.querySelector('input[name="username"]').classList.remove('is-invalid');
                this.querySelector('input[name="full_name"]').classList.remove('is-invalid');
                this.querySelector('select[name="role"]').classList.remove('is-invalid');

                if (!username || username.length > 50) {
                    this.querySelector('input[name="username"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!fullName || fullName.length > 100) {
                    this.querySelector('input[name="full_name"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!role) {
                    this.querySelector('select[name="role"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit user');
                    e.preventDefault();
                    return;
                }

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
    </script>
</body>
</html>