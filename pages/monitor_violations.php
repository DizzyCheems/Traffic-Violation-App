<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle violation status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_violation'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $is_paid = trim($_POST['is_paid'] ?? '');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (empty($id) || empty($status) || !isset($_POST['is_paid'])) {
            $toastr_messages[] = "toastr.error('Violation ID, Status, and Payment Status are required.');";
        } elseif (!in_array($status, ['Pending', 'Resolved', 'Disputed'])) {
            $toastr_messages[] = "toastr.error('Invalid status selected.');";
        } elseif (!in_array($is_paid, ['0', '1', '2'])) {
            $toastr_messages[] = "toastr.error('Invalid payment status selected.');";
        } else {
            $stmt = $pdo->prepare("UPDATE violations SET status = ?, is_paid = ?, notes = ? WHERE id = ?");
            $stmt->execute([$status, $is_paid, $notes, $id]);
            $toastr_messages[] = "toastr.success('Violation updated successfully.');";
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating violation: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Update Violation Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch violation types for filter
try {
    $stmt = $pdo->query("SELECT id, violation_type FROM types ORDER BY violation_type");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation types: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Types Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $types = [];
}

// Handle filtering
$where_clauses = [];
$params = [];
$filter_violation_type = $_GET['violation_type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

if ($filter_violation_type) {
    $where_clauses[] = "v.violation_type_id = ?";
    $params[] = $filter_violation_type;
}
if ($filter_status) {
    $where_clauses[] = "v.status = ?";
    $params[] = $filter_status;
}
if ($filter_date_from) {
    $where_clauses[] = "v.issued_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_clauses[] = "v.issued_date <= ?";
    $params[] = $filter_date_to;
}

$sql = "SELECT v.id, v.violator_name, v.plate_number, v.plate_image, v.impound_pic, v.reason, t.violation_type, t.fine_amount, v.issued_date, v.has_license, v.is_impounded, v.is_paid, v.status, v.notes, u.full_name as user_name 
        FROM violations v 
        JOIN types t ON v.violation_type_id = t.id 
        JOIN users u ON v.user_id = u.id";
if ($where_clauses) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY v.issued_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $violations = [];
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
                    <h1 class="h2 text-primary">Monitor Violations</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Filter Violations</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select class="form-select" name="violation_type" id="violation_type">
                                    <option value="">All Violation Types</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['id']); ?>" <?php echo $filter_violation_type == $type['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['violation_type']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label" for="violation_type">Violation Type</label>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status" id="status">
                                    <option value="">All Statuses</option>
                                    <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Resolved" <?php echo $filter_status === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="Disputed" <?php echo $filter_status === 'Disputed' ? 'selected' : ''; ?>>Disputed</option>
                                </select>
                                <label class="form-label" for="status">Status</label>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" />
                                <label class="form-label" for="date_from">Date From</label>
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" />
                                <label class="form-label" for="date_to">Date To</label>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violations</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Violator Name</th>
                                        <th>Plate Number</th>
                                        <th>Plate Image</th>
                                        <th>Impound Image</th>
                                        <th>Reason</th>
                                        <th>Violation Type</th>
                                        <th>Fine Amount</th>
                                        <th>Issued Date</th>
                                        <th>License</th>
                                        <th>Impounded</th>
                                        <th>Paid</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($violations)): ?>
                                        <tr><td colspan="15" class="text-center text-muted">No violations found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($violations as $violation): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($violation['id']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violator_name']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['plate_number']); ?></td>
                                                <td>
                                                    <?php if ($violation['plate_image'] && file_exists($violation['plate_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($violation['plate_image']); ?>" 
                                                             alt="Plate Image" 
                                                             class="img-thumbnail" 
                                                             style="max-width: 50px; object-fit: contain; cursor: pointer;" 
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#imageModal" 
                                                             data-image="<?php echo htmlspecialchars($violation['plate_image']); ?>" 
                                                             data-title="Plate Image for Violation #<?php echo htmlspecialchars($violation['id']); ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">No Image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($violation['impound_pic'] && file_exists($violation['impound_pic'])): ?>
                                                        <img src="<?php echo htmlspecialchars($violation['impound_pic']); ?>" 
                                                             alt="Impound Image" 
                                                             class="img-thumbnail" 
                                                             style="max-width: 50px; object-fit: contain; cursor: pointer;" 
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#imageModal" 
                                                             data-image="<?php echo htmlspecialchars($violation['impound_pic']); ?>" 
                                                             data-title="Impound Image for Violation #<?php echo htmlspecialchars($violation['id']); ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($violation['reason']); ?></td>
                                                <td><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                                                <td><?php echo '₱' . number_format($violation['fine_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($violation['issued_date']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $violation['has_license'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $violation['has_license'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $violation['is_impounded'] ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                                        <?php echo $violation['is_impounded'] ? 'Yes' : 'No'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $violation['is_paid'] == 1 ? 'bg-success' : 
                                                            ($violation['is_paid'] == 2 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                        <?php 
                                                            echo $violation['is_paid'] == 1 ? 'Paid' : 
                                                                ($violation['is_paid'] == 2 ? 'Payment Pending For Approval' : 'Unpaid'); 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $violation['status'];
                                                    $badgeClass = '';
                                                    $badgeText = $status;

                                                    switch ($status) {
                                                        case 'Pending':
                                                            $badgeClass = 'bg-warning text-dark';
                                                            break;
                                                        case 'Resolved':
                                                            $badgeClass = 'bg-success';
                                                            break;
                                                        case 'Disputed':
                                                            $badgeClass = 'bg-danger';
                                                            break;
                                                        default:
                                                            $badgeClass = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($badgeText); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($violation['notes'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editViolationModal<?php echo $violation['id']; ?>">Update</button>
                                                </td>
                                            </tr>
                                            <!-- Edit Violation Modal -->
                                            <div class="modal fade" id="editViolationModal<?php echo $violation['id']; ?>" tabindex="-1" aria-labelledby="editViolationModalLabel<?php echo $violation['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editViolationModalLabel<?php echo $violation['id']; ?>">Update Violation: #<?php echo htmlspecialchars($violation['id']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-violation-form">
                                                                <input type="hidden" name="id" value="<?php echo $violation['id']; ?>">
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="status" id="status_<?php echo $violation['id']; ?>" required>
                                                                        <option value="Pending" <?php echo $violation['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                        <option value="Resolved" <?php echo $violation['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                        <option value="Disputed" <?php echo $violation['status'] === 'Disputed' ? 'selected' : ''; ?>>Disputed</option>
                                                                    </select>
                                                                    <label class="form-label" for="status_<?php echo $violation['id']; ?>">Status</label>
                                                                    <div class="invalid-feedback">Please select a status.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <select class="form-select" name="is_paid" id="is_paid_<?php echo $violation['id']; ?>" required>
                                                                        <option value="0" <?php echo $violation['is_paid'] == 0 ? 'selected' : ''; ?>>Unpaid</option>
                                                                        <option value="1" <?php echo $violation['is_paid'] == 1 ? 'selected' : ''; ?>>Paid</option>
                                                                        <option value="2" <?php echo $violation['is_paid'] == 2 ? 'selected' : ''; ?>>Payment Pending For Approval</option>
                                                                    </select>
                                                                    <label class="form-label" for="is_paid_<?php echo $violation['id']; ?>">Payment Status</label>
                                                                    <div class="invalid-feedback">Please select a payment status.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <textarea class="form-control" name="notes" id="notes_<?php echo $violation['id']; ?>" rows="4"><?php echo htmlspecialchars($violation['notes'] ?: ''); ?></textarea>
                                                                    <label class="form-label" for="notes_<?php echo $violation['id']; ?>">Notes</label>
                                                                </div>
                                                                <button type="submit" name="update_violation" class="btn btn-primary">Update Violation</button>
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

                <!-- Image Viewer Modal -->
                <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="imageModalLabel">Image Viewer</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="modalImage" src="" alt="Violation Image" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../layout/footer.php'; ?>
    <style>
        .img-thumbnail {
            max-width: 50px;
            object-fit: contain;
            border-radius: 4px;
            cursor: pointer;
        }
        .table-row-hover:hover {
            background-color: #f8f9fa;
        }
        #modalImage {
            max-height: 70vh;
            object-fit: contain;
        }
    </style>
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

        // Image modal handler
        document.querySelectorAll('.img-thumbnail').forEach(img => {
            img.addEventListener('click', function() {
                const imageSrc = this.getAttribute('data-image');
                const imageTitle = this.getAttribute('data-title');
                document.getElementById('modalImage').src = imageSrc;
                document.getElementById('imageModalLabel').textContent = imageTitle;
                console.log('Opening image modal for:', imageSrc);
            });
        });

        document.querySelectorAll('.edit-violation-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit violation form submission attempted');
                const status = this.querySelector('select[name="status"]').value;
                const isPaid = this.querySelector('select[name="is_paid"]').value;

                if (!status || !isPaid) {
                    if (!status) {
                        this.querySelector('select[name="status"]').classList.add('is-invalid');
                    }
                    if (!isPaid) {
                        this.querySelector('select[name="is_paid"]').classList.add('is-invalid');
                    }
                    console.log('Client-side validation failed for edit violation');
                    e.preventDefault();
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this violation?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit violation form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit violation form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>