<?php

class ShopifyInventory extends Shopify {

	var $updatesToRun = [];
	var $quantityUpdates;

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

	function doBatchVariantUpdates(){
		foreach($this->updatesToRun as $vid => $newData){

			usleep(500000); // rate limit to .5 seconds so we dont' hit our api limit
			$res = $this->updateVariant($vid,$newData);
			if( property_exists($res,'errors')) {

				echo '<li style="color:red">Error: '. json_encode($res->errors) .'</li>';
				$this->countErrored(1);
			} else {
				$this->countUpdated(1);
				echo '<li>Updated barcode <b>'.$res->variant->barcode.'</b> to quantity <b>'.$res->variant->inventory_quantity . '</b></li>';
			}
		}
		$this->updatesToRun = [];
	}

	function updateInventory() {
		$products = $this->getAllProducts();
		echo '<ol>';
		foreach($products as $product) {
			foreach($product->variants as $variant){
				if( array_key_exists($variant->barcode,$this->quantityUpdates) ) {
	
					$this->countMatched(1);

					$quantity = $this->quantityUpdates[ $variant->barcode ];
					$oldQuantity = $variant->inventory_quantity;

					// if Shopify is set not to track inventory, skip this one
					if( $variant->inventory_management != 'shopify' )
						continue;

					// only need to update if the quantity is different.
					if( $quantity == $oldQuantity)
						continue;

					$this->updatesToRun[ $variant->id ] = array(
						'inventory_quantity' => $quantity,
						'old_inventory_quantity' => $oldQuantity
					);

					// run the batch 20 at a time. 
					if( count($this->updatesToRun) > 20 )
						$this->doBatchVariantUpdates();

				} else {
					
				}
			}
		}
		$this->doBatchVariantUpdates();
		echo '</ol>';
		$this->countUnmatched();
	}

	function parseCSV($filename,$inventoryHeader,$barcodeHeader) {
		$csv = array_map('str_getcsv', file( $filename ));
		$headerRow = array_shift($csv);
		$processedCsv = [];
		foreach($csv as $rowKey => $row){
			foreach($row as $k=>$v){
				$processedCsv[$rowKey][ $headerRow[$k] ] = $v;
			}
		}
		unset($csv);
		$this->countCsvRows( count($processedCsv) );
		// $inventoryKey = array_search($inventoryHeader, $headerRow );
		// $barcodeKey = array_search($barcodeHeader, $headerRow );
		$toUpdate = [];
		// echo'<pre>';var_export($processedCsv);echo'</pre>';die;
		foreach($processedCsv as $row) {
			$barcode = $row[$barcodeHeader];
			$inventoryCount = $row[$inventoryHeader];

			$toUpdate[ $barcode ] = $inventoryCount;
		}
		$this->setQuantityUpdates( $toUpdate );
	}

}