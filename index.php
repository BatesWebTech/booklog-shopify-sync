<?php
// ini_set('display_errors','1');
ini_set('max_execution_time',1800);

require_once 'config.php';
require_once 'ShopifyClient.class.php';
require_once 'ShopifyWrapper.class.php';
require_once 'ShopifyInventory.class.php';

require_once 'setup.php';

if($_POST['download_report']) {
	$Inventory = new ShopifyInventory();
	$result = $Inventory->downloadReport();
	if(!$result)
		echo 'No previous saved report';
	else
		exit;
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Bates Inventory Updater</title>
	<link rel="stylesheet" href="style.css?v2" type="text/css">
	<script src="https://cdn.shopify.com/s/assets/external/app.js"></script>
	<script type="text/javascript">
	var shop = 'https://<?php echo STORE_NAME ?>';
	ajaxurl = shop + '/ajax.php';
    ShopifyApp.init({
      apiKey: '<?php echo API_KEY ?>',
      shopOrigin: shop
    });
  </script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.0.0/jquery.min.js"></script>
	<script src="script.js?v3"></script>
</head>
<body>

<?php

if( isset($_POST['submit'])) {

	if( $_FILES['csv']['tmp_name'] != '' ) {

	// Parse CSV
	/* This is in format
		barcode => new quantity
	 */
	$Inventory = new ShopifyInventory();
	$inventoryCSVHeader = $_POST['csv_header_inventory'];
	$barcodeCSVHeader = $_POST['csv_header_barcode'];
	$titleCSVHeader = $_POST['csv_header_title'];
	$Inventory->parseCSV( $_FILES['csv']['tmp_name'], $inventoryCSVHeader, $barcodeCSVHeader, $titleCSVHeader );
	
	$Inventory->updateInventory();
	?> 
	
	<div class="finish-message">
		<p>Matched <b><?php echo $Inventory->countMatched() ?></b> out of <b><?php echo $Inventory->countCsvRows() ?></b> barcodes.</p>
		<p class="success">Updated <?php echo $Inventory->countUpdated() ?> product variants</p>
		<p class="warning">No changes to <?php echo ($Inventory->countMatched() - $Inventory->countUpdated()) ?> product variants.</p>
		<p class="error">Errored <?php echo $Inventory->countErrored() ?> times.</p>
		<form action="" method="POST">
			<input type="hidden" name="download_report" value="1">
			<button id="saveResultsReport">Download results</button>
		</form>
	</div>

<?php
	$Inventory->printUpdateReport();

	}

} else {
	?>
	
	<form action="" method="POST" class="download-report-form page-top">
		<input type="hidden" name="download_report" value="1">
		<button id="saveResultsReport">Download last report</button>
	</form>

<?php
} // end posted

?>


<form method="post" action="" enctype="multipart/form-data" class="upload-csv">

	<h2>Update Inventory</h2>

	<p>
		<label for="csv_header_inventory">Column header for quantity</label>	
		<input type="text" id="csv_header_inventory" value="in_qty_onhand" name="csv_header_inventory" />
	</p>
	<p>
		<label for="csv_header_barcode">Column header for barcode</label>
		<input type="text" id="csv_header_barcode" value="in_isbn" name="csv_header_barcode" />
	</p>
	<p>
		<label for="csv_header_title">Column header for title</label>
		<input type="text" id="csv_header_title" value="in_title" name="csv_header_title" />
	</p>

	<p>
		<label for="csv">Upload a CSV</label>
		<input type="file" name="csv" id="csv">
	</p>
	<p>
		<input type="submit" value="Run Update" name="submit">
	</p>

</form>


</body>
</html>