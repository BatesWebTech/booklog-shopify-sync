<?php

class ShopifyClientWrapper extends ShopifyClient {

	private $apiKey;
	private $password;
	private $secret;
	private $fourTwentyNines = 0;
	var $url;
	public $db = false;

	function call($method,$path,$params=array()){
		// echo '<pre style="font-size:.7em;">---$call---<br>';var_dump(array( 'method' => $method, 'path' => $path, 'params'=> $params )); echo'<br></pre>';
		try {
			try_to_update:
			$result = parent::call($method,$path,$params);

			if( $this->fourTwentyNines > 0 )
				$this->fourTwentyNines = $this->fourTwentyNines - 1;
			if( $this->fourTwentyNines < 0 )
				$this->fourTwentyNines = 0;
				
		} catch (ShopifyApiException $e){

			$headers = $e->getResponseHeaders();

			// if we get a 429 (too many requests), wait and try again
			// @see https://help.shopify.com/api/getting-started/api-call-limit
			if( $headers['http_status_code']  == '429' ) {

				$waitTime = 1000000 * ($this->fourTwentyNines + 1.5);
				$this->fourTwentyNines = $this->fourTwentyNines + 1;

				usleep($waitTime);

				// error_log("429: Wait for {$waitTime} milliseconds...");					
				goto try_to_update;
			}

			// if the error was not 429, rethrow it
			throw new ShopifyApiException(
				$e->getMethod(), 
				$e->getPath(), 
				$e->getParams(), 
				$e->getResponseHeaders(), 
				$e->getResponse()
			);
		}
		return $result;
	}

	function getDB() {
		if( $this->db === false) :
			$this->db = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
			if(mysqli_connect_error())
    			die('Could not connect to database');
    	endif;
    	return $this->db;
	}

	function getSavedToken(){
		$store = $this->shop_domain;
		$db = $this->getDB();
		$stmt = $db->prepare("SELECT token FROM inventory_sync_tokens WHERE store = ?");
		$stmt->bind_param('s',$store);
		$stmt->execute();
		$stmt->bind_result($token);
		$stmt->fetch();
		return (!$token) ? false : $token;
	}

	function saveToken($token){
		$store = $this->shop_domain;
		$db = $this->getDB();
		if($this->getSavedToken())
			$sql = "UPDATE inventory_sync_tokens SET token=? WHERE store=?";
		else
			$sql = "INSERT INTO inventory_sync_tokens (token,store) VALUES (?,?)";

		$stmt = $db->prepare($sql);
		$stmt->bind_param('ss',$token,$store);
		$stmt->execute();
		if($stmt->affected_rows == -1 )
			return false;
		return $stmt->affected_rows;
	}

	function getLocations(){
		$locs = $this->call("GET",'admin/locations.json');
		return $locs;
	}

	function getProductCount() {
		try {
			$resp = $this->call('GET','admin/products/count.json');
		} catch (ShopifyApiException $e) {
			die("Error: " . $e->getMessage() );
		}
		// $resp = json_decode($resp);
		return $resp;
	}

	function getProducts($fields=array()){
		$products = $this->call('GET','admin/products.json',$fields);
		// $products = json_decode($products);
		return $products;
	}

	function getProductsPage($pageNum,$fields) {
		$input = $fields;
		$input['page'] = $pageNum;
		return $this->getProducts($input);
	}

	function getAllProducts($fields=array()){
		$allProducts = array();
		$limit = 50;
		$totalProducts = $this->getProductCount();
		$pageCount = ceil( $totalProducts / $limit );

		for( $pageNum=1; $pageNum<=$pageCount; $pageNum++ ) {
			$products = $this->getProductsPage($pageNum,$fields);
			$allProducts = array_merge( $allProducts, $products);
		}

		return $allProducts;
	}

	function getProduct($id,$fields=array()){
		$result = $this->call('GET',"admin/products/{$id}.json",$fields);
		return $result;
		// return json_decode($result);
	}

	function getInventoryItems($inventory_item_ids, $location_id){
		if( empty($inventory_item_ids) )
			return array();
		if( is_array($inventory_item_ids) )
			$inventory_item_ids = implode(',',$inventory_item_ids);
		$res = $this->call("GET","admin/inventory_levels.json?inventory_item_ids={$inventory_item_ids}&location_ids={$location_id}");
		return $res;
	}

	function updateItem($type,$id,$updatefields){
		$data[$type] = array_merge(array(
			'id' => $id,
			),$updatefields);
		$result = $this->call('PUT',"admin/{$type}s/{$id}.json",$data);
		// $result = json_decode($result);
		return $result;
	}

	function updateProduct($id,$updatefields){
		return $this->updateItem('product',$id,$updatefields);
	}

	/**
	 * update inventory levels for a single inventory item
	 *
	 * @param  int|string  $inventory_item_id 
	 * @param  int|string  $location_id
	 * @param  int|string  $newQuantity
	 * @return result of update call
	 */
	function updateInventory($id, $location_id, $newQuantity){
		$data = array(
			'inventory_item_id' => $id,
			"location_id" => $location_id, 
			"available" => $newQuantity
		);
		$result = $this->call('POST',"admin/inventory_levels/set.json",$data);
		return $result;
	}

}
