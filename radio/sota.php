<?php
$debug_start = time();
$debug = "Start: ".(time() - $debug_start)."\n";

	require ('../phpscripts/sqlfunctions.php');
	require ('functions.php');
	require ('../maps/gridrefutils.php');
	
$output = "<!DOCTYPE html>
<html>
	<head>
		<title>Radio Database - SOTA Chasing</title>
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
					if (document.getElementById('sortField').value == sortField && document.getElementById('sortOrder').value != 'ASC') {
							document.getElementById('sortOrder').value = 'ASC'
						} else {
							document.getElementById('sortOrder').value = '';
						};
					document.getElementById('sortField').value=sortField;
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
			<form method='post' action='sota.php' enctype='multipart/form-data'>
				<input type='hidden' name='myimport' value='1'>
				Import latest CSV <input type='submit' value='Import'>
			</form>
		</div>
		<br />
		<hr />
		<form method='post' action='sota.php'>
			<table border='1' cellspacing='0' style='margin: 0 auto;'>
				<tr>
					<th align='left'>Calling Location Name</th>
					<th align='left'>Calling Ant Height</th>
					<th align='left'>Custom Calling Location</th>
					<th align='left'>Summit Reference(s)</th>
					<th align='left'>Frequency(s) MHz</th>
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
						<input type='text' size='6' name='frequency' value='".$_POST['frequency']."'>
					</td>
					<td>
						<input type='submit' name='mySubmit' value='Search'>
						<input type='hidden' name='search' value='1'>
					</td>
				</tr>
			</table>
		</form>";
		
		if ($_POST['myimport']){
				$locations_array = array();

				$url = 'https://storage.sota.org.uk/summitslist.csv';
				$ch = curl_init($url);

				// 1. Configure cURL
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
				curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'); 

				// 2. TEMPORARILY disable SSL verification to bypass cURL Error 60
				// (If this fixes it, your PHP environment needs an updated cacert.pem)
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

				// 3. Execute and grab HTTP status
				$csvData = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// 4. Catch and display exact errors instead of failing silently
				if (curl_errno($ch)) {
					die('<b>cURL Error:</b> ' . curl_error($ch));
				}
				if ($httpCode !== 200) {
					die('<b>HTTP Error:</b> The server returned status code ' . $httpCode);
				}
				if (empty($csvData)) {
					die('<b>Error:</b> cURL connected successfully, but the file was totally empty.');
				}

				curl_close($ch);

				// 2. Write the fetched data to a temporary memory stream
				$stream = fopen('php://temp', 'r+');
				fwrite($stream, $csvData);
				rewind($stream); // Move the pointer back to the beginning of the stream

//				$csvFile = fopen($_FILES['summits']['tmp_name'], 'r');
				$data = [];

				// Read the first line of rubbish data
				$headers = fgetcsv($stream);

				// Read the second line to get the header row
				$headers = fgetcsv($stream);
			
				$count = 0;
				$query_header = 'INSERT INTO `radio_sota_summits`
											(`SummitCode`, `AssociationName`, `RegionName`, `SummitName`, `AltM`, `AltFt`, `GridRef1`, `GridRef2`, `Longitude`, `Latitude`, `Points`, `BonusPoints`, `ValidFrom`, `ValidTo`, `ActivationCount`, `ActivationDate`, `ActivationCall`)
										VALUES ';
				$query_footer = ' ON DUPLICATE KEY UPDATE
									`AssociationName` = VALUES(`AssociationName`), 
									`RegionName` = VALUES(`RegionName`), 
									`SummitName` = VALUES(`SummitName`), 
									`AltM` = VALUES(`AltM`), 
									`AltFt` = VALUES(`AltFt`), 
									`GridRef1` = VALUES(`GridRef1`), 
									`GridRef2` = VALUES(`GridRef2`), 
									`Longitude` = VALUES(`Longitude`), 
									`Latitude` = VALUES(`Latitude`), 
									`Points` = VALUES(`Points`), 
									`BonusPoints` = VALUES(`BonusPoints`), 
									`ValidFrom` = VALUES(`ValidFrom`), 
									`ValidTo` = VALUES(`ValidTo`), 
									`ActivationCount` = VALUES(`ActivationCount`), 
									`ActivationDate` = VALUES(`ActivationDate`), 
									`ActivationCall` = VALUES(`ActivationCall`) ';
				$query = '';

				if ($headers !== false) {
						// Clean whitespace from headers once
						$headers = array_map('trim', $headers);
						$headerCount = count($headers);

						while (($row = fgetcsv($stream)) !== false) {
								
								// Safety check: array_combine will fail if the row has more or fewer columns than the header.
								// This is a common issue when dealing with manually transcribed log sheets.
								if (count($row) !== $headerCount) {
										// Optional: You could pad the array here using array_pad() if you want to keep incomplete rows
										continue; 
									};

								// Merge headers and row data into a single associative array
								$rowData = array_combine($headers, $row);

								// Now extraction is incredibly clean

								$SummitCode			= $rowData['SummitCode'] ?? null;
								$AssociationName	= $rowData['AssociationName'] ?? null;
								$RegionName			= $rowData['RegionName'] ?? null;
								$SummitName			= $rowData['SummitName'] ?? null;
								$AltM				= $rowData['AltM'] ?? null;
								$AltFt				= $rowData['AltFt'] ?? null;
								$GridRef1			= $rowData['GridRef1'] ?? null;
								$GridRef2			= $rowData['GridRef2'] ?? null;
								$Longitude			= $rowData['Longitude'] ?? null;
								$Latitude			= $rowData['Latitude'] ?? null;
								$Points				= $rowData['Points'] ?? null;
								$BonusPoints		= $rowData['BonusPoints'] ?? null;
								$ValidFrom			= $rowData['ValidFrom'] ?? null;
								$ValidTo			= $rowData['ValidTo'] ?? null;
								$ActivationCount	= $rowData['ActivationCount'] ?? null;
								$ActivationDate		= $rowData['ActivationDate'] ?? null;
								$ActivationCall		= $rowData['ActivationCall'] ?? null;

								if ($SummitCode){
										$count ++;
										$query .= '("'.$SummitCode.'", "'.$AssociationName.'", "'.$RegionName.'", "'.$SummitName.'", "'.$AltM.'", "'.$AltFt.'", "'.$GridRef1.'", "'.$GridRef2.'", "'.$Longitude.'", "'.$Latitude.'", "'.$Points.'", "'.$BonusPoints.'", STR_TO_DATE("'.$ValidFrom.'", "%d/%m/%Y"), STR_TO_DATE("'.$ValidTo.'", "%d/%m/%Y"), "'.$ActivationCount.'",STR_TO_DATE("'.$ActivationDate.'", "%d/%m/%Y"), "'.$ActivationCall.'"),';
									};

								if ($count > 100 or feof($stream)){
										$full_query = $query_header.substr($query,0,-1).$query_footer;
										$result = sqlfunct_query($full_query);
										$updated_count += sqlfunct_affected_rows();
										
										$count = 0;
										$query = '';
									}
							};
						print "Number of affected rows = ".$updated_count;
					};

				fclose($stream);
			};
		if ($_POST['search']){
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

				$_POST['home_latitude']  = $home_latitude;
				$_POST['home_longitude'] = $home_longitude;
				
				$points = array(
								0 => array(
											'name' => ($_POST['location_name'] != 'custom' ? $_POST['location_name'] : $_POST['home_latitude'].",".$_POST['home_longitude']),
											'latitude' => $_POST['home_latitude'],
											'longitude' => $_POST['home_longitude']
										)
							);

				if (!$_POST['frequency']) $_POST['frequency'] = 1;
				$locations = explode(',',$_POST['destination_location']);
				$frequencies = explode(',',$_POST['frequency']);
				$first_frequency = $frequencies[0];
				
				if (count($locations) != count($frequencies)) {
						$frequencies = array();
						for ($x = 0; $x < count($locations); $x++) {
								$frequencies[] = $first_frequency;
							};
					};
				
				$query = 'SELECT * FROM `radio_sota_summits` WHERE `SummitCode` IN (';
				foreach ($locations AS $value) $query .= 'TRIM("'.$value.'"),';
				$query = substr($query,0,-1).')';

				$table = sqlfunct_query($query);
				$x = 0;
				while ($row = sqlfunct_fetch_array($table)){
						$output .= "<form method='post' action='profile.php' target='_blank' id='form_".str_replace(array('/','-'),NULL,$row['SummitCode'])."'>
											<input type='hidden' name='id' value='".$row['SummitCode']."'>
											<input type='hidden' name='type' value='sota'>
											<input type='hidden' name='frequency' value='".$frequencies[$x]."'>
											<input type='hidden' name='home_latitude' value='".$home_latitude."'>
											<input type='hidden' name='home_longitude' value='".$home_longitude."'>
											<input type='hidden' name='antenna_height' value='".$_POST['antenna_height']."'>
											<input type='hidden' name='location_name' value='".$_POST['location_name']."'>
										</form>";
						$x++;
						$points[] = array(
										'name' => $row['SummitCode'],
										'latitude' => $row['Latitude'],
										'longitude' => $row['Longitude'],
										'form_id' => 'form_'.str_replace(array('/','-'),NULL,$row['SummitCode'])
									);
					};
				
				$output .= "

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