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

	$reportId = isset($_POST['report-to-download'])
		? $_POST['report-to-download']
		: null;
	$result = $Inventory->downloadReport($reportId); // this has it's own "exit" statement
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
	echo '<div class="finish-message">Updated Ignored Barcodes</div>';
}




?>


	<div class="col-wrapper">
		<div class="col half">
			<form method="post" action="" enctype="multipart/form-data" class="main-actions" id="csv-upload-form">

			<input type="hidden" name="current-page" value="main">
			<h2>Update Inventory</h2>

			<?php
			$locs = $s->getLocations();
			// if there is only one location, automatically use that one. Otherwise, offer a choice
			if( count($locs) == 1 ) {
				echo "<input type='hidden' name='location-to-sync' value='{$locs[0]['id']}'>";
			} else {
				echo '
				<p>
					<b>Choose a Location for which to update inventory</b>
					<br>
				';
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

			<p>
				<label for="csv_header_inventory" class="block">Column header for quantity</label>	
				<input type="text" id="csv_header_inventory" value="<?= $csv_header_inventory ?>" name="csv_header_inventory" />
			</p>
			<p>
				<label for="csv_header_barcode" class="block">Column header for barcode</label>
				<input type="text" id="csv_header_barcode" value="<?= $csv_header_barcode ?>" name="csv_header_barcode" />
			</p>
			<p>
				<label for="csv_header_title" class="block">Column header for title</label>
				<input type="text" id="csv_header_title" value="<?= $csv_header_title ?>" name="csv_header_title" />
			</p>
			<p>
				<label for="float_reserve" class="block">Float Amount</label>
				<i>Subtract this number from each product's inventory quantity in the csv before syncing the amount in Shopify. So, if, in the uploaded csv, the quantity for Brown Shoes is 25, and this float amount is 1, the Shopify quantity will be set to 24. The default value is 0 and should not be changed unless it is a special circumstance.</i>
				<input type="number" id="float_reserve" value="<?= $float_reserve ?>" name="float_reserve">
			</p>

			<p>
				<label for="csv" class="block">Upload a CSV</label>
				<input type="file" name="csv" id="csv">
			</p>
			<p>
				<label class="block">Break Import to multiple pages</label>
				<i>This is useful if the import process is taking too long</i><br>
				<input type="checkbox" name="pagebreak_import" value="1" id="pagebreak_import" <?= $pagebreak_importChecked ?>> <label for="pagebreak_import">Break import into multiple pages</label>
				<br>
				&nbsp;&nbsp;&nbsp;Page <input type="number" style="width:36px;" min="1" max="<?= $pages ?>" value="<?= $nextPage ?>" name="pagebreak_page_number"> of <?= $pages ?>
			</p>

			<p>
				<input type="submit" value="Run Update" name="upload-csv">
			</p>
			</form>
		</div>

		<div class="col half">
			<form method="post"  class="main-actions">

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
	

</form>

</div><!-- end pageMain -->
<div id="bates-inventory-sync-pageReports" class="bates-inventory-sync-page <?= $currentPageReports ?>">

	<h1>Reports</h1>

	<?php
	if( isset($_POST['purge-reports']) ){
		if( $Inventory->purgeReports() ){
			echo '<h2>Purged Reports</h2>';
		} else {
			echo '<h2>Could not purge reports</h2>';
		}
	}

	?>
	<form action="" method="POST" id="purge-form">
		<input type="hidden" name="current-page" value="reports">
		<input type="submit" value="Purge Reports" name="purge-reports">
	</form>
	<script>
	(function(){
		var purgeButton = document.querySelector('input[name="purge-reports"]');
		purgeButton.addEventListener('click',function(e){
			if( ! confirm('Are you sure you want to purge all reports?') )
				return false;
		});
	})();
	</script>

	<?php 
	if( isset($_POST['get-reports']) || isset($_POST['download_report']) ) {

		// if( $lastReportDate = $Inventory->getLastReportDate() ) {
		// 	echo '<form action="" method="POST" class="download-report-form page-top">
		// 		<input type="hidden" name="download_report" value="1">
		// 		<p>Last report run: <b>'. date('F j, Y (g:i a)',strtotime($lastReportDate)) .'</b></p>
		// 		<button class="action-button">Download it</button>
		// 	</form>
		// 	';
		// }
		$reports = $Inventory->getAllReports();
		echo <<<FORM
		<form method="POST" action="" class="">
			<input type="hidden" name="current-page" value="reports">

			<h2>Choose a report to download</h2>
FORM;

		$date = false;
		foreach($reports as $report){
			$reportDate = date("F j, Y (l)",strtotime($report['timestamp']));
			if( $reportDate !== $date){
				echo '<h3>From ' . $reportDate . '</h3>';
				$date = $reportDate;
			}
			$reportElementId = "report-download-option-{$report['id']}";
			echo '<input type="radio" name="report-to-download" value="'.$report['id'].'" id="'.$reportElementId.'" > 
				<label for="'.$reportElementId.'">'.$report['name'].'</label>
				<br>';
		}

		echo '
		<p>
			<input type="submit" value="Download Report" name="download_report">
		</p>
		</form>';

	} else {

		echo <<<FORM
		<form method="POST" action="" class="main-actions">
			<input type="hidden" name="current-page" value="reports">
			<p>
				<input type="submit" value="Get Reports" name="get-reports">
			</p>
		</form>
FORM;
	}
	?>

</div><!-- end pageReports -->
</body>
</html>
