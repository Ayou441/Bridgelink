<?php
session_start();
include 'log_event.php';

$user_id = $_SESSION['user_id'] ?? 0;

log_event($user_id, "Visited Page", basename($_SERVER['PHP_SELF']));
?>
