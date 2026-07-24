<?php
$debug_start = time();
$debug = "Start: ".(time() - $debug_start)."\n";

	require ('../phpscripts/sqlfunctions.php');
	require ('functions.php');
	require ('../maps/gridrefutils.php');
	
	
	if ($_POST['id']){
			if (!$_POST['type']){
					$query = "SELECT 
									*,
											CASE 
												WHEN CHAR_LENGTH(`locator`) <= 6 THEN
													LCOSGTBENtoWSG84LL(ST_X(OSGB_TO_EASTNORTH(CONCAT(LEFT(`ngr`, 4), '5', RIGHT(`ngr`, 2), '5'))), ST_Y(OSGB_TO_EASTNORTH(CONCAT(LEFT(`ngr`, 4), '5', RIGHT(`ngr`, 2), '5'))), 1)
												ELSE
													(
														-90.0 
														+ (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 2, 1)) - 65) * 10.0
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 4 THEN  (ASCII(SUBSTRING(TRIM(`locator`), 4, 1)) - 48) * 1.0 ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 6 THEN  (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 6, 1)) - 65) * (1.0 / 24.0) ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 8 THEN  (ASCII(SUBSTRING(TRIM(`locator`), 8, 1)) - 48) * (1.0 / 240.0) ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 10 THEN (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 10, 1)) - 65) * (1.0 / 5760.0) ELSE 0 END
														+ CASE 
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 2  THEN 5.0
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 4  THEN 0.5
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 6  THEN (1.0 / 48.0)
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 8  THEN (1.0 / 480.0)
															WHEN CHAR_LENGTH(TRIM(`locator`)) >= 10 THEN (1.0 / 11520.0)
															ELSE 0 
														  END
													)
												END
											AS `latitude`,
											CASE 
												WHEN CHAR_LENGTH(`locator`) <= 6 THEN
													LCOSGTBENtoWSG84LL(ST_X(OSGB_TO_EASTNORTH(CONCAT(LEFT(`ngr`, 4), '5', RIGHT(`ngr`, 2), '5'))), ST_Y(OSGB_TO_EASTNORTH(CONCAT(LEFT(`ngr`, 4), '5', RIGHT(`ngr`, 2), '5'))), 0)
												ELSE
													(
														-180.0 
														+ (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 1, 1)) - 65) * 20.0
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 4 THEN  (ASCII(SUBSTRING(TRIM(`locator`), 3, 1)) - 48) * 2.0 ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 6 THEN  (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 5, 1)) - 65) * (1.0 / 12.0) ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 8 THEN  (ASCII(SUBSTRING(TRIM(`locator`), 7, 1)) - 48) * (1.0 / 120.0) ELSE 0 END
														+ CASE WHEN CHAR_LENGTH(TRIM(`locator`)) >= 10 THEN (ASCII(SUBSTRING(UPPER(TRIM(`locator`)), 9, 1)) - 65) * (1.0 / 2880.0) ELSE 0 END
														+ CASE 
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 2  THEN 10.0
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 4  THEN 1.0
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 6  THEN (1.0 / 24.0)
															WHEN CHAR_LENGTH(TRIM(`locator`)) = 8  THEN (1.0 / 240.0)
															WHEN CHAR_LENGTH(TRIM(`locator`)) >= 10 THEN (1.0 / 5760.0)
															ELSE 0 
														  END
													)
												END
											AS `longitude`
								FROM `radio_repeaters`
								WHERE `id` = ".$_POST['id'];
				} else if ($_POST['type'] == 'sota') {
					if (!$_POST['frequency']) $_POST['frequency'] = 1;
					$query = "SELECT 
									`Latitude` AS `latitude`,
									`Longitude` AS `longitude`,
									".($_POST['frequency'] * 1000 * 1000)." AS 'tx'
								FROM `radio_sota_summits`
								WHERE `SummitCode` = '".$_POST['id']."'";
				} else {
					if (!$_POST['frequency']) $_POST['frequency'] = 1;
				};
			$table = sqlfunct_query($query);
			$row = sqlfunct_fetch_array($table);

			if (strlen($row['locator']) < 6 and $row['ngr']){
					$grutoolbox = Grid_Ref_Utils::toolbox();
					$grid = $grutoolbox->get_UK_grid_nums( $row['ngr'], $grutoolbox->DATA_ARRAY);
					$gps_coords = $grutoolbox->grid_to_lat_long($grid,$grutoolbox->COORDS_GPS_UK);
					list($row['latitude'],$row['longitude']) = $gps_coords;
				};
		} else if ($_POST['destination_location']) {
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
					$location_table = sqlfunct_query($query);
					$location_row = sqlfunct_fetch_array($location_table);
					$location_qth = $location_row['qth'];
					$home_latitude = $location_row['latitude'];
					$home_longitude = $location_row['longitude'];
				} else if (preg_match('/^[0-9,\.-]+$/', str_replace(' ','',$_POST['custom_location']))){
					list ($home_latitude,$home_longitude) = explode(',',str_replace(' ','',$_POST['custom_location']));
					$qth = latLongToMaidenhead($home_latitude, $home_longitude,8);
				};

			list ($destination_latitude,$destination_longitude) = explode(',',$_POST['destination_location']);
			$row['locator'] = latLongToMaidenhead($destination_latitude, $destination_longitude,8);
			$row['latitude']  = $destination_latitude;
			$row['longitude'] = $destination_longitude;
			
			$_POST['home_latitude']  = $home_latitude;
			$_POST['home_longitude'] = $home_longitude;
			
			$row['tx'] = ($_POST['frequency'] * 1000 * 1000);
			$row['antennaHeight'] = ($_POST['destination_antenna_height'] ? $_POST['destination_antenna_height'] : 0);
		};
	
	if (!$_POST['antenna_height']) $_POST['antenna_height'] = 0;
	$distance = haversine($_POST['home_latitude'], $_POST['home_longitude'], $row['latitude'], $row['longitude']);
	$bearing = getBearing($_POST['home_latitude'], $_POST['home_longitude'], $row['latitude'], $row['longitude']); 
	
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
		<link rel='stylesheet' href='https://unpkg.com/leaflet/dist/leaflet.css' />
		<script src='https://unpkg.com/leaflet/dist/leaflet.js'></script>
		
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
		";
		if (!$_POST['id']){
				$output .= "<form method='post' action='profile.php'>
								<table border='1' cellspacing='0' style='margin: 0 auto;'>
									<tr>
										<th align='left'>Calling Location Name</th>
										<th align='left'>Calling Ant Height</th>
										<th align='left'>Custom Calling Location</th>
										<th align='left'>Destination Location</th>
										<th align='left'>Destination Ant Height</th>
										<th align='left'>Frequency MHz</th>
										<th align='left'>&nbsp;</th>
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
											<input type='text' name='destination_location' size='12' value='".$_POST['destination_location']."'>
										</td>
										<td>
											<input type='text' name='destination_antenna_height' size='2' value='".$_POST['destination_antenna_height']."'>
										</td>
										<td>
											<input type='text' size='6' name='frequency' value='".$_POST['frequency']."'>
										</td>
										<td>
											<input type='submit' name='mySubmit' value='Search'>
										</td>
									</tr>
								</table>
							</form>";
			};
$debug .= "Updating records: ".(time() - $debug_start)."\n";
		if (($home_latitude OR $_POST['home_latitude']) AND ($home_longitude OR $_POST['home_longitude']) AND $row['latitude'] AND $row['longitude']){
				$output .= "<hr />";
				$incriment = 100;

				$altitude_array = array();
				$altitude_array[] = array(
										'latitude' => $_POST['home_latitude'],
										'longitude' => $_POST['home_longitude'],
										'distance' => 0,
										'altitude' => get_alt (get_dtm_alt($_POST['home_latitude'], $_POST['home_longitude'], TRUE))
									);
				if ($distance > 0){
						for ($x = 1; $x < $incriment; $x++){
								$point = getDestinationPoint($_POST['home_latitude'], $_POST['home_longitude'], $bearing, ($distance / $incriment * $x));
								$altitude_array [] = array(
														'latitude' => $point['latitude'],
														'longitude' => $point['longitude'],
														'distance' => ($distance / $incriment * $x),
														'altitude' => get_alt (get_dtm_alt($point['latitude'], $point['longitude'], TRUE))
													);
							};
					};
				$altitude_array [] = array(
										'latitude' => $row['latitude'],
										'longitude' => $row['longitude'],
										'distance' => $distance,
										'altitude' => get_alt (get_dtm_alt($row['latitude'], $row['longitude']))
									);
				
				$output .= "<table border='1' cellspacing='0' style='margin: 0 auto;'>
						<tr>
							<th align='left'>From</th>
							<th align='left'>QTH</th>
							<th align='left'>Ant</th>
							<th align='left'>To</th>
							<th align='left'>QTH</th>
							<th align='left'>Ant</th>
							<th align='left'>Distance</th>
							<th align='left'>Bearing</th>
							<th align='left'>Start Alt</th>
							<th align='left'>End Alt</th>
							<th align='left'>TX</th>
							<th align='left'>RX</th>
							<th align='left'>CTCSS</th>
						</tr>
						<tr>
							<td>".($_POST['location_name'] != 'custom' ? $_POST['location_name'] : $_POST['home_latitude'].",".$_POST['home_longitude'])."</td>
							<td>".latLongToMaidenhead($_POST['home_latitude'],$_POST['home_longitude'], 10)."</td>
							<td>".round($_POST['antenna_height'],1)." m</td>
							<td>".($row['repeater'] ? $row['repeater'] : $row['latitude'].",".$row['longitude'])."</td>
							<td>".$row['locator']."</td>
							<td>".round($row['antennaHeight'],1)." m</td>
							<td>".round($distance,1)." km</td>
							<td>".round($bearing,1)."&deg;</td>
							<td>".round($altitude_array [0]['altitude'] + $_POST['antenna_height'],1)."m</td>
							<td>".round($altitude_array [@count($altitude_array) - 1]['altitude'] + $row['antennaHeight'],1)."m</td>
							<td>".number_format($row['tx'] / 1000000,4)." MHz</td>
							<td>".($row['rx'] && $row['tx'] != $row['rx'] ? number_format($row['rx'] / 1000000,4)." MHz" : "&nbsp;")."</td>
							<td>".($row['ctcss'] ? $row['ctcss']." Hz" : "&nbsp;")."</td>
						</tr>
						<tr>
							<td colspan='13'></d>
						</tr>
						<tr>
							<th align='left'>Type</th>
							<th align='left'>Status</th>
							<th align='left'>Keeper</th>
							<th align='left' colspan='2'>Town</th>
							<th align='left'>Modes</th>
							<th align='left'>Bandwidth</th>
							<th align='left'>Band</th>
							<th align='left'>dbw ERP</th>
							<th align='left'>Grid</th>
							<th align='left'>Polarisation</th>
							<th align='left'>Frensel Radius</th>
							<th align='left'>FSPL</th>
						</tr>
						<tr>
							<td>";
								switch ($row['type']){
										case 'AV' :
											$output .= "Analogue Voice";
											break;
										case 'DV' :
											$output .= "Digital Voice";
											break;
										case 'RL' :
											$output .= "Repeater Link";
											break;
										case 'DM' :
											$output .= "DMR";
											break;
										case 'DG' :
											$output .= "Digital Gateway";
											break;
										case 'AG' :
											$output .= "Analogue Gateway";
											break;
										case 'AP' :
											$output .= "APRS";
											break;
										case 'PX' :
											$output .= "AX25 /PACKET";
											break;
										case 'TV' :
											$output .= "TV Repeater";
											break;
										case 'PN' :
											$output .= "Packet Node";
											break;
										case 'BN' :
											$output .= "Beacon";
											break;
										case 'DD' :
											$output .= "Digital Data";
											break;
										case 'RN' :
											$output .= "ROSE Node";
											break;
										case 'SP' :
											$output .= "Simplex";
											break;
										default :
											$output .= "&nbsp;";
									};
							$output .= "</td>
							<td>".$row['status']."</td>
							<td>".$row['keeperCallsign']."</td>
							<td colspan='2'>".$row['town']."</td>
							<td>";
								$modes_list = explode(',',$row['modeCodes']);
								foreach ($modes_list as $mode){
										switch ($mode){
												case 'A':
													$output .= "<p style='margin: 0; padding: 0;'>Analogue</p>";
													break;
												case 'D':
													$output .= "<p style='margin: 0; padding: 0;'>D-Star</p>";
													break;
												case 'M:1':
													$output .= "<p style='margin: 0; padding: 0;'>DMR:1 (BrandMeister)</p>";
													break;
												case 'M:3':
													$output .= "<p style='margin: 0; padding: 0;'>DMR:3</p>";
													break;
												case 'M:5':
													$output .= "<p style='margin: 0; padding: 0;'>DMR:5</p>";
													break;
												case 'M:10':
													$output .= "<p style='margin: 0; padding: 0;'>DMR:10</p>";
													break;
												case 'F':
													$output .= "<p style='margin: 0; padding: 0;'>Fusion</p>";
													break;
												case 'P':
													$output .= "<p style='margin: 0; padding: 0;'>P25</p>";
													break;
												case 'N':
													$output .= "<p style='margin: 0; padding: 0;'>NXDN</p>";
													break;
												case 'T':
													$output .= "<p style='margin: 0; padding: 0;'>TETRA</p>";
													break;
												case 'X':
													$output .= "<p style='margin: 0; padding: 0;'>WIRES-X / Multi-Mode</p>";
													break;
												case '7':
													$output .= "<p style='margin: 0; padding: 0;'>M17</p>";
													break;
												default:
													$output .= "<p style='margin: 0; padding: 0;'>".$mode."</p>";
													break;
											};
									};
							$output .= "</td>
							<td>".($row['txbw'] ? $row['txbw']." kHz" : "&nbsp;")."</td>
							<td>".$row['band']."</td>
							<td>".($row['dbwErp'] ? $row['dbwErp']." db" : "&nbsp;")."</td>
							<td>".$row['ngr']."</td>
							<td>".$row['polarisation']."</td>
							<td>".round((8.656 * SQRT($distance / ($row['tx'] / 1000000000))),1)." m</td>
							<td>".round((20 * log10($distance)) + (20 * log10($row['tx'] / 1000000000)) + 92.45)." db</td>
						</tr>
					</table>
				<hr />";
				
				$points = array(
								0 => array(
											'name' => ($_POST['location_name'] != 'custom' ? $_POST['location_name'] : $_POST['home_latitude'].",".$_POST['home_longitude']),
											'latitude' => $_POST['home_latitude'],
											'longitude' => $_POST['home_longitude']
										),
								1 => array(
											'name' => ($row['repeater'] ? $row['repeater'] : $row['latitude'].",".$row['longitude']),
											'latitude' => $row['latitude'],
											'longitude' => $row['longitude']
										)
							);
		/*
					for ($x = 0; $x < @count($altitude_array); $x++){
							$points[] = array(
											'name' => $x,
											'latitude' => $altitude_array[$x]['latitude'],
											'longitude' => $altitude_array[$x]['longitude']
										);
						};
		*/
				
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

				// Calculate the lower Fresnel
				for ($x = 0; $x < @count($altitude_array); $x++){
						$fresnel_radius = 547.6 *
											sqrt(
													(
														$frensel_n * 
														($x * ($distance / $incriment)) * 
														(($incriment - $x) * ($distance / $incriment))
													)
													/
													(
														($row['tx'] / 1000000) * 
														$distance
													)
												);
						$earth_curve_add = abs($drop_at_start) + (-1 * (0.0785 * pow(abs($half_way - $x) * ($distance / $incriment),2)));
						$frensel_current_upper 	= $altitude_array[0]['altitude'] + (float)($_POST['antenna_height']) - ($x * $incriment_delta) + $fresnel_radius;
						$frensel_current_lower 	= $altitude_array[0]['altitude'] + (float)($_POST['antenna_height']) - ($x * $incriment_delta) - $fresnel_radius;
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

				$output .= "<div id='osm-map-wrapper' style='width: 80%; max-width: 80%; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: white;'>
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
				
				<p></p>
				
				<div id='osm-map-wrapper' style='width: 80%; max-width: 80%; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;'>
					<div id='osm-hub-map' style='height: 500px; width: 100%;'></div>
					<div id='osm-hub-info' style='padding: 15px; font-family: sans-serif; font-size: 16px; background: black;'>
						Calculating distances...
					</div>
				</div>";
				
				include('map.php');
			};


	if (!$_POST['cli']) print $output_head.$output_start.$output;

	$output = "</body></html>";
	if (!$_POST['cli']) {
			include('footer.php');
			print $output;
		};
?>