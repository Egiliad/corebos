<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
set_time_limit(0);
 
global $app_strings, $currentModule;
 
function send_message($id, $message, $progress, $processed, $total) {
	$d = array('message' => $message , 'progress' => $progress, 'processed' => $processed, 'total' => $total);
	echo "id: $id" . PHP_EOL;
	echo 'data:'. json_encode($d) . PHP_EOL;
	echo PHP_EOL;
	ob_flush();
	flush();
}
$recordcount = count($_REQUEST)+3+4;
$recordprocessed = 0;
$id = 1;
$SSE_SOURCE_KEY = '';
foreach ($_REQUEST as $key => $value) {
	$progress = round($recordprocessed / $recordcount * 100, 0);
	$msg = $key.' => '.$value;
	send_message($id++, $msg, $progress, $recordprocessed, $recordcount);
	$recordprocessed++;
	if ($key=='params') {
		$params = json_decode(vtlib_purify($value), true);
		foreach ($params as $pkey => $pvalue) {
			$msg = $pkey.' => '.$pvalue;
			send_message($id++, $msg, $progress, $recordprocessed, $recordcount);
			$recordprocessed++;
			$progress = round($recordprocessed / $recordcount * 100, 0);
			if ($pkey=='SSE_SOURCE_KEY') {
				$SSE_SOURCE_KEY = $pvalue;
				$listparams = coreBOS_Settings::getSetting($SSE_SOURCE_KEY, null);
				$listparams = json_decode($listparams, true);
				foreach ($listparams as $lkey => $lvalue) {
					$msg = $lkey.' => '.$lvalue;
					send_message($id++, $msg, $progress, $recordprocessed, $recordcount);
					$recordprocessed++;
					$progress = round($recordprocessed / $recordcount * 100, 0);
				}
			}
		}
	}
}
 
send_message('CLOSE', $app_strings['processcomplete'], 100, $recordcount, $recordcount);
coreBOS_Settings::delSetting($SSE_SOURCE_KEY);
?>