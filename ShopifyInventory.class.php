<?php

class ShopifyInventory {

	var $updatesToRun = array();
	var $quantityUpdates;
	var $notChanged = array();

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
		return ($this->countCsvRows() - $this->countMatched() );
	}
	function countCsvRows($num=false) {
		return $this->count('csv_rows',$num);
	}

	function setQuantityUpdates($vals) {
		$this->quantityUpdates = $vals;
	}

	function printResultRow($custom) {
		$td = array_merge(array(
			'title' => '',
			'barcode' => '',
			'oldQuantity' => '',
			'newQuantity' => ''
		),$custom);

		return <<<ROW
		<tr>
			<td>{$td['title']}</td>
			<td>{$td['barcode']}</td>
			<td class="display-numeric">{$td['oldQuantity']}</td>
			<td class="display-numeric success">{$td['newQuantity']}</td>
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
					// the updated data.
					// So we use the value that we saved earlier for the output below
					$this->countUpdated(1);
					echo $this->printResultRow(array(
						'title' => $oldData['title'],
						'barcode' => $res['barcode'],
						'newQuantity' => $res['inventory_quantity'],
						'oldQuantity' => $newData['old_inventory_quantity']
					));

				}
			} catch (ShopifyApiException $e) {
				$err = $e->getResponse();
				echo '<li style="color:red">Error: '. var_dump($err['errors']) .'</li>';
				$this->countErrored(1);
			}
		}
		$this->updatesToRun = array();
	}

	function updateInventory() {
		global $s;
		$products = $s->getAllProducts();
		echo '<table class="results-table">
				<tr>
					<th>Title</th>
					<th>Barcode</th>
					<th>Old Inventory</th>
					<th>Updated Inventory</th>
				</tr>
				<tr class="heading-row">
					<td colspan="4" ><h3>Updated</h3></td>
				</tr>';
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
		$this->doBatchVariantUpdates();
		$this->countUnmatched();

		if( count( $this->notChanged ) ) {
			echo '<tr class="heading-row">
				<td colspan="4"><h3>Matched, no update needed</h3></td>
			</tr>';

			foreach($this->notChanged as $variant) {
				echo $this->printResultRow(array(
					'title' => $variant['oldData']['title'],
					'barcode' => $variant['oldData']['barcode'],
					'oldQuantity' => $variant['newData']['inventory_quantity']
				));
			}
		}

		echo '</table>';
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
		// echo'<pre>';var_export($toUpdate);echo'</pre>';die;
		$this->setQuantityUpdates( $toUpdate );
	}

}