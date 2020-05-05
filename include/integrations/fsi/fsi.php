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
		//Sync Accounts with facturascripts
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Accounts' AND targetname='Accounts'");
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
			$rec['mapname'] = 'FS:Create Accounts';
			$rec['targetname'] = 'Accounts';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Accounts</originname>
			</originmodule>
			
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>clientes</methodname>
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
			<OrgfieldName>accountname</OrgfieldName>
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
			<fieldname>data.codcliente</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
		}
		$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Accounts on FacturaScripts' and module_name='Accounts'");
		if ($fswfres && $adb->num_rows($fswfres)>0) {
			//workflow exist
		} else {
			$fsworkflow = new VTWorkflowManager($adb);
				$fswflow = $fsworkflow->newWorkFlow('Accounts');
				$fswflow->description = "Create Accounts on FacturaScripts";
				$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
				$fswflow->defaultworkflow = 1;
				$fswflow->test = '';
				$fsworkflow->save($fswflow);
				$fstm = new VTTaskManager($adb);
				$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
				$fstask->active=true;
				$fstask->summary = "Create Accounts on FacturaScripts";
				$fstask->bmapid =$bruleId;
				$fstask->bmapid_display = $rec['mapname'];
				$fstm->saveTask($fstask);
		}
		//update Account record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Accounts' AND targetname='Accounts'");
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
			$rec['mapname'] = 'FS:Update Accounts';
			$rec['targetname'] = 'Accounts';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Accounts</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>clientes</methodname>
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
			<OrgfieldName>accountname</OrgfieldName>
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
			<field>
			<fieldname>codcliente</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
			
			<Response>
			<field>
			<fieldname>data.codcliente</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Accounts on FacturaScripts' and module_name='Accounts'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
				//workflow exist
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Accounts');
					$fswflow->description = "Update Accounts on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test ='';
					$fsworkflow->save($fswflow);
					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Accounts on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		//Sync Contacts with facturascripts
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Contacts' AND targetname='Contacts'");
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
			<fieldname>data.codcontacto</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Contacts on FacturaScripts' and module_name='Contacts'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
				//workflow exist
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Contacts');
					$fswflow->description = "Create Contacts on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);
					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Contacts on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		//update contact record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Contacts' AND targetname='Contacts'");
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
			$rec['mapname'] = 'FS:Update Contacts';
			$rec['targetname'] = 'Contacts';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Contacts</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
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
			<field>
			<fieldname>codcontacto</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codcontacto</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Contacts on FacturaScripts' and module_name='Contacts'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
				//workflow exist
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Contacts');
					$fswflow->description = "Update Contacts on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);
					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Contacts on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}

		// Sync Invoice record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Invoice' AND targetname='Invoice'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create Invoice';
			$rec['targetname'] = 'Invoice';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Invoice</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>facturaclientes</methodname>
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
			<OrgfieldName>invoicename</OrgfieldName>
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
			<fieldname>data.codfacturacliente</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice on FacturaScripts' and module_name='Invoice'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Invoice');
					$fswflow->description = "Create Invoice on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$MANUAL;
					$fswflow->defaultworkflow = 1;
					$fsworkflow->save($fswflow);
					$fswflow->test='';
					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Invoice on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update Invoice record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Invoice' AND targetname='Invoice'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update Invoice';
			$rec['targetname'] = 'Invoice';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Invoice</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>facturaclientes</methodname>
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
			<OrgfieldName>invoicename</OrgfieldName>
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
			<field>
			<fieldname>codfacturacliente</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codfacturacliente</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Invoice on FacturaScripts' and module_name='Invoice'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Invoice');
					$fswflow->description = "Update Invoice on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Invoice on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync Invoice Lines(InventoryDetails) record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Invoice Lines' AND targetname='InventoryDetails'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create Invoice Lines';
			$rec['targetname'] = 'InventoryDetails';
			$rec['content'] = '<map>
			<originmodule>
			<originname>InventoryDetails</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>lineafacturaclientes</methodname>
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
			<OrgfieldName>inventorydetailname</OrgfieldName>
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
			<fieldname>data.codlineafacturacliente</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice Lines on FacturaScripts' and module_name='InventoryDetails'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('InventoryDetails');
					$fswflow->description = "Create Invoice Lines on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Invoice Lines on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update Invoice Lines(InventoryDetails) record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Invoice Lines' AND targetname='InventoryDetails'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update Invoice Lines';
			$rec['targetname'] = 'InventoryDetails';
			$rec['content'] = '<map>
			<originmodule>
			<originname>InventoryDetails</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>lineafacturaclientes</methodname>
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
			<OrgfieldName>inventorydetailname</OrgfieldName>
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
			<field>
			<fieldname>codlineafacturacliente</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codlineafacturacliente</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Invoice Lines on FacturaScripts' and module_name='InventoryDetails'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('InventoryDetails');
					$fswflow->description = "Update Invoice Lines on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Invoice Lines on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync Vendors record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Vendors' AND targetname='Vendors'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create Vendors';
			$rec['targetname'] = 'Vendors';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Vendors</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>proveedores</methodname>
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
			<OrgfieldName>vendorname</OrgfieldName>
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
			<fieldname>data.codproveedore</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Vendors on FacturaScripts' and module_name='Vendors'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Vendors');
					$fswflow->description = "Create Vendors on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Vendors on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update Vendors record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Vendors' AND targetname='Vendors'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update Vendors';
			$rec['targetname'] = 'Vendors';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Vendors</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>proveedores</methodname>
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
			<OrgfieldName>vendorname</OrgfieldName>
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
			<field>
			<fieldname>codproveedore</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codproveedore</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Vendors on FacturaScripts' and module_name='Vendors'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Vendors');
					$fswflow->description = "Update Vendors on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Vendors on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync Products record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Products' AND targetname='Products'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create Products';
			$rec['targetname'] = 'Products';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Products</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>productos</methodname>
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
			<OrgfieldName>productname</OrgfieldName>
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
			<fieldname>data.codproducto</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Products on FacturaScripts' and module_name='Products'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Products');
					$fswflow->description = "Create Products on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Products on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update Products record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Products' AND targetname='Products'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update Products';
			$rec['targetname'] = 'Products';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Products</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>productos</methodname>
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
			<OrgfieldName>productname</OrgfieldName>
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
			<field>
			<fieldname>codproducto</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codproducto</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Products on FacturaScripts' and module_name='Products'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Products');
					$fswflow->description = "Update Products on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Products on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync Services record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create Services' AND targetname='Services'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create Services';
			$rec['targetname'] = 'Services';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Services</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>productos</methodname>
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
			<OrgfieldName>servicename</OrgfieldName>
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
			<fieldname>data.codproducto</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Services on FacturaScripts' and module_name='Services'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Services');
					$fswflow->description = "Create Services on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create Services on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update Services record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update Services' AND targetname='Services'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update Services';
			$rec['targetname'] = 'Services';
			$rec['content'] = '<map>
			<originmodule>
			<originname>Services</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>productos</methodname>
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
			<OrgfieldName>servicename</OrgfieldName>
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
			<field>
			<fieldname>codproducto</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codproducto</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Services on FacturaScripts' and module_name='Services'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('Services');
					$fswflow->description = "Update Services on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update Services on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync PurchaseOrder record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create PurchaseOrder' AND targetname='PurchaseOrder'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create PurchaseOrder';
			$rec['targetname'] = 'PurchaseOrder';
			$rec['content'] = '<map>
			<originmodule>
			<originname>PurchaseOrder</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>facturaproveedores</methodname>
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
			<OrgfieldName>purchaseordername</OrgfieldName>
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
			<fieldname>data.codfacturaproveedore</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create PurchaseOrder on FacturaScripts' and module_name='PurchaseOrder'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('PurchaseOrder');
					$fswflow->description = "Create PurchaseOrder on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$MANUAL;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create PurchaseOrder on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update PurchaseOrder record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update PurchaseOrder' AND targetname='PurchaseOrder'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update PurchaseOrder';
			$rec['targetname'] = 'PurchaseOrder';
			$rec['content'] = '<map>
			<originmodule>
			<originname>PurchaseOrder</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>facturaproveedores</methodname>
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
			<OrgfieldName>purchaseordername</OrgfieldName>
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
			<field>
			<fieldname>codfacturaproveedore</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codfacturaproveedore</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update PurchaseOrder on FacturaScripts' and module_name='PurchaseOrder'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('PurchaseOrder');
					$fswflow->description = "Update PurchaseOrder on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update PurchaseOrder on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Sync PurchaseOrder Lines(InventoryDetails) record with facturascript
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Create PurchaseOrder Lines' AND targetname='InventoryDetails'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Create PurchaseOrder Lines';
			$rec['targetname'] = 'InventoryDetails';
			$rec['content'] = '<map>
			<originmodule>
			<originname>InventoryDetails</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>POST</wshttpmethod>
			<methodname>lineafacturaproveedores</methodname>
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
			<OrgfieldName>inventorydetailname</OrgfieldName>
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
			<fieldname>data.codlineafacturaproveedore</fieldname>
			<destination>
			<field>fscode</field>
			</destination>
			</field>
			<field>
			<fieldname>error</fieldname>
			<destination>
			<field>fsresult</field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create PurchaseOrder Lines on FacturaScripts' and module_name='InventoryDetails'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('InventoryDetails');
					$fswflow->description = "Create PurchaseOrder Lines on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_FIRST_SAVE;
					$fswflow->defaultworkflow = 1;
					$fswflow->task='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Create PurchaseOrder Lines on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
		// Update PurchaseOrder Lines(InventoryDetails) record
		$mapres = $adb->query("SELECT cbmapid FROM vtiger_cbmap WHERE mapname='FS:Update PurchaseOrder Lines' AND targetname='InventoryDetails'");
		if ($mapres && $adb->num_rows($mapres)>0) {
			// Map exist;
		} else {
			//create map
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
			$rec['mapname'] = 'FS:Update PurchaseOrder Lines';
			$rec['targetname'] = 'InventoryDetails';
			$rec['content'] = '<map>
			<originmodule>
			<originname>InventoryDetails</originname>
			</originmodule>
				
			<wsconfig>
			<wsurl>getSetting('.self::KEY_FSURL.')</wsurl>
			<wshttpmethod>PUT</wshttpmethod>
			<methodname>lineafacturaproveedores</methodname>
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
			<OrgfieldName>inventorydetailname</OrgfieldName>
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
			<field>
			<fieldname>codlineafacturaproveedore</fieldname>
			<Orgfields>
			<Orgfield>
			<OrgfieldName>fscode</OrgfieldName>
			<OrgfieldID></OrgfieldID>
			</Orgfield>
			<delimiter></delimiter>
			</Orgfields>
			</field>
			</fields>
				
			<Response>
			<field>
			<fieldname>data.codlineafacturaproveedore</fieldname>
			<destination>
			<field></field>
			</destination>
			</field>
			</Response>
			</map>';
			$brule = vtws_create('cbMap', $rec, $current_user);
			$idComponents = vtws_getIdComponents($brule['id']);
			$bruleId = isset($idComponents[1]) ? $idComponents[1] : 0;
			$fswfres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update PurchaseOrder Lines on FacturaScripts' and module_name='InventoryDetails'");
			if ($fswfres && $adb->num_rows($fswfres)>0) {
			} else {
				$fsworkflow = new VTWorkflowManager($adb);
					$fswflow = $fsworkflow->newWorkFlow('InventoryDetails');
					$fswflow->description = "Update PurchaseOrder Lines on FacturaScripts";
					$fswflow->executionCondition = VTWorkflowManager::$ON_MODIFY;
					$fswflow->defaultworkflow = 1;
					$fswflow->test='';
					$fsworkflow->save($fswflow);

					$fstm = new VTTaskManager($adb);
					$fstask = $fstm->createTask('RunWebserviceWorkflowTask', $fswflow->id);
					$fstask->active=true;
					$fstask->summary = "Update PurchaseOrder Lines on FacturaScripts";
					$fstask->bmapid =$bruleId;
					$fstask->bmapid_display = $rec['mapname'];
					$fstm->saveTask($fstask);
			}
		}
	}

	public function deactivateFS() {
		global $adb;
		$workflowres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Accounts on FacturaScripts' and module_name='Accounts'");
		if ($workflowres && $adb->num_rows($workflowres)>0) {
			$workflowId = $adb->query_result($workflowres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$workflowId'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
		$workflowres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Accounts on FacturaScripts' and module_name='Accounts'");
		if ($workflowres && $adb->num_rows($workflowres)>0) {
			$workflowId = $adb->query_result($workflowres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$workflowId'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
		$workflowres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Contacts on FacturaScripts' and module_name='Contacts'");
		if ($workflowres && $adb->num_rows($workflowres)>0) {
			$workflowId = $adb->query_result($workflowres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$workflowId'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
		$workflowres = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Contacts on FacturaScripts' and module_name='Contacts'");
		if ($workflowres && $adb->num_rows($workflowres)>0) {
			$workflowId = $adb->query_result($workflowres, 0, 0);
			$taskres = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$workflowId'");
			if ($taskres && $adb->num_rows($taskres)>0) {
				$taskid = $adb->query_result($taskres, 0, 0);
				$tm = new VTTaskManager($adb);
				$task = $tm->retrieveTask($taskid);
				$task->active = false;
				$tm->saveTask($task);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice on FacturaScripts' and module_name='Invoice'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Invoice on FacturaScripts' and module_name='Invoice'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Invoice Lines on FacturaScripts' and module_name='InventoryDetails'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Invoice Lines on FacturaScripts' and module_name='InventoryDetails'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create vendors on FacturaScripts' and module_name='vendors'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update vendors on FacturaScripts' and module_name='vendors'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create products on FacturaScripts' and module_name='products'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update products on FacturaScripts' and module_name='products'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create Services on FacturaScripts' and module_name='Services'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update Services on FacturaScripts' and module_name='Services'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create PurchaseOrder on FacturaScripts' and module_name='PurchaseOrder'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update PurchaseOrder on FacturaScripts' and module_name='PurchaseOrder'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Create PurchaseOrder Line on FacturaScripts' and module_name='InventoryDetails'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
		$wfresquery = $adb->query("SELECT workflow_id FROM com_vtiger_workflows WHERE summary='Update PurchaseOrder Line on FacturaScripts' and module_name='InventoryDetails'");
		if ($wfresquery && $adb->num_rows($wfresquery)>0) {
			$fswfid = $adb->query_result($wfresquery, 0, 0);
			$tresquery = $adb->query("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id='$fswfid'");
			if ($tresquery && $adb->num_rows($tresquery)>0) {
				$taskId = $adb->query_result($tresquery, 0, 0);
				$taskman = new VTTaskManager($adb);
				$tasks = $taskman->retrieveTask($taskId);
				$tasks->active = false;
				$tm->saveTask($tasks);
			}
		}
	}
}
?>