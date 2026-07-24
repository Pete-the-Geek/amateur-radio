<?php

	require ('../phpscripts/sqlfunctions.php');

print "<!DOCTYPE html>
<html>
	<head>
		<title>Radio Database - Import</title>
		<link rel='icon' href='favicon.ico' />
		<script src='OpenLayers.js'></script>
		<style type='text/css'>";
			// note background and foreground colours are set in css.php
			$darkmodeSetting = 1;
			include ('css.php');
		print "</style>
	</head>

	<body id='main'>
		";
$query = "TRUNCATE TABLE `radio_repeaters`";
$result = @sqlfunct_query($query);

try {
    // 1. Connect to the database using PDO
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Read the uploaded JSON file
    $jsonString = file_get_contents('https://api-beta.rsgb.online/all/systems');

    // 3. Decode the JSON string
    $dataArray = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error decoding JSON: " . json_last_error_msg());
    }

    // 4. Prepare the SQL INSERT statement
    $sql = "INSERT INTO `radio_repeaters` (
        id, fac, type, status, keeperCallsign, town, modeCodes, 
        tx, repeater, rx, ctcss, txbw, band, locator, dbwErp, 
        ngr, antennaHeight, polarisation
    ) VALUES (
        :id, :fac, :type, :status, :keeperCallsign, :town, :modeCodes, 
        :tx, :repeater, :rx, :ctcss, :txbw, :band, :locator, :dbwErp, 
        :ngr, :antennaHeight, :polarisation
    )";
    $stmt = $pdo->prepare($sql);

    // 5. Start a database transaction for speed and safety
    $pdo->beginTransaction();

    $count = 0;
    
    // 6. Your JSON has all the items inside a root "data" key, so we loop over that
    if (isset($dataArray['data']) && is_array($dataArray['data'])) {
        foreach ($dataArray['data'] as $row) {
            
            // Handle potentially missing/null nested 'extraDetails' (like in ID 743)
            $ngr = isset($row['extraDetails']['ngr']) ? $row['extraDetails']['ngr'] : null;
            $antennaHeight = isset($row['extraDetails']['antennaHeight']) ? $row['extraDetails']['antennaHeight'] : null;
            $polarisation = isset($row['extraDetails']['polarisation']) ? $row['extraDetails']['polarisation'] : null;

            // 'modeCodes' is an array in your JSON (e.g. ["A", "D"]). 
            // We convert it to a comma-separated string (e.g. "A,D") for storage.
            $modeCodes = (isset($row['modeCodes']) && is_array($row['modeCodes'])) 
                            ? implode(',', $row['modeCodes']) 
                            : null;

            // Execute the statement for this specific row
            $stmt->execute([
                ':id'             => $row['id'],
                ':fac'            => $row['fac'] ? 1 : 0,  // Convert boolean to 1 or 0
                ':type'           => $row['type'],
                ':status'         => $row['status'],
                ':keeperCallsign' => $row['keeperCallsign'],
                ':town'           => $row['town'],
                ':modeCodes'      => $modeCodes,
                ':tx'             => $row['tx'],
                ':repeater'       => $row['repeater'],
                ':rx'             => $row['rx'],
                ':ctcss'          => $row['ctcss'],
                ':txbw'           => $row['txbw'],
                ':band'           => $row['band'],
                ':locator'        => $row['locator'],
                ':dbwErp'         => $row['dbwErp'],
                ':ngr'            => $ngr,
                ':antennaHeight'  => $antennaHeight,
                ':polarisation'   => $polarisation
            ]);
            $count++;
        }
    }

    // 7. Commit the transaction
    $pdo->commit();
    echo "Success! Inserted $count repeater records into the database.";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}


/*
		$output .= "<form method='post' action='scrape.php'>
			<p>Web browse to <a href='https://ukrepeater.net/repeaterlist.html?filter=ALL' target='_blank'>https://ukrepeater.net/repeaterlist.html?filter=ALL</a> and <a href='https://ukrepeater.net/gateway_list.html?filter7=ALL'>https://ukrepeater.net/gateway_list.html?filter7=ALL</a></p>
			<p>Press F12 to enable developers pannel</p>
			<p>Locate the Table</p>
			<p>Right Click on the table element and select Copy -> Copy Element</p>
			<p>Paste the result into the below box</p>
			<textarea rows='20' cols='200' name='scrape_text'></textarea>
			<br />
			<input type='submit' name='submit' value='Submit'>
		</form>";

if ($_POST['submit']){
		$html = $_POST['scrape_text'];
		
		$dom = new DOMDocument();

		// Suppress warnings for non-strict HTML
		libxml_use_internal_errors(true);
		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$tables = $dom->getElementsByTagName('table');
		$data = [];

		foreach ($tables as $table) {
				$rows = $table->getElementsByTagName('tr');
				foreach ($rows as $row) {
						$cells = $row->getElementsByTagName('td');
						$rowData = [];
						foreach ($cells as $cell) {
								if ($cell->hasAttribute('class')) {
										// Get the value of the class attribute
										$classValue = $cell->getAttribute('class');
										
										// Split the classes into an array in case there are multiple
										$classes = explode(' ', $classValue);
										
										// Check if 'minic' is in the array of classes
										if (in_array('minic', $classes)) {
												$cell_result = trim(str_replace("\xC2\xA0", ' ', trim($cell->nodeValue)));
												$rowData[] = substr(trim(str_replace("\xC2\xA0", ' ', $cell->nodeValue)),0,4);
											} else {
												$rowData[] = trim($cell->nodeValue);
											};
									} else {
										$rowData[] = trim($cell->nodeValue);
									};
							}
						if ($rowData) {
								$data[] = $rowData;
							}
					}
			};
		
		$isFirstRow = true;
		$insertedCount = 0;

		// ==========================================
		// 4. Prepare Database Query and Insert
		// ==========================================
		// IMPORTANT: You must update the table name and columns to match your MySQL database!
		// The number of placeholders (?) must match the number of columns you are extracting.
		$insertQueryStart = "INSERT INTO `radio_repeaters_gateways` (`callsign`,`channel`,`tx`,`rx`,`qth`,`where`,`agent`,`code`,`mode`,`www`,`keeper`,`status`,`type`) VALUES ";
		$insertQuery = "";
		$insertedCount = 0;
		$totalInsertedCount = 0;

		foreach ($data as $row) {
				// Usually, the first row contains <th> tags (headers). We want to skip this.
				if ($isFirstRow) {
						if ($row[1] == '[CHAN]') $type = 'repeater';
						if ($row[6] == 'G8SFR'){
								$type = 'gateway';
								$insertQuery .= '(
									"'.trim(str_replace("\xC2\xA0", ' ', $row[0])).'",
									NULL,
									'.(trim(str_replace("\xC2\xA0", NULL, $row[3])) ? trim(str_replace("\xC2\xA0", NULL, $row[3])) : "NULL").',
									NULL,
									"'.trim(str_replace("\xC2\xA0", ' ', $row[5])).'",
									"'.rtrim(trim(str_replace("\xC2\xA0", ' ', $row[7])),",").'",
									"'.trim(str_replace("\xC2\xA0", ' ', $rox[6])).'",
									'.(trim(str_replace("\xC2\xA0", NULL, $row[2])) ? preg_replace('/[^0-9.]/', '', trim(str_replace("\xC2\xA0", NULL, $row[2]))) : "NULL").',
									"'.trim(str_replace("\xC2\xA0", ' ', $row[4])).'",
									NULL,
									"'.trim(str_replace("\xC2\xA0", ' ', $row[1])).'",
									"'.trim(str_replace("\xC2\xA0", ' ', $row[8])).'",
									"'.$type.'")';
								$insertedCount++;
							};
						$query = "DELETE FROM `radio_repeaters_gateways` WHERE `type` = '".$type."'";
						$result = @sqlfunct_query($query);
						$isFirstRow = false;
						continue; 
					}

				if ($insertedCount > 0) $insertQuery.= ",";
				
				if ($type == 'repeater'){
						// 	[KEY],[CHAN],[OUTPUT],[RX],[QTHR],[km],[deg],[WHERE],[AGENT],[CTCSS/CC],[MODES],[WWW],[KEEPER],[STATUS]	
						$insertQuery .= '(
							"'.trim(str_replace("\xC2\xA0", ' ', $row[0])).'",
							"'.trim(str_replace("\xC2\xA0", ' ', $row[1])).'",
							'.(trim(str_replace("\xC2\xA0", NULL, $row[2])) ? trim(str_replace("\xC2\xA0", NULL, $row[2])) : "NULL").',
							'.(trim(str_replace("\xC2\xA0", NULL, $row[3])) ? trim(str_replace("\xC2\xA0", NULL, $row[3])) : "NULL").',
							"'.trim(str_replace("\xC2\xA0", ' ', $row[4])).'",
							"'.trim(str_replace("\xC2\xA0", ' ', $row[7])).'",
							"'.trim(str_replace("\xC2\xA0", ' ', $rox[8])).'",
							'.(trim(str_replace("\xC2\xA0", NULL, $row[9])) ? trim(str_replace("\xC2\xA0", NULL, $row[9])) : "NULL").',
							"'.trim(str_replace("\xC2\xA0", ' ', $row[10])).'",
							'.(trim(str_replace("\xC2\xA0", ' ', $row[11])) ? 1 : "NULL").',
							"'.trim(str_replace("\xC2\xA0", ' ', $row[12])).'",
							"'.trim(str_replace("\xC2\xA0", ' ', $row[13])).'",
							"'.$type.'")';
					};
				if ($type == 'gateway'){
						$insertQuery .= '(
							"'.trim(str_replace("\xC2\xA0", ' ', $row[0])).'",
							NULL,
							'.(trim(str_replace("\xC2\xA0", NULL, $row[3])) ? trim(str_replace("\xC2\xA0", NULL, $row[3])) : "NULL").',
							NULL,
							"'.trim(str_replace("\xC2\xA0", ' ', $row[5])).'",
							"'.rtrim(trim(str_replace("\xC2\xA0", ' ', $row[7])),",").'",
							"'.trim(str_replace("\xC2\xA0", ' ', $rox[6])).'",
							'.(trim(str_replace("\xC2\xA0", NULL, $row[2])) ? preg_replace('/[^0-9.]/', '', trim(str_replace("\xC2\xA0", NULL, $row[2]))) : "NULL").',
							"'.trim(str_replace("\xC2\xA0", ' ', $row[4])).'",
							NULL,
							"'.trim(str_replace("\xC2\xA0", ' ', $row[1])).'",
							"'.trim(str_replace("\xC2\xA0", ' ', $row[8])).'",
							"'.$type.'")';
					};
					
				$insertedCount++;
				
				if ($insertedCount == 100){
						$insertedCount = 0;
						$result = @sqlfunct_query($insertQueryStart.$insertQuery);
						if ($result){
								$totalInsertedCount += sqlfunct_affected_rows();
							} else {
								$output .= "Failed to import
											<br />
											".sqlfunct_errno()."
											<br />
											".sqlfunct_error()."
											<br />
											".$insertQueryStart.$insertQuery;
								$output ."<body></html>";
								print $output;
								exit;
							};
						$insertQuery = "";
					};
			};

		if ($insertedCount > 0) {
				$result = @sqlfunct_query($insertQueryStart.$insertQuery);
				if ($result){
						$totalInsertedCount += sqlfunct_affected_rows();
						$output .= "<br />
									<b>".$totalInsertedCount." records inserted</b>";
					} else {
						$output .= "Failed to import
									<br />
									".sqlfunct_errno()."
									<br />
									".sqlfunct_error()."
									<br />
									".$insertQueryStart.$insertQuery;
					};
			};
	};
*/

		$known = array (
					array(
						'name' => 'MB7ISK',
						'qth' => 'IO81lo50OJ'
						)
					,
					array(
						'name' => 'MB7IFN',
						'qth' => 'IO81mn08UE'
						),
					array(
						'name' => 'GB3BC',
						'qth' => 'IO81KO55ia'
						)
					);
		foreach ($known as $location){
				$query = "UPDATE `radio_repeaters` SET `locator` = '".$location['qth']."' WHERE `repeater` = '".$location['name']."'";
				sqlfunct_query($query);
			};
print "<body>
</html>";

?>