<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/measurements.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $station = $_GET['station'] ?? '';
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    
    if (empty($station)) {
        echo 'ERROR|Station required';
        exit;
    }
    
    // Verify station ownership
    $stmt = mysqli_prepare($conn,
        "SELECT 1 FROM station WHERE pk_serialNumber = ? AND fk_registeredBy = ?"
    );
    mysqli_stmt_bind_param($stmt, "ss", $station, $_SESSION['username']);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
        echo 'ERROR|Access denied';
        exit;
    }
    
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT * FROM measurement WHERE fk_station = ?";
    $params = [$station];
    $types = "s";
    
    if (!empty($start)) {
        $query .= " AND timestamp >= ?";
        $params[] = $start;
        $types .= "s";
    }
    
    if (!empty($end)) {
        $query .= " AND timestamp <= ?";
        $params[] = $end;
        $types .= "s";
    }
    
    // Get total count
    $countQuery = str_replace("*", "COUNT(*) as cnt", $query);
    $stmt = mysqli_prepare($conn, $countQuery);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $countRes = mysqli_stmt_get_result($stmt);
    $total = mysqli_fetch_assoc($countRes)['cnt'];
    
    // Get data
    $query .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    ob_start();
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
    $html = ob_get_clean();
    
    echo json_encode([
        'html' => $html,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
    exit;
}
?>