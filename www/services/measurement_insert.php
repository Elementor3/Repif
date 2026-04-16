<?php
function insertMeasurement(mysqli $conn, array $payload): array
{
    $station = trim((string)($payload['station_serial'] ?? $payload['serial'] ?? $payload['station'] ?? ''));
    $timestampRaw = trim((string)($payload['timestamp'] ?? ''));
    $temperature = isset($payload['temperature']) ? (float)$payload['temperature'] : null;
    $pressure = isset($payload['pressure']) ? (float)$payload['pressure'] : (isset($payload['airPressure']) ? (float)$payload['airPressure'] : null);
    $light = isset($payload['light']) ? (float)$payload['light'] : (isset($payload['lightIntensity']) ? (float)$payload['lightIntensity'] : null);
    $gas = isset($payload['gas']) ? (float)$payload['gas'] : (isset($payload['airQuality']) ? (float)$payload['airQuality'] : null);

    if ($station === '' || $timestampRaw === '') {
        return ['ok' => false, 'status' => 400, 'error' => 'Missing station_serial or timestamp'];
    }

    if (!str_contains($timestampRaw, '.')) {
        $timestampRaw .= '.000000';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $timestampRaw);
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestampRaw);
    }
    if (!$dt) {
        return ['ok' => false, 'status' => 400, 'error' => 'Invalid timestamp format'];
    }
    $timestamp = $dt->format('Y-m-d H:i:s');

    $stationCheck = $conn->prepare('SELECT pk_serialNumber FROM station WHERE pk_serialNumber = ?');
    if (!$stationCheck) {
        return ['ok' => false, 'status' => 500, 'error' => 'Prepare failed: station check'];
    }
    $stationCheck->bind_param('s', $station);
    $stationCheck->execute();
    $stationExists = $stationCheck->get_result()->num_rows > 0;
    $stationCheck->close();

    if (!$stationExists) {
        return ['ok' => false, 'status' => 400, 'error' => 'Unknown station_serial'];
    }

    $conn->begin_transaction();
    try {
        $insert = $conn->prepare(
            'INSERT INTO measurement (timestamp, temperature, airPressure, lightIntensity, airQuality, fk_station)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$insert) {
            throw new RuntimeException('Prepare failed: measurement insert');
        }

        $insert->bind_param('sdddds', $timestamp, $temperature, $pressure, $light, $gas, $station);
        if (!$insert->execute()) {
            throw new RuntimeException('Execute failed: measurement insert');
        }
        $measurementId = (int)$insert->insert_id;
        $insert->close();

        $slotQuery = $conn->prepare(
            'SELECT fk_collection
             FROM slot
             WHERE fk_station = ?
               AND startDateTime <= ?
               AND endDateTime >= ?'
        );
        if (!$slotQuery) {
            throw new RuntimeException('Prepare failed: slot query');
        }
        $slotQuery->bind_param('sss', $station, $timestamp, $timestamp);
        $slotQuery->execute();
        $slots = $slotQuery->get_result();

        $insContains = $conn->prepare(
            'INSERT IGNORE INTO contains (pkfk_measurement, pkfk_collection) VALUES (?, ?)'
        );
        if (!$insContains) {
            throw new RuntimeException('Prepare failed: contains insert');
        }

        while ($slot = $slots->fetch_assoc()) {
            $collectionId = (int)$slot['fk_collection'];
            $insContains->bind_param('ii', $measurementId, $collectionId);
            $insContains->execute();
        }

        $insContains->close();
        $slotQuery->close();

        $conn->commit();
        return ['ok' => true, 'status' => 200, 'measurement_id' => $measurementId];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'status' => 500, 'error' => $e->getMessage()];
    }
}