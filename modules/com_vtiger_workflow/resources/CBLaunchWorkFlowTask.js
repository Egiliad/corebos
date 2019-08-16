let fileurl = 'module=com_vtiger_workflow&action=com_vtiger_workflowAjax&file=getrelatedmods&currentmodule='+moduleName;
$(document).ready(function () {
	jQuery.ajax({
		method: 'GET',
		url: 'index.php?' + fileurl
	}).done(function (modlistres) {
		document.getElementById('relModlist_type').innerHTML =modlistres;
	});
});

function doGlobalGridSelect() {
	let ops = document.querySelectorAll("input[name='options[]']");
	let globalcheck = document.getElementById('checkbox-0').checked;
	let idlist = '';
	for (let op = 0; op < ops.length; ++op) {
		ops[op].checked = globalcheck;
		if (globalcheck) {
			if (idlist=='') {
				idlist = ops[op].value;
			} else {
				idlist += ','+ops[op].value;
			}
		}
	}
	document.getElementById('idlist').value = idlist;
}


