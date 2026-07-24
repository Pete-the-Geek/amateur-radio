<?php
$debug_start = time();
$debug = "Start: ".(time() - $debug_start)."\n";

	require ('../phpscripts/sqlfunctions.php');
	require ('functions.php');
	require ('../maps/gridrefutils.php');
	
	if (!$_POST['location_name']) $_POST['location_name'] = 'Home';
	if (!$_POST['max_distance']) $_POST['max_distance'] = '100';
	if (!$_POST['sortField']) $_POST['sortField'] = 'distance';
	if (!$_POST['sortOrder']) $_POST['sortOrder'] = 'ASC';

$debug .= "Start building page: ".(time() - $debug_start)."\n";

$output = "<!DOCTYPE html>
<html>
	<head>
		<title>Radio Database - Nodes</title>
		<link rel='icon' href='favicon.ico' />
		<script src='OpenLayers.js'></script>
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
$debug .= "Updating records: ".(time() - $debug_start)."\n";

		$output .= "<form method='post' action='./' enctype='multipart/form-data' class='hidemeprint' name='searchForm'>
			<table>
				<tr>
					<th align='left'>Location Name</th>
					<th align='left'>Ant Height</th>
					<th align='left'>Custom Location</th>
					<th align='left'>Mode</th>
					<th align='left'>Band</th>
					<th align='left'>Type</th>
					<th align='left'>Status</th>
					<th align='left'>Max Distance (km)</th>
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
						<select name='type'>
							<option value=''></option>
							<option value='AV'".("AV" == $_POST['type'] ? " selected": "").">Analogue Voice</option>
							<option value='DV'".("DV" == $_POST['type'] ? " selected": "").">Digital Voice</option>
							<option value='RL'".("RL" == $_POST['type'] ? " selected": "").">Repeater Link</option>
							<option value='DM'".("DM" == $_POST['type'] ? " selected": "").">DMR</option>
							<option value='DG'".("DG" == $_POST['type'] ? " selected": "").">Digital Gateway</option>
							<option value='AG'".("AG" == $_POST['type'] ? " selected": "").">Analogue Gateway</option>
							<option value='AP'".("AP" == $_POST['type'] ? " selected": "").">APRS</option>
							<option value='PX'".("PX" == $_POST['type'] ? " selected": "").">AX25 /PACKET</option>
							<option value='TV'".("TV" == $_POST['type'] ? " selected": "").">TV Repeater</option>
							<option value='PN'".("PN" == $_POST['type'] ? " selected": "").">Packet Node</option>
							<option value='BN'".("BN" == $_POST['type'] ? " selected": "").">Beacon</option>
							<option value='DD'".("DD" == $_POST['type'] ? " selected": "").">Digital Data</option>
							<option value='RN'".("RN" == $_POST['type'] ? " selected": "").">ROSE Node</option>
							<option value='SP'".("SP" == $_POST['type'] ? " selected": "").">Simplex</option>
						</select>
					</td>
					<td>
						<select name='status'>
							<option value=''></option>";
							$search_query = "SELECT DISTINCT `status` FROM `radio_repeaters_gateways` ORDER BY `status`";
							$search_table = sqlfunct_query($search_query);
							while ($search_row = sqlfunct_fetch_array($search_table))
								$output .= "<option value='".$search_row['status']."'".($_POST['status'] == $search_row['status'] ? " selected": "").">".ucwords(strtolower($search_row['status']))."</option>";
						$output .= "</select>
					</td>
					<td>
						<input type='text' size='2' name='max_distance' value='".$_POST['max_distance']."'>
					</td>
					<td>
						<input type='hidden' name='sortField' value='".$_POST['sortField']."' id='sortField'>
						<input type='hidden' name='sortOrder' value='".$_POST['sortOrder']."' id='sortOrder'>
						<input type='submit' name='mySubmit' value='Search'>
					</td>
				</tr>
			</table>
		</form>
		<hr />";
		
	if (!$_POST['cli']) print $output;
	$output = "";
	$locations_array = array();
$debug .= "Begin Analysis: ".(time() - $debug_start)."\n";
//	if ($_POST['mySubmit']){

			$output_head = "<table border='1' cellpadding='2' cellspacing='0' class='freezeTop' style='margin: 0 auto;'>
					<tr>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"callsign\");'>Callsign</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"tx\");'>TX</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"rx\");'>RX</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"qth\");'>QTH</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"where\");'>Where</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"agent\");'>Agent</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"code\");'>Code</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"mode\");'>Mode</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"keeper\");'>Keeper</a></th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"status\");'>Status</a></th>
						<th class='freezeTop'>Offset</th>
						<th class='freezeTop'>Lat &amp; Long</th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"distance\");'>Distance</a></th>
						<th class='freezeTop'>Bearing</th>
						<th class='freezeTop'><a href='javascript:sortSubmit(\"type\");'>Type</a></th>
						<th class='freezeTop'>Profile</th>
					</tr>";

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
					$location_qth = $row['qth'];
					$home_latitude = $row['latitude'];
					$home_longitude = $row['longitude'];
				} else if (preg_match('/^[0-9,\.-]+$/', $_POST['custom_location'])){
					list ($home_latitude,$home_longitude) = explode(',',$_POST['custom_location']);
					$location_qth = latLongToMaidenhead($home_latitude, $home_longitude,8);
				};

			$points = array(
							array(
									'name' => ($_POST['location_name'] != 'custom' ? $_POST['location_name'] : $home_latitude.",".$home_longitude),
									'latitude' => $home_latitude,
									'longitude' => $home_longitude
								)
						);

			$locations_array [] = array($home_latitude,$home_longitude,'home');

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
							AS `longitude`,
							(
								6371 * ACOS(COS(RADIANS(
								(
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
								)
								)) * COS(RADIANS(
								(
									".$home_latitude."
								)
								)) * COS(RADIANS(
								(
									".$home_longitude."
								)
								) - RADIANS(
								(
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
								)
								)) + SIN(RADIANS(
								(
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
								)
								)) * SIN(RADIANS(
								(
									".$home_latitude."
								)
								)))
							) AS distance
						FROM `radio_repeaters`
						WHERE 1 = 1";
			if ($_POST['max_distance']) $query .= " AND 							(
								6371 * ACOS(COS(RADIANS(
								(
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
								)
								)) * COS(RADIANS(
								(
									".$home_latitude."
								)
								)) * COS(RADIANS(
								(
									".$home_longitude."
								)
								) - RADIANS(
								(
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
								)
								)) + SIN(RADIANS(
								(
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
								)
								)) * SIN(RADIANS(
								(
									".$home_latitude."
								)
								)))
							) <= ".$_POST['max_distance'];
			if ($_POST['band']) {
					$query .= " AND `band` = '".$_POST['band']."' ";
				};
			if ($_POST['mode']) $query .= " AND `modeCodes` LIKE '%".$_POST['mode']."%'";
			if ($_POST['type']) $query .= " AND `type` = '".$_POST['type']."'";
			if ($_POST['status']) $query .= " AND `status` = '".$_POST['status']."'";
			$query .= " ORDER BY `".$_POST['sortField']."` ".$_POST['sortOrder'].", `distance` ASC";
			$table = sqlfunct_query($query);

			while ($row = sqlfunct_fetch_array($table)){
					if ($row['latitude'] AND $row['longitude']) $locations_array [] = array($row['latitude'],$row['longitude'],$row['type']);
					$output_start .= "<tr>
									<td><a href='javascript:document.getElementById(\"form_".$row['id']."\").submit();'>".$row['repeater']."</a></td>
									<td style='text-align: right;'>".number_format($row['tx'] / 1000000,4)."</td>
									<td style='text-align: right;'>".($row['rx'] != $row['tx'] && $row['rx'] ? number_format($row['rx'] / 1000000,4) : "&nbsp;")."</td>
									<td>".$row['locator']."</td>
									<td>".$row['town']."</td>
									<td>".$row['agent']."</td>
									<td>".($row['ctcss'] ? $row['ctcss'] : '')."</td>
									<td>".$row['modeCodes']."</td>
									<td>".$row['keeperCallsign']."</td>
									<td>".$row['status']."</td>
									<td>".($row['rx'] != $row['tx'] && $row['rx'] ? round(($row['tx'] - $row['rx']) / 1000000,3) : "&nbsp;")."</td>
									<td>".($row['latitude'] ? "<a href='https://www.google.co.uk/maps/place/".$row['latitude'].",".$row['longitude']."/@".$row['latitude'].",".$row['longitude'].",17z' target='_blank'>".number_format($row['latitude'],5).",".number_format($row['longitude'],5)."</a>" : "&nbsp;")."</td>
									<td style='text-align: right;'>".round(haversine($home_latitude,$home_longitude,$row['latitude'],$row['longitude']),1)." km</td>
									<td style='text-align: right;'>".round(getBearing($home_latitude,$home_longitude,$row['latitude'],$row['longitude']))."&deg;</td>
									<td>".ucwords($row['type'])."</td>
									<td>
										<form method='post' action='profile.php' target='_blank' id='form_".$row['id']."'>
											<input type='hidden' name='id' value='".$row['id']."'>
											<input type='hidden' name='home_latitude' value='".$home_latitude."'>
											<input type='hidden' name='home_longitude' value='".$home_longitude."'>
											<input type='hidden' name='antenna_height' value='".$_POST['antenna_height']."'>
											<input type='hidden' name='location_name' value='".$_POST['location_name']."'>
											<input type='image' src='img/profile.jpg' alt='Submit' width='50' height='18'>
										</form>
									</td>
								</tr>";
					$points[] = array(
									'name' => $row['repeater'],
									'latitude' => $row['latitude'],
									'longitude' => $row['longitude'],
									'form_id' => 'form_'.$row['id']
								);

				};
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
			<br />
<!--
			<div style='max-width:50%; margin: auto;'>
				<div id='Map' style='width:100%; aspect-ratio : 1 / 1;  margin: auto;'></div>
			</div>
			<script>
				var zoom           	= ".($zoom).";

				var fromProjection 	= new OpenLayers.Projection('EPSG:4326');   // Transform from WGS 1984
				var toProjection   	= new OpenLayers.Projection('EPSG:900913'); // to Spherical Mercator Projection
				var centerPosition 	= new OpenLayers.LonLat(".$mean_lon." , ".$mean_lat." ).transform( fromProjection, toProjection);
				map 				= new OpenLayers.Map('Map');
				var mapnik         	= new OpenLayers.Layer.OSM();
				map.addLayer(mapnik);

				 // create layer switcher widget in top right corner of map.
					var layer_switcher= new OpenLayers.Control.LayerSwitcher({});
					map.addControl(layer_switcher);

				var markers = new OpenLayers.Layer.Markers( 'Markers' );
				map.addLayer(markers);

				".$locations_js."

				map.setCenter(centerPosition , zoom);
			</script>
				
			<p></p>
-->
			
			<div id='osm-map-wrapper' style='width: 80%; max-width: 80%; margin: 0 auto; border: 1px solid #ccc; border-radius: 4px; overflow: hidden;'>
				<div id='osm-hub-map' style='height: 500px; width: 100%;'></div>
				<div id='osm-hub-info' style='padding: 15px; font-family: sans-serif; font-size: 16px; background: black;'>
					Calculating distances...
				</div>
			</div>";
			
			include('map.php');

			if (!$_POST['cli']) print $output_head.$output_start.$output;
//		};
	$output = "</body></html>";
	if (!$_POST['cli']) {
			include('footer.php');
			print $output;
		};
?>