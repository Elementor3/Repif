<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Manual Sender</title>

<style>
    body {
        font-family: Arial, sans-serif;
        background: #f7f7f7;
        padding: 20px;
    }
    .form-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 400px;
        margin: auto;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .form-box label {
        font-weight: bold;
        margin-top: 10px;
        display: block;
    }
    .form-box input {
        width: 100%;
        padding: 8px;
        margin-top: 4px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .form-box button {
        margin-top: 15px;
        width: 100%;
        padding: 10px;
        background: #0078ff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 15px;
    }
    .form-box button:hover {
        background: #005fcc;
    }
</style>

</head>
<body>

<div class="form-box">
    <h2>Manual Data Sender</h2>
    <form method="POST">
        <input type="hidden" name="from_form" value="1">

        <label>Station Serial:</label>
        <input type="text" name="station_serial" required>

        <label>Timestamp (YYYY-MM-DD HH:MM:SS):</label>
        <input type="text" id="timestamp" name="timestamp" required>

        <label>Temperature:</label>
        <input type="number" name="temperature" required>

        <label>Humidity:</label>
        <input type="number" name="humidity" required>

        <label>Pressure:</label>
        <input type="number" name="pressure" required>

        <label>Light:</label>
        <input type="number" name="light" required>

        <label>Gas:</label>
        <input type="number" name="gas" required>

        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>
<?php
    exit;
}
header('Content-Type: application/json');
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => mysqli_connect_error()]);
    exit;
}

$station = $_POST['station_serial'] ?? null;
$timestamp_raw = $_POST['timestamp'] ?? null;
$temperature = $_POST['temperature'] ?? null;
$humidity = $_POST['humidity'] ?? null;
$pressure = $_POST['pressure'] ?? null;
$light = $_POST['light'] ?? null;
$gas = $_POST['gas'] ?? null;

if (!$station || !$timestamp_raw) {
    http_response_code(400);
    echo json_encode(["error" => "Missing station_serial or timestamp"]);
    exit;
}
if (!str_contains($timestamp_raw, '.')) {
    $timestamp_raw .= '.000000';
}
$station_check = $conn->prepare("SELECT pk_stationID FROM station WHERE pk_stationID = ?");
$station_check->bind_param("s", $station);
$station_check->execute();
$station_exists = $station_check->get_result()->num_rows > 0;

if (!$station_exists) {
    http_response_code(400);
    echo json_encode([
        "error" => "Unknown station_serial"
    ]);
    exit;
}

$dt = DateTime::createFromFormat("Y-m-d H:i:s.u", $timestamp_raw);
if (!$dt) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid timestamp format"]);
    exit;
}
$timestamp = $dt->format("Y-m-d H:i:s");

$stmt = $conn->prepare("
    INSERT INTO measurement (timestamp, temperature, humidity, airPressure, lightIntensity, airQuality, fk_station)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("sddddds", $timestamp, $temperature, $humidity, $pressure, $light, $gas, $station);
$stmt->execute();

$measurement_id = $stmt->insert_id;

$slot_query = $conn->prepare("
    SELECT pk_slotID, fk_collection
    FROM slot
    WHERE fk_station = ?
      AND startDateTime <= ?
      AND endDateTime >= ?
");
$slot_query->bind_param("sss", $station, $timestamp, $timestamp);
$slot_query->execute();
$slots = $slot_query->get_result();

while ($slot = $slots->fetch_assoc()) {
    $collection_id = $slot['fk_collection'];

    $ins = $conn->prepare("
        INSERT INTO contains (pkfk_measurement, pkfk_collection)
        VALUES (?, ?)
    ");
    $ins->bind_param("ii", $measurement_id, $collection_id);
    $ins->execute();
}
if (isset($_POST['from_form'])) {
    header("Location: recieve_data.php");
    exit;
}
echo json_encode([
    "status" => "ok",
    "measurement_id" => $measurement_id,
]);
