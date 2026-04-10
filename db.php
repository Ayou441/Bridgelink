<?php
date_default_timezone_set('Europe/Paris');

$host = "sql110.infinityfree.com";
$user = "if0_41115032";
$pass = "yzQhanFybw";
$dbname = "if0_41115032_bridgelink";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Configurer le fuseau horaire MySQL aussi
$conn->query("SET time_zone = '+02:00'"); 
// +01:00 pour hiver et +02:00 pour l'heure d'été

?>
