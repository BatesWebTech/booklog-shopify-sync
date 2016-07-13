<?php

class ShopifyInventory {

	var $updatesToRun = array();
	var $quantityUpdates;
	var $notChanged = array();
	var $changed = array();
	var $errored = array();
	var $notMatched = array();
	var $matchedBlacklist = array();
	var $OKAYTOCOUNTUNMATCHED = false;
	var $blacklistedBarcodes = false;

	var $counts = array(
		'matched' => 0,
		'errored' => 0,
		'csv_rows' => 0,
		'updated' => 0
	);

	function count($which,$num=false) {
		if($num)
			$this->counts[$which] = $this->counts[$which] + $num;
		else
			return $this->counts[$which];
	}

	function countMatched($num=false) {
		return $this->count('matched',$num);
	}
	function countErrored($num=false) {
		return $this->count('errored',$num);
	}
	function countUpdated($num=false) {
		return $this->count('updated',$num);
	}
	function countUnmatched() {
		if( ! $this->OKAYTOCOUNTUNMATCHED ) {
			$msg = 'You called countUnmatched too early. You have to updateInventory first';
			error_log($msg);
			echo $msg;
			return false;
		}
		$this->notMatched = $this->quantityUpdates;
		return ($this->countCsvRows() - $this->countMatched() );
	}
	function countCsvRows($num=false) {
		return $this->count('csv_rows',$num);
	}

	function countMatchedBlacklist() {
		return count($this->matchedBlacklist);
	}

	function countMatchedNotUpdated() {
		$this->countMatched() - $count->countUpdated();
	}

	function setQuantityUpdates($vals) {
		$this->quantityUpdates = $vals;
	}

	function printResultRow($custom,$classes='') {
		$td = array_merge(array(
			'title' => '',
			'barcode' => '',
			'oldQuantity' => '',
			'newQuantity' => ''
		),$custom);

		$updatedRowClass = '';
		$oldRowClass = '';

		if( $td['oldQuantity'] < $td['newQuantity'] )
			$updatedRowClass = ' incremented ';
		elseif( $td['oldQuantity'] > $td['newQuantity'] )
			$updatedRowClass = ' decremented ';
		elseif( $td['oldQuantity'] == 'untracked by Shopify' || $td['oldQuantity'] == 'blacklisted barcode' ) {
			$updatedRowClass = $oldRowClass = ' untracked ';
		}

		return <<<ROW
		<tr class="{$classes}">
			<td class="result-set-title">{$td['title']}</td>
			<td class="result-set-barcode">{$td['barcode']}</td>
			<td class="result-set-old display-numeric {$oldRowClass}">{$td['oldQuantity']}</td>
			<td class="result-set-updated display-numeric {$updatedRowClass}">{$td['newQuantity']}</td>
ROW;
	}

	function doBatchVariantUpdates(){
		global $s;
		foreach($this->updatesToRun as $vid => $data){
			usleep(500000); // rate limit to .5 seconds so we dont' hit our api limit
			$newData = $data['newData'];
			$oldData = $data['oldData'];
			try {
				$res = $s->updateVariant($vid,$newData);
				if( ! array_key_exists('errors', $res) ) {

					// the "old_inventory_quantity" in the result set is not actually the old inventory. 
					// That value is more for values that might get changed during the transmission of
					// the updated data. So we use the value that we saved earlier for the output below
					$this->countUpdated(1);
					$this->changed[] = array(
						'title' => $oldData['title'],
						'barcode' => $oldData['barcode'],
						'newQuantity' => $res['inventory_quantity'],
						'oldQuantity' => $newData['old_inventory_quantity']
					);

				}
			} catch (ShopifyApiException $e) {
				$err = $e->getResponse();
				$this->errored[] = array(
					'title' => $oldData['title'],
					'barcode' => $oldData['barcode'],
					'error' => var_export($err['errors'],1)
				);
				// echo '<li style="color:red">Error: '. var_dump($err['errors']) .'</li>';
				$this->countErrored(1);
			}
		}
		$this->updatesToRun = array();
	}

	function printUpdateReport(){
		echo '<table class="results-table">
		<tr>
			<th>Title</th>
			<th>Barcode</th>
			<th>Old Inventory</th>
			<th>Updated Inventory</th>
		</tr>';


		if( count( $this->changed ) ) {
			echo '<tr class="heading-row">
				<td colspan="4" ><h3>Updated</h3></td>
			</tr>';
			foreach($this->changed as $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $variant['barcode'],
					'oldQuantity' => $variant['oldQuantity'],
					'newQuantity' => $variant['newQuantity']
				), 'updated');
			}
		}

		if( count( $this->notChanged ) ) {
			echo '<tr class="heading-row">
				<td colspan="4"><h3>Matched, no update needed</h3></td>
			</tr>';

			foreach($this->notChanged as $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['oldData']['title'],
					'barcode' => $variant['oldData']['barcode'],
					'oldQuantity' => $variant['newData']['old_inventory_quantity'],
					'newQuantity' => $variant['newData']['inventory_quantity']
				), 'not-changed');
			}
		}


		if( count( $this->notMatched ) ) {
			echo '<tr class="heading-row">
				<td colspan="4"><h3>Unmatched barcodes</h3></td>
			</tr>';

			foreach($this->notMatched as $barcode => $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $barcode,
					'oldQuantity' => '',
					'newQuantity' => ''
				),'not-matched');
			}
		}


		echo '</table>';

		if( count( $this->errored ) ) {
			echo '<h3>'.count($this->errored).' Errors Occured</h3>
				<table>
				<tr>
					<th>Title</th>
					<th>Barcode</th>
					<th>Error</th>
				</tr>';
			foreach($this->errored as $variant) {
				echo "<tr>
					<td>{$variant['title']}</td>
					<td>{$variant['barcode']}</td>
					<td>{$variant['error']}</td>
				</tr>";
			}
			echo '</table>';
		}
	}

	function updateInventory() {
		global $s;
		$products = $s->getAllProducts();

		foreach($products as $product) {
			foreach($product['variants'] as $variant){

				if( ! $variant['barcode'] )
					continue;

				if( array_key_exists($variant['barcode'],$this->quantityUpdates) ) {
	
					$this->countMatched(1);

					$title = $this->quantityUpdates[ $variant['barcode'] ]['title'];
					$quantity = $this->quantityUpdates[ $variant['barcode'] ]['inventory_quantity'];
					$oldQuantity = $variant['inventory_quantity'];

					$idAsString = (string) $variant['id'];

					$variantCustomData = array(
						'oldData' => array(
							'title' => $title,
							'barcode' => $variant['barcode']
						),
						'newData' => array(
							'inventory_quantity' => $quantity,
							'old_inventory_quantity' => $oldQuantity
						)
					);

					// remove this one from the quantityUpdates array b/c we've dealt with it
					// also, then later we'll know which haven't been matched
					unset($this->quantityUpdates[ $variant['barcode'] ]);

					if(in_array($variant['barcode'], $this->getBlackListedBarcodes())) {
						$variantCustomData['newData']['inventory_quantity']
							= $variantCustomData['newData']['old_inventory_quantity']
							= 'blacklisted barcode';

						$this->notChanged[ $idAsString ] 
							= $this->matchedBlacklist
							= $variantCustomData;
						continue;
					}

					// if Shopify is set not to track inventory, skip this one
					if( $variant['inventory_management'] != 'shopify' ) {
						$variantCustomData['newData']['inventory_quantity'] = 'untracked by Shopify';
						$variantCustomData['newData']['old_inventory_quantity'] = 'untracked by Shopify';
						$this->notChanged[ $idAsString ] = $variantCustomData;
						continue;
					}

					// only need to update if the quantity is different.
					if( $quantity == $oldQuantity) {
						$this->notChanged[ $idAsString ] = $variantCustomData;
						continue;
					}

					// must cast as a string, otherwise the array falls over
					$this->updatesToRun[ $idAsString ] = $variantCustomData;
					
					// run the batch 20 at a time. 
					if( count($this->updatesToRun) > 20 )
						$this->doBatchVariantUpdates();

				} else {
					
				}
			}
		}
		$this->OKAYTOCOUNTUNMATCHED = true;
		$this->doBatchVariantUpdates();
		$this->countUnmatched();

		$this->saveResultsReport();

	}

	private function _serialize($val){
		// return base64_encode(serialize($val));
		return serialize($val);
	}
	private function _unserialize($val){
		// return unserialize(base64_decode($val));
		return unserialize($val);
	}

	function getBlackListedBarcodes() {
		if($this->blacklistedBarcodes===FALSE) {
			$this->blacklistedBarcodes = array();
			global $s;
			$db = $s->getDB();
			$sql = "SELECT barcode FROM barcodes WHERE action = 'blacklist' AND store = '{$s->shop_domain}'";
			$res = $db->query($sql);
			if($res->num_rows > 0) {
				while($barcode = $res->fetch_array(MYSQLI_NUM))
					$this->blacklistedBarcodes[] = $barcode[0];
			}
		}
		return $this->blacklistedBarcodes;
	}

	function saveBlackListedBarcodes($barcodes) {
		if( empty($barcodes) )
			return false;
		global $s;
		$db = $s->getDB();
		$barcodes = explode("\n",$barcodes);

		// remove previously saved barcodes
		$db->query("DELETE FROM barcodes WHERE store='{$s->shop_domain}' AND action='blacklist'");

		$stmt = $db->prepare("INSERT INTO barcodes (store,barcode,action) VALUES ('{$s->shop_domain}',?,'blacklist');");

		foreach($barcodes as $bc) {
			$bc = trim($bc);
			if( empty($bc) ) continue;

			$stmt->bind_param('s',$bc);
			$stmt->execute();
		}
	}

	function saveResultsReport() {
		global $s;
		$db = $s->getDB();

		$save = array(
			'errored' => $this->errored,
			'changed' => $this->changed,
			'notChanged' => $this->notChanged,
			'notMatched' => $this->notMatched
		);

		$save = $this->_serialize($save);
		$save = $db->real_escape_string($save);

		// only allow one saved report per store
		$id = $db->query("SELECT id FROM reports WHERE store = '{$s->shop_domain}'");

		if($id->num_rows > 0 ) {
			$id = $id->fetch_row();
			$id = $id[0];
			$sql = "UPDATE reports SET report = '{$save}', timestamp=NOW() WHERE id={$id}";
		} else {
			$sql = "INSERT INTO reports (store,report,timestamp) VALUES
				('{$s->shop_domain}','{$save}',NOW());";
		}
		$result = $db->query($sql);

	}

	function getLastReportDate() {
		global $s;
		$db = $s->getDB();

		$result = $db->query("SELECT timestamp FROM reports WHERE STORE = '{$s->shop_domain}'");
		if($result->num_rows == 0)
			return false;
		list($stamp) = $result->fetch_row();
		return $stamp;
		
	}

	function getReport() {
		global $s;
		$db = $s->getDB();

		$result = $db->query("SELECT report FROM reports WHERE STORE = '{$s->shop_domain}'");
		if($result->num_rows == 0)
			return false;
		list($report) = $result->fetch_row();
		$report = $this->_unserialize($report);
		return $report;
	}

	function downloadReport() {
		if( headers_sent() )
			die( 'Cannot download CSV, function called too late');

		$report = $this->getReport();
		if(!$report)
			return false;

		header ('Content-Type: text/csv; charset=UTF-8');
		header ('Content-Disposition: attachment; filename="inventory-update-report_'.date('Y-m-d-H-i-s').'.csv"');


		$csv = fopen('php://output', 'w');

		$headers = array(
			'Title',
			'Barcode',
			'Old Inventory',
			'Updated Inventory',
			'Status'
		);

		fputcsv($csv, $headers);
		// errors
		foreach($report['errored'] as $err){
			fputcsv($csv,array(
				$err['title'],
				$err['barcode'],
				'',
				'',
				$err['error']
			));
		}

		foreach($report['changed'] as $ch){
			fputcsv($csv,array(
				$ch['title'],
				$ch['barcode'],
				$ch['oldQuantity'],
				$ch['newQuantity'],
				'changed'
			));
		}

		foreach ($report['notChanged'] as $nc) {
			fputcsv($csv,array(
				$nc['oldData']['title'],
				$nc['oldData']['barcode'],
				$nc['newData']['inventory_quantity'],
				$nc['newData']['old_inventory_quantity'],
				'not changed'
			));
		}

		foreach ($report['notMatched'] as $nm) {
			fputcsv($csv,array(
				$nm['title'],
				$nm['barcode'],
				'',
				'',
				'not matched'
			));
		}
		fclose($csv);
		exit;
	}

	function parseCSV($filename,$inventoryHeader,$barcodeHeader,$titleHeader) {
		//  str_getcsv not in < php 5.3, use fgetcsv instead
		$csv = array();
		if( function_exists('str_getcsv') ) {
			$csv = array_map('str_getcsv', file( $filename ));
		} else {
			$fh = fopen( $filename, 'r');
			while(($data = fgetcsv($fh)) !== FALSE) {
				$csv[] = $data;
			}
			fclose($fh);
		}
		if( ! count($csv) ) {
			echo '<h1>Could not parse csv file</h1>';
			return;
		}
		$headerRow = array_shift($csv);
		$processedCsv = array();
		foreach($csv as $rowKey => $row){
			foreach($row as $k=>$v){
				// skip blank values
				if($v=='') continue;
				$processedCsv[$rowKey][ $headerRow[$k] ] = $v;
			}
		}
		unset($csv);
		$this->countCsvRows( count($processedCsv) );
		// $inventoryKey = array_search($inventoryHeader, $headerRow );
		// $barcodeKey = array_search($barcodeHeader, $headerRow );
		$toUpdate = array();
		// echo'<pre>';var_export($processedCsv);echo'</pre>';die;
		foreach($processedCsv as $row) {
			$barcode = $row[$barcodeHeader];
			$inventoryCount = $row[$inventoryHeader];
			$title = $row[$titleHeader];

			$toUpdate[ $barcode ] = array(
				'inventory_quantity' => $inventoryCount,
				'title' => $title
			);
		}

		$this->setQuantityUpdates( $toUpdate );
	}

}