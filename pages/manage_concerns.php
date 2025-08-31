<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle update concern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_concern'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $status = trim($_POST['status'] ?? '');

        file_put_contents('../debug.log', "Update Concern Input: id='$id', status='$status'\n", FILE_APPEND);

        if (empty($id) || empty($status)) {
            $toastr_messages[] = "toastr.error('Concern ID and Status are required.');";
        } elseif (!in_array($status, ['OPEN', 'RESOLVED', 'PENDING'])) {
            $toastr_messages[] = "toastr.error('Invalid status selected.');";
        } else {
            $stmt = $pdo->prepare("UPDATE concerns SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $id]);
            if ($success) {
                $toastr_messages[] = "toastr.success('Concern updated successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to update concern.');";
                file_put_contents('../debug.log', "Update Concern Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating concern: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Update Concern Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch concerns
try {
    $stmt = $pdo->query("SELECT c.id, c.concern_text, c.status, c.created_at, c.updated_at, u.username 
                         FROM concerns c 
                         JOIN users u ON c.user_id = u.id 
                         ORDER BY c.created_at DESC");
    $concerns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching concerns: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Concerns Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $concerns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../layout/header.php'; ?>
<body>
    <?php include '../layout/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/menubar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2 text-primary">Manage Complaints</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Citizen Concerns</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Submitted By</th>
                                        <th>Concern</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($concerns)): ?>
                                        <tr><td colspan="7" class="text-center text-muted">No concerns found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($concerns as $concern): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($concern['id']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['username']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['concern_text']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['status']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($concern['updated_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editConcernModal<?php echo $concern['id']; ?>">Update</button>
                                                </td>
                                            </tr>
                                            <!-- Edit Concern Modal -->
                                            <div class="modal fade" id="editConcernModal<?php echo $concern['id']; ?>" tabindex="-1" aria-labelledby="editConcernModalLabel<?php echo $concern['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editConcernModalLabel<?php echo $concern['id']; ?>">Update Concern: #<?php echo htmlspecialchars($concern['id']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-concern-form">
                                                                <input type="hidden" name="id" value="<?php echo $concern['id']; ?>">
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="status" id="status_<?php echo $concern['id']; ?>" required>
                                                                        <option value="OPEN" <?php echo $concern['status'] === 'OPEN' ? 'selected' : ''; ?>>Open</option>
                                                                        <option value="PENDING" <?php echo $concern['status'] === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="RESOLVED" <?php echo $concern['status'] === 'RESOLVED' ? 'selected' : ''; ?>>Resolved</option>
                                                                    </select>
                                                                    <label class="form-label" for="status_<?php echo $concern['id']; ?>">Status</label>
                                                                    <div class="invalid-feedback">Please select a status.</div>
                                                                </div>
                                                                <button type="submit" name="update_concern" class="btn btn-primary">Update Concern</button>
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
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000
        };
        <?php foreach ($toastr_messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        document.querySelectorAll('.edit-concern-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit concern form submission attempted');
                const status = this.querySelector('select[name="status"]').value;

                if (!status) {
                    this.querySelector('select[name="status"]').classList.add('is-invalid');
                    console.log('Client-side validation failed for edit concern');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this concern?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit concern form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit concern form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>