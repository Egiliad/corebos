<?php
include_once 'include/Webservices/ExecuteWorkflow.php';
global $current_user;
$module = vtlib_purify($_REQUEST['module']);  // here we get Invoice or PO
$WSmodule = vtws_getEntityId($module); // here we go to the ws_entity table to get the module WS number
$ids = explode(',', vtlib_purify($_REQUEST['ids'])); // we convert thecomma separated list of crmids into an array
$wsids = array_map(
  function ($id) {
     global $WSmodule;
     return $WSmodule.'x'.$id;
  },
  $ids
);// we convert the crmids into wsids
$fswfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?', 
array('Create Accounts on FacturaScripts', $module));
if ($fswfres && $adb->num_rows($fswfres)>0) {
  $workflow = $adb->query_result($fswfres, 0, 'workflow_id');
}//get the workflow ID to execute
$response = cbwsExecuteWorkflow($workflow, $wsids, $current_user);
// check the response for errors and inform
?>