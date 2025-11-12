<?php
/**
 * pages/testscan.php
 * Fixed: form field name + Windows path handling
 */

$baseDir   = dirname(__DIR__);                     // \Traffic-Violation-App
$uploadDir = $baseDir . '\\uploads\\';
$outputDir = $baseDir . '\\outputs\\';
$python    = 'python';                             // or 'C:\\Python311\\python.exe'
$script    = __DIR__ . '\\ocr_plate.py';

foreach ([$uploadDir, $outputDir] as $d) {
    if (!is_dir($d)) mkdir($d, 0777, true);
}

/* -------------------  UPLOAD FORM  ------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Plate OCR</title>
<style>
body{font-family:Segoe UI;background:#f5f7fa;padding:40px;}
.box{max-width:720px;margin:auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);}
input[type=file],button{margin:12px 0;padding:12px;font-size:16px;}
button{background:#e67e22;color:#fff;border:none;border-radius:6px;cursor:pointer;}
button:hover{background:#d35400;}
</style></head><body>
<div class="box"><h2>Upload Plate Image</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="img" accept="image/*" required><br>
    <button type="submit">Scan with OpenCV</button>
</form></div></body></html>
HTML;
    exit;
}

/* -------------------  PROCESS IMAGE  ------------------- */
if (!isset($_FILES['img']) || $_FILES['img']['error'] !== UPLOAD_ERR_OK) {
    die("<p style='color:red;'>Upload failed. Please try again.</p>");
}

$ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
$in  = $uploadDir . 'in_' . time() . '.' . $ext;
$out = $outputDir . 'out_' . time() . '.jpg';

if (!move_uploaded_file($_FILES['img']['tmp_name'], $in)) {
    die("<p style='color:red;'>Failed to save uploaded file.</p>");
}

/* -------------------  CALL PYTHON (Windows-safe paths) ------------------- */
$cmd = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg($python),
    escapeshellarg($script),
    escapeshellarg($in),
    escapeshellarg($out)
);

exec($cmd, $raw, $ret);
$json = implode("\n", $raw);
$res  = json_decode($json, true) ?: ['text' => 'Python error', 'plate_found' => false];

/* -------------------  RESULT  ------------------- */
$plate = htmlspecialchars($res['text'] ?? 'No text');
$found = $res['plate_found'] ?? false;

$debug = "<pre><strong>Command:</strong> " . htmlspecialchars($cmd) . "\n\n"
       . "<strong>Return code:</strong> $ret\n\n"
       . "<strong>Raw output:</strong>\n" . htmlspecialchars($json) . "</pre>";

echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Result</title>
<style>
body{font-family:Segoe UI;background:#f5f7fa;padding:40px;}
.box{max-width:800px;margin:auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);}
.plate{font-size:2.5em;font-weight:bold;color:#27ae60;}
img{max-width:100%;border:4px solid #27ae60;border-radius:10px;margin:20px 0;}
.debug{background:#f1f1f1;padding:15px;border-radius:8px;font-family:Consolas;margin-top:30px;font-size:0.9em;}
</style></head><body><div class="box">
<h2>OCR Result</h2>
<p><strong>Detected Plate:</strong> <span class="plate">$plate</span></p>
<p>Green rectangle = detected plate region</p>
<img src="../outputs/".basename($out)."?t=".time().">
<p><a href="">Scan another image</a></p>

<hr><h3>Debug (remove in production)</h3>
<div class="debug">$debug</div>
</div></body></html>
HTML;