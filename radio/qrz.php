<?php
$debug_start = time();
$debug = "Start: ".(time() - $debug_start)."\n";

	require ('../phpscripts/sqlfunctions.php');
	require ('functions.php');
	require ('../maps/gridrefutils.php');
	
	if (!$_POST['location_name']) $_POST['location_name'] = 'Home';

	
$debug .= "Start building page: ".(time() - $debug_start)."\n";

$output = "<!DOCTYPE html>
<html>
	<head>
		<title>Radio Database - Profile</title>
		<link rel='icon' href='favicon.ico' />
		<script src='OpenLayers.js'></script>
		<script src='RGraph/libraries/RGraph.common.core.js'></script>
		<script src='RGraph/libraries/RGraph.common.tooltips.js'></script>
		<script src='RGraph/libraries/RGraph.common.key.js'></script> 
		<script src='RGraph/libraries/RGraph.common.dynamic.js'></script>
		<script src='RGraph/libraries/RGraph.svg.common.core.js' ></script>
		<script src='RGraph/libraries/RGraph.svg.common.tooltips.js'></script>
		<script src='RGraph/libraries/RGraph.svg.line.js' ></script>
		<script src='RGraph/libraries/RGraph.drawing.yaxis.js'></script>
		<script src='RGraph/libraries/RGraph.line.js'></script>
		<script src='https://unpkg.com/leaflet/dist/leaflet.js'></script>
		<link rel='stylesheet' href='https://unpkg.com/leaflet/dist/leaflet.css' />
		
		<style type='text/css'>";
			// note background and foreground colours are set in css.php
			$darkmodeSetting = 1;
			ob_start();
				include ('css.php');
			$output .= ob_get_clean();
		$output .= "</style>
		<script>
			function sortSubmit (sortField){
					if (document.getElementById(\"sortField\").value == sortField && document.getElementById(\"sortOrder\").value != 'ASC') {
							document.getElementById(\"sortOrder\").value = 'ASC'
						} else {
							document.getElementById(\"sortOrder\").value = '';
						};
					document.getElementById(\"sortField\").value=sortField;
					document.searchForm.submit();
				};


			function getLocation() {
				const output = document.getElementById('locationOutput');
				// 1. Check if the browser supports Geolocation
				if (navigator.geolocation) {
					output.value = 'Locating...';
					// 2. Call getCurrentPosition, passing success and error callbacks
					navigator.geolocation.getCurrentPosition(showPosition, showError);
				} else {
					output.value = 'ERROR: Geolocation is not supported by this browser.';
				}
			}

			// 3. Success Callback: Extracts and prints the coordinates
			function showPosition(position) {
				const output = document.getElementById('locationOutput');
				const lat = Math.round(position.coords.latitude * 100000) / 100000;
				const lon = Math.round(position.coords.longitude * 100000) / 100000;
				output.value = lat + ',' + lon;
			}

			// 4. Error Callback: Handles user denials or technical issues
			function showError(error) {
				const output = document.getElementById('locationOutput');
				switch(error.code) {
					case error.PERMISSION_DENIED:
						output.value = 'ERROR: User denied the request for Geolocation.';
						break;
					case error.POSITION_UNAVAILABLE:
						output.value = 'ERROR: Location information is unavailable.';
						break;
					case error.TIMEOUT:
						output.value = 'ERROR: The request to get user location timed out.';
						break;
					case error.UNKNOWN_ERROR:
						output.value = 'ERROR: An unknown error occurred.';
						break;
				}
			}
		</script>
	</head>

	<body id='main'>
		<form method='post' action='./qrz.php' enctype='multipart/form-data' class='hidemeprint' name='searchForm'>
			<table>
				<tr>
					<th align='left'>Location Name</th>
					<th align='left'>Ant Height</th>
					<th align='left'>Custom Location</th>
					<th align='left'>Mode</th>
					<th align='left'>Band</th>
					<th align='left'>Frequency MHz</th>
					<th align='left'>QSO</th>
					<th align='left'>Grid</th>
				</tr>
				<tr>
					<td>
						<select name='location_name'>
							<option value=''></option>";
							$search_query = "SELECT DISTINCT `location_name` FROM `radio_locations` WHERE `location_name` != '' AND `location_name` IS NOT NULL ORDER BY `location_name`";
							$search_table = sqlfunct_query($search_query);
							while ($search_row = sqlfunct_fetch_array($search_table))
								$output .= "<option value='".$search_row['location_name']."'".($_POST['location_name'] == $search_row['location_name'] ? " selected": "").">".$search_row['location_name']."</option>";
							$output .= "<option value='custom'".($_POST['location_name'] == 'custom' ? ' selected' : '').">Custom</option>
						</select>
					</td>
					<td>
						<input type='text' name='antenna_height' size='2' value='".$_POST['antenna_height']."'>
					</td>
					<td>
						<input type='text' id='locationOutput' name='custom_location' size='12' value='".$_POST['custom_location']."'>
						<input type='button' value='Current' onclick='getLocation()' id='show_my_position_button'>
					</td>
					<td>
						<select name='mode'>
							<option value=''></option>
							<option value='A'".("A" == $_POST['mode'] ? " selected": "").">Analogue</option>
							<option value='D'".("D" == $_POST['mode'] ? " selected": "").">D-Star</option>
							<option value='M:1'".("M:1" == $_POST['mode'] ? " selected": "").">DMR:1 (BrandMeister)</option>
							<option value='M:3'".("M:3" == $_POST['mode'] ? " selected": "").">DMR:3</option>
							<option value='M:5'".("M:5" == $_POST['mode'] ? " selected": "").">DMR:5</option>
							<option value='M:10'".("M:10" == $_POST['mode'] ? " selected": "").">DMR:10</option>
							<option value='F'".("F" == $_POST['mode'] ? " selected": "").">Fusion</option>
							<option value='P'".("P" == $_POST['mode'] ? " selected": "").">P25</option>
							<option value='N'".("N" == $_POST['mode'] ? " selected": "").">NXDN</option>
							<option value='T'".("T" == $_POST['mode'] ? " selected": "").">TETRA</option>
							<option value='X'".("X" == $_POST['mode'] ? " selected": "").">WIRES-X / Multi-Mode</option>
							<option value='7'".("7" == $_POST['mode'] ? " selected": "").">M17</option>
							</select>
					</td>
					<td>
						<select name='band'>
							<option value=''></option>";
							$search_query = "SELECT distinct `band` FROM `radio_repeaters` ORDER BY alpha(band), CAST(num(band) AS INTEGER)";
							$search_table = sqlfunct_query($search_query);
							while ($search_row = sqlfunct_fetch_array($search_table))
								$output .= "<option value='".$search_row['band']."'".($_POST['band'] == $search_row['band'] ? " selected": "").">".ucwords(strtolower($search_row['band']))."</option>";
						$output .= "</select>
					</td>
					<td>
						<input type='text' size='5' name='frequency' value='".$_POST['frequency']."'>
					</td>
					<td>
						<input type='text' size='5' name='qso' value='".$_POST['qso']."'>
					</td>
					<td>
						<input type='text' size='5' name='grid' value='".$_POST['grid']."'>
					</td>
					<td>
						<input type='submit' name='mySubmit' value='Search'>
						<input type='hidden' name='postme' value='1'>
					</td>
				</tr>
			</table>
		</form>
		<hr />";
		
		if ($_POST['postme']){
				if ($_POST['location_name'] != 'custom'){
						$query = "SELECT 
									CONCAT(
										CHAR(65 + FLOOR((`longitude` + 180) / 20)),
										CHAR(65 + FLOOR((`latitude` + 90) / 10)),
										CHAR(48 + MOD(FLOOR((`longitude` + 180) / 2), 10)),
										CHAR(48 + MOD(FLOOR(`latitude` + 90), 10)),
										CHAR(97 + FLOOR(MOD(`longitude` + 180, 2) * 12)),
										CHAR(97 + FLOOR(MOD(`latitude` + 90, 1) * 24))
									) AS `locator`, 
									`latitude`, 
									`longitude`
									FROM `radio_locations` WHERE `location_name` = '".$_POST['location_name']."'";
						$table = sqlfunct_query($query);
						$row = sqlfunct_fetch_array($table);
						$location_qth = $row['locator'];
						$home_latitude = $row['latitude'];
						$home_longitude = $row['longitude'];
					} else if (preg_match('/^[0-9,\.-]+$/', $_POST['custom_location'])){
						list ($home_latitude,$home_longitude) = explode(',',$_POST['custom_location']);
						$location_qth = latLongToMaidenhead($home_latitude, $home_longitude,8);
					};

				$targetCallsign = $_POST['qso'];
				$operatorData = getCallsignData($targetCallsign);
				if ($operatorData && $operatorData['status'] === "VALID") {
						$callsign 		= $operatorData['current']['callsign'];
						$name 			= $operatorData['name'];
						$class			= $operatorData['current']['operClass'];
						$grid_square	= $operatorData['location']['gridsquare'];
					} else {
						$qrz = new QrzApi($qrz_username, $qrz_password);
							
						// Perform a lookup
						$result = $qrz->lookup($_POST['qso']);
							
						if ($result['status'] === 'success') {
								$operatorData 	= $result['data'];
								$callsign 		= $operatorData['call'];
								$name 			= $operatorData['fname'];
								$city			= $operatorData['addr2'];
								$state			= $operatorData['state'];
								$country		= $operatorData['country'];
								$grid_square	= $operatorData['grid'];
								$class			= $operatorData['class'];
								$lat			= $operatorData['lat'];
								$lon			= $operatorData['lon'];
							};
					};

				if (!$grid_square){
						$output .= "<div style='width: 80%; max-width: 80%; margin: 0 auto;'>
										<p>
											<a href='https://www.qrz.com/db/".$_POST['qso']."#t_detail' target='_blank' rel='noopener noreferrer'>
												Lookup CALLSIGN on QRZ.com
											</a>
										</p>
									</div>";
						$grid_square = $_POST['grid'];
					};

				if($grid_square){
						if (!$_POST['frequency']) $_POST['frequency'] = 1;
						if (!$lat or !$lon) {
								$coords = maidenheadToLatLon($grid_square);
								$row['latitude'] = $coords['lat'];
								$row['longitude'] = $coords['lon'];
							} else {
								$row['latitude'] = $lat;
								$row['longitude'] = $lon;
							};
						if (!$_POST['antenna_height']) $_POST['antenna_height'] = 0;
						$distance = haversine($home_latitude, $home_longitude, $row['latitude'], $row['longitude']);
						$bearing = getBearing($home_latitude, $home_longitude, $row['latitude'], $row['longitude']); 

						$locations_array [] = array($home_latitude,$home_longitude,'home');
						if ($coords['lat'] AND $coords['lon']) $locations_array [] = array($coords['lat'],$coords['lon'],$row['type']);

						$points = array(
										0 => array(
													'name' => ($_POST['location_name'] != 'custom' ? $_POST['location_name'] : $_POST['home_latitude'].",".$_POST['home_longitude']),
													'latitude' => $home_latitude,
													'longitude' => $home_longitude
												),
										1 => array(
													'name' => ($row['repeater'] ? $row['repeater'] : $row['latitude'].",".$row['longitude']),
													'latitude' => $row['latitude'],
													'longitude' => $row['longitude']
												)
									);
						
						$incriment = 100;

						$altitude_array = array();
						$altitude_array[] = array(
												'latitude' => $home_latitude,
												'longitude' => $home_longitude,
												'distance' => 0,
												'altitude' => get_alt (get_dtm_alt($home_latitude, $home_longitude, TRUE))
											);
						if ($distance > 0){
								for ($x = 1; $x < $incriment; $x++){
										$point = getDestinationPoint($home_latitude, $home_longitude, $bearing, ($distance / $incriment * $x));
										$altitude_array [] = array(
																'latitude' => $point['latitude'],
																'longitude' => $point['longitude'],
																'distance' => ($distance / $incriment * $x),
																'altitude' => get_alt (get_dtm_alt($point['latitude'], $point['longitude'], TRUE))
															);
									};
							};

						$start 					= max(0, (float)$altitude_array[0]['altitude'] + (float)$_POST['antenna_height']);
						$end 					= max(0,$altitude_array[@count($altitude_array) - 1]['altitude'] + $row['antennaHeight']);
						$delta 					= $start - $end;
						$incriment_delta 		= $delta / @count($altitude_array);
						$frensel_n 				= 0.6;
						$frensel_upper 			= "";
						$frensel_lower 			= "";
						$altitude 				= "";
						$earth_curve			= "";
						$xAxis = "";
						$yaxisScaleMin			= 0;
						$yaxisScaleMax			= 0;
						$half_way				= floor(@count($altitude_array) / 2);
						$drop_at_start 			= -1 * (0.0785 * pow(abs($half_way) * ($distance / $incriment),2));

						// Calculate altitudes
						for ($x = 0; $x < @count($altitude_array); $x++){
								if ($_POST['frequency'] > 140 and $distance < 100){
										$fresnel_radius = 547.6 *
															sqrt(
																	(
																		$frensel_n * 
																		($x * ($distance / $incriment)) * 
																		(($incriment - $x) * ($distance / $incriment))
																	)
																	/
																	(
																		$_POST['frequency'] * $distance
																	)
																);
										$frensel_current_upper 	= $altitude_array[0]['altitude'] + (float)($_POST['antenna_height']) - ($x * $incriment_delta) + $fresnel_radius;
										$frensel_current_lower 	= $altitude_array[0]['altitude'] + (float)($_POST['antenna_height']) - ($x * $incriment_delta) - $fresnel_radius;
									} else {
										$frensel_current_upper 	= 0;
										$frensel_current_lower 	= 0;
									};
								$earth_curve_add = abs($drop_at_start) + (-1 * (0.0785 * pow(abs($half_way - $x) * ($distance / $incriment),2)));
								$altitude_current 		= $altitude_array[0]['altitude'] + (float)($_POST['antenna_height']) - ($x * $incriment_delta);
								$terrain_current 		= $altitude_array[$x]['altitude'] + $earth_curve_add;
								
								$yaxisScaleMin = min($yaxisScaleMin, $frensel_current_upper, $frensel_current_lower, $altitude_current, $terrain_current, $earth_curve_add);
								$yaxisScaleMax = max($yaxisScaleMax, $frensel_current_upper, $frensel_current_lower, $altitude_current, $terrain_current, $earth_curve_add);

								$frensel_upper 	.= $frensel_current_upper.",";
								$frensel_lower 	.= $frensel_current_lower.",";
								$altitude 		.= $altitude_current.",";
								$earth_curve	.= $earth_curve_add.",";
								$terrain 		.= $terrain_current.",";
								
								if ($x % 10 == 0){
										$xAxis .= round($distance * $x / 100,1).",";
									} else {
										$xAxis .= "\"\",";
									};
							};

						$output .= "<table border='1' cellspacing='0' style='width: 80%; max-width: 80%; margin: 0 auto;'>
										<tr>
											<th>Callsign</th>
											<th>Name</th>
											<th>City</th>
											<th>State</th>
											<th>Country</th>
											<th>Grid Square</th>
											<th>Class</th>
											<th>Distance</th>
											<th>Direction</th>
										</tr>
										<tr>
											<td>".$operatorData['call']."</td>
											<td>".$operatorData['fname']."</td>
											<td>".$operatorData['addr2']."</td>
											<td>".$operatorData['state']."</td>
											<td>".$operatorData['country']."</td>
											<td>".$operatorData['grid']."</td>
											<td>".$operatorData['class']."</td>
											<td>".round($distance,1)." km</td>
											<td>".round($bearing,1)."&deg;</td>
										</tr>
									</table>
									<br />";
						$output .= $output2."<div id='osm-map-wrapper' style='width: 80%; max-width: 80%; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: white;'>
										<div id='chart-container' style='height: 500px; width: 100%;'></div>
									</div>
						
						<script>
							
							new RGraph.SVG.Line({
								id: 'chart-container',
								data: [
									[".substr($frensel_upper,0,-1)."],
									[".substr($frensel_lower,0,-1)."],
									[".substr($altitude,     0,-1)."],
									[".substr($earth_curve,  0,-1)."],
									[".substr($terrain,      0,-1)."]
								],
								options: {
									marginLeft: 45,
									marginRight: 20,
									backgroundColor: '#eee',
									backgroundGridColor: 'white',
									backgroundGridLinewidth: 2,
									spline: true,
									colors: ['blue','blue','red','grey','green'],
									tickmarksStyle: '', 
									tooltips: '%{value}',
									yaxisScaleUnitsPost: 'm',
									textSize: 10,
									backgroundGrid: true,
									yaxisScaleMin: ".$yaxisScaleMin.",
									yaxisScaleMax: ".$yaxisScaleMax.",
									xaxis: true,
									xaxisLabels: [".substr($xAxis,     0,-1)."],
									xaxisLabelsOffsety: 0,
									xaxisTitle: 'km',
									xaxisTickmarks: false,
									xaxisLinewidth: 1,
									xaxisColor: 'black',
									xaxisTickmarksCount: 10,
								}
						 
							// Use the trace() animation to show the chart
							}).trace();
							
						</script>
						
						<p>&nbsp;</p>
						
						<div id='osm-map-wrapper' style='width: 80%; max-width: 80%; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;'>
							<div id='osm-hub-map' style='height: 500px; width: 100%;'></div>
							<div id='osm-hub-info' style='padding: 15px; font-family: sans-serif; font-size: 16px; background: black;'>
								Calculating distances...
							</div>
						</div>";
						
						include('map.php');
					};
			};
		print $output;
	
	if (!$_POST['cli']) {
			$output = '';
			include('footer.php');
			$output .= "</body></html>";
			print $output;
		};
?>