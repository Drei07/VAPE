<?php
// Include the Database class
include_once '../../database/dbconfig.php';

// Create a new Database object and establish a connection
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

// Close the connection
$conn = null;
?>

<!DOCTYPE HTML>
<html>
<head>  
<script>
window.onload = function () {
 
var chart = new CanvasJS.Chart("chartContainer", {
	animationEnabled: true,
	theme: "light2",
	title: {
		text: "Alert Messages Count"
	},
	axisY: {
		title: "Number of Alerts"
	},
	data: [{
		type: "column",
		yValueFormatString: "#,##0",
		indexLabel: "{y}",
		indexLabelPlacement: "inside",
		indexLabelFontColor: "white",
		dataPoints: <?php echo json_encode($dataPoints, JSON_NUMERIC_CHECK); ?>
	}]
});
chart.render();
 
}
</script>
</head>
<body>
<div id="chartContainer" style="height: 370px; width: 100%;"></div>
<script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>
</body>
</html>
