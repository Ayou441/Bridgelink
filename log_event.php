<?php
include 'db.php';

function log_event($user_id, $action, $page = null) {
    global $conn;

    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare(
        "INSERT INTO logs (user_id, action, page, ip_address)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param("isss", $user_id, $action, $page, $ip);
    $stmt->execute();
}
?>
