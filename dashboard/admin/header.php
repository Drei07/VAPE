<?php
require_once 'authentication/admin-class.php';
include_once '../../config/settings-configuration.php';
include_once '../../config/header.php';
include_once '../../config/footer.php';
require_once 'sidebar.php';

$currentPage = basename($_SERVER['PHP_SELF'], ".php"); // Gets the current page name without the extension
$sidebar = new SideBar($config, $currentPage);

$config = new SystemConfig();
$header_dashboard = new HeaderDashboard($config);
$footer_dashboard = new FooterDashboard();
$user = new ADMIN();

if(!$user->isUserLoggedIn())
{
 $user->redirect('../../');
}

// retrieve user data
$stmt = $user->runQuery("SELECT * FROM users WHERE id=:uid");
$stmt->execute(array(":uid"=>$_SESSION['adminSession']));
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// retrieve profile user and full name
$user_id                = $user_data['id'];
$user_profile           = $user_data['profile'];
$user_fname             = $user_data['first_name'];
$user_mname             = $user_data['middle_name'];
$user_lname             = $user_data['last_name'];
$user_fullname          = $user_data['last_name'] . ", " . $user_data['first_name'];
$user_sex               = $user_data['sex'];
$user_birth_date        = $user_data['date_of_birth'];
$user_age               = $user_data['age'];
$user_civil_status      = $user_data['civil_status'];
$user_phone_number      = $user_data['phone_number'];
$user_email             = $user_data['email'];
$user_last_update       = $user_data['updated_at'];

$database = new Database();
$conn = $database->dbConnection();

// List of all possible alert messages
$alertMessages = [
    'Tampering Alert',
    'Fire Alert',
    'Detected: Smoke from burning objects',
    'Detected: Vape Smoke'
];

// SQL query to count specific alerts
$sql = "SELECT alert_message, COUNT(*) as alert_count 
        FROM sensorTable 
        WHERE alert_message IN ('" . implode("', '", $alertMessages) . "')
        GROUP BY alert_message";
$stmt = $conn->prepare($sql);
$stmt->execute();

// Prepare data for the chart
$dataPoints = array();

// Store the results in an associative array for easy access
$alertCounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $alertCounts[$row['alert_message']] = $row['alert_count'];
}

// Loop through all possible alert messages and assign counts
foreach ($alertMessages as $alertMessage) {
    // If the alert message exists in the database results, use its count
    // If not, set the count to 0
    $count = isset($alertCounts[$alertMessage]) ? $alertCounts[$alertMessage] : 0;
    $dataPoints[] = array("label" => $alertMessage, "y" => $count);
}

// SQL query to retrieve the last inserted data from the sensorTable
$sqlLastInsert = "SELECT * FROM sensorTable ORDER BY created_at DESC LIMIT 1"; // assuming 'created_at' column exists
$stmtLastInsert = $conn->prepare($sqlLastInsert);
$stmtLastInsert->execute();

// Fetch the last inserted record
$lastInsertedData = $stmtLastInsert->fetch(PDO::FETCH_ASSOC);

$lastImageCaptured = $lastInsertedData['image'];
$lastAlertMessage = $lastInsertedData['alert_message'];
$lastDate = date("F j, Y (h:i A)", strtotime($lastInsertedData['created_at']));




// Close the connection
$conn = null;
?>