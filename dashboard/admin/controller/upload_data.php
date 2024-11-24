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
        if ($alertMessage === "NO DATA" || $room === "NO DATA") {
            $this->saveDataStatus = false;
            return false; // Skip insertion
        }
    
        if (!$this->saveDataStatus) {
            try {
                $stmt = $this->runQuery("INSERT INTO sensorTable(image, alert_message, room) VALUES (:image, :alert_message, :room)");
                $stmt->bindParam(':image', $fileName);
                $stmt->bindParam(':alert_message', $alertMessage);
                $stmt->bindParam(':room', $room);
    
                if ($stmt->execute()) {
                    $this->saveDataStatus = true; // Set status to true after successful insertion
                    $this->sendEmailNotification($alertMessage, $room);
                    return true;
                } else {
                    // Debugging output for query failure
                    $errorInfo = $stmt->errorInfo();
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Database insertion failed.',
                        'error_info' => $errorInfo
                    ]);
                    return false;
                }
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Exception during query execution.',
                    'exception' => $e->getMessage()
                ]);
                return false;
            }
        }
    
        return false; // Prevent multiple insertions
    }

    public function sendEmailNotification($alertMessage, $room){
        // Assuming you have a method to get SMTP details
        $user = new ADMIN();
        $smtp_email = $user->smtpEmail();
        $smtp_password = $user->smtpPassword();
        $system_name = $user->systemName();
        
        // Retrieve user data
        $stmt = $user->runQuery("SELECT * FROM users WHERE id=:uid");
        $stmt->execute(array(":uid" => 1));
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
        $email = $user_data['email'];
        $subject = "Alert Message: $alertMessage";
    
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Alert Message</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f5f5f5;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    padding: 30px;
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                }
                h1 {
                    color: #333333;
                    font-size: 22px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                p {
                    color: #666666;
                    font-size: 16px;
                    line-height: 1.5;
                    margin-bottom: 20px;
                }
                .button {
                    display: block;
                    width: 200px;
                    margin: 20px auto;
                    padding: 12px 20px;
                    background-color: #0088cc;
                    color: #ffffff;
                    text-align: center;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 16px;
                    font-weight: bold;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 30px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>
                    <img src='cid:logo' alt='System Logo' width='150'>
                </div>
                <h1>Important Alert: $alertMessage</h1>
                <p>Hello, $email</p>
                <p>We have detected an alert in <strong>Room $room</strong>. Please take the necessary actions immediately.</p>
                <p>Details of the alert:</p>
                <ul>
                    <li><strong>Message:</strong> $alertMessage</li>
                    <li><strong>Room:</strong> $room</li>
                    <li><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</li>
                </ul>
                <p>If you believe this is a mistake, please contact the system administrator.</p>
                <a class='button' href='#'>View More Details</a>
            </div>
        </body>
        </html>
        ";
    
        // Send the email
        $user->send_mail($email, $message, $subject, $smtp_email, $smtp_password, $system_name);
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
            // Parse JSON data
            $jsonData = $_POST['json'] ?? '{}'; // Assuming JSON data is sent as 'json' in POST
            $data = json_decode($jsonData, true);

            $alertMessage = $data['AlertMessage'] ?? null;
            $room = $data['Room'] ?? null;

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
