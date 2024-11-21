<?php
include_once '../../../config/settings-configuration.php';
include_once __DIR__ . '/../../../database/dbconfig.php';
require_once '../authentication/admin-class.php';

class SensorData
{
    private $conn;
    private $admin;
    private $saveDataStatus = false; // Persistent status tracker
    private $timeoutDuration = 60; // 1-minute timeout duration
    private $dataFile = 'latest_data.json'; // File to store the latest data

    public function __construct()
    {
        $this->admin = new ADMIN();

        $database = new Database();
        $db = $database->dbConnection();
        $this->conn = $db;
    }

    public function runQuery($sql)
    {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }

    public function saveData($fileName)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Receive data from ESP32
            $data = file_get_contents('php://input');
            $dataArray = json_decode($data, true);
            $dataArray['timestamp'] = time(); // Add timestamp

            file_put_contents($this->dataFile, json_encode($dataArray));
        }

        // Read the latest data
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $currentTime = time();
            $dataAge = $currentTime - $data['timestamp'];

            $alertMessage = $dataAge > $this->timeoutDuration ? 'NO DATA' : $data['AlertMessage'] ?? 'Unknown';
            $room = $dataAge > $this->timeoutDuration ? 'NO DATA' : $data['Room'] ?? 'Unknown';

            if ($alertMessage === 'NO DATA' || $room === 'NO DATA') {
                $this->saveDataStatus = false;
                return false;
            }

            if (!$this->saveDataStatus) {
                try {
                    $stmt = $this->runQuery("INSERT INTO sensorData (image, alert_message, room) VALUES (:image, :alert_message, :room)");
                    $stmt->bindParam(':image', $fileName);
                    $stmt->bindParam(':alert_message', $alertMessage);
                    $stmt->bindParam(':room', $room);

                    if ($stmt->execute()) {
                        $this->saveDataStatus = true; // Prevent duplicate insertions
                        return true;
                    } else {
                        $errorInfo = $stmt->errorInfo();
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Database insertion failed.',
                            'error_info' => $errorInfo
                        ]);
                    }
                } catch (Exception $e) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Exception during query execution.',
                        'exception' => $e->getMessage()
                    ]);
                }
            }
        }

        return false;
    }
}

// Main Script
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sensorData = new SensorData();

    $uploadDir = '../../../src/evidences/';
    $response = ['status' => 'error', 'message' => 'Something went wrong.'];

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Generate unique file name
        $uniqueId = uniqid('', true);
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $uniqueId . '.' . $fileExtension;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            if ($sensorData->saveData(basename($uploadFile))) {
                $response = ['status' => 'success', 'message' => 'Data saved successfully.'];
            } else {
                $response['message'] = 'Failed to save data to the database.';
            }
        } else {
            $response['message'] = 'Error uploading file.';
        }
    } else {
        $response['message'] = 'No file uploaded or upload error.';
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    echo 'Invalid request method.';
}
