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


    public function saveData($fileName)
    {
        // Set the proxy server URL
        $proxyServerUrl = 'https://adutect.website/dashboard/admin/controller/fetch_data.php'; // Replace with your proxy server URL
        // Fetch data from the proxy server
        $response = file_get_contents($proxyServerUrl);
    
        // Check if the response was successful
        if ($response !== false) {
            // Decode the JSON response from the proxy server
            $data = json_decode($response, true);
    
            // Check if data is valid and contains required fields
            if (isset($data['alert_message']) && isset($data['room'])) {
                $alertMessage = $data['alert_message'];
                $room = $data['room'];
    
                // If data contains "NO DATA", do not proceed with the insertion
                if ($alertMessage === "NO DATA" || $room === "NO DATA") {
                    $this->saveDataStatus = false;
                    return false; // Skip insertion if no data
                }
    
                // Check if the data should be inserted into the database
                if (!$this->saveDataStatus) {
                    try {
                        // Prepare and execute the SQL query
                        $stmt = $this->runQuery("INSERT INTO sensorTable(image, alert_message, room) VALUES (:image, :alert_message, :room)");
                        $stmt->bindParam(':image', $fileName);
                        $stmt->bindParam(':alert_message', $alertMessage);
                        $stmt->bindParam(':room', $room);
    
                        // Execute the query and check if it was successful
                        if ($stmt->execute()) {
                            $this->saveDataStatus = true; // Set status to true after successful insertion
                            return true; // Successfully inserted
                        } else {
                            // Output error info if the query failed
                            $errorInfo = $stmt->errorInfo();
                            echo json_encode([
                                'status' => 'error',
                                'message' => 'Database insertion failed.',
                                'error_info' => $errorInfo
                            ]);
                            return false;
                        }
                    } catch (Exception $e) {
                        // Handle exception if any occurs during query execution
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Exception during query execution.',
                            'exception' => $e->getMessage()
                        ]);
                        return false;
                    }
                }
    
                return false; // Prevent multiple insertions if already done
            } else {
                // Handle the case when the response doesn't contain the expected data
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid data received from proxy server.'
                ]);
                return false;
            }
        } else {
            // Handle the case when the proxy server request fails
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch data from proxy server.'
            ]);
            return false;
        }
    }
    
    
}

// Main Script
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sensorData = new SensorData();

    // Define the upload directory
    $uploadDir = '../../../src/evidences/';
    $response = ['status' => 'error', 'message' => 'Something went wrong.'];

    if (isset($_FILES['file'])) {
        // Generate a unique file name
        $uniqueId = uniqid('', true);
        $fileExtension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . $uniqueId . '.' . $fileExtension;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {

                // Save data to the database
                if ($sensorData->saveData(basename($uploadFile))) {
                    $response = ['status' => 'success', 'message' => 'Data saved successfully.'];
                } else {
                    $response['message'] = 'Failed to save data to the database.';
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
