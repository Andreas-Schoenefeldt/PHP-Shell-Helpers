<?php

function date_operating_system_timezone_set() {
	
	$timezones = array(
		  'GMT' => 'Europe/London'
		, '0' => 'Europe/London'
		, '1' => 'Europe/London'
		, 'GMT Standard Time' => 'Europe/London'
		, 'CEST' => 'Europe/Berlin'
		, '2' => 'Europe/Berlin'
		, 'W. Europe Standard Time' => 'Europe/Berlin'
	);
	
	switch (PHP_OS){
		default:
			throw new Exception("Can'T handle OS: " . PHP_OS);
			break;
		case 'WIN':
		case 'WINNT':
			$timezone = exec('tzutil /g');
			break;
		case 'LINUX':
		case 'MACOS':
			$timezone = exec('date +%Z');
			break;
	}
	
	if( array_key_exists($timezone, $timezones)) {		
		echo("> timezone identified as " . $timezones[$timezone] . "\n");
		date_default_timezone_set($timezones[$timezone]);
	} else {
		die("Unknown Timezone: " . $timezone);
	}
}

?>