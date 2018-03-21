<?php

class ShopifyInventory {

	var $updatesToRun = array();
	var $quantityUpdates;
	var $notChanged = array();
	var $changed = array();
	var $errored = array();
	var $matchedBarcodes = array();
	var $notMatched = array();
	var $notMatchedFromShopify = array();
	var $nonExistentInShopifyLocation = array();
	var $matchedBlacklist = array();
	var $OKAYTOCOUNTUNMATCHED = false;
	var $blacklistedBarcodes = false;
	var $debugging = false;
	var $location_id = null;
	var $floatReserve = 0;
	var $productPage = 0;

	// how many items to queue before sending the updates to Shopify
	private $sizeOfBatchUpdates = 20;

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
	/** 
	 * Count the rows from the uploaded spreadsheet which were not matched
	 */
	function countUnmatched() {
		if( ! $this->OKAYTOCOUNTUNMATCHED ) {
			$msg = 'You called countUnmatched too early. You have to updateInventory first';
			error_log($msg);
			echo $msg;
			return false;
		}
		// $this->notMatched = $this->quantityUpdates;
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

	function setLocation( $location_id ){
		$this->location_id = $location_id;
	}
	function getLocation() {
		return $this->location_id;
	}
	
	function setFloatReserve( $float ) {
		$this->floatReserve = (int) $float;
	}

	function setProductPage( $int ){
		$this->productPage = (int) $int;
	}

	function printResultRow($custom,$classes='') {
		$td = array_merge(array(
			'title' => '',
			'barcode' => '',
			'oldQuantity' => '',
			'newQuantity' => '',
			'note' => ''
		),$custom);

		$updatedRowClass = '';
		$oldRowClass = '';

		if( $td['oldQuantity'] < $td['newQuantity'] )
			$updatedRowClass = ' incremented ';
		elseif( $td['oldQuantity'] > $td['newQuantity'] )
			$updatedRowClass = ' decremented ';
		elseif( is_null($td['oldQuantity']) || is_null($td['oldQuantity']) ) {
			$updatedRowClass = $oldRowClass = ' untracked ';
		}

		if( is_array($td['note']) )
			$notes = implode(' ',$td['note']);
		else
			$notes = $td['note'];
		return <<<ROW
		<tr class="{$classes}">
			<td class="result-set-title">{$td['title']}</td>
			<td class="result-set-barcode">{$td['barcode']}</td>
			<td class="result-set-old display-numeric {$oldRowClass}">{$td['oldQuantity']}</td>
			<td class="result-set-updated display-numeric {$updatedRowClass}">{$td['newQuantity']}</td>
			<td class="result-set-note">{$notes}</td>
		</tr>
ROW;
	}

	function doBatchVariantUpdates(){
		global $s;
		foreach($this->updatesToRun as $inventory_item_id => $data){
			$newData = $data['newData'];
			$oldData = $data['oldData'];
			try {

				$res = $s->updateInventory($inventory_item_id, $this->getLocation(), $newData['inventory_quantity']);
				// $this->debug(array('id'=>$vid,'data'=>$newData),'Called updateVariant() with these params');
				// $this->debug($res,'- - got these results');
				if( ! array_key_exists('errors', $res) ) {

					// the "old_inventory_quantity" in the result set is not actually the old inventory. 
					// That value is more for values that might get changed during the transmission of
					// the updated data. So we use the value that we saved earlier for the output below
					$this->countUpdated(1);

					$this->changed[] = array(
						'title' => $oldData['title'],
						'barcode' => $oldData['barcode'],
						'newQuantity' => $res['available'],
						'oldQuantity' => $newData['old_inventory_quantity'],
						'note' => $data['note']
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
			// make sure proxy is receiving stuff sometimes to avoid timeouts
			echo '.';
			flush();
		}
		$this->updatesToRun = array();
	}

	function printUpdateReport(){

		if( count( $this->errored ) ) {
			echo '<table class="results-table error-table">
				<tr class="heading-row">
					<td colspan="3"><h3>'.count($this->errored).' Errors Occured</h3></td>
				</tr>
				<tr>
					<th><i>Title</i></th>
					<th><i>Barcode</i></th>
					<th><i>Error</i></th>
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

		echo '<table class="results-table">
		<tr>
			<th>Title</th>
			<th>Barcode</th>
			<th>Old Inventory</th>
			<th>Updated Inventory</th>
			<th>Note</th>
		</tr>';


		if( count( $this->changed ) ) {
			echo '<tr class="heading-row">
				<td colspan="5" ><h3>Updated</h3></td>
			</tr>';
			foreach($this->changed as $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $variant['barcode'],
					'oldQuantity' => $variant['oldQuantity'],
					'newQuantity' => $variant['newQuantity'],
					'note' => $variant['note']
				), 'updated');
			}
		}

		if( count( $this->notChanged ) ) {
			echo '<tr class="heading-row">
				<td colspan="5"><h3>Matched, no update needed</h3></td>
			</tr>';

			foreach($this->notChanged as $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['oldData']['title'],
					'barcode' => $variant['oldData']['barcode'],
					'oldQuantity' => $variant['newData']['old_inventory_quantity'],
					'newQuantity' => $variant['newData']['inventory_quantity'],
					'note' => $variant['note']
				), 'not-changed');
			}
		}

		if( count( $this->nonExistentInShopifyLocation ) ) {
			echo '<tr class="heading-row">
				<td colspan="5"><h3>Products that were matched in Shopify, but were not present in the given Location</h3></td>
			</tr>';

			foreach($this->nonExistentInShopifyLocation as $barcode => $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $barcode,
					'oldQuantity' => '',
					'newQuantity' => '',
					'note' => ''
				),'not-matched');
			}
		}

		if( count( $this->notMatched ) ) {
			echo '<tr class="heading-row">
				<td colspan="5"><h3>Unmatched barcodes</h3></td>
			</tr>';

			foreach($this->notMatched as $barcode => $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $barcode,
					'oldQuantity' => '',
					'newQuantity' => '',
					'note' => ''
				),'not-matched');
			}
		}

		if( count( $this->notMatchedFromShopify ) ) {
			echo '<tr class="heading-row">
				<td colspan="5"><h3>Products in Shopify that weren\'t matched in the upload</h3></td>
			</tr>';

			foreach($this->notMatchedFromShopify as $barcode => $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['title'],
					'barcode' => $barcode,
					'oldQuantity' => '',
					'newQuantity' => '',
					'note' => ''
				),'not-matched');
			}
		}


		echo '</table>';

	}

	function updateInventory() {
		$this->debug('Beginning to update inventory...');
		global $s;
		if( $this->productPage > 0 ) {
			$this->debug('About to get list of products on page '.$this->productPage.' ...');
			$products = $s->getProductsPage( $this->productPage );
			$this->debug('Got list of '.count($products).' products from shopify api');

		} else {
			$this->debug('About to get list of all products...');
			$products = $s->getAllProducts();
			$this->debug('Got list of all '.count($products).' products from shopify api');
		}

		foreach($products as $product) {
			
			if( empty($product['tags']) )
				$tags = array();
			else {
				$tags = explode(',',$product['tags']);
				$tags = array_map('trim',$tags);
			}

			// get all the inventory items for all this product's variants in one call
			$inventoryItemIds = null;
			foreach($product['variants'] as $variant){

				if( ! $variant['barcode'] ) {
					$this->notMatchedFromShopify[ $variant['barcode'] ] = $variant;
					// $this->debug("{$variant['title']}", "skipped because no barcode (shopify variant title) ");
					continue;
				}

				if( ! array_key_exists($variant['barcode'],$this->quantityUpdates) ) {
					// the shopify barcode is not in the uploaded file
					$this->notMatchedFromShopify[ $variant['barcode'] ] = $variant;
					continue;
				}
				// first time initialization
				if( is_null($inventoryItemIds) )
					$inventoryItemIds = array();
				$inventoryItemIds[] = $variant['inventory_item_id'];
			}
			if( is_null($inventoryItemIds) )
				continue;
			$this->debug('About to get list of inventory items...');
			$inventoryItems = $s->getInventoryItems( $inventoryItemIds, $this->getLocation() );
			$this->debug('Got list of '.count($inventoryItems).' inventoryItems from shopify API');
			if( ! $inventoryItems ){
				$this->nonExistentInShopifyLocation[ $variant['barcode'] ] = $variant;
				continue;
			}
			
			//modify the array so can access inventory items by their id
			foreach($inventoryItems as $integer_key=>$i_item){
				$inventoryItems[$i_item['inventory_item_id']] = $i_item;
				unset($inventoryItems[$integer_key]);
			}

			/* loop through variants again
			 */
			foreach($product['variants'] as $variant){

				if( ! array_key_exists( $variant['inventory_item_id'], $inventoryItems) )
					continue;

				$this->countMatched(1);

				if( is_null($this->quantityUpdates[ $variant['barcode'] ]['inventory_quantity']) )
					$this->debug($this->quantityUpdates[ $variant['barcode'] ],'This barcode had strangeness');

				$title = $this->quantityUpdates[ $variant['barcode'] ]['title'] . " ({$variant['title']})";
				$idAsString = (string) $variant['inventory_item_id'];
				$inventoryItem = $inventoryItems[$variant['inventory_item_id']];

				$variantCustomData = array(
					'oldData' => array(
						'title' => $title,
						'barcode' => $variant['barcode']
					),
					'newData' => array(
						'inventory_quantity' => null,
						'old_inventory_quantity' => null
					),
					'note' => array()
				);

				// if two Shopify products share a barcode, make a note of it.
				if( in_array($variant['barcode'], $this->matchedBarcodes) ){

					$variantCustomData['note'][] = "This barcode has a duplicate in the Shopify stock. Both product variants with this barcode will be updated as necessary.";
				
				} else {
					$this->matchedBarcodes[] = $variant['barcode'];
					// remove this one from the quantityUpdates array b/c we've dealt with it
					// also, then later we'll know which haven't been matched
					/**
					 * 2015-05 commented this so that if Shopify stock has dupe barcodes, 
					 *         they both get updated to the spreadsheet value.
					 */
					// unset($this->quantityUpdates[ $variant['barcode'] ]);
				}
				
				// blacklisted because of barcode entry
				if(in_array($variant['barcode'], $this->getBlackListedBarcodes())) {
					// $this->debug($variant['barcode'],'Barcode skipped cuz blacklist');
					$variantCustomData['note'][] = 'Blacklisted barcode.';

					$this->notChanged[ $idAsString ] 
						= $this->matchedBlacklist[]
						= $variantCustomData;
					continue;
				}

				// blacklisted because of tag
				if( in_array($this->getTagToBlockInventorySync(), $tags)) {
					// $this->debug($variant['barcode'],'Barcode skipped cuz blacklist tags');
					$variantCustomData['note'][] = 'Blacklisted: this product has ' . $this->getTagToBlockInventorySync() . ' tag';

					$this->notChanged[ $idAsString ] 
						= $this->matchedBlacklist[]
						= $variantCustomData;
					continue;
				}

				// if Shopify is set not to track inventory, skip this one
				if( $variant['inventory_management'] != 'shopify' ) {
					// $this->debug($variant['barcode'],'Barcode skipped cuz shopify is not tracking its inventory');
					$variantCustomData['note'][] = 'Untracked by Shopify.';
					$this->notChanged[ $idAsString ] = $variantCustomData;
					continue;
				}

				$oldQuantity = $inventoryItem['available'];
				$quantity = $this->quantityUpdates[ $variant['barcode'] ]['inventory_quantity'];
				$quantityMinusFloat = $quantity - $this->floatReserve;
				if($quantityMinusFloat < 0 )
					$quantityMinusFloat = 0;				
				$variantCustomData['newData']['inventory_quantity'] = $quantityMinusFloat;
				$variantCustomData['newData']['old_inventory_quantity'] = $oldQuantity;

				// only need to update if the quantity is different.
				if( $quantityMinusFloat == $oldQuantity) {
					$this->notChanged[ $idAsString ] = $variantCustomData;
					continue;
				}

				// must cast as a string, otherwise the array falls over
				$this->updatesToRun[ $idAsString ] = $variantCustomData;
				
				$this->debug('Added this inventory item to list of actual updates needed');
				// run the batch if we've maxed our queue. 
				if( count($this->updatesToRun) >= $this->sizeOfBatchUpdates )
					$this->doBatchVariantUpdates();

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

	function getBlackListedBarcodes($withNotes = false) {
		// save in cache if not already
		if($this->blacklistedBarcodes===FALSE) {
			$this->blacklistedBarcodes = array();
			global $s;
			$db = $s->getDB();
			$sql = "SELECT barcode FROM barcodes WHERE action = 'blacklist' AND store = '{$s->shop_domain}'";
			$res = $db->query($sql);
			if($res->num_rows > 0) {
				while($barcode = $res->fetch_array(MYSQLI_NUM)) {
					$thisBarcode = $this->maybe_unserialize($barcode[0]);
					if( is_array($thisBarcode) )
						$this->blacklistedBarcodes[] = $thisBarcode;
					else {
						// backward compat
						$this->blacklistedBarcodes[] = array('barcode'=>$thisBarcode,'reason'=>'');
					}
				}
			}
		}
		$blacklistedBarcodes = $this->blacklistedBarcodes;
		if( ! $withNotes ) {
			$transfer= array();
			foreach($blacklistedBarcodes as $key=>$bc){
				$transfer[] = $bc['barcode'];
			}
			$blacklistedBarcodes = $transfer;
		}
		return $blacklistedBarcodes;
	}

	function saveBlackListedBarcodes($barcodes) {

		global $s;
		$db = $s->getDB();

		// remove previously saved barcodes
		$db->query("DELETE FROM barcodes WHERE store='{$s->shop_domain}' AND action='blacklist'");

		if( is_null($barcodes) )
			return;

		$stmt = $db->prepare("INSERT INTO barcodes (store,barcode,action) VALUES ('{$s->shop_domain}',?,'blacklist');");

		foreach($barcodes as $bc) {
			if( is_array($bc) && empty($bc['barcode']) ) continue;

			$bc = $this->_serialize($bc);

			$stmt->bind_param('s',$bc);
			$stmt->execute();
		}
	}

	function getTagToBlockInventorySync(){
		// @TODO stub
		return '__donotsyncinventory';
	}

	function saveResultsReport() {
		global $s;
		$db = $s->getDB();

		if( $this->productPage )
			$pageNumber = "Page_{$this->productPage}_";
		$save = array(
			'reportName' => 'Inventory_Sync_Results_' . $pageNumber . date('Y-m-d_H-i-s'),
			'errored' => $this->errored,
			'changed' => $this->changed,
			'notChanged' => $this->notChanged,
			'notMatched' => $this->notMatched,
			'notMatchedFromShopify' => $this->notMatchedFromShopify,
			'nonExistentInShopifyLocation' => $this->nonExistentInShopifyLocation,
		);

		$save = $this->_serialize($save);
		$save = $db->real_escape_string($save);

		// only allow one saved report per store
		// $id = $db->query("SELECT id FROM reports WHERE store = '{$s->shop_domain}'");

		// if($id->num_rows > 0 ) {
		// 	$id = $id->fetch_row();
		// 	$id = $id[0];
		// 	$sql = "UPDATE reports SET report = '{$save}', timestamp=NOW() WHERE id={$id}";
		// } else {
			$sql = "INSERT INTO reports (store,report,timestamp) VALUES
				('{$s->shop_domain}','{$save}',NOW());";
		// }
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

	function getAllReports(){
		global $s;
		$db = $s->getDB();
		$sql = "SELECT report, id, timestamp FROM reports WHERE store = '{$s->shop_domain}' ORDER BY timestamp DESC ";
		$result = $db->query($sql);
		$reports = array();
		if($result->num_rows === 0)
			return $reports;
		while ($row = $result->fetch_object()) {
			$report = $this->_unserialize($row->report);
			$reports[] = array(
				'id' => $row->id,
				'name' => $report['reportName'],
				'timestamp' => $row->timestamp
			);
		}
		return $reports;
	}

	function getReport( $id = null) {
		global $s;
		$db = $s->getDB();

		if( is_null($id) )
			$sql = "SELECT report FROM reports WHERE STORE = '{$s->shop_domain}'";
		else
			$sql = "SELECT report FROM reports WHERE STORE = '{$s->shop_domain}' AND id = '{$id}'";

		$result = $db->query($sql);
		if($result->num_rows == 0)
			return false;
		list($report) = $result->fetch_row();
		$report = $this->_unserialize($report);
		return $report;
	}

	function purgeReports() {
		global $s;
		$db = $s->getDB();
		return $db->query("DELETE FROM reports WHERE store = '{$s->shop_domain}'");
	}

	private function _stringifyArray($mixed){
		if( is_array($mixed) )
			return implode(' ',$mixed);
		return $mixed;
	}

	function downloadReport() {
		if( headers_sent() )
			die( 'Cannot download CSV, function called too late');

		$report = $this->getReport();
		if(!$report)
			return false;

		header ('Content-Type: text/csv; charset=UTF-8');
		header ('Content-Disposition: attachment; filename="' .$report['reportName']. '.csv"');

		$csv = fopen('php://output', 'w');

		$headers = array(
			'Title',
			'Barcode',
			'Old Inventory',
			'Updated Inventory',
			'Status',
			'Notes'
		);

		fputcsv($csv, $headers);
		// errors
		foreach($report['errored'] as $err){
			fputcsv($csv,array(
				$err['title'],
				$err['barcode'],
				'',
				'',
				'errored',
				$this->_stringifyArray( $err['error'] )
			));
		}

		foreach($report['changed'] as $ch){
			fputcsv($csv,array(
				$ch['title'],
				$ch['barcode'],
				$ch['oldQuantity'],
				$ch['newQuantity'],
				'changed',
				$this->_stringifyArray( $ch['note'] )
			));
		}

		foreach ($report['notChanged'] as $nc) {
			$note = isset($nc['note'] )
				? $this->_stringifyArray( $nc['note'] )
				: '';
			fputcsv($csv,array(
				$nc['oldData']['title'],
				$nc['oldData']['barcode'],
				$nc['newData']['inventory_quantity'],
				$nc['newData']['old_inventory_quantity'],
				'not changed',
				$note
			));
		}

		foreach ($report['notMatched'] as $nm) {
			fputcsv($csv,array(
				$nm['title'],
				$nm['barcode'],
				'',
				'',
				'not matched (product from uploaded csv)',
				$this->_stringifyArray( $nm['note'] )
			));
		}

		foreach ($report['notMatchedFromShopify'] as $nms) {
			fputcsv($csv,array(
				$nms['title'],
				$nms['barcode'],
				'',
				'',
				'not matched (product in Shopify)',
				$this->_stringifyArray( $nms['note'] )
			));
		}
		foreach ($report['nonExistentInShopifyLocation'] as $nes) {
			fputcsv($csv,array(
				$nes['title'],
				$nes['barcode'],
				'',
				'',
				'not present in this Shopify location',
				$this->_stringifyArray( $nes['note'] )
			));
		}

		fclose($csv);
		exit;
	}

	function parseCSV($filename,$inventoryHeader,$barcodeHeader,$titleHeader) {

		$csv = array();
	
		$fh = fopen( $filename, 'r');
		while(($data = fgetcsv($fh)) !== FALSE) {
			$csv[] = $data;
		}
		fclose($fh);
	
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
		// $this->debug($processedCsv);
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

			$values = array(
				'inventory_quantity' => $inventoryCount,
				'title' => $title
			);

			if( array_key_exists($barcode, $toUpdate) ) {

				$this->errored[] = array(
					'title' => $title,
					'barcode' => $barcode,
					'error' => "Duplicate barcode found in uploaded csv. This quantity ({$inventoryCount}) was not used, previous barcode's quantity ({$toUpdate[$barcode]['inventory_quantity']}) was used instead."
				);
				$this->countErrored(1);
			
			} else {
				$toUpdate[ $barcode ] = $values;
			}

		}

		$this->debug('Completed parsing CSV file with '.count($toUpdate).' valid rows');
		$this->setQuantityUpdates( $toUpdate );
	}

	function debug($value,$title=false){
		if( ! $this->debugging) return false;

		if( $this->debugging == 'error_log' ){
			error_log("$title: ". var_export($value,1));
			return;
		}
		if($title)
			echo '<p style="margin:0;padding:0"><b>'.$title.'</b></p>';
		echo '<pre style="font-size:9px;background:hsl(0,0%,94%);padding:1em;">';
		var_export($value);
		echo '</pre>';
	}

	/**
	 * This function is from Wordpress.org project
	 * ./wp-includes/functions.php
	 */
	function maybe_unserialize( $original ) {
		if ( $this->_is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
			return $this->_unserialize( $original );
		return $original;
	}
	/**
	 * This function is from Wordpress.org project
	 * ./wp-includes/functions.php
	 */
	private function _is_serialized($data,$strict=true){
		// if it isn't a string, it isn't serialized.
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
	 	if ( 'N;' == $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			// Either ; or } must exist.
			if ( false === $semicolon && false === $brace )
				return false;
			// But neither must be in the first X characters.
			if ( false !== $semicolon && $semicolon < 3 )
				return false;
			if ( false !== $brace && $brace < 4 )
				return false;
		}
		$token = $data[0];
		switch ( $token ) {
			case 's' :
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( false === strpos( $data, '"' ) ) {
					return false;
				}
				// or else fall through
			case 'a' :
			case 'O' :
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b' :
			case 'i' :
			case 'd' :
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
		}
		return false;
	}

}
