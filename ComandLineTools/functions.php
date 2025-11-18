<?php

function date_operating_system_timezone_set() {
	
	$timezones = array(
          'GMT' => 'Europe/London'
		, '0' => 'Europe/London'
		, '1' => 'Europe/London'
		, 'GMT Standard Time' => 'Europe/London'
		, 'BST' => 'Europe/London'
		, 'CEST' => 'Europe/Berlin'
		, '2' => 'Europe/Berlin'
		, 'W. Europe Standard Time' => 'Europe/Berlin'
		, 'CET' => 'Europe/Berlin'
		, 'EET' => 'Europe/Athens'
        , 'EEST' => 'Europe/Kiev'
        , 'IDT' => 'Asia/Jerusalem' // israel day time
        , 'MSK' => 'Europe/Moscow' // Moscow Standard Time
        , 'IST' => 'Asia/Kolkata'
        , 'KRAT' => 'Asia/Krasnoyarsk'
        , '+07' => 'Asia/Krasnoyarsk'
        , 'IRKT' => 'Asia/Irkutsk'
        , 'VLAT' => 'Asia/Vladivostok'
		, 'Eastern Standard Time' => 'America/New_York'
		, 'CDT' => 'America/Chicago'
		, 'Pacific Standard Time' => 'America/Los_Angeles'
		, 'MDT' => 'America/Denver'
		, 'MST' => 'America/Phoenix'
	);

	switch (PHP_OS){
		default:
			throw new Exception("Can'T handle OS: " . PHP_OS);
			break;
		case 'WIN':
		case 'WINNT':
			$timezone = exec('tzutil /g');
			break;
		case 'Linux':
            $timezone = exec('date +%Z');
            break;
        case 'Darwin': // OS X El'Captain+
		case 'MACOS':
            $systemZoneName = readlink('/etc/localtime');    
            $systemZoneName = substr($systemZoneName, strpos($systemZoneName, '/zoneinfo/') + 10);
			break;
	}
	
    
    if (isset($systemZoneName)) {
        echo("> timezone identified as " . $systemZoneName . "\n");
        date_default_timezone_set($systemZoneName);
    } else if( array_key_exists($timezone, $timezones)) {		
		echo("> timezone identified as " . $timezones[$timezone] . " ($timezone)\n");
		date_default_timezone_set($timezones[$timezone]);
	} else {
		die("Unknown Timezone: " . $timezone . "\n");
	}
}