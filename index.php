<?php
// ini_set('display_errors','1');
ini_set('max_execution_time',1800);

require_once 'config.php';
require_once 'ShopifyClient.class.php';
require_once 'ShopifyWrapper.class.php';
require_once 'ShopifyInventory.class.php';

require_once 'setup.php';

$Inventory = new ShopifyInventory();

if( isset($_POST['download_report']) ) {
	$result = $Inventory->downloadReport(); // this has it's own "exit" statement
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
	<link rel="stylesheet" href="style.css?v4" type="text/css">
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

if( isset($_POST['upload-csv'])) {

	if( $_FILES['csv']['tmp_name'] != '' ) {

	// Parse CSV
	/* This is in format
		barcode => new quantity
	 */
	$inventoryCSVHeader = $_POST['csv_header_inventory'];
	$barcodeCSVHeader = $_POST['csv_header_barcode'];
	$titleCSVHeader = $_POST['csv_header_title'];
	$Inventory->parseCSV( $_FILES['csv']['tmp_name'], $inventoryCSVHeader, $barcodeCSVHeader, $titleCSVHeader );
	
	$Inventory->updateInventory();

	?> 
	
	<div class="finish-message">
		<p>Matched <b><?php echo $Inventory->countMatched() ?></b> out of <b><?php echo $Inventory->countCsvRows() ?></b> barcodes.</p>
		<p class="success">Updated <?php echo $Inventory->countUpdated() ?> product variants</p>
		<p class="warning">No changes to <?php echo ($Inventory->countMatched() - $Inventory->countUpdated()) ?> product variants (<?php echo $Inventory->countMatchedBlacklist() ?> barcodes ignored from blacklist).</p>
		<p class="error">Errored <?php echo $Inventory->countErrored() ?> times.</p>
		<form action="" method="POST">
			<input type="hidden" name="download_report" value="1">
			<button id="saveResultsReport">Download results</button>
		</form>
	</div>

<?php
	$Inventory->printUpdateReport();

	}

}

if( $lastReportDate = $Inventory->getLastReportDate() ) {
	echo '<form action="" method="POST" class="download-report-form page-top">
		<input type="hidden" name="download_report" value="1">
		<p>Last report run: <b>'. date('F j, Y (g:i a)',strtotime($lastReportDate)) .'</b></p>
		<button id="saveResultsReport">Download it</button>
	</form>
	';
}


if( isset($_POST['save-blacklist']) ){
	$Inventory->saveBlackListedBarcodes($_POST['blacklist']);
	echo '<div class="finish-message">Updated Ignored Barcodes</div>';
}




?>


	<div class="col-wrapper">
		<div class="col half">
			<form method="post" action="" enctype="multipart/form-data" class="main-actions">
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
				<input type="submit" value="Run Update" name="upload-csv">
			</p>
			</form>
		</div>

		<div class="col half">
			<form method="post"  class="main-actions">
			<h2>Ignored Barcodes</h2>
				<p>
					<label for="blacklist">List barcodes to ignore, one on each line. These will be remembered between uploads.</label>
					<?php 	
					$saved_barcodes = $Inventory->getBlackListedBarcodes();
					$saved_barcodes = implode("\n",$saved_barcodes);
					 ?>
					<textarea name="blacklist" style="height:285px;"><?php echo $saved_barcodes ?></textarea>
				</p>
				<p>
				<input type="submit" value="Save" name="save-blacklist">
			</form>
		</div>
	</div>
	

</form>




</body>
</html>