<?php
include_once '../../../config/settings-configuration.php';
include_once __DIR__ . '/../../../database/dbconfig.php';
require_once '../authentication/admin-class.php';

class SensorData
{
    private $conn;
    private $admin;
    private $saveDataStatus = false; // Persistent status tracker

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


    public function saveData($fileName, $alertMessage, $room)
    {
        // Reset status to false if invalid data
        if ($alertMessage === "NO DATA" || $room === "NO DATA") {
            $this->saveDataStatus = false;
            return false; // Skip insertion
        }

        // Check if the status is already true to prevent multiple insertions
        if (!$this->saveDataStatus) {
            try {
                // Perform the insert query
                $stmt = $this->runQuery("INSERT INTO sensorData (image, alert_message, room) VALUES (:image, :alert_message, :room)");
                $stmt->bindParam(':image', $fileName);
                $stmt->bindParam(':alert_message', $alertMessage);
                $stmt->bindParam(':room', $room);

                if ($stmt->execute()) {
                    $this->saveDataStatus = true; // Set status to true after successful insertion
                    return true;
                }
            } catch (Exception $e) {
                // Handle exception
                return false;
            }
        }

        return false; // Prevent multiple insertions
    }
}

// Main Script
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sensorData = new SensorData();

    // Define the upload directory
    $uploadDir = 'uploads/';
    $response = ['status' => 'error', 'message' => 'Something went wrong.'];

    if (isset($_FILES['file'])) {
        // Generate a unique file name
        $uniqueId = uniqid('', true);
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $uniqueId . '.' . $fileExtension;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            if ($alertMessage && $room) {
                // Save data to the database
                if ($sensorData->saveData(basename($uploadFile), $alertMessage, $room)) {
                    $response = ['status' => 'success', 'message' => 'Data saved successfully.'];
                } else {
                    $response['message'] = 'Failed to save data to the database.';
                }
            } else {
                $response['message'] = 'Invalid JSON data.';
            }
        } else {
            $response['message'] = 'Error uploading file.';
        }
    } else {
        $response['message'] = 'No file uploaded.';
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    echo 'Invalid request method.';
}
