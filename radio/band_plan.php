<?php
	$debug_start = time();
	$debug = "Start: ".(time() - $debug_start)."\n";

		require ('../phpscripts/sqlfunctions.php');
		require ('functions.php');

	$debug .= "Start building page: ".(time() - $debug_start)."\n";

	$output = "<!DOCTYPE html>
	<html>
		<head>
			<title>Radio Database - Band Plan</title>
			<link rel='icon' href='favicon.ico' />
			<script src='OpenLayers.js'></script>
			<style type='text/css'>";
				// note background and foreground colours are set in css.php
				$darkmodeSetting = 1;
				ob_start();
					include ('css.php');
				$output .= ob_get_clean();
			$output .= "</style>
			<script>
				function toggle_view(myForm,myBandPlan) {
					// 1. Get the element first
					var target = document.getElementById('div_' + myForm);
					var iframe = document.getElementById('iframe_' + myForm);
					var target_button = document.getElementById('div_' + myForm + '_button');
					
					// 2. Added .style and fixed the missing closing quote on 'none'
					if (target.style.display === 'none') {
						iframe.src = 'bandplan_edited.php?bandplan=' + myBandPlan;
						target.style.display = '';
						target_button.innerHTML = '-';
					} else {
						target.style.display = 'none';
						target_button.innerHTML = '+';
						iframe.src = 'loading.php';
					}
				}
			</script>
		</head>

		<body id='main'>
			<table border='1' cellspacing='0' style='max-width: 80%; margin: 0 auto;'>
				<thead>
					<tr>
						<th rowspan='2' colspan='4'>Frequency Bands</th>
						<th rowspan='2' style='max-width: 260px;'>Status of Amateur Service allocation under this licence</th>
						<th rowspan='2' style='max-width: 260px;'>Status of Amateur Satellite  Service allocation under this licence</th>
						<th colspan='3' style='max-width: 260px;'>Maximum Peak Envelope Power level in Watts (and dB  relative to 1 Watt)</th>
					</tr>
					<tr>
						<th style='max-width: 260px;'>Foundation</th>
						<th style='max-width: 260px;'>Intermediate</th>
						<th style='max-width: 260px;'>Full</th>
					</tr>
				</thead>
				<tbody>";
					$query = "SELECT * FROM `radio_ofcom_bandplan_summary` ORDER BY `frequency_from`";
					$table = sqlfunct_query($query);
					while ($row = sqlfunct_fetch_array($table)){
							$output .= "<tr>
											<td style='text-align: center; border-right: 0;'>&nbsp;<button id='div_".$row['frequency_from']."_button' onclick='toggle_view(\"".$row['frequency_from']."\",\"".$row['bandplan']."\");'>+</button>&nbsp;</td>
											<td style='text-align: right; border-right: 0;'>".format_hertz($row['frequency_from'],false)."</td>
											<td style='border-left: 0; border-right: 0;'>&nbsp;&nbsp;&nbsp;to&nbsp;&nbsp;&nbsp;</td>
											<td style='border-left: 0;'>".format_hertz ($row['frequency_to'],true)."</td>
											<td style='max-width: 200px;'>".$row['status_service']."</td>
											<td style='max-width: 200px;'>".$row['status_satellite']."</td>
											<td style='max-width: 200px;'>".str_replace(chr(10),"<br />",$row['max_pep_foundation'])."</td>
											<td style='max-width: 200px;'>".str_replace(chr(10),"<br />",$row['max_pep_intermediate'])."</td>
											<td style='max-width: 200px;'>".str_replace(chr(10),"<br />",$row['max_pep_full'])."</td>
										</tr>";
							if ($row['bandplan'] /*and file_exists('bandplan/sheet'.$row['bandplan'].'.htm')*/){
									$output .= "<tr id='div_".$row['frequency_from']."' style='background-color:white; display: none;'>
													<td colspan='9'>
														<iframe id='iframe_".$row['frequency_from']."' src='loading.php' width='100%' height='30px' style='border: none;'></iframe>
													</td>
												</tr>";
								};
						};
					$output .= "</tbody>
			</table>";
			print $output;
		
		if (!$_POST['cli']) {
				include('footer.php');
				$output = "</body></html>";
				print $output;
			};
	?>