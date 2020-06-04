<?php
include_once 'include/Webservices/ExecuteWorkflow.php';
global $current_user,$log,$adb;
$module = $_REQUEST['module'];  // here we get Invoice or PO
$WSmodule = vtws_getEntityId($module); // here we go to the ws_entity table to get the module WS number
$ids = explode(';', vtlib_purify(trim($_REQUEST['ids'], ';'))); // we convert thecomma separated list of crmids into an array
$crmids = array_map(
    function ($id) {
       global $WSmodule;
       return $WSmodule.'x'.$id;
    },
    $ids
  );// we convert the crmids into wsids
$response = 0;
$step1 = 0;
$step2 = 0;
$step3 = 0;
switch ($module) {
	case 'PurchaseOrder':
		//Get workflow to create purchaseorder without totals
		$fswfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
		 array('Create PurchaseOrder on FacturaScripts', $module));
		//Get workflow to update purchaseorder with totals for the final step to be created.
		$fswfuptres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
		 array('Final step to created PurchaseOrder on FacturaScripts sending totals', $module));
		if (($fswfres && $adb->num_rows($fswfres)>0) && ($fswfuptres && $adb->num_rows($fswfuptres)>0)) {
			$wfcreateinv = $adb->query_result($fswfres, 0, 'workflow_id');
			foreach ($crmids as $crmid) {
				$wsids = json_encode(array($crmid));
				//Create purchaseorder on FS without totals
				$step1 = cbwsExecuteWorkflow($wfcreateinv, $wsids, $current_user);
				if ($step1) {
					$wsinv = explode('x',$crmid);
					$reslines = $adb->pquery("SELECT inventorydetailsid,sequence_no FROM vtiger_inventorydetails
						 INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_inventorydetails.inventorydetailsid
						 WHERE deleted = 0 AND related_to = ? ORDER BY sequence_no ASC", array($wsinv[1]));
					$WSInvDetail = vtws_getEntityId('InventoryDetails');
					//Get Workflow id to sync inventorydetails.
					$fsInvDwfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
					 array('Create Inventory Details (PurchaseOrder) on FacturaScripts', 'InventoryDetails'));
					if ($fsInvDwfres && $adb->num_rows($fsInvDwfres)>0) {
						//Now we sync all the inventory lines.
						$wfcreateinvdt = $adb->query_result($fsInvDwfres, 0, 'workflow_id');
						while ($row = $adb->fetch_array($reslines)) {
							$wsinvd = json_encode(array($WSInvDetail.'x'.$row['inventorydetailsid']));
							$step2 = cbwsExecuteWorkflow($wfcreateinvdt, $wsinvd, $current_user);
							if (!$step2) {
								$response = 'Error when try to sync line: '.$row['sequence_no'];
								continue;
							}
						}
						if ($step2) {
							//Update purchaseorder in FS with the totals
							$wfupdateinvtotals = $adb->query_result($fswfuptres, 0, 'workflow_id');
							$step3 = cbwsExecuteWorkflow($wfupdateinvtotals, $wsids, $current_user);
							if (!$step3) {
								$response = 'Error when try to execute the final step to sync PurchaseOrder with totals';
							} else {
								$response = $step3;
							}
						} else {
							$response = 'Error when try to execute workflow to sync inventory lines';
						}
					} else {
						$response = 'Error when try to get workflow to sync inventory lines';
					}
				} else {
					$response = 'Error when try to create PurchaseOrder without totals';
				}
			}
		}
		break;
	case 'Invoice':
		//Get workflow to create invoice without totals
		$fswfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
		 array('Create Invoice on FacturaScripts', $module));
		//Get workflow to update invoice with totals for the final step to be created.
		$fswfuptres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
		 array('Final step to created Invoice on FacturaScripts sending totals', $module));
		if (($fswfres && $adb->num_rows($fswfres)>0) && ($fswfuptres && $adb->num_rows($fswfuptres)>0)) {
			$wfcreateinv = $adb->query_result($fswfres, 0, 'workflow_id');
			foreach ($crmids as $crmid) {
				$wsids = json_encode(array($crmid));
				//Create invoice on FS without totals
				$step1 = cbwsExecuteWorkflow($wfcreateinv, $wsids, $current_user);
				if ($step1) {
					$wsinv = explode('x',$crmid);
					$reslines = $adb->pquery("SELECT inventorydetailsid,sequence_no FROM vtiger_inventorydetails
						 INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_inventorydetails.inventorydetailsid
						 WHERE deleted = 0 AND related_to = ? ORDER BY sequence_no ASC", array($wsinv[1]));
					$WSInvDetail = vtws_getEntityId('InventoryDetails');
					//Get Workflow id to sync inventorydetails.
					$fsInvDwfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
					 array('Create Inventory Details on FacturaScripts', 'InventoryDetails'));
					if ($fsInvDwfres && $adb->num_rows($fsInvDwfres)>0) {
						//Now we sync all the inventory lines.
						$wfcreateinvdt = $adb->query_result($fsInvDwfres, 0, 'workflow_id');
						while ($row = $adb->fetch_array($reslines)) {
							$wsinvd = json_encode(array($WSInvDetail.'x'.$row['inventorydetailsid']));
							$step2 = cbwsExecuteWorkflow($wfcreateinvdt, $wsinvd, $current_user);
							if (!$step2) {
								$response = 'Error when try to sync line: '.$row['sequence_no'];
								continue;
							}
						}
						if ($step2) {
							//Update invoice in FS with the totals
							$wfupdateinvtotals = $adb->query_result($fswfuptres, 0, 'workflow_id');
							$step3 = cbwsExecuteWorkflow($wfupdateinvtotals, $wsids, $current_user);
							if (!$step3) {
								$response = 'Error when try to execute the final step to sync Invoice with totals';
							} else {
								$response = $step3;
							}
						} else {
							$response = 'Error when try to execute workflow to sync inventory lines';
						}
					} else {
						$response = 'Error when try to get workflow to sync inventory lines';
					}
				} else {
					$response = 'Error when try to create Invoice without totals';
				}
			}
		}
		break;
}
echo json_encode($response);
?>
