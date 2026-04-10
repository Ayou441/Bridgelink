<?php
include 'db.php';

$result = $conn->query("SELECT * FROM logs ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Tracking Logs</title>
<style>
table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial;
}
td, th {
    padding: 8px;
    border: 1px solid #ccc;
    text-align: left;
}
th {
    background: #f4f4f4;
}
</style>
</head>
<body>

<h2>📊 User Tracking Logs</h2>

<table>
<tr>
<th>ID</th>
<th>User ID</th>
<th>Action</th>
<th>Page</th>
<th>IP</th>
<th>Date</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['user_id'] ?></td>
<td><?= $row['action'] ?></td>
<td><?= $row['page'] ?></td>
<td><?= $row['ip_address'] ?></td>
<td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>

</table>

</body>
</html>
