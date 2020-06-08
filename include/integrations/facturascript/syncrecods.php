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
$response = getTranslatedString('Invoice_synced_correct', 'Invoice');
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
					$reslines = $adb->pquery("SELECT inventorydetailsid,sequence_no,tax_percent FROM vtiger_inventorydetails
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
							$fstaxtype = getFSTaxType($row['tax_percent']);
							$context = json_encode(array('codimpuesto' => $fstaxtype));
							$step2 = cbwsExecuteWorkflowWithContext($wfcreateinvdt, $wsinvd, $context,$current_user);
							if (!$step2) {
								$response = getTranslatedString('Error_when_sync_line', 'PurchaseOrder').$row['sequence_no'];
								continue;
							}
						}
						if ($step2) {
							//Update purchaseorder in FS with the totals
							$wfupdateinvtotals = $adb->query_result($fswfuptres, 0, 'workflow_id');
							$step3 = cbwsExecuteWorkflow($wfupdateinvtotals, $wsids, $current_user);
							if (!$step3) {
								$response = getTranslatedString('Error_when_sync_purchaseorder_with_total', 'PurchaseOrder');
							}
						} else {
							$response = getTranslatedString('Error_when_sync_Inventory_Line_PurchaseOrder', 'PurchaseOrder');
						}
					} else {
						$response = getTranslatedString('Error_when_get_workflow_sync_Inventory_PurchaseOrder', 'PurchaseOrder');
					}
				} else {
					$response = getTranslatedString('Error_when_create_PurchaseOrder_without_total', 'PurchaseOrder');
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
					$reslines = $adb->pquery("SELECT inventorydetailsid,sequence_no,tax_percent FROM vtiger_inventorydetails
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
							$fstaxtype = getFSTaxType($row['tax_percent']);
							$context = json_encode(array('codimpuesto' => $fstaxtype));
							$step2 = cbwsExecuteWorkflowWithContext($wfcreateinvdt, $wsinvd, $context,$current_user);
							if (!$step2) {
								$response = getTranslatedString('Error_when_sync_line', 'Invoice').$row['sequence_no'];
								continue;
							}
						}
						if ($step2) {
							//Update invoice in FS with the totals
							$wfupdateinvtotals = $adb->query_result($fswfuptres, 0, 'workflow_id');
							$step3 = cbwsExecuteWorkflow($wfupdateinvtotals, $wsids, $current_user);
							if (!$step3) {
								$response = getTranslatedString('Error_when_sync_Invoice_with_total', 'Invoice');
							}
						} else {
							$response = getTranslatedString('Error_when_sync_InventoryLine_line_Invoice', 'Invoice');
						}
					} else {
						$response = getTranslatedString('Error_when_get_workflow_sync_Inventory_line_Invoice', 'Invoice');
					}
				} else {
					$response = getTranslatedString('Error_when_create_Invoice_without_total', 'Invoice');
				}
			}
		}
		break;
}
echo json_encode($response);

function getFSTaxType($taxtype) {
	$fstaxtype = '';
	switch ($taxtype) {
		case '21.00':
			$fstaxtype = 'IVA21';
			break;
		case '10.00':
			$fstaxtype = 'IVA10';
			break;
		case '4.00':
			$fstaxtype = 'IVA4';
			break;
		case '0.00':
			$fstaxtype = 'IVA0';
			break;
		case '7.00':
			$fstaxtype = 'IGIC7';
			break;
		case '3.00':
			$fstaxtype = 'IGIC3';
			break;
	}
	return $fstaxtype;
}
?>
