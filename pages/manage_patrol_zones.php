<?php
session_start();
include '../config/conn.php';

// Debug: Log session data
file_put_contents('../debug.log', "Session Data: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

// Check session variables
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin') {
    $reason = "Redirecting to login.php. ";
    if (!isset($_SESSION['user_id'])) {
        $reason .= "user_id not set. ";
    }
    if (!isset($_SESSION['role'])) {
        $reason .= "role not set. ";
    }
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) !== 'admin') {
        $reason .= "role is '" . $_SESSION['role'] . "' instead of 'admin'.";
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

// Handle create patrol zone assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_patrol_zone'])) {
    try {
        $officer_id = trim($_POST['officer_id'] ?? '');
        $zone_name = trim($_POST['zone_name'] ?? '');
        $coordinates = trim($_POST['coordinates'] ?? '') ?: null;
        $hotspots = trim($_POST['hotspots'] ?? '') ?: null;
        $urgency = trim($_POST['urgency'] ?? 'Low');
        $assigned_date = trim($_POST['assigned_date'] ?? '') ?: date('Y-m-d H:i:s');
        $created_at = date('Y-m-d H:i:s');

        // Log received input
        file_put_contents('../debug.log', "Create Patrol Zone Input: officer_id='$officer_id', zone_name='$zone_name', urgency='$urgency'\n", FILE_APPEND);

        if (empty($officer_id) || empty($zone_name) || empty($urgency)) {
            $toastr_messages[] = "toastr.error('Officer, Zone Name, and Urgency are required.');";
        } else {
            $stmt = $pdo->prepare("INSERT INTO patrol_zones (officer_id, zone_name, coordinates, hotspots, urgency, assigned_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $params = [$officer_id, $zone_name, $coordinates, $hotspots, $urgency, $assigned_date, $created_at];
            file_put_contents('../debug.log', "Create Patrol Zone Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Patrol zone assigned successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to assign patrol zone.');";
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error assigning patrol zone: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Create Patrol Zone Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle edit patrol zone assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_patrol_zone'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        $officer_id = trim($_POST['officer_id'] ?? '');
        $zone_name = trim($_POST['zone_name'] ?? '');
        $coordinates = trim($_POST['coordinates'] ?? '') ?: null;
        $hotspots = trim($_POST['hotspots'] ?? '') ?: null;
        $urgency = trim($_POST['urgency'] ?? 'Low');
        $assigned_date = trim($_POST['assigned_date'] ?? '') ?: date('Y-m-d H:i:s');

        // Log received input
        file_put_contents('../debug.log', "Edit Patrol Zone Input: id='$id', officer_id='$officer_id', zone_name='$zone_name', urgency='$urgency'\n", FILE_APPEND);

        if (empty($id) || empty($officer_id) || empty($zone_name) || empty($urgency)) {
            $toastr_messages[] = "toastr.error('ID, Officer, Zone Name, and Urgency are required.');";
        } else {
            $stmt = $pdo->prepare("UPDATE patrol_zones SET officer_id = ?, zone_name = ?, coordinates = ?, hotspots = ?, urgency = ?, assigned_date = ? WHERE id = ?");
            $params = [$officer_id, $zone_name, $coordinates, $hotspots, $urgency, $assigned_date, $id];
            file_put_contents('../debug.log', "Edit Patrol Zone Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Patrol zone updated successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to update patrol zone. No rows affected.');";
                file_put_contents('../debug.log', "Edit Patrol Zone Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error updating patrol zone: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Edit Patrol Zone Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Handle delete patrol zone assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patrol_zone'])) {
    try {
        $id = trim($_POST['id'] ?? '');
        // Log received input
        file_put_contents('../debug.log', "Delete Patrol Zone Input: id='$id'\n", FILE_APPEND);

        if (empty($id)) {
            $toastr_messages[] = "toastr.error('Patrol zone ID is required.');";
        } else {
            $stmt = $pdo->prepare("DELETE FROM patrol_zones WHERE id = ?");
            $params = [$id];
            file_put_contents('../debug.log', "Delete Patrol Zone Query Params: " . print_r($params, true) . "\n", FILE_APPEND);
            $success = $stmt->execute($params);
            if ($success) {
                $toastr_messages[] = "toastr.success('Patrol zone deleted successfully.');";
            } else {
                $toastr_messages[] = "toastr.error('Failed to delete patrol zone. No rows affected.');";
                file_put_contents('../debug.log', "Delete Patrol Zone Failed: No rows affected.\n", FILE_APPEND);
            }
        }
    } catch (PDOException $e) {
        $toastr_messages[] = "toastr.error('Error deleting patrol zone: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Delete Patrol Zone Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Fetch admin details
$admin_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching admin details: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Admin Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $admin = ['full_name' => 'Unknown', 'username' => 'Unknown'];
}

// Fetch all officers
try {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'officer' ORDER BY full_name");
    $stmt->execute();
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching officers: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Officers Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $officers = [];
}

// Fetch all patrol zone assignments
try {
    $stmt = $pdo->prepare("
        SELECT pz.id, pz.officer_id, pz.zone_name, pz.coordinates, pz.hotspots, pz.urgency, pz.assigned_date, u.full_name as officer_name 
        FROM patrol_zones pz 
        JOIN users u ON pz.officer_id = u.id 
        ORDER BY pz.assigned_date DESC
    ");
    $stmt->execute();
    $patrol_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching patrol zones: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Patrol Zones Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $patrol_zones = [];
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
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/manage_patrol_zones.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Manage Patrol Zones
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
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-2"></i>
                                Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../pages/manage_patrol_zones.php">
                                <i class="fas fa-map-marked-alt me-2"></i>
                                Manage Patrol Zones
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
                    <h1 class="h2 text-primary">Manage Patrol Zones - <?php echo htmlspecialchars($admin['full_name']); ?></h1>
                    <div>
                        <a href="../index.php" class="btn btn-outline-primary">Back to Home</a>
                    </div>
                </div>

                <!-- Patrol Zones Table -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Patrol Zone Assignments</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createPatrolZoneModal">Assign Patrol Zone</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Officer</th>
                                        <th>Zone Name</th>
                                        <th>Urgency</th>
                                        <th>Assigned Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($patrol_zones)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No patrol zones assigned</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($patrol_zones as $zone): ?>
                                            <tr class="table-row-hover">
                                                <td><?php echo htmlspecialchars($zone['id']); ?></td>
                                                <td><?php echo htmlspecialchars($zone['officer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($zone['zone_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $zone['urgency'] === 'High' ? 'bg-danger' : ($zone['urgency'] === 'Medium' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                        <?php echo htmlspecialchars($zone['urgency']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($zone['assigned_date']))); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editPatrolZoneModal<?php echo $zone['id']; ?>">Edit</button>
                                                    <form method="POST" style="display: inline;" class="delete-patrol-zone-form">
                                                        <input type="hidden" name="id" value="<?php echo $zone['id']; ?>">
                                                        <input type="hidden" name="delete_patrol_zone" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <!-- Edit Patrol Zone Modal -->
                                            <div class="modal fade" id="editPatrolZoneModal<?php echo $zone['id']; ?>" tabindex="-1" aria-labelledby="editPatrolZoneModalLabel<?php echo $zone['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editPatrolZoneModalLabel<?php echo $zone['id']; ?>">Edit Patrol Zone: <?php echo htmlspecialchars($zone['zone_name']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST" class="form-outline edit-patrol-zone-form">
                                                                <input type="hidden" name="id" value="<?php echo $zone['id']; ?>">
                                                                <input type="hidden" name="edit_patrol_zone" value="1">
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="officer_id_<?php echo $zone['id']; ?>" class="form-label">Officer</label>
                                                                        <select class="form-select" name="officer_id" id="officer_id_<?php echo $zone['id']; ?>" required>
                                                                            <option value="">Select Officer</option>
                                                                            <?php foreach ($officers as $officer): ?>
                                                                                <option value="<?php echo htmlspecialchars($officer['id']); ?>" <?php echo $officer['id'] == $zone['officer_id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($officer['full_name']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <div class="invalid-feedback">Please select an officer.</div>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="zone_name_<?php echo $zone['id']; ?>" class="form-label">Zone Name</label>
                                                                        <input type="text" class="form-control" name="zone_name" id="zone_name_<?php echo $zone['id']; ?>" required value="<?php echo htmlspecialchars($zone['zone_name']); ?>">
                                                                        <div class="invalid-feedback">Please enter a valid zone name.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="coordinates_<?php echo $zone['id']; ?>" class="form-label">Coordinates (JSON)</label>
                                                                        <textarea class="form-control" name="coordinates" id="coordinates_<?php echo $zone['id']; ?>" rows="4"><?php echo htmlspecialchars($zone['coordinates'] ?: ''); ?></textarea>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label for="hotspots_<?php echo $zone['id']; ?>" class="form-label">Hotspots (JSON)</label>
                                                                        <textarea class="form-control" name="hotspots" id="hotspots_<?php echo $zone['id']; ?>" rows="4"><?php echo htmlspecialchars($zone['hotspots'] ?: ''); ?></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="urgency_<?php echo $zone['id']; ?>" class="form-label">Urgency</label>
                                                                    <select class="form-select" name="urgency" id="urgency_<?php echo $zone['id']; ?>" required>
                                                                        <option value="Low" <?php echo $zone['urgency'] === 'Low' ? 'selected' : ''; ?>>Low</option>
                                                                        <option value="Medium" <?php echo $zone['urgency'] === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                                                        <option value="High" <?php echo $zone['urgency'] === 'High' ? 'selected' : ''; ?>>High</option>
                                                                    </select>
                                                                    <div class="invalid-feedback">Please select an urgency level.</div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label for="assigned_date_<?php echo $zone['id']; ?>" class="form-label">Assigned Date</label>
                                                                    <input type="datetime-local" class="form-control" name="assigned_date" id="assigned_date_<?php echo $zone['id']; ?>" value="<?php echo date('Y-m-d\TH:i', strtotime($zone['assigned_date'])); ?>">
                                                                </div>
                                                                <button type="submit" class="btn btn-primary">Update Patrol Zone</button>
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

                <!-- Create Patrol Zone Modal -->
                <div class="modal fade" id="createPatrolZoneModal" tabindex="-1" aria-labelledby="createPatrolZoneModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createPatrolZoneModalLabel">Assign New Patrol Zone</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" class="form-outline create-patrol-zone-form">
                                    <input type="hidden" name="create_patrol_zone" value="1">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="officer_id" class="form-label">Officer</label>
                                            <select class="form-select" name="officer_id" id="officer_id" required>
                                                <option value="">Select Officer</option>
                                                <?php foreach ($officers as $officer): ?>
                                                    <option value="<?php echo htmlspecialchars($officer['id']); ?>">
                                                        <?php echo htmlspecialchars($officer['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Please select an officer.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="zone_name" class="form-label">Zone Name</label>
                                            <input type="text" class="form-control" name="zone_name" id="zone_name" required>
                                            <div class="invalid-feedback">Please enter a valid zone name.</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="coordinates" class="form-label">Coordinates (JSON)</label>
                                            <textarea class="form-control" name="coordinates" id="coordinates" rows="4"></textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="hotspots" class="form-label">Hotspots (JSON)</label>
                                            <textarea class="form-control" name="hotspots" id="hotspots" rows="4"></textarea>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="urgency" class="form-label">Urgency</label>
                                        <select class="form-select" name="urgency" id="urgency" required>
                                            <option value="Low">Low</option>
                                            <option value="Medium">Medium</option>
                                            <option value="High">High</option>
                                        </select>
                                        <div class="invalid-feedback">Please select an urgency level.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="assigned_date" class="form-label">Assigned Date</label>
                                        <input type="datetime-local" class="form-control" name="assigned_date" id="assigned_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Assign Patrol Zone</button>
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

        // Client-side validation for Create Patrol Zone Form
        document.querySelector('.create-patrol-zone-form').addEventListener('submit', function(e) {
            console.log('Create patrol zone form submission attempted');
            const officerId = document.getElementById('officer_id').value;
            const zoneName = document.getElementById('zone_name').value.trim();
            const urgency = document.getElementById('urgency').value;

            let isValid = true;

            // Reset validation states
            document.getElementById('officer_id').classList.remove('is-invalid');
            document.getElementById('zone_name').classList.remove('is-invalid');
            document.getElementById('urgency').classList.remove('is-invalid');

            if (!officerId) {
                document.getElementById('officer_id').classList.add('is-invalid');
                isValid = false;
            }
            if (!zoneName) {
                document.getElementById('zone_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!urgency) {
                document.getElementById('urgency').classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Client-side validation failed for create patrol zone');
                e.preventDefault();
                return;
            }

            // SweetAlert2 confirmation
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to assign this patrol zone?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, assign it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Create patrol zone form submission confirmed');
                    this.submit();
                } else {
                    console.log('Create patrol zone form submission canceled');
                }
            });
        });

        // Client-side validation for Edit Patrol Zone Forms
        document.querySelectorAll('.edit-patrol-zone-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Edit patrol zone form submission attempted');
                const officerId = this.querySelector('select[name="officer_id"]').value;
                const zoneName = this.querySelector('input[name="zone_name"]').value.trim();
                const urgency = this.querySelector('select[name="urgency"]').value;

                let isValid = true;

                // Reset validation states
                this.querySelector('select[name="officer_id"]').classList.remove('is-invalid');
                this.querySelector('input[name="zone_name"]').classList.remove('is-invalid');
                this.querySelector('select[name="urgency"]').classList.remove('is-invalid');

                if (!officerId) {
                    this.querySelector('select[name="officer_id"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!zoneName) {
                    this.querySelector('input[name="zone_name"]').classList.add('is-invalid');
                    isValid = false;
                }
                if (!urgency) {
                    this.querySelector('select[name="urgency"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    console.log('Client-side validation failed for edit patrol zone');
                    e.preventDefault();
                    return;
                }

                // SweetAlert2 confirmation
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to update this patrol zone?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, update it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Edit patrol zone form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Edit patrol zone form submission canceled');
                    }
                });
            });
        });

        // Client-side validation and confirmation for Delete Patrol Zone Forms
        document.querySelectorAll('.delete-patrol-zone-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                console.log('Delete patrol zone form submission attempted');
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'Do you want to delete this patrol zone? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('Delete patrol zone form submission confirmed');
                        this.submit();
                    } else {
                        console.log('Delete patrol zone form submission canceled');
                    }
                });
            });
        });
    </script>
</body>
</html>