<?php

class ShopifyClientWrapper extends ShopifyClient {

	private $apiKey;
	private $password;
	private $secret;
	var $url;

	// function __construct($url,$apiKey,$password) {
	// 	$this->apiKey = $apiKey;
	// 	$this->password = $password;
	// 	$this->storeUrl = $url;
	// }

	// private function getApiKey() {
	// 	return $this->apiKey;
	// }

	// private function getPassword() {
	// 	return $this->password;
	// }

	// function getStoreUrl() {
	// 	return $this->storeUrl;
	// }

	// function buildUrl($path) {
	// 	$base = sprintf("https://%s.myshopify.com/admin", $this->getStoreUrl() );

	// 	if($path[0] != '/')
	// 		$path = '/'.$path;

	// 	return $base . $path;
	// }

	// function call($method,$path,$data=array()) {
	// 	$url = $this->buildUrl($path);

	// 	$curl = curl_init();

	// 	switch($method) {
	// 		case 'post' :
	// 			break;
	// 		case 'put' :
	// 			$json = json_encode($data);
	// 			curl_setopt_array($curl,array(
	// 				CURLOPT_POSTFIELDS => $json,
	// 				CURLOPT_CUSTOMREQUEST => 'PUT', // NOT curlopt_put=1, THAT DIDN'T WORK
	// 				CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
	// 			));
	// 			break;
	// 		case 'get' :
	// 			if ($data && count($data))
	// 				$url = sprintf("%s?%s", $url, http_build_query($data));
	// 			break;	
	// 	}

	// 	curl_setopt_array($curl,array(
	// 		CURLOPT_RETURNTRANSFER => 1,
	// 		CURLOPT_URL => $url,
	// 		CURLOPT_USERPWD => $this->getApiKey() . ':' . $this->getPassword()
	// 	));

	// 	// echo'<pre>';var_export($url);echo'</pre>';
	// 	// echo'<pre>';var_export($json);echo'</pre>';
	// 	// die;

	// 	$result = curl_exec($curl);
	// 	curl_close($curl);
	// 	return $result;
	// }

	function getProductCount() {
		$resp = $this->call('GET','admin/products/count.json');
		// $resp = json_decode($resp);
		return $resp['count'];
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
		$limit = '50';
		$totalProducts = 400;//$this->getProductCount();
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

	function updateItem($type,$id,$updatefields){
		$data[$type] = array_merge(array(
			'id' => (int) $id,
			),$updatefields);
		$result = $this->call('PUT',"admin/{$type}s/{$id}.json",$data);
		// $result = json_decode($result);
		return $result;
	}

	function updateProduct($id,$updatefields){
		return $this->updateItem('product',$id,$updatefields);
	}
	function updateVariant($id,$updatefields){
		return $this->updateItem('variant',$id,$updatefields);
	}

}