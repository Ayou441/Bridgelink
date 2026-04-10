<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $stmt = $conn->prepare(
            "INSERT INTO logs (user_id, action, page, ip_address)
             VALUES (?, ?, ?, ?)"
        );
        
        $page = 'client_event'; // Marquer comme événement client
        $stmt->bind_param("isss", $user_id, $action, $page, $ip);
        $stmt->execute();
    }
}
?>