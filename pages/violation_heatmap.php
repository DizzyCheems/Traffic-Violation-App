<?php
session_start();
include '../config/conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Initialize toastr messages
$toastr_messages = [];

// Fetch violation locations
try {
    $stmt = $pdo->query("SELECT vl.latitude, vl.longitude, t.violation_type 
                         FROM violation_locations vl 
                         JOIN violations v ON vl.violation_id = v.id 
                         JOIN types t ON v.violation_type_id = t.id");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $toastr_messages[] = "toastr.error('Error fetching violation locations: " . addslashes(htmlspecialchars($e->getMessage())) . "');";
    file_put_contents('../debug.log', "Fetch Violation Locations Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $locations = [];
}

// Prepare data for heatmap
$heatmap_data = [];
foreach ($locations as $location) {
    $heatmap_data[] = [
        'lat' => (float)$location['latitude'],
        'lng' => (float)$location['longitude'],
        'value' => 1, // Weight for heatmap intensity
        'violation_type' => $location['violation_type']
    ];
}
$heatmap_data_json = json_encode($heatmap_data);
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
                    <h1 class="h2 text-primary">Violation Heatmap</h1>
                    <div>
                        <a href="../pages/admin_dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Violation Locations Heatmap</h3>
                    </div>
                    <div class="card-body">
                        <div id="map" style="height: 500px;"></div>
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

        // Initialize Leaflet map
        var map = L.map('map').setView([0, 0], 2); // Default center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Add heatmap layer
        var heatmapData = <?php echo $heatmap_data_json; ?>;
        var heat = L.heatLayer(
            heatmapData.map(point => [point.lat, point.lng, point.value]),
            { radius: 25, blur: 15, maxZoom: 17 }
        ).addTo(map);

        // Fit map to bounds if data exists
        if (heatmapData.length > 0) {
            var bounds = L.latLngBounds(heatmapData.map(point => [point.lat, point.lng]));
            map.fitBounds(bounds);
        }

        // Add markers with popups
        heatmapData.forEach(point => {
            L.marker([point.lat, point.lng])
                .addTo(map)
                .bindPopup(`Violation Type: ${point.violation_type}`);
        });
    </script>
</body>
</html>