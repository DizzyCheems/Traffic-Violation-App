<?php
/* --------------------------------------------------------------
   get_user_by_license.php
   Returns ONLY the plate_number (and other fields you may need later)
   from the **most recent** row in the `violations` table that matches
   the license number.
   -------------------------------------------------------------- */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- 1. DB CONNECTION ---------- */
$conn_path = __DIR__ . '/../config/conn.php';
if (!file_exists($conn_path)) {
    error_log('get_user_by_license.php – conn.php not found: ' . $conn_path);
    echo json_encode(['success'=>false,'message'=>'Server config error']);
    exit;
}
require_once $conn_path;

header('Content-Type: application/json');

/* ---------- 2. INPUT ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['license_number'])) {
    echo json_encode(['success'=>false,'message'=>'Missing license']);
    exit;
}

$lic = strtoupper(trim($_POST['license_number']));
$lic = preg_replace('/[^A-Z0-9]/', '', $lic);

if (strlen($lic) !== 12) {
    echo json_encode(['success'=>false,'message'=>'License must be 12 chars']);
    exit;
}

/* ---------- 3. QUERY (only violations table) ---------- */
try {
    $sql = "
        SELECT 
            plate_number,
            violator_name,
            email,
            has_license,
            user_id
        FROM violations
        WHERE REPLACE(license_number, '-', '') = ?
        ORDER BY issued_date DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lic]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('DB error in get_user_by_license.php: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'DB error']);
    exit;
}

/* ---------- 4. RETURN ---------- */
if ($row) {
    echo json_encode(['success'=>true, 'data'=>$row]);
} else {
    echo json_encode(['success'=>false, 'message'=>'No plate found']);
}
exit;