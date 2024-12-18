<?php
include_once 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<?php echo $header_dashboard->getHeaderDashboard() ?>
	<link href='https://fonts.googleapis.com/css?family=Antonio' rel='stylesheet'>

	<title>Dashboard</title>
</head>

<body>
	<script>
		// Function to check for new data
		function checkForNewData() {
			fetch('controller/upload_data.php', {
					method: 'GET', // Using GET to check for new data
					headers: {
						'Content-Type': 'application/json'
					}
				})
				.then(response => response.json())
				.then(data => {
					if (data.status === 'success') {
						const latestDataTime = data.latestDataTime;

						// If the data time has changed, refresh the page
						if (latestDataTime !== lastCheckedTime) {
							// Update the stored last checked time
							localStorage.setItem('lastCheckedTime', latestDataTime);
							location.reload(); // Refresh the page
						}
					}
				})
				.catch(error => console.log('Error fetching new data:', error));
		}

		// Poll for new data every 10 seconds (10000 milliseconds)
		setInterval(checkForNewData, 1000); // 10 seconds interval
	</script>
	<!-- Loader -->
	<div class="loader"></div>

	<!-- SIDEBAR -->
	<?php echo $sidebar->getSideBar(); ?> <!-- This will render the sidebar -->
	<!-- SIDEBAR -->



	<!-- CONTENT -->
	<section id="content">
		<!-- NAVBAR -->
		<nav>
			<i class='bx bx-menu'></i>
			<form action="#">
				<div class="form-input">
					<button type="submit" class="search-btn"><i class='bx bx-search'></i></button>
				</div>
			</form>
			<div class="username">
				<span>Hello, <label for=""><?php echo $user_fname ?></label></span>
			</div>
			<a href="profile" class="profile" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Profile">
				<img src="../../src/img/<?php echo $user_profile ?>">
			</a>
		</nav>
		<!-- NAVBAR -->

		<!-- MAIN -->
		<main>
			<div class="head-title">
				<div class="left">
					<h1>Dashboards</h1>
					<ul class="breadcrumb">
						<li>
							<a class="active" href="./">Home</a>
						</li>
						<li>|</li>
						<li>
							<a href="">Dashboards</a>
						</li>
					</ul>
				</div>
			</div>

			<div class="table-data">
				<div class="order">
					<div class="head">
						<h3><i class='bx bxs-camera'></i> Latest Image Captured</h3>
					</div>
					<p class="image-text">Alert Message : <?php echo $lastAlertMessage ?></p>
					<p class="image-text" >Date : <?php echo $lastDate ?></p>
					<div class="image">
						<img src="../../src/evidences/<?php echo $lastImageCaptured ?>" alt="Image">
					</div>
				</div>
			</div>

			<div class="table-data">
				<div class="order">
					<div id="chartContainer" style="height: 370px; width: 100%;"></div>
				</div>
			</div>

		</main>

		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<?php echo $footer_dashboard->getFooterDashboard() ?>
	<?php include_once '../../config/sweetalert.php'; ?>

	<script>
		//live search---------------------------------------------------------------------------------------//
		$(document).ready(function() {

			load_data(1);

			function load_data(page, query = '') {
				$.ajax({
					url: "tables/sensor-logs-table.php",
					method: "POST",
					data: {
						page: page,
						query: query
					},
					success: function(data) {
						$('#dynamic_content').html(data);
					}
				});
			}

			$(document).on('click', '.page-link', function() {
				var page = $(this).data('page_number');
				var query = $('#search_box').val();
				load_data(page, query);
			});

			$('#search_box').keyup(function() {
				var query = $('#search_box').val();
				load_data(1, query);
			});

		});
		window.onload = function() {

			var chart = new CanvasJS.Chart("chartContainer", {
				animationEnabled: true,
				theme: "light2", // You can change the theme or keep it
				title: {
					text: "Alert Messages Count"
				},
				backgroundColor: "#f9f9f9", // Set the chart's background color
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
	<script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>

</body>

</html>