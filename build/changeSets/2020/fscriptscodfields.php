<?php
/*************************************************************************************************
 * Copyright 2019 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
* Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
* file except in compliance with the License. You can redistribute it and/or modify it
* under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
* granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
* the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
* warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
* applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
* either express or implied. See the License for the specific language governing
* permissions and limitations under the License. You may obtain a copy of the License
* at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
*************************************************************************************************/

class fscriptscodfields extends cbupdaterWorker {

	public function applyChange() {
		if ($this->hasError()) {
			$this->sendError();
		}
		if ($this->isApplied()) {
			$this->sendMsg('Changeset '.get_class($this).' already applied!');
		} else {
			$fscode = array(
				'fscode' => array(
					'columntype'=>'varchar(53)',
					'typeofdata'=>'V~O',
					'uitype'=>'1',
					'displaytype'=>'1',
					'label'=>'FS Code',
					'massedit' => 0,
				),
			);
			$fields = array(
				'Accounts' => array(
					'LBL_ACCOUNT_INFORMATION' => $fscode,
				),
				'Contacts' => array(
					'LBL_CONTACT_INFORMATION' => $fscode,
				),
				'Invoice' => array(
					'LBL_INVOICE_INFORMATION' => $fscode,
				),
				'PurchaseOrder' => array(
					'LBL_PURCHASEORDER_INFORMATION' => $fscode,
				),
				'Products' => array(
					'LBL_PRODUCTS_INFORMATION' => $fscode,
				),
				'Services' => array(
					'LBL_SERVICES_INFORMATION' => $fscode,
				),
				'Vendors' => array(
					'LBL_VENDORS_INFORMATION' => $fscode,
				),
			);
			$this->massCreateFields($fields);
			$this->sendMsg('Changeset '.get_class($this).' applied!');
			$this->markApplied();
		}
		$this->finishExecution();
	}
}