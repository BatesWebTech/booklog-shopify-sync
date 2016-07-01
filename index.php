<?php
ini_set('display_errors', '1');

require 'Shopify.class.php';
require 'ShopifyInventory.class.php';


define('STORE_NAME','bates-college-store-dev');
define('API_KEY','APIKEYGOESHERE');
define('API_SECRET','APIKEYGOESHERE');
define('API_PASSWORD','APIKEYGOESHERE');

$s = new ShopifyInventory(STORE_NAME,API_KEY,API_PASSWORD);

/* This is in format
	barcode => new quantity
 */
$s->setQuantityUpdates(array(
	'684418864012' => '2'
));

$s->updateInventory();
// 
// echo'<pre>';var_export($s->getProduct(7351058691));echo'</pre>';
// $s->updateVariant('XXXX23051571459',array('inventory_quantity'=>'3'));