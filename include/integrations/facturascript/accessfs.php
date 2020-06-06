<?php
include_once 'vtlib/Vtiger/Module.php';
global $adb,$current_user;

$isactive = coreBOS_Settings::getSetting('fsi_isactive', '');
if ($isactive) {
	$urlapi = coreBOS_Settings::getSetting('fs_url', '');
	$url = explode('api/3',$urlapi);
	$result = $adb->pquery("SELECT fsnick, fspassword FROM vtiger_users WHERE id = ?", array($current_user->id));

	$fsnick = $adb->query_result($result,'0','fsnick');
	$fspassword = $adb->query_result($result,'0','fspassword');
	if (!empty($fsnick) && !empty($fspassword)) {
?>
		<form autocomplete="off" id="fsform" method="post" action="<?php echo $url[0]; ?>" target="_self">
		<input type="hidden" name="fsNick" value="<?php echo $fsnick ?>">
		<input type="hidden" name="fsPassword" value="<?php echo $fspassword; ?>">
		</form>
		<script type="text/javascript">
		document.getElementById('fsform').submit();
		</script>
	<?php
	} else {?>
		<script type="text/javascript">
		window.history.back();
		</script>
	<?php
	} ?>
<?php
} else {?>
	<script type="text/javascript">
	window.history.back();
	</script>
<?php
} ?>
