<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle appeal status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appeal'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (empty($id) || empty($status)) {
            $toastr_messages[] = "toastr.error('Appeal ID and Status are required.');";
        } elseif (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'])) {
            $toastr_messages[] = "toastr.error('Invalid status selected.');";
        } else {
            $stmt = $pdo->prepare("UPDATE appeals SET status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$status, $notes, $id]);
            $toastr_messages[] = "toastr.success('Appeal updated successfully.');";
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating appeal: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Update Appeal Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch appeals
try {
    $stmt = $pdo->query("SELECT a.id, a.violation_id, a.appeal_reason, a.status, a.notes, a.created_at, a.updated_at, u.full_name as user_name, v.violator_name 
                         FROM appeals a 
                         JOIN users u ON a.user_id = u.id 
                         JOIN violations v ON a.violation_id = v.id 
                         ORDER BY a.created_at DESC");
    $appeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching appeals: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Appeals Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $appeals = [];
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
                    <h1 class="h2 text-primary">Appeal Workflow</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Manage Appeals</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Violation ID</th>
                                        <th>Violator Name</th>
                                        <th>Submitted By</th>
                                        <th>Appeal Reason</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($appeals)): ?>
                                        <tr><td colspan="10" class="text-center text-muted">No appeals found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($appeals as $appeal): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($appeal['id']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['violation_id']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['appeal_reason']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['status']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['notes'] ?: 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($appeal['updated_at']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editAppealModal<?php echo $appeal['id']; ?>">Update</button>
                                                </td>
                                            </tr>
                                            <!-- Edit Appeal Modal -->
                                            <div class="modal fade" id="editAppealModal<?php echo $appeal['id']; ?>" tabindex="-1" aria-labelledby="editAppealModalLabel<?php echo $appeal['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editAppealModalLabel<?php echo $appeal['id']; ?>">Update Appeal: #<?php echo htmlspecialchars($appeal['id']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-appeal-form">
                                                                <input type="hidden" name="id" value="<?php echo $appeal['id']; ?>">
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="status" id="status_<?php echo $appeal['id']; ?>" required>
                                                                        <option value="PENDING" <?php echo $appeal['status'] === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="APPROVED" <?php echo $appeal['status'] === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                                                                        <option value="REJECTED" <?php echo $appeal['status'] === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                                                                    </select>
                                                                    <label class="form-label" for="status_<?php echo $appeal['id']; ?>">Status</label>
                                                                    <div class="invalid-feedback">Please select a status.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="notes" id="notes_<?php echo $appeal['id']; ?>" rows="4"><?php echo htmlspecialchars($appeal['notes'] ?: ''); ?></textarea>
                                                                    <label class="form-label" for="notes_<?php echo $appeal['id']; ?>">Notes</label>
                                                                </div>
                                                                <button type="submit" name="update_appeal" class="btn btn-primary">Update Appeal</button>
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

        document.querySelectorAll('.edit-appeal-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit appeal form submission attempted');
                const status = this.querySelector('select[name="status"]').value;

                if (!status) {
                    this.querySelector('select[name="status"]').classList.add('is-invalid');
                    console.log('Client-side validation failed for edit appeal');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this appeal?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit appeal form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit appeal form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>