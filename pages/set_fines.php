<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle update fine amount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fine'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $fine_amount = trim($_POST['fine_amount'] ?? '');

        file_put_contents('../debug.log', "Update Fine Input: id='$id', fine_amount='$fine_amount'\n", FILE_APPEND);

        if (empty($id) || empty($fine_amount)) {
            $toastr_messages[] = "toastr.error('ID and Fine Amount are required.');";
        } elseif (!is_numeric($fine_amount) || $fine_amount < 0) {
            $toastr_messages[] = "toastr.error('Fine Amount must be a non-negative number.');";
        } else {
            $stmt = $pdo->prepare("UPDATE types SET fine_amount = ? WHERE id = ?");
            $success = $stmt->execute([(float)$fine_amount, $id]);
            if ($success) {
                $toastr_messages[] = "toastr.success('Fine amount updated successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to update fine amount.');";
                file_put_contents('../debug.log', "Update Fine Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating fine amount: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Update Fine Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch violation types
try {
    $stmt = $pdo->query("SELECT id, violation_type, fine_amount, description FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
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
                    <h1 class="h2 text-primary">Set Fine Amounts</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violation Types</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Violation Type</th>
                                        <th>Fine Amount</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($types)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No violation types found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($types as $type): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($type['id']); ?></td>
                                                <td><?php echo htmlspecialchars($type['violation_type']); ?></td>
                                                <td><?php echo '$' . number_format($type['fine_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($type['description'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editFineModal<?php echo $type['id']; ?>">Edit Fine</button>
                                                </td>
                                            </tr>
                                            <!-- Edit Fine Modal -->
                                            <div class="modal fade" id="editFineModal<?php echo $type['id']; ?>" tabindex="-1" aria-labelledby="editFineModalLabel<?php echo $type['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editFineModalLabel<?php echo $type['id']; ?>">Edit Fine: <?php echo htmlspecialchars($type['violation_type']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-fine-form">
                                                                <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                                <div class="mb-3">
                                                                    <input type="number" step="0.01" min="0" class="form-control" name="fine_amount" id="fine_amount_<?php echo $type['id']; ?>" required value="<?php echo htmlspecialchars($type['fine_amount']); ?>" />
                                                                    <label class="form-label" for="fine_amount_<?php echo $type['id']; ?>">Fine Amount</label>
                                                                    <div class="invalid-feedback">Please enter a valid non-negative number.</div>
                                                                </div>
                                                                <button type="submit" name="update_fine" class="btn btn-primary">Update Fine</button>
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

        document.querySelectorAll('.edit-fine-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit fine form submission attempted');
                const fineAmount = this.querySelector('input[name="fine_amount"]').value.trim();

                if (!fineAmount || isNaN(fineAmount) || parseFloat(fineAmount) < 0) {
                    this.querySelector('input[name="fine_amount"]').classList.add('is-invalid');
                    console.log('Client-side validation failed for edit fine');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this fine amount?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit fine form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit fine form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>