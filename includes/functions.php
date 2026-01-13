<?php
function log_activity($pdo, $user_id, $action, $details = null, $target_id = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, target_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $target_id]);
    } catch (PDOException $e) {
        // Silently fail logging to not disrupt user flow, or handle error appropriately
        // error_log("Logging failed: " . $e->getMessage());
    }
}
?>
