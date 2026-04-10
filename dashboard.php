<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT identifiant FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>

<h2>Bienvenue <?php echo htmlspecialchars($user['identifiant']); ?> !</h2>

<p>Vous êtes connecté.</p>
<p><a href="logout.php">Déconnexion</a></p>

</body>
</html>
