<?php
session_start();
include '../config/conn.php';

// Debug: Log logout attempt
file_put_contents('../debug.log', "Logout.php accessed: user_id=" . ($_SESSION['user_id'] ?? 'unknown') . ", time=" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

try {
    if (isset($_SESSION['user_id'])) {
        $session_token = session_id();
        $stmt = $pdo->prepare("UPDATE sessions SET is_valid = 0 WHERE user_id = ? AND session_token = ?");
        $success = $stmt->execute([$_SESSION['user_id'], $session_token]);

        // Debug: Log session invalidation
        if ($success && $stmt->rowCount() > 0) {
            file_put_contents('../debug.log', "Session invalidated: user_id={$_SESSION['user_id']}, session_token=$session_token\n", FILE_APPEND);
        } else {
            file_put_contents('../debug.log', "No session invalidated: user_id={$_SESSION['user_id']}, session_token=$session_token\n", FILE_APPEND);
        }
    }
} catch (PDOException $e) {
    file_put_contents('../debug.log', "Logout error: " . $e->getMessage() . "\n", FILE_APPEND);
}

session_unset();
session_destroy();
header("Location: ../pages/login.php");
exit;
?>