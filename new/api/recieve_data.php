<?php
require_once '../config/database.php';

// Set response header
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get POST parameters
    $stationSerial = trim($_POST['station_serial'] ?? '');
    $timestamp = $_POST['timestamp'] ?? '';
    $temperature = $_POST['temperature'] ?? null;
    $humidity = $_POST['humidity'] ?? null;
    $pressure = $_POST['pressure'] ?? null;
    $light = $_POST['light'] ?? null;
    $gas = $_POST['gas'] ?? null;
    
    // Validate required fields
    if (empty($stationSerial) || empty($timestamp)) {
        $response['message'] = 'Missing required fields: station_serial and timestamp are required';
        echo json_encode($response);
        exit();
    }
    
    // Verify station exists
    $stmt = mysqli_prepare($conn, "SELECT pk_serialNumber FROM station WHERE pk_serialNumber = ?");
    mysqli_stmt_bind_param($stmt, "s", $stationSerial);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        $response['message'] = "Station with serial number '{$stationSerial}' not found in database";
        echo json_encode($response);
        exit();
    }
    
    // Validate and convert timestamp
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
    if (!$dt) {
        $response['message'] = 'Invalid timestamp format. Expected: YYYY-MM-DD HH:MM:SS';
        echo json_encode($response);
        exit();
    }
    
    // Convert numeric values or set to NULL
    $temperature = is_numeric($temperature) ? (float)$temperature : null;
    $humidity = is_numeric($humidity) ? (float)$humidity : null;
    $pressure = is_numeric($pressure) ? (float)$pressure : null;
    $light = is_numeric($light) ? (float)$light : null;
    $gas = is_numeric($gas) ? (float)$gas : null;
    
    // Insert measurement into database
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO measurement (timestamp, temperature, humidity, airPressure, lightIntensity, airQuality, fk_station) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sddddds", 
        $timestamp, $temperature, $humidity, $pressure, $light, $gas, $stationSerial
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $response['success'] = true;
        $response['message'] = 'Measurement data saved successfully';
        $response['measurement_id'] = mysqli_insert_id($conn);
    } else {
        $response['message'] = 'Database error: ' . mysqli_error($conn);
    }
    
    echo json_encode($response);
    exit();
}

// If not POST, show test form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Receiver - Test Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Station Data Receiver - Test Form</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>API Endpoint:</strong> <code><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code><br>
                            <strong>Method:</strong> POST<br>
                            <strong>Content-Type:</strong> application/x-www-form-urlencoded
                        </div>
                        
                        <form method="POST" action="" id="testForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="station_serial" class="form-label">Station Serial Number *</label>
                                    <input type="text" class="form-control" id="station_serial" name="station_serial" 
                                           value="ST001" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="timestamp" class="form-label">Timestamp *</label>
                                    <input type="text" class="form-control" id="timestamp" name="timestamp" 
                                           value="<?php echo date('Y-m-d H:i:s'); ?>" required>
                                    <small class="text-muted">Format: YYYY-MM-DD HH:MM:SS</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="temperature" class="form-label">Temperature (°C)</label>
                                    <input type="number" step="0.01" class="form-control" id="temperature" name="temperature" 
                                           value="22.5">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="humidity" class="form-label">Humidity (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="humidity" name="humidity" 
                                           value="65.0">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pressure" class="form-label">Air Pressure (hPa)</label>
                                    <input type="number" step="0.01" class="form-control" id="pressure" name="pressure" 
                                           value="1013.25">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="light" class="form-label">Light Intensity (lux)</label>
                                    <input type="number" step="0.01" class="form-control" id="light" name="light" 
                                           value="450.0">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gas" class="form-label">Air Quality (ppm)</label>
                                    <input type="number" step="0.01" class="form-control" id="gas" name="gas" 
                                           value="420.0">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Test Data</button>
                                <button type="button" class="btn btn-secondary" onclick="generateRandomData()">
                                    Generate Random Data
                                </button>
                            </div>
                        </form>
                        
                        <div id="response" class="mt-3"></div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">cURL Example</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3"><code>curl -X POST <?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?> \
  -d "station_serial=ST001" \
  -d "timestamp=<?php echo date('Y-m-d H:i:s'); ?>" \
  -d "temperature=22.5" \
  -d "humidity=65.0" \
  -d "pressure=1013.25" \
  -d "light=450.0" \
  -d "gas=420.0"</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle form submission with AJAX
        $('#testForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: '',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        $('#response').html(
                            '<div class="alert alert-success">' +
                            '<strong>Success!</strong> ' + data.message +
                            (data.measurement_id ? '<br>Measurement ID: ' + data.measurement_id : '') +
                            '</div>'
                        );
                    } else {
                        $('#response').html(
                            '<div class="alert alert-danger">' +
                            '<strong>Error!</strong> ' + data.message +
                            '</div>'
                        );
                    }
                },
                error: function(xhr) {
                    $('#response').html(
                        '<div class="alert alert-danger">' +
                        '<strong>Request Failed!</strong> ' + xhr.statusText +
                        '</div>'
                    );
                }
            });
        });
        
        // Generate random data
        function generateRandomData() {
            $('#temperature').val((Math.random() * 15 + 15).toFixed(2));
            $('#humidity').val((Math.random() * 40 + 40).toFixed(2));
            $('#pressure').val((Math.random() * 30 + 1000).toFixed(2));
            $('#light').val((Math.random() * 1000 + 100).toFixed(2));
            $('#gas').val((Math.random() * 200 + 300).toFixed(2));
            $('#timestamp').val(new Date().toISOString().slice(0, 19).replace('T', ' '));
        }
    </script>
</body>
</html>