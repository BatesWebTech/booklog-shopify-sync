<?php

class ShopifyInventory extends Shopify {

	var $updatesToRun = [];

	function setQuantityUpdates($vals) {
		$this->quantityUpdates = $vals;
	}

	function doBatchVariantUpdates(){

		foreach($this->updatesToRun as $vid => $newQuantity){
			$res = $this->updateVariant($vid,array('inventory_quantity'=>$newQuantity));
			if( property_exists($res,'errors'))
				echo '<p style="color:red">Error: '. $res->errors .'</p>';
			else
				echo '<p>Updated barcode <b>'.$res->variant->barcode.'</b> to quantity <b>'.$res->variant->inventory_quantity . '</b></p>';
		}
		$this->updatesToRun = [];
	}

	function updateInventory() {
		$products = $this->getAllProducts();

		foreach($products as $product) {
			foreach($product->variants as $variant){
				if( array_key_exists($variant->barcode,$this->quantityUpdates) ) {

					$this->updatesToRun[ $variant->id ] = $this->quantityUpdates[ $variant->barcode ];
					if( count($this->updatesToRun) > 20 )
						$this->doBatchVariantUpdates();

				}
			}
		}
		$this->doBatchVariantUpdates();
	}

}