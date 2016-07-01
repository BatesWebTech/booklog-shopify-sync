<?php
// ini_set('display_errors','1');
ini_set('max_execution_time',1800);
?>
<!DOCTYPE html>
<html>
<head>
	<title>Bates Inventory Updater</title>
	<link rel="stylesheet" href="style.css" type="text/css">
</head>
<body>

<?php
// ini_set('display_errors', '1');

$loginform = <<<FORM
<form action="" method="post" class="login">
	Enter the password: <input type="text" value="" name="cookiepwd">
	<input type="submit" name="submit-cookie" value="login">
	</form>
FORM;
// Just some temporary easy security
if( isset($_POST['cookiepwd']) ) {
	sleep(2);
	if( $_POST['cookiepwd'] == 'goodness' ){
		setcookie('nosecurity','this is just temporary');
		// flow through
	} else {
		echo '<p class="error">Wrong</p>';
		echo $loginform;
		die;
	}
} else if( $_COOKIE['nosecurity'] !== 'this is just temporary' ) {
	echo $loginform;
	die;
}



if( isset($_POST['submit'])) :

	if( $_FILES['csv']['tmp_name'] != '' ) {


	echo '<div class="ouput">';
	// Setup Shopify Connection
	require 'Shopify.class.php';
	require 'ShopifyInventory.class.php';

	define('STORE_NAME','bates-college-store-dev');
	define('API_KEY','APIKEYGOESHERE');
	define('API_SECRET','APIKEYGOESHERE');
	define('API_PASSWORD','APIKEYGOESHERE');
	$s = new ShopifyInventory(STORE_NAME,API_KEY,API_PASSWORD);


	// Parse CSV
	/* This is in format
		barcode => new quantity
	 */
	$inventoryCSVHeader = 'in_qty_onhand';
	$barcodeCSVHeader = 'in_isbn';
	$s->parseCSV( $_FILES['csv']['tmp_name'], $inventoryCSVHeader, $barcodeCSVHeader );
	$s->updateInventory();

	echo '</div>';

	?> 
	
	<div class="finish-message">
		<p>Matched <b><?php echo $s->countMatched() ?></b> out of <b><?php echo $s->countCsvRows() ?></b> barcodes.</p>
		<p class="success">Updated <?php echo $s->countUpdated() ?> product variants</p>
		<p class="warning">No changes to <?php echo ($s->countMatched() - $s->countUpdated()) ?> product variants.</p>
		<p class="error">Errored <?php echo $s->countErrored() ?> times.</p>
	</div>

<?php
	}
endif; // end posted


?>

<form method="post" action="" enctype="multipart/form-data" class="upload-csv">

<h2>Upload a csv file</h2>
<input type="file" name="csv" value=""><input type="submit" value="Run Update" name="submit">

</form>


</body>
</html>