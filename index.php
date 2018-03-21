<?php
// ini_set('display_errors','1');
ini_set('max_execution_time',1800);

require_once 'config.php';
require_once 'ShopifyClient.class.php';
require_once 'ShopifyWrapper.class.php';
require_once 'ShopifyInventory.class.php';

require_once 'setup.php';

$Inventory = new ShopifyInventory();
// $Inventory->debugging = "error_log";

if( isset($_POST['download_report']) ) {

	$reportIds = isset($_POST['reports-to-download'])
		? $_POST['reports-to-download']
		: null;
	$result = $Inventory->downloadReports($reportIds); // this has it's own "exit" statement
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
	<link rel="stylesheet" href="style.css?v7" type="text/css">
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
	<script src="script.js?v7"></script>
</head>
<body>

<?php
if( isset($_REQUEST['current-page']) ){
	switch($_REQUEST['current-page']){
		case 'reports' :
			$currentPageReports = ' current-page ';
			break;
		default :
			$currentPageMain = ' current-page ';
	}
} else {
	$currentPageMain = ' current-page ';
}
?>

<nav class="main-nav">
	<a href="#pageMain" class="<?= $currentPageMain ?>">Main Page</a>
	<a href="#pageReports" class="<?= $currentPageReports ?>">Reports</a>
</nav>

<div id="bates-inventory-sync-pageMain" class="bates-inventory-sync-page <?= $currentPageMain ?>">
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
	
	$Inventory->setLocation( $_POST['location-to-sync'] );
	if( $_POST['float_reserve'] != 0 )
		$Inventory->setFloatReserve( $_POST['float_reserve'] );
	if( isset($_POST['pagebreak_import']) )
		$Inventory->setProductPage( $_POST['pagebreak_page_number'] );
	
	$Inventory->updateInventory();

	?> 
	
	<div class="finish-message">
		<?php 
		if( isset($_POST['pagebreak_import']) ){
			echo "<p><i>Sync results for Shopify product page {$_POST['pagebreak_page_number']}</i></p>";
		}
		?>
		<p>Matched <b><?php echo $Inventory->countMatched() ?></b> out of <b><?php echo $Inventory->countCsvRows() ?></b> barcodes.</p>
		<p class="success">Updated <?php echo $Inventory->countUpdated() ?> product variants</p>
		<p class="warning">No changes to <?php echo ($Inventory->countMatched() - $Inventory->countUpdated()) ?> product variants (<?php echo $Inventory->countMatchedBlacklist() ?> barcodes ignored from blacklist).</p>
		<p class="error">Errored <?php echo $Inventory->countErrored() ?> times.</p>
		<form action="" method="POST">
			<input type="hidden" name="download_report" value="1">
			<button id="saveResultsReport" class="action-button">Download this report</button>
		</form>
	</div>

	<p style="margin:1em;"><a href="#csv-upload-form">jump to form</a></p>

<?php
	$Inventory->printUpdateReport();

	}

}

if( isset($_POST['save-blacklist']) ){
	$blacklist = array();

	// emptying out the list
	$firstBlacklistItem = current($_POST['blacklist']);
	if( count($_POST['blacklist']) == 1 && $firstBlacklistItem['barcode']=='' ) {
		$blacklist = null;
	} else {

		foreach($_POST['blacklist'] as $key=>$blEntry){
			if( ! empty($blEntry['barcode']) )
				$blacklist[] = $blEntry;
		}

	}

	$Inventory->saveBlackListedBarcodes($blacklist);
	echo '<div class="message">Updated Ignored Barcodes</div>';
}




?>


	<div class="col-wrapper">
		<div class="col half">
			<form method="post" action="" enctype="multipart/form-data" class="main-actions csv-upload-form" id="csv-upload-form">

			<input type="hidden" name="current-page" value="main">
			<h1>Update Inventory</h1>

			<div class="csv-upload-input-section">
				<label for="csv" class="block">Upload a CSV</label>
				<input type="file" name="csv" id="csv">
			</div>
			
			<div class="pagebreak-form-inputs">
				<label class="block">Break Import to multiple pages</label>
				<p class="incidental">This is useful if the import process is taking too long</p>
				<p>
					<input type="checkbox" name="pagebreak_import" value="1" id="pagebreak_import" <?= $pagebreak_importChecked ?>> 
					<label for="pagebreak_import">Break import into multiple pages</label>
				</p>
				<p style="">
					Page <input type="number" style="width:36px;" min="1" max="<?= $pages ?>" value="<?= $nextPage ?>" name="pagebreak_page_number"> of <?= $pages ?>
				</p>
			</div>

			<p>
				<input type="submit" value="Run Update" name="upload-csv">
			</p>

			<hr>
			<h2>Advanced</h2>
			<?php
			$locs = $s->getLocations();
			// if there is only one location, automatically use that one. Otherwise, offer a choice
			if( count($locs) == 1 ) {
				echo "<input type='hidden' name='location-to-sync' value='{$locs[0]['id']}'>";
			} else {
				echo '<label class="block">Choose a Location for which to update inventory</label>';
				$i=0;
				foreach($locs as $loc){
					// set the first location as the default
					$selected = ($i==0) ? ' checked ' : '';
					echo "<input type='radio' name='location-to-sync' value='{$loc['id']}' {$selected} id='location-to-sync-{$loc['id']}'> 
						<label for='location-to-sync-{$loc['id']}'>{$loc['name']}</label>
						<br>";
					$i++;
				}
			}

			// input defaults
			$csv_header_inventory = isset($_POST['csv_header_inventory'])
				? $_POST['csv_header_inventory']
				: "in_qty_onhand";
			$csv_header_barcode = isset($_POST['csv_header_barcode'])
				? $_POST['csv_header_barcode']
				: "in_isbn";
			$csv_header_title = isset($_POST['csv_header_title'])
				? $_POST['csv_header_title']
				: "in_title";
			$float_reserve = isset($_POST['float_reserve'])
				? $_POST['float_reserve']
				: 0;
			$count = $s->getProductCount();
			$pages = ceil($count/50);
			if( isset($_POST['pagebreak_page_number']) ) {
				$nextPage = $_POST['pagebreak_page_number'] + 1;
				if( $nextPage > $pages )
					$nextPage = 0; 
			} else {
				$nextPage = '1';
			}
			$pagebreak_importChecked = isset($_POST['pagebreak_import'])
				? ' checked '
				: '';

			?>

			<label for="csv_header_inventory" class="block">Column header for quantity</label>	
			<input type="text" id="csv_header_inventory" value="<?= $csv_header_inventory ?>" name="csv_header_inventory" />

			<label for="csv_header_barcode" class="block">Column header for barcode</label>
			<input type="text" id="csv_header_barcode" value="<?= $csv_header_barcode ?>" name="csv_header_barcode" />

			<label for="csv_header_title" class="block">Column header for title</label>
			<input type="text" id="csv_header_title" value="<?= $csv_header_title ?>" name="csv_header_title" />

			<label for="float_reserve" class="block">Float Amount</label>
			<p class="incidental">Subtract this number from each product's inventory quantity in the csv before syncing the amount in Shopify. So, if, in the uploaded csv, the quantity for Brown Shoes is 25, and this float amount is 1, the Shopify quantity will be set to 24. The default value is 0 and should not be changed unless it is a special circumstance.</p>
			<input type="number" id="float_reserve" value="<?= $float_reserve ?>" name="float_reserve">

			</form>
		</div>

		<div class="col half">
			<form method="post"  class="main-actions secondary-form ignored-barcodes-form">

			<input type="hidden" name="current-page" value="main">

			<h2>Ignored Barcodes</h2>
				<p>List barcodes to ignore. These will be remembered between uploads.</p>
				<table class="js-dynamic-rows">
					<tr>
						<th>Barcode</th>
						<th>Reason (optional)</th>
					</tr>
					<?php 
					$saved_barcodes = $Inventory->getBlackListedBarcodes(true);
					$i=1;
					foreach($saved_barcodes as $barcodeArrayData){
						$barcode = htmlspecialchars($barcodeArrayData['barcode']);
						$reason = htmlspecialchars($barcodeArrayData['reason']);

						echo '<tr data-rownum="'.$i.'">
							<td><input type="text" name="blacklist['.$i.'][barcode]" value="'.$barcode.'" class="js-dynamic-row">
							<td><textarea name="blacklist['.$i.'][reason]" class="js-dynamic-row">'.$reason.'</textarea>
								<a href="#" class="js-delete-dynamic-row">X</a>
						</tr>';						
						$i++;
					}
					echo '<tr data-rownum="'.$i.'" class="empty">
							<td><input type="text" name="blacklist['.$i.'][barcode]" value="" class="js-dynamic-row">
							<td><textarea name="blacklist['.$i.'][reason]" class="js-dynamic-row"></textarea>
								<a href="#" class="js-delete-dynamic-row">X</a>
						</tr>';
					?>
				</table>

				<p>
				<input type="submit" value="Save" name="save-blacklist">
				</p>
			</form>

			<div class="secondary-form">
			<h3>Products which are ignored because of the <i><?= $Inventory->getTagToBlockInventorySync() ?></i> tag</h3>
			<ol>
			<?php

				$products = $s->getAllProducts(array('fields'=>'id,title,tags'));
				$foundAny = false;
				foreach($products as $product){
					if( empty($product["tags"]) ) continue;
					if( strpos($product["tags"], $Inventory->getTagToBlockInventorySync()) === FALSE) continue;

					echo '<li><a href="https://'.$s->shop_domain.'/admin/products/'.$product['id'].'" target="_blank">'.$product['title'].'</a></li>';
					$foundAny = true;

				}
			?>
			</ol>
			<?php if( ! $foundAny) {
				echo '<p>No products found</p>';
			}
			?>
			</div>
		</div>
	</div>
	

</form>

</div><!-- end pageMain -->
<div id="bates-inventory-sync-pageReports" class="bates-inventory-sync-page <?= $currentPageReports ?>">

	<h1>Reports</h1>

	<?php
	if( isset($_POST['purge-reports']) ){
		$msg = '';
		if( $_POST['purge-reports-test'] == 'purge reports') {
			if( $Inventory->purgeReports() ){
				$msg .= '<p>Purged Reports</p>';
			} else {
				$msg .= '<p class="error">Could not purge reports</p>';
			}
		} else {
			$msg .= '<p class="error">Whoops, you didn\'t type the correct confirmation text.</p>';
		}
		echo '<div class="message">'.$msg.'</div>';
	}

	?>

	<?php 
	if( isset($_POST['get-reports']) || isset($_POST['download_report']) ) {

		$reports = $Inventory->getAllReports();
		if( ! $reports ) {
			echo '<div class="message">There are no reports. 🙁</div>';
		} else {
			echo '
			<form method="POST" action="" class="">
				<input type="hidden" name="current-page" value="reports">

				<h3>Choose a report to download</h3>
				<p class="incidental">Selecting multiple reports will merge them into a single output file.</p>
				';

			$date = false;
			foreach($reports as $report){
				$reportDate = date("F j, Y (l)",strtotime($report['timestamp']));
				if( $reportDate !== $date){
					echo '<h4>From ' . $reportDate . '</h4>';
					$date = $reportDate;
				}
				$reportElementId = "report-download-option-{$report['id']}";
				echo '<input type="checkbox" name="reports-to-download[]" value="'.$report['id'].'" id="'.$reportElementId.'" > 
					<label for="'.$reportElementId.'">'.$report['name'].'</label>
					<br>';
			}

			echo '
			<p>
				<input type="submit" value="Download Report" name="download_report">
			</p>
			</form>';
		}

	} else {

		echo <<<FORM
		<form method="POST" action="" class="main-actions">
			<input type="hidden" name="current-page" value="reports">
			<input type="submit" value="Get Reports" name="get-reports">
		</form>
FORM;
	}
	?>

	<form action="" method="POST" id="purge-form" class="secondary-form">
		<h3>Purge Records</h3>
		<input type="hidden" name="current-page" value="reports">
		<span class="incidental">To confirm, please type <b>purge reports</b></span> <input type="text" name="purge-reports-test" data-checkfor="purge reports">
		<br>
		<input type="submit" value="Purge Reports" name="purge-reports" class="disabled">
	</form>

</div><!-- end pageReports -->
</body>
</html>
