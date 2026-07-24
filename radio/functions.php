<?php
	function haversine($lat1, $lon1,
                   $lat2, $lon2)
{
    // distance between latitudes
    // and longitudes
    $dLat = ($lat2 - $lat1) *
                M_PI / 180.0;
    $dLon = ($lon2 - $lon1) * 
                M_PI / 180.0;

    // convert to radians
    $lat1 = ($lat1) * M_PI / 180.0;
    $lat2 = ($lat2) * M_PI / 180.0;

    // apply formulae
    $a = pow(sin($dLat / 2), 2) + 
         pow(sin($dLon / 2), 2) * 
             cos($lat1) * cos($lat2);
    $rad = 6371;
    $c = 2 * asin(sqrt($a));
    return $rad * $c;
}

function getBearing($lat1, $lon1, $lat2, $lon2) {
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    // Calculate delta longitude
    $dLon = $lon2 - $lon1;

    // Apply the formula components
    $y = sin($dLon) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);

    // Calculate the angle in radians and convert to degrees
    $bearing = atan2($y, $x);
    $bearing = rad2deg($bearing);

    // Normalize to a 0-360 degree compass heading
    return ($bearing + 360) % 360;
}

function maidenheadToLatLon(string $locator) {
    // Clean the input: remove whitespace and convert to uppercase
    $locator = strtoupper(trim($locator));
    
    // Validate format: 2 to 10 characters, alternating Letters/Numbers/Letters/Numbers/Letters
    if (!preg_match('/^[A-R]{2}([0-9]{2}([A-X]{2}([0-9]{2}([A-X]{2})?)?)?)?$/', $locator)) {
        return false; 
    }

    // Grid starts at the South-West corner of the map
    $lon = -180.0;
    $lat = -90.0;
    
    // Degrees width/height for each pair (Field, Square, Subsquare, Ext Square, Super Ext Square)
    $lon_widths = [20.0, 2.0, 2.0 / 24.0, 2.0 / 240.0, 2.0 / 5760.0];
    $lat_widths = [10.0, 1.0, 1.0 / 24.0, 1.0 / 240.0, 1.0 / 5760.0];

    $length = strlen($locator);
    $pairs = $length / 2;

    // Process each pair of characters
    for ($i = 0; $i < $pairs; $i++) {
        $charLon = $locator[$i * 2];
        $charLat = $locator[$i * 2 + 1];

        if ($i === 0 || $i === 2 || $i === 4) {
            // Letter pairs (A-R or A-X)
            $valLon = ord($charLon) - 65; // 'A' = 0
            $valLat = ord($charLat) - 65;
        } else {
            // Number pairs (0-9)
            $valLon = ord($charLon) - 48; // '0' = 0
            $valLat = ord($charLat) - 48;
        }

        $lon += $valLon * $lon_widths[$i];
        $lat += $valLat * $lat_widths[$i];
    }

    // Offset by half the width of the final precision level to get the center of the box
    $lon += $lon_widths[$pairs - 1] / 2;
    $lat += $lat_widths[$pairs - 1] / 2;

    return [
        'lat' => round($lat, 6),
        'lon' => round($lon, 6)
    ];
}

function latLongToMaidenhead($lat, $lon, $length = 6) {
    // Validate bounds
    if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
        return false;
    }

    // Shift coordinates so that everything is positive
    $lat += 90.0;
    $lon += 180.0;

    $locator = "";

    // 1. Calculate Field (First pair: A-R)
    // Longitude fields are 20 degrees wide, Latitude fields are 10 degrees high
    $lonField = (int) ($lon / 20);
    $latField = (int) ($lat / 10);
    $locator .= chr(ord('A') + $lonField) . chr(ord('A') + $latField);
    
    $lon -= $lonField * 20;
    $lat -= $latField * 10;

    if ($length <= 2) return $locator;

    // 2. Calculate Square (Second pair: 0-9)
    // Longitude squares are 2 degrees wide, Latitude squares are 1 degree high
    $lonSquare = (int) ($lon / 2);
    $latSquare = (int) ($lat / 1);
    $locator .= $lonSquare . $latSquare;
    
    $lon -= $lonSquare * 2;
    $lat -= $latSquare * 1;

    if ($length <= 4) return $locator;

    // 3. Calculate Sub-square (Third pair: a-x)
    // Longitude sub-squares are 5 minutes (1/12 degree) wide
    // Latitude sub-squares are 2.5 minutes (1/24 degree) high
    $lonSub = (int) ($lon * 12);
    $latSub = (int) ($lat * 24);
    $locator .= chr(ord('a') + $lonSub) . chr(ord('a') + $latSub);

    if ($length <= 6) return $locator;

    // Update lat/lon remainders for the 4th pair
    $lon -= $lonSub / 12.0;
    $lat -= $latSub / 24.0;

    // 4. Calculate Extended Square / Sub-sub-square (Fourth pair: 0-9)
    // Breaks down the previous sub-square into a 10x10 grid
    // Longitude extended squares are 0.5 minutes (1/120 degree) wide
    // Latitude extended squares are 0.25 minutes (1/240 degree) high
    $lonExt = (int) ($lon * 120);
    $latExt = (int) ($lat * 240);
    $locator .= $lonExt . $latExt;

    if ($length <= 8) return $locator;

    // Update lat/lon remainders for the 5th pair
    $lon -= $lonExt / 120.0;
    $lat -= $latExt / 240.0;

    // 5. Calculate Super Extended Square (Fifth pair: a-x)
    // Breaks down the previous extended square into a 24x24 grid
    // Longitude spans 1/2880 degree
    // Latitude spans 1/5760 degree
    $lonSuperExt = (int) ($lon * 2880);
    $latSuperExt = (int) ($lat * 5760);
    
    // Safety clamp to prevent floating point inaccuracies from pushing values to 'y'
    if ($lonSuperExt > 23) $lonSuperExt = 23;
    if ($latSuperExt > 23) $latSuperExt = 23;

    $locator .= chr(ord('a') + $lonSuperExt) . chr(ord('a') + $latSuperExt);

    return $locator;
}
	
/**
 * Calculates a destination coordinate given a starting point, bearing, and distance.
 *
 * @param float $lat Latitude of the starting point in decimal degrees
 * @param float $lng Longitude of the starting point in decimal degrees
 * @param float $bearing Bearing in degrees (0 = North, 90 = East, etc.)
 * @param float $distanceKm Distance to travel in kilometers
 * @return array Associative array containing 'latitude' and 'longitude'
 */
function getDestinationPoint($lat, $lng, $bearing, $distanceKm) {
    // Earth's mean radius in kilometers
    $earthRadius = 6371.01; 

    // 1. Convert decimal degrees to radians for the math functions
    $lat1 = deg2rad($lat);
    $lng1 = deg2rad($lng);
    $bearingRad = deg2rad($bearing);

    // 2. Calculate the angular distance (distance divided by Earth's radius)
    $angularDistance = $distanceKm / $earthRadius;

    // 3. Calculate the new latitude
    $lat2 = asin(
        sin($lat1) * cos($angularDistance) +
        cos($lat1) * sin($angularDistance) * cos($bearingRad)
    );

    // 4. Calculate the new longitude
    $lng2 = $lng1 + atan2(
        sin($bearingRad) * sin($angularDistance) * cos($lat1),
        cos($angularDistance) - sin($lat1) * sin($lat2)
    );

    // 5. Convert the resulting radians back to decimal degrees
    $newLat = rad2deg($lat2);
    $newLng = rad2deg($lng2);

    // 6. Normalize the longitude to be between -180 and +180 degrees
    $newLng = fmod(($newLng + 540), 360) - 180;

    return [
        'latitude' => $newLat,
        'longitude' => $newLng
    ];
}

	function get_dtm_alt($latitude, $longitude, $leave_asc = FALSE){
			// $leave_asc means don't delete the ASCII file once completed. This speeds up processing where multiple requests are made for the same square but will also increase storage space unless cleaned up manually
			// Ideally, if $leave_asc is set as TRUE for numerous aquisions the last aquision should have $leave_asc set to FALSE to clean up the ASCII file
			
			// Initialise the bad location of the DTM files
			$baseSourceFolder = '../../downloads/DTM';
			// Initialise result array
			$result = array();
			// Initialise Grid Ref Utils
			$grutoolbox = Grid_Ref_Utils::toolbox();
			$source_coords = Array($latitude,$longitude);
			$uk_grid_numbers = $grutoolbox->lat_long_to_grid($source_coords,$grutoolbox->COORDS_GPS_UK);
			// Generate OSGB Reference
			$grid_array = $grutoolbox->get_UK_grid_ref($uk_grid_numbers,5,$grutoolbox->DATA_ARRAY);
			// Place OSGB reference in result array
			$result['osgb'] = $grid_array;
			
			// Getting first 2 characters of Grid Reference
			$x_first_2 = $grid_array[1][0].($grid_array[1][1] & 1 ? ($grid_array[1][1] - 1) : $grid_array[1][1]);
			$y_first_2 = $grid_array[2][0].($grid_array[2][1] & 1 ? ($grid_array[2][1] - 1) : $grid_array[2][1]);
			
			// Build array for different resolutions: 50cm in 100m square, 1m in 200m square, 2m in 200m square, 50m in 1km square
			$resolutions = array(
					'50cm' => array (
							'baseSourceFolder' => $baseSourceFolder,
							'directory' => '../../downloads/DTM/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0],
							'zipfile' => $baseSourceFolder.'/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0]. "/50cm/".$grid_array[0].$grid_array[1][0].$grid_array[1][1].$grid_array[2][0].$grid_array[2][1].'.asc.zip',
							'file' => $grid_array[0].$grid_array[1][0].$grid_array[1][1].$grid_array[2][0].$grid_array[2][1].'.asc',
							'header' => 6,
							'multiplier' => 1000
						),
					'1m' => array (
							'baseSourceFolder' => $baseSourceFolder,
							'directory' => '../../downloads/DTM/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0],
							'zipfile' => $baseSourceFolder.'/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0]. "/1m/".$grid_array[0].$x_first_2.$y_first_2.'.asc.zip',
							'file' => $grid_array[0].$x_first_2.$y_first_2.'.asc',
							'header' => 6,
							'multiplier' => 1000
						),
					'2m' => array (
							'baseSourceFolder' => $baseSourceFolder,
							'directory' => $baseSourceFolder.'/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0],
							'zipfile' => $baseSourceFolder.'/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0]. "/2m/".$grid_array[0].$x_first_2.$y_first_2.'.asc.zip',
							'file' => $grid_array[0].$x_first_2.$y_first_2.'.asc',
							'header' => 6,
							'multiplier' => 1000
						),
					'50m' => array (
							'baseSourceFolder' => $baseSourceFolder.'/terr50_gagg_gb',
							'directory' => $baseSourceFolder.'/'.$grid_array[0].$grid_array[1][0].$grid_array[2][0],
							'zipfile' => $baseSourceFolder.'/terr50_gagg_gb/'.strtolower($grid_array[0]).$x_first_2[0].$y_first_2[0].'_OST50GRID_20240530.zip',
							'file' => $grid_array[0].$x_first_2[0].$y_first_2[0].'.asc',
							'header' => 5,
							'multiplier' => 1
						)
				);
				
			foreach ($resolutions AS $resolution => $metadata){
					// For each resolution
					
					//Does the ZIP file Exist
					if (file_exists($metadata['zipfile'])) {
							// Does the extracted file exist
							if (!file_exists($metadata['directory'].'/'.$resolution.'/'.$metadata['file'])){
									// Unzip the ZIP file
									$zip = new ZipArchive;
									$zip->open($metadata['zipfile']);
									$zip->extractTo($metadata['directory'].'/'.$resolution,$metadata['file']);
									$zip->close();
								};
							
							// Read the contents of the ASCII XYZ file
							$ascfile = new SplFileObject($metadata['directory'].'/'.$resolution.'/'.$metadata['file']);
							
							// Get file headers
							
							$ascfile->seek( 0 );
							$x_size_array = explode(' ',trim($ascfile->current()));
							$x_size = $x_size_array[count($x_size_array) - 1];

							$ascfile->seek( 1 );
							$y_size_array = explode(' ',trim($ascfile->current()));
							$y_size = $y_size_array[count($y_size_array) - 1];

							$ascfile->seek( 2 );
							$x_ll_array = explode(' ',trim($ascfile->current()));
							$x_ll = (float)($x_ll_array[count($x_ll_array) - 1]);
							$x_ll = ($x_ll - (floor($x_ll / 100000) * 100000));

							$ascfile->seek( 3 );
							$y_ll_array = explode(' ',trim($ascfile->current()));
							$y_ll = (float)($y_ll_array[count($y_ll_array) - 1]);
							$y_ll = ($y_ll - (floor($y_ll / 100000) * 100000));

							$ascfile->seek( 4 );
							$cell_size_array = explode(' ',trim($ascfile->current()));
							$cell_size = (float)($cell_size_array[count($cell_size_array) - 1]);

							// Calculate grid position in the ASCII file
							
							$x_pos = (float)((float)$grid_array[1] - $x_ll);
							$y_pos = (float)((float)$grid_array[2] - $y_ll);
							
							$gridX = floor((float)$x_pos / (float)$cell_size);
							$gridY = floor((float)$y_pos / (float)$cell_size);
							
							$row = $y_size + $metadata['header'] - floor($gridY) - 1;
							$column = floor($gridX);
							
							// Obtain Z from XY file positions

							// Read the row
							$ascfile->seek( $row );
							// Split the colums 
							$words = explode( " ", trim($ascfile->current()) );
							// Place the Z in the results array
							$result[$resolution] = (($words[$column] AND $words[$column] != -9999) ? $words[$column] / $metadata['multiplier'] : NULL);
							
							// Free memory
							$ascfile = null;
							// Delete uncompressed file
							if (!$leave_asc) unlink($metadata['directory'].'/'.$resolution.'/'.$metadata['file']);
						};
				};
			// Add the LIDAR download location
			$result['download'] = "https://datamap.gov.wales/maps/lidar-data-download/embed?center=".$longitude.",".$latitude."&zoom=9#/";
			
			// Return the result
			return $result;
		};

	function getCallsignData($callsign) {
		// Format the URL for Callook's free JSON API
		$url = "https://callook.info/" . urlencode($callsign) . "/json";
		
		// Initialize cURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'MyPHPAmateurRadioApp/1.0'); // Good practice
		
		// Execute and close
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		// Check if the request was successful
		if ($httpCode == 200 && $response) {
			return json_decode($response, true);
		}
		
		return false;
	}
	
class QrzApi {
    private $username;
    private $password;
    private $agent = 'PHP-QRZ-Client-v1.0';
    private $sessionKey = null;
    private $apiUrl = 'https://xmldata.qrz.com/xml/current/';

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Authenticates with QRZ and retrieves a Session Key
     */
    private function login() {
        $query = http_build_query([
            'username' => $this->username,
            'password' => $this->password,
            'agent'    => $this->agent
        ]);

        $ch = curl_init($this->apiUrl . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            throw new Exception("Failed to connect to QRZ API server.");
        }

        // Parse the XML response
        $xml = simplexml_load_string($response);
        if (isset($xml->Session->Key)) {
            $this->sessionKey = (string)$xml->Session->Key;
            return true;
        } elseif (isset($xml->Session->Error)) {
            throw new Exception("QRZ Login Error: " . (string)$xml->Session->Error);
        }

        throw new Exception("Unknown error authenticating with QRZ.");
    }

    /**
     * Looks up a callsign and returns an associative array of data
     */
    public function lookup($callsign) {
        // Log in if we don't have an active session key yet
        if (!$this->sessionKey) {
            $this->login();
        }

        $query = http_build_query([
            's'        => $this->sessionKey,
            'callsign' => $callsign
        ]);

        $ch = curl_init($this->apiUrl . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return ['status' => 'error', 'message' => 'Failed to connect to QRZ API server during lookup.'];
        }

        $xml = simplexml_load_string($response);

        // Check for session expiration or error
        if (isset($xml->Session->Error)) {
            $error = (string)$xml->Session->Error;
            
            // If session expired, reset key and try one more time
            if (stripos($error, 'invalid session') !== false || stripos($error, 'expired') !== false) {
                $this->sessionKey = null;
                return $this->lookup($callsign);
            }
            return ['status' => 'error', 'message' => $error];
        }

        // Return data if the callsign record exists
        if (isset($xml->Callsign)) {
            // Convert SimpleXMLElement to a clean associative array
            $data = json_decode(json_encode($xml->Callsign), true);
            return [
                'status' => 'success',
                'data' => $data
            ];
        }

        return ['status' => 'error', 'message' => 'Callsign not found.'];
    }
}

	function get_alt ($altitude_array){
			switch (TRUE) {
					case $altitude_array['1m']:
						$result = $altitude_array['1m'];
						break;
					case $altitude_array['2m']:
						$result = $altitude_array['2m'];
						break;
					case $altitude_array['50m']:
						$result = $altitude_array['50m'];
						break;
					default:
						$result = NULL;
				};
			return $result;
		};
		
	function format_hertz ($frequency,$unit = TRUE){
			switch (TRUE) {
					case $frequency < 1000 :
						$result = $frequency;
						if ($unit) $result .= " Hz";
						break;
					case $frequency < (1000 * 1000) :
						$result = ($frequency / 1000);
						if ($unit) $result .= " kHz";
						break;
					case $frequency < (1000 * 1000 * 1000) :
						$result = ($frequency / (1000 * 1000));
						if ($unit) $result .= " MHz";
						break;
					case $frequency >= (1000 * 1000 * 1000) :
						$result = ($frequency / (1000 * 1000 * 1000));
						if ($unit) $result .= " GHz";
						break;
					default:
						$result = $frequency;
						if ($unit) $result .= " Hz";
						break;
				};
			return $result;
		};

?>