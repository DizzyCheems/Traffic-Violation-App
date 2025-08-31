<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Handle backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        // Simulate backup process (replace with actual mysqldump or equivalent in production)
        $backup_file = 'backup_' . date('Ymd_His') . '.sql';
        file_put_contents('../debug.log', "Database Backup Initiated: $backup_file\n", FILE_APPEND);
        // Example: exec("mysqldump --user=your_user --password=your_pass your_db > ../backups/$backup_file");
        $toastr_messages[] = "toastr.success('Database backup initiated successfully: $backup_file');";
    } catch (Exception $e) {
        $toastr_messages[] = "toastr.error('Error initiating backup: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
        file_put_contents('../debug.log', "Database Backup Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
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
                    <h1 class="h2 text-primary">Database Backup</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Create Database Backup</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form-outline" id="backupForm">
                            <input type="hidden" name="create_backup" value="1">
                            <p class="text-muted">Click the button below to initiate a database backup. The backup file will be saved on the server.</p>
                            <button type="submit" class="btn btn-primary">Create Backup</button>
                        </form>
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

        document.getElementById('backupForm').addEventListener('submit', function(e) {
            console.log('Backup form submission attempted');
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to create a database backup?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, create it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Backup form submission confirmed');
                    this.submit();
                } else {
                    console.log('Backup form submission canceled');
                }
            });
        });
    </script>
</body>
</html>