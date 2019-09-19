<?php

class ShopifyClientWrapper extends ShopifyClient {

	private $apiKey;
	private $password;
	private $secret;
	private $fourTwentyNines = 0;
	var $url;
	public $db = false;
	public $apiVersion = '2019-07';

	function call($method,$path,$params=array()){
		// error_log(var_export($path,1));
		// error_log(var_export($params,1));
		try {
			try_to_update:
			$result = parent::call($method,$path,$params);
			if( isset($this->last_response_headers['X-Shopify-Api-Deprecated-Reason']) ){
				error_log('Deprecated call in Shopify API call: ' . $this->last_response_headers['X-Shopify-Api-Deprecated-Reason'] );
			}

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
		$locs = $this->call("GET","admin/api/{$this->apiVersion}/locations.json");
		return $locs;
	}

	function getProductCount() {
		try {
			$resp = $this->call('GET',"admin/api/{$this->apiVersion}/products/count.json");
		} catch (ShopifyApiException $e) {
			die("Error: " . $e->getMessage() );
		}
		// $resp = json_decode($resp);
		return $resp;
	}

	function getProducts($fields=array()){
		$products = $this->call('GET',"admin/api/{$this->apiVersion}/products.json",$fields);
		return $products;
	}

	function getProductsPage($pageNum,$fields=array()) {
		$input = $fields;
		$input['page'] = $pageNum;
		return $this->getProducts($input);
	}

	function getAllProducts($fields=array()){
		$allProducts = array();
		$limit = 50;

		$fields = array_merge([
			'limit' => $limit,
		], $fields);

		// get first batch to populate our page cursors, with no "page_info" 
		// field (which is where the cursor for the next page goes)
		$allProducts = $this->getProducts($fields);
		$nextCursor = $this->getNextPageCursor();
		while( $nextCursor !== false  ) {
			$fields['page_info'] = $nextCursor;
			$products = $this->getProducts( $fields );
			$allProducts = array_merge($allProducts, $products);
			$nextCursor = $this->getNextPageCursor();
		}

		return $allProducts;
	}

	function getProduct($id,$fields=array()){
		$result = $this->call('GET',"admin/api/{$this->apiVersion}/products/{$id}.json",$fields);
		return $result;
		// return json_decode($result);
	}

	function getInventoryItems($inventory_item_ids, $location_id){
		if( empty($inventory_item_ids) )
			return array();
		if( ! is_array($inventory_item_ids) )
			$inventory_item_ids = explode(',',$inventory_item_ids);
		// inventory_levels.json has a max of 50 inventory_item_ids at a time
		// @see https://help.shopify.com/en/api/reference/inventory/inventorylevel#endpoints
		$i=0;
		$at_a_time = 50;
		$out = [];
		$chunkedIds = array_slice($inventory_item_ids, $i, $at_a_time);
		while ( count($chunkedIds) ) {
			$inventoryIdsString = implode(',',$chunkedIds);
			// @NOTE Technically this endpoint does require cursors to page 
			// through results, but because we're limited to supplying 50 item ids, 
			// we'd never get to the max unless we had 5 locations, which won't 
			// happen in my lifetime.
			$res = $this->call("GET","admin/api/{$this->apiVersion}/inventory_levels.json?inventory_item_ids={$inventoryIdsString}&location_ids={$location_id}&limit=250");
			if( is_array($res) )
				$out = array_merge($out,$res);
			$i = $i + $at_a_time;
			$chunkedIds = array_slice($inventory_item_ids, $i, $at_a_time);
		}

		return $out;
	}

	function updateItem($type,$id,$updatefields){
		$data[$type] = array_merge(array(
			'id' => $id,
			),$updatefields);
		$result = $this->call('PUT',"admin/api/{$this->apiVersion}/{$type}s/{$id}.json",$data);
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
		$result = $this->call('POST',"admin/api/{$this->apiVersion}/inventory_levels/set.json",$data);
		return $result;
	}

	function getAdjacentPageCursor ( $side='next' ) {
		if( ! isset($this->last_response_headers['link']) )
			return false;

		$links = explode( ',' , $this->last_response_headers['link'] );
		foreach($links as $link){
			// A link looks like this:
			//  <https://bates-college-store.myshopify.com/admin/api/2019-07/products.json?limit=150&page_info=eyJkaXJIn0>; rel="previous"
			// (I've shortened the page_info value for readability here)
			// Our regex:
			// 1. Starts at the < 
			// 2. capture everything after the ?
			// 3. until and before the >
			preg_match("_<[^?]*\?([^>]*)>_", $link, $matches);
			parse_str($matches[1], $params);

			$lookfor = ( $side=='next' )
				? 'rel="next"'
				: 'rel="previous"';

			if( strpos($link, $lookfor) !== false)
				return $params['page_info'];
		}
		return false;
	}
	function getNextPageCursor () {
		return $this->getAdjacentPageCursor('next');
	}
	function getPrevPageCursor () {
		return $this->getAdjacentPageCursor('prev');
	}

}
