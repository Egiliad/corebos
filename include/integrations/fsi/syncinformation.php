<?php
/*************************************************************************************************
 * Copyright 2020 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS customizations.
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
 *************************************************************************************************/
require_once 'include/MemoryLimitManager/MemoryLimitManager.php';
include_once 'include/Webservices/ExecuteWorkflow.php';

class corebos_sync {

    public function syncInformation() {
        global $adb, $current_user;
        $manager = new MemoryLimitManager();
        $phplimit = $manager->getPHPLimitInMegaBytes();
        $manager->setBufferInMegaBytes(100);
        $manager->setLimitInMegaBytes($phplimit);
        $batch = 10000;
        $modules2sync = array('Accounts','Vendors');
        $fssync_working = coreBOS_Settings::getSetting('fssync_working', null);
        if (is_null($fssync_working) || $fssync_working=='0') { // not working > we start
            $fssync_working = coreBOS_Settings::setSetting('fssync_working', '1');
            coreBOS_Settings::setSetting('fssync_startedate', date('Y-m-d H:i:s'));
            $fssync_startedate = coreBOS_Settings::getSetting('fssync_startedate', date('Y-m-d H:i:s'));
        } else { // working > we continue
            $fssync_startedate = coreBOS_Settings::getSetting('fssync_startedate', date('Y-m-d H:i:s'));
        }
        foreach ($modules2sync as $module) {
            $focus = CRMEntity::getInstance($module);
            $query = 'SELECT crmid FROM '.$focus->table_name.' inner join vtiger_crmentity on crmid='.$focus->table_index.' WHERE deleted=0 and fssynced = 0 and modifiedtime<?';
            $workflow = $this->getWorkflowFor($module);
            $querycache = array();
            $cnt=1;
            $finished = true;
            $rs = $adb->pquery($query, array($fssync_startedate));
            $WSmodule = vtws_getEntityId($module);
            while ($record = $rs->fetchRow()) {
                $wscrmid = $WSmodule.'x'.$record['crmid'];
                $wsid = json_encode(array($wscrmid));
                cbwsExecuteWorkflow($workflow, $wsid, $current_user);
                if ($cnt==$batch) {
                    $this->sendMsg('BATCH PROCESSED '.$cnt);
                }
                $cnt++;
                if ($manager->isLimitReached()) {
                    $this->sendMsgError('This changeset HAS NOT FINISHED. You must launch it again!');
                    $finished = false;
                    break 2;
                }
            }
        }
        // finish Execution
        if ($finished) {
            coreBOS_Settings::setSetting('fssync_working', '0');
            echo '<script type="text/javascript"> displayResult('.$cnt.'); </script>';
        }
    }
    public function getWorkflowFor($module){
        global $adb;
        switch($module){
            case 'Accounts':
                $fswfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
                array('Create Accounts on FacturaScripts', $module));
                $workflowId = $adb->query_result($fswfres, 0, 0);
                return $workflowId;
            break;
            case 'Vendors':
                $fswfres = $adb->pquery('SELECT workflow_id FROM com_vtiger_workflows WHERE summary=? and module_name=?',
                array('Create vendors on FacturaScripts', $module));
                $workflowId = $adb->query_result($fswfres, 0, 0);
                return $workflowId;
            break;
        }
    }
    public function sendMsgError($msg) {
        echo '<div class="slds-col slds-size_10-of-10"><span style="color:red">'.$msg.'</span></div>';
    }
    public function sendMsg($msg) {
        echo '<div class="slds-col slds-size_10-of-10">'.$msg.'</div>';
    }

}

$smarty->display('Smarty/templates/MassEditHtml.tpl');
?>
<script>
function displayResult(cnt){
	// console.log('BATCH PROCESSED '.$cnt);
	var rdo = document.getElementById('relresultssection');
	rdo.style.visibility = 'visible';
	rdo.style.display = 'block';
		__addLog('BATCH PROCESSED' + '&nbsp;&nbsp;' + cnt);
		var pBar = document.getElementById('progressor');
		pBar.value = pBar.max; //max out the progress bar
		var perc = document.getElementById('percentage');
		perc.innerHTML   = 100  + '% &nbsp;&nbsp;' + cnt + '/' + cnt;
}
</script>