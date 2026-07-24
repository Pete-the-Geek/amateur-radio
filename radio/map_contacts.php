<?php
$debug_start = time();
$debug = "Start: ".(time() - $debug_start)."\n";

	require ('../phpscripts/sqlfunctions.php');
	require ('functions.php');
	require ('../maps/gridrefutils.php');
	
$output = "<!DOCTYPE html>
<html>
	<head>
		<title>Radio Database - Map Contacts</title>
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
		<div style='width: 80%; max-width: 80%; margin: auto; text-align: center;'>
			<form method='post' action='map_contacts.php' enctype='multipart/form-data''>
				Contact file to upload <input type='file' name='contacts' accept='.csv' />
				<input type='submit' value='Upload'>
			</form>
			<br />
			station_callsign (M), call (M), station_gridsquare (O), summit (O), date (O), time (O), frequency (O), gridsquare (O), name (O), qth (O), notes (O)
		</div>
		<hr /> 
		";
		if ($_FILES['contacts']){
				$locations_array = array();

				$csvFile = fopen($_FILES['contacts']['tmp_name'], 'r');
				$data = [];

				// Read the first line to get the header row
				$headers = fgetcsv($csvFile);

				if ($headers !== false) {
						// Clean whitespace from headers once
						$headers = array_map('trim', $headers);
						$headerCount = count($headers);

						$record_number = 0;
						while (($row = fgetcsv($csvFile)) !== false) {
								
								// Safety check: array_combine will fail if the row has more or fewer columns than the header.
								// This is a common issue when dealing with manually transcribed log sheets.
								
								// Get the current row count for comparison
								$currentRowCount = count($row);
								
								if ($currentRowCount !== $headerCount) {
										if ($currentRowCount < $headerCount) {
												// Row is too short: pad it to the right with empty strings (or null)
												$row = array_pad($row, $headerCount, '');
											} else {
												// Row is too long: truncate the extra columns
												$row = array_slice($row, 0, $headerCount);
											}
									}

								// Merge headers and row data into a single associative array
								$rowData = array_combine($headers, $row);
								// Now extraction is incredibly clean
								$station_callsign	= $rowData['station_callsign'] ?? null;
								$station_grid		= $rowData['station_gridsquare'] ?? null;
								$caller				= $rowData['call'] ?? null;
								$caller_grid		= $rowData['gridsquare'] ?? null;
								$caller_name		= $rowData['name'] ?? null;
								$caller_qth			= $rowData['qth'] ?? null;
								$summit       		= $rowData['summit'] ?? null;
								$date       		= $rowData['date'] ?? null;
								$time       		= $rowData['time'] ?? null;
								$notes       		= $rowData['notes'] ?? null;
								$frequency       	= $rowData['frequency'] ?? null;
								$operator       	= $rowData['operator'] ?? null;

								$data[$record_number] = array(
										'csv_station_callsign' 	=> $station_callsign,
										'csv_station_grid'     	=> $station_grid,
										'csv_caller'           	=> $caller,
										'csv_caller_grid'      	=> $caller_grid,
										'csv_caller_name'      	=> $caller_name,
										'csv_caller_qth'       	=> $caller_qth,
										'csv_summit'			=> $summit,
										'csv_notes'				=> $notes,
										'csv_frequency'			=> $frequency,
										'csv_date'				=> $date,
										'csv_time'				=> $time,
										'csv_operator'			=> $operator
									);

								if (!$points){
										// If the base station is not yet recorded
										if (!$summit) {
												// If the SOTA summit code is not given use the grid location in the file
												$station_lat_long = maidenheadToLatLon($station_grid);
											} else{
												// If the SOTA summit is given, look up the location from SOTA
												$summit_query = "SELECT * FROM `radio_sota_summits` WHERE `SummitCode` = '".$summit."'";
												$summit_table = sqlfunct_query($summit_query);
												$summit_row   = sqlfunct_fetch_array($summit_table);
												$station_lat_long = array(
														'lat' => $summit_row['Latitude'],
														'lon' => $summit_row['Longitude']
													);
												$station_grid = latLongToMaidenhead($summit_row['Latitude'], $summit_row['Longitude'], 6);
												$data[$record_number]['csv_station_grid'] = $station_grid;
											};
										// Add the base station to the list
										$points = array(
														array(
																'name' => $station_callsign." ".$station_grid,
																'latitude' => $station_lat_long['lat'],
																'longitude' => $station_lat_long['lon']
															)
													);
										$locations_array[] = array($lat_long['lat'],$lat_long['lon'],'home');
									};
								
								$data[$record_number]['station_lat_lon'] = $station_lat_long;
								if (!$data[$record_number]['csv_station_grid']) $data[$record_number]['csv_station_grid'] = $data[0]['csv_station_grid'];
								if (!$data[$record_number]['csv_summit']) $data[$record_number]['csv_summit'] = $data[0]['csv_summit'];
								
								if ($caller_grid and $caller_grid != ' '){
									// It the caller grid location is given
										$lat_long = maidenheadToLatLon($caller_grid);
										$locations_array[] = array($lat_long['lat'],$lat_long['lon'],'caller');
										$data[$record_number]['caller_lat_lon'] = $lat_long;
									} else {
										$qrz = new QrzApi($qrz_username, $qrz_password);
										// Perform a lookup
										$result = $qrz->lookup($caller);

										if ($result['status'] === 'success') {
												$operatorData 		= $result['data'];
												$callsign 			= $operatorData['call'];
												$name 				= $operatorData['name_fmt'];
												$city				= $operatorData['addr2'];
												$state				= $operatorData['state'];
												$country			= $operatorData['country'];
												$grid_square		= $operatorData['grid'];
												$class				= $operatorData['class'];
												$lat_long['lat']	= $operatorData['lat'];
												$lat_long['lon']	= $operatorData['lon'];
												$data[$record_number]['caller_lat_lon'] 	= $lat_long;
												$data[$record_number]['qrz_city']			= $city;
												$data[$record_number]['qrz_state']			= $state;
												$data[$record_number]['qrz_country']		= $country;
												$data[$record_number]['qrz_grid_square']	= $grid_square;
												$data[$record_number]['qrz_class']			= $class;
												$data[$record_number]['name']				= $name;
											} else {
												$name = "Unknown";
												$lat_long['lat'] = NULL;
												$lat_long['lon'] = NULL;
												$data[$record_number]['caller_lat_lon'] = $lat_long;
												$data[$record_number]['name']			= $name;
											};
										$locations_array[] = array($lat_long['lat'],$lat_long['lon'],'caller');
									};
								$namefield = "<b>".$caller."</b>";
								if ($name){
										$namefield .= "<br />".$name;
									} else if ($caller_name and $caller_name != ' ') {
										$namefield .= "<br />".$caller_name;
									};
								if ($caller_qth and $caller_qth != ' ') {
										$namefield .= "<br />".$caller_qth;
									} else {
										$namefield .= "<br />".($city ? $city.", " : "").($state ? $state.", " : "").($country ? $country : "");
									};
								if ($notes) $namefield .= "<br />".$notes;
								$namefield .= "<br />";
								if ($lat_long['lat'] and $lat_long['lon']){
										$points[] = array(
														'name' => str_replace('"',"'",$namefield),
														'latitude' => $lat_long['lat'],
														'longitude' => $lat_long['lon'],
														'form_id' => 'form_'.$caller
													);
										$data[$record_number]['distance'] = haversine($data[0]['station_lat_lon']['lat'], $data[0]['station_lat_lon']['lon'], $data[$record_number]['caller_lat_lon']['lat'], $data[$record_number]['caller_lat_lon']['lon']);
									};
								$record_number++;
							}
					}

				fclose($csvFile);
				
				$output .= "<div style='width: 80%; max-width: 80%; margin: auto;'>
								<table border='1' cellspacing='0'>
									<thead>
										<tr>
											<th>Station Callsign</th>
											<th>Station Grid</th>
											<th>Station Coord</th>
											<th>Summit</th>
											<th>Date</th>
											<th>Time</th>
											<th>Frequency MHz</th>
											<th>Operator</th>
											<th>Caller</th>
											<th>Caller Grid</th>
											<th>Caller Coord</th>
											<th>Distance km (miles)</th>
											<th>Name</th>
											<th>QTH</th>
											<th>Caller Home Grid</th>
											<th>Caller Home Address</th>
											<th>Notes</th>
											<th>Profile</th>
										</tr>
									</thead>
									<tbody>";
										for ($x = 0; $x < count($data); $x++){
												$output .= "<tr>
																<td>".$data[$x]['csv_station_callsign']."</td>
																<td>".$data[$x]['csv_station_grid']."</td>
																<td>".$data[$x]['station_lat_lon']['lat'].", ".$data[$x]['station_lat_lon']['lon']."</td>
																<td>".$data[$x]['csv_summit']."</td>
																<td>".$data[$x]['csv_date']."</td>
																<td>".$data[$x]['csv_time']."</td>
																<td>".number_format($data[$x]['csv_frequency'],3)."</td>
																<td>".$data[$x]['csv_operator']."</td>
																<td>".$data[$x]['csv_caller']."</td>
																<td>".$data[$x]['csv_caller_grid']."</td>
																<td>".($data[$x]['caller_lat_lon']['lat'] & $data[$x]['caller_lat_lon']['lon'] ? $data[$x]['caller_lat_lon']['lat'].", ".$data[$x]['caller_lat_lon']['lon'] : "&nbsp;")."</td>
																<td>".($data[$x]['distance'] ? round($data[$x]['distance'],1)."<br />(".round($data[$x]['distance'] * 0.621371,1).")" : "&nbsp;")."</td>
																<td>".ucwords(strtolower($data[$x]['csv_caller_name'] ? $data[$x]['csv_caller_name'] : $data[$x]['name']))."</td>
																<td>".$data[$x]['csv_caller_qth']."</td>
																<td>".$data[$x]['qrz_grid_square']."</td>
																<td>".($data[$x]['qrz_city'] ? $data[$x]['qrz_city'].", " : "").($data[$x]['qrz_state'] ? $data[$x]['qrz_state'].", " : "").$data[$x]['qrz_country']."</td>
																<td>".$data[$x]['csv_notes']."</td>
																<td>
																	<form method='post' action='profile.php' target='_blank' id='form_".$data[$x]['csv_caller']."'>
																		<input type='hidden' name='destination_location' value='".$data[$x]['caller_lat_lon']['lat'].",".$data[$x]['caller_lat_lon']['lon']."'>
																		<input type='hidden' name='custom_location' value='".$data[$x]['station_lat_lon']['lat'].",".$data[$x]['station_lat_lon']['lon']."'>
																		<input type='hidden' name='antenna_height' value='0'>
																		<input type='hidden' name='type' value='random'>
																		<input type='hidden' name='frequency' value='".$data[$x]['csv_frequency']."'>
																		<input type='hidden' name='location_name' value='custom'>
																		<input type='image' src='img/profile.jpg' alt='Submit' width='50' height='18'>
																	</form>
																</td>
															</tr>";
											};
				$output .= "		</tbody>
								</table>
								<br />
							</div>";
				
				$locations_js = '';
				for ($x = 0; $x < count($locations_array); $x++){
						switch ($locations_array[$x][2]){
								case 'home':
									$icon = 'marker.png';
									break;
								case 'repeater':
									$icon = 'marker-blue.png';
									break;
								case 'gateway':
									$icon = 'marker-gold.png';
									break;
								DEFAULT:
									$icon = 'marker-green.png';
							};
						$locations_js .= "var lat".$x." = ".$locations_array[$x][0].";
											var lon".$x." = ".$locations_array[$x][1].";
											var position".$x." = new OpenLayers.LonLat(lon".$x." , lat".$x." ).transform( fromProjection, toProjection);
											var label = new OpenLayers.LonLat(1,1);
											var icon = new OpenLayers.Icon('img/".$icon."');
											markers.addMarker(new OpenLayers.Marker(position".$x.", icon));
											";
					};
				$min_lat  = min(array_column($locations_array, 0));
				$max_lat  = max(array_column($locations_array, 0));
				$mean_lat = $min_lat + (($max_lat - $min_lat) / 2);
				$min_lon  = min(array_column($locations_array, 1));
				$max_lon  = max(array_column($locations_array, 1));
				$mean_lon = $min_lon + (($max_lon - $min_lon) / 2);
				$delta_lat = $max_lat - $min_lat + 0.01;
				$delta_lon = $max_lon - $min_lon + 0.01;
				$delta_max = max($delta_lat, $delta_lon);
				if ($delta_max >= 360){
						$zoom = 0;
					} else if ($delta_max >= 180){
						$zoom = 1;
					} else if ($delta_max >= 90){
						$zoom = 2;
					} else if ($delta_max >= 45){
						$zoom = 3;
					} else if ($delta_max >= 22.5){
						$zoom = 4;
					} else if ($delta_max >= 11.25){
						$zoom = 5;
					} else if ($delta_max >= 5.625){
						$zoom = 6;
					} else if ($delta_max >= 2.813){
						$zoom = 7;
					} else if ($delta_max >= 1.406){
						$zoom = 8;
					} else if ($delta_max >= 0.703){
						$zoom = 9;
					} else if ($delta_max >= 0.352){
						$zoom = 10;
					} else if ($delta_max >= 0.176){
						$zoom = 11;
					} else if ($delta_max >= 0.088){
						$zoom = 12;
					} else if ($delta_max >= 0.044){
						$zoom = 13;
					} else if ($delta_max >= 0.022){
						$zoom = 14;
					} else if ($delta_max >= 0.011){
						$zoom = 15;
					} else if ($delta_max >= 0.005){
						$zoom = 16;
					} else if ($delta_max >= 0.003){
						$zoom = 17;
					} else if ($delta_max >= 0.001){
						$zoom = 18;
					} else if ($delta_max >= 0.0005){
						$zoom = 19;
					} else if ($delta_max >= 0.00025){
						$zoom = 20;
					} else {
						$zoom = 21;
					};
				$output .= "</table>

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