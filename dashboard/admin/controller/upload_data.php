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
        // Read the latest data
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $currentTime = time();
            $dataAge = $currentTime - $data['timestamp'];

            $alertMessage = $dataAge > $this->timeoutDuration ? 'NO DATA' : ($data['AlertMessage'] ?? 'Unknown');
            $room = $dataAge > $this->timeoutDuration ? 'NO DATA' : ($data['Room'] ?? 'Unknown');

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

    public function fetchJsonData()
    {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $currentTime = time();
            $dataAge = $currentTime - $data['timestamp'];

            if ($dataAge > $this->timeoutDuration) {
                return [
                    'imageStatus' => 'NOT CAPTURED',
                    'AlertMessage' => 'NO DATA',
                    'Room' => 'NO DATA',
                ];
            }

            return $data; // Return latest JSON data
        }

        return [
            'imageStatus' => 'NOT CAPTURED',
            'AlertMessage' => 'NO DATA',
            'Room' => 'NO DATA',
        ];
    }
}

// Main Script
$sensorData = new SensorData();
$response = ['status' => 'error', 'message' => 'Something went wrong.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = '../../../src/evidences/';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Generate a unique file name
        $uniqueId = uniqid('', true);
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $uniqueId . '.' . $fileExtension;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            if ($sensorData->saveData(basename($uploadFile))) {
                $response = [
                    'status' => 'success',
                    'message' => 'Data saved successfully.',
                    'json_data' => $sensorData->fetchJsonData() // Fetch JSON and include in response
                ];
            } else {
                $response['message'] = 'Failed to save data to the database.';
            }
        } else {
            $response['message'] = 'Error uploading file.';
        }
    } else {
        $response['message'] = 'No file uploaded or upload error.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Serve the latest JSON data on a GET request
    $response = [
        'status' => 'success',
        'message' => 'Fetched latest JSON data.',
        'json_data' => $sensorData->fetchJsonData()
    ];
} else {
    $response['message'] = 'Invalid request method.';
}

// File to store the latest data
$dataFile = 'latest_data.json';
$timeoutDuration = 60; // 1 minute timeout duration

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receive data from ESP32 and save it to the file
    $data = file_get_contents('php://input');
    $dataArray = json_decode($data, true);
    $dataArray['timestamp'] = time(); // Add a timestamp
    file_put_contents($dataFile, json_encode($dataArray));
    echo 'Data received';
} else {
    // Serve the latest data
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        $currentTime = time();
        $dataAge = $currentTime - $data['timestamp'];
        
        if ($dataAge > $timeoutDuration) {
            echo json_encode([
                'imageStatus' => 'NOT CAPTURED',
                'AlertMessage' => 'NO DATA',
                'Room' => 'NO DATA',

            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode($data);
        }
    } else {
        echo json_encode([
            'imageStatus' => 'NOT CAPTURED',
            'AlertMessage' => 'NO DATA',
            'Room' => 'NO DATA',
        ]);
    }
}
?>
