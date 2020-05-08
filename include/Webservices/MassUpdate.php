<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'include/Webservices/Revise.php';

function vtws_massupdate($elements, $user) {
	$failedUpdates = [];
	$successUpdates = [];

	foreach ($elements as $element) {
		try {
			$e = vtws_revise($element, $user);
			$successUpdates[] = $e;
		} catch (Exception $e) {
			$failedUpdates[] = [
				'id' => $element['id'],
				'code' => $e->getCode(),
				'message' => $e->getMessage()
			];
		}
	}

	$result = [
		'success_updates' => $successUpdates,
		'failed_updates' => $failedUpdates
	];

	return $result;
}
?>
