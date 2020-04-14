<?php
/*************************************************************************************************
 * Copyright 2019 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS customizations.
 * You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
 * Vizsage Public License (the "License"). You may not use this file except in compliance with the
 * License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
 * and share improvements. However, for proper details please read the full License, available at
 * http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
 * the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
 * applicable law or agreed to in writing, any software distributed under the License is distributed
 * on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the
 * License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
 *************************************************************************************************
 *  Module    : FS Integration
 *  Version   : 1.0
 *  Author    : JPL TSolucio, S. L.
 *************************************************************************************************/
include_once 'vtlib/Vtiger/Module.php';
require_once 'include/Webservices/Create.php';

class corebos_fsi {
	// Configuration Properties
	private $fsurl = '';
	private $fstoken = '';

	// Configuration Keys
	const KEY_ISACTIVE = 'fsi_isactive';
	const KEY_FSURL = 'fs_url';
	const KEY_FSTOKEN = 'fs_token';

	public function __construct() {
		$this->initGlobalScope();
	}

	public function initGlobalScope() {
		$this->fsurl = coreBOS_Settings::getSetting(self::KEY_FSURL, '');
		$this->fstoken = coreBOS_Settings::getSetting(self::KEY_FSTOKEN, '');
	}

	public function saveSettings($isactive, $fsurl, $fstoken) {
		if ($isactive=='1') {
			$this->activateFS();
			$ad = true;
		} else {
			$ad = $this->deactivateFS();
		}
		if ($ad) {
			coreBOS_Settings::setSetting(self::KEY_ISACTIVE, $isactive);
			coreBOS_Settings::setSetting(self::KEY_FSURL, $fsurl);
			coreBOS_Settings::setSetting(self::KEY_FSTOKEN, $fstoken);
		}
		return $ad;
	}

	public function getSettings() {
		return array(
			'isActive' => coreBOS_Settings::getSetting(self::KEY_ISACTIVE, ''),
			'fsurl' => coreBOS_Settings::getSetting(self::KEY_FSURL, ''),
			'fstoken' => coreBOS_Settings::getSetting(self::KEY_FSTOKEN, ''),
		);
	}

	public function isActive() {
		$isactive = coreBOS_Settings::getSetting(self::KEY_ISACTIVE, '0');
		return ($isactive=='1');
	}

	public function activateFS() {
		global $adb, $current_user;
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Contacts' AND targetname='Contacts'");
		// var_dump($mapres); die();
		if ($mapres && $adb->num_rows($mapres)>0) {
		} else {
			$usrwsid = vtws_getEntityId('Users').'x'.$current_user->id;
			$brules = array();
			$default_values =  array(
				'mapname' => '',
				'maptype' => 'Webservice Mapping',
				'targetname' => '',
				'content' => '',
				'description' => '',
				'assigned_user_id' => $usrwsid,
			);
			$rec = $default_values;
			$rec['mapname'] = 'FS:Create Contacts';
			$rec['targetname'] = 'Contacts';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Contacts</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>contactos</methodname>
			<wsresponsetime></wsresponsetime>
			<wsuser></wsuser>
			<wspass></wspass>
			<wsheader>
			<header> 
			<keyname>Content-type</keyname> 
			<keyvalue>application/x-www-form-urlencoded</keyvalue> 
			</header>
			<header> 
			<keyname>token</keyname> 
			<keyvalue>getSetting('.self::KEY_FSTOKEN.')</keyvalue> 
			</header>
			</wsheader>
			<wstype>REST</wstype>
			<inputtype>JSON</inputtype>
			<outputtype>JSON</outputtype> 
			</wsconfig>
				
			<fields>
			<field>
			<fieldname>nombre</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>contactname</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			<field>
			<fieldname>email</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>email1</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			<field>
			<fieldname>cifnif</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>siccode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codcontact</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			var_dump($bruleId);
			die();
		}
			$wfresult = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Contacts on FacturaScripts' and module_name='Contacts'");
		if ($wfresult && $adb->num_rows($wfresult)>0) {
			//workflow exist
		} else {
			$fsworkflow = new VTWorkflowManager($adb);
				$fswflow = $fsworkflow->newWorkFlow('Contacts');
				$fswflow->description = "Create Contacts on FacturaScripts";
				$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
				$fswflow->defaultworkflow = 1;

				$fsworkflow->save($fswflow);
				$fstm = new VTTaskManager($adb);
				$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
				$fstask->active=true;
				$fstask->summary = "Create Contacts on FacturaScripts";
				$fstask->bmapid =$bruleId;
				$fstask->bmapid_display = $rec['mapname'];
				$fstm->saveTask($fstask);
		}
			//update contact map

			// Create Invoice record to fs
			// $mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Invoice' AND targetname='Invoice'");
			// if ($mapres && $adb->num_rows($mapres)>0) {
			// 	// Map exist;
			// 	// var_dump('exist'); die();
			// } else {
				//create map
			// 	$usrwsid = vtws_getEntityId('Users').'x'.$current_user->id;
			// 	$brules = array();
			// 	$default_values =  array(
			// 		'mapname' => '',
			// 		'maptype' => 'Webservice Mapping',
			// 		'targetname' => '',
			// 		'content' => '',
			// 		'description' => '',
			// 		'assigned_user_id' => $usrwsid,
			// 	);
			// 	$rec = $default_values;
			// 	$rec['mapname'] = 'FS:Create Invoice';
			// 	$rec['targetname'] = 'Invoice';
			// 	$rec['content'] = '<map>
			// 	<originmodule>
			// 	<originname>Invoice</originname>
			// 	</originmodule>

			// 	<wsconfig>
			// 	<wsurl>'.coreBOS_Settings::getSetting(self::KEY_FSURL, '').'</wsurl>
			// 	<wshttpmethod>POST</wshttpmethod>
			// 	<methodname>facturaclientes</methodname>
			// 	<wsresponsetime></wsresponsetime>
			// 	<wsuser></wsuser>
			// 	<wspass></wspass>
			// 	<wsheader>
			//  <header>
			//  <keyname>Content-type</keyname>
			//  <keyvalue>application/x-www-form-urlencoded</keyvalue>
			// 	</header>
			//  <header>
			//  <keyname>token</keyname>
			//  <keyvalue>'.coreBOS_Settings::getSetting(self::KEY_FSTOKEN, '').'</keyvalue>
			// 	</header>
			// 	</wsheader>
			// 	<wstype>REST</wstype>
			// 	<inputtype>JSON</inputtype>
			//  <outputtype>JSON</outputtype>
			// 	</wsconfig>

			// 	<fields>
			// 	<field>
			// 	<fieldname>nombre</fieldname>
			// 	<Orgfields>
			// 	<Orgfield>
			// 	<OrgfieldName>invoicename</OrgfieldName>
			// 	<OrgfieldID></OrgfieldID>
			// 	</Orgfield>
			// 	<delimiter></delimiter>
			// 	</Orgfields>
			// 	</field>
			// 	<field>
			// 	<fieldname>email</fieldname>
			// 	<Orgfields>
			// 	<Orgfield>
			// 	<OrgfieldName>email1</OrgfieldName>
			// 	<OrgfieldID></OrgfieldID>
			// 	</Orgfield>
			// 	<delimiter></delimiter>
			// 	</Orgfields>
			// 	</field>
			// 	<field>
			// 	<fieldname>cifnif</fieldname>
			// 	<Orgfields>
			// 	<Orgfield>
			// 	<OrgfieldName>siccode</OrgfieldName>
			// 	<OrgfieldID></OrgfieldID>
			// 	</Orgfield>
			// 	<delimiter></delimiter>
			// 	</Orgfields>
			// 	</field>
			// 	</fields>

			// 	<Response>
			// 	<field>
			// 	<fieldname>data.codinvoice</fieldname>
			// 	<destination>
			// 	<field>fscode</field>
			// 	</destination>
			// 	</field>
			// 	</Response>
			// 	</map>';
			// 	// $brule = vtws_create('cbMap', $rec, $current_user);
			// 	// $brules['FS:Create Contacts'] = $brule['id'];
			// 	$brule = vtws_create('cbMap', $rec, $current_user);
			// 	$idComponents = vtws_getIdComponents($brule['id']);
			// 	$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			// }
				// $fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice on FacturaScripts' and module_name='Invoice'");
				// if ($fswfres && $adb->num_rows($fswfres)>0) {
				// } else {
				// 	$fsworkflow = new VTWorkflowManager($adb);
				// 		$fswflow = $fsworkflow->newWorkFlow('Invoice');
				// 		$fswflow->description = "Create Invoice on FacturaScripts";
				// 		$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
				// 		$fswflow->defaultworkflow = 1;
				// 		$fsworkflow->save($fswflow);

				// 		$fstm = new VTTaskManager($adb);
				// 		$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
				// 		$fstask->active=true;
				// 		$fstask->summary = "Create Invoice on FacturaScripts";
				// 		$fstask->bmapid =$bruleId;
				// 		$fstask->bmapid_display = $rec['mapname'];
				// 		// $fstask->executeImmediately = true;
				// 		$fstm->saveTask($fstask);
				// }
	}

	public function deactivateFS() {
		global $adb;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Contacts on FacturaScripts' and module_name='Contacts'");
		if ($fswfres && $adb->num_rows($fswfres)>0) {
			$wfid = $adb->query_result($fswfres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$wfid'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
				$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice on FacturaScripts' and module_name='Invoice'");
		if ($fswfres && $adb->num_rows($fswfres)>0) {
			$wfid = $adb->query_result($fswfres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$wfid'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
	}
}
?>