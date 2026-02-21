<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();

$id = (int)$_GET['id'] ?? 0;

// Check if user has access to this collection (owner or shared)
$stmt = mysqli_prepare($conn,
    "SELECT 1 FROM collection c
     LEFT JOIN shares s ON c.pk_collectionID = s.pk_collection
     WHERE c.pk_collectionID = ? 
       AND (c.fk_user = ? OR s.pk_user = ?)
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "iss", $id, $_SESSION['username'], $_SESSION['username']);
mysqli_stmt_execute($stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
    echo '<tr><td colspan="6" class="text-danger">Access denied</td></tr>';
    exit;
}

$stmt = mysqli_prepare($conn,
    "SELECT m.timestamp, m.temperature, m.humidity, m.airPressure,
            m.lightIntensity, m.airQuality
     FROM measurement m
     JOIN contains c ON m.pk_measurementID = c.pkfk_measurement
     WHERE c.pkfk_collection = ?
     ORDER BY m.timestamp DESC"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo '<tr><td colspan="6" class="text-muted text-center">No measurements in this collection</td></tr>';
} else {
    while ($row = mysqli_fetch_assoc($result)) {
        ?>
        <tr>
            <td><?= formatDateTime($row['timestamp']) ?></td>
            <td><?= htmlspecialchars($row['temperature']) ?></td>
            <td><?= htmlspecialchars($row['humidity']) ?></td>
            <td><?= htmlspecialchars($row['airPressure']) ?></td>
            <td><?= htmlspecialchars($row['lightIntensity']) ?></td>
            <td><?= htmlspecialchars($row['airQuality']) ?></td>
        </tr>
        <?php
    }
}
?>