<?php

// return cached file if within last 30 secs.
$cache_file = '/tmp/real.php.cache';
if( @$_GET['cached'] != 'no' ) {
    if( time() - @filemtime($cache_file) < 30 ) {
        $fh = fopen( $cache_file, 'r' );
        if( flock( $fh, LOCK_SH ) ) {
            $buf = stream_get_contents( $fh );
            echo $buf;
            exit;
        }
    }
}

// We open and lock the cache file first in order to
// serialize queries and ensure that the charger controller
// is only queried once per cache-period regardless of how many
// http requests we receive.
//
// we use 'a' instead of 'w' to avoid truncating file before lock is obtained.
$fh = fopen( $cache_file, 'a');
if( !$fh ) {
    die( "Could not open cache file\n" );
}

if( !flock( $fh, LOCK_EX )) {
   die( "Could not lock cache file\n" );
}
ftruncate($fh, 0);




/** 
 * Wireframe for realtime calls via ajax
 * Only works with daemon mode
 *
 *
 *
 *
 **/
 
$id_view= 1;


### Prelim

//php set
ini_set('display_errors', 'on');

require("init.php");

$blackbox= new Blackbox();
$modules= $blackbox->modules;
	
foreach ($modules as $mod=>$module) {
        // prevent output that can interfere with usage as a webservice
	ob_start();
	$module->read_direct();
	ob_end_clean();
	$profiler->add("Module $module->name read direct");
}		



//get elements
$query= "
	select * from blackboxelements
	where id_view=':id_view'
	order by panetag,position
";	
$params= array('id_view'=>$id_view);
$result= $db->query($query,$params) or codeerror('DB error',__FILE__,__LINE__);

$buf = '';
while ($row= $db->fetch_row($result)) {
	$id_element=   $row['id_element'];
	$name=         $row['name'];
	$type=         $row['type'];
	$panetag=      $row['panetag'];
	$settings=      unserialize($row['settings']);

	if (!isset($page->tags["Pane::$panetag"])) $page->tags["Pane::$panetag"]= '';
	
	//datapt
	if ($type=='d') {
		$mod=        $settings['module'];
		$dp=         $settings['datapoint'];
		$resolution= $settings['resolution'];
		$style=      $settings['style'];

		if (isset($modules[$mod])) {
			//get current value for dp
			$value= $modules[$mod]->datapoints[$dp]->current_value;
			$unit=  $modules[$mod]->datapoints[$dp]->unit; if (!$unit) $unit='&nbsp;';

			if (preg_match("/^\d+$/",$resolution) and preg_match("/^[\d.]+$/",$value)) $value= number_format($value, (int)$resolution, '.','');

			$buf .= "$name|$value|$unit\n"; //json todo
		}
	}
	
}

fwrite( $fh, $buf );
flock( $fh, LOCK_UN );
fclose( $fh );

echo $buf;


#print $profiler->dump();


?>
