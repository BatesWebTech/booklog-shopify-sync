<?php
include 'config.php';

$db = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);

if(mysqli_connect_error()){
	die('that did not work');
} else
	echo 'connected!';

$store = 'bates-college-store-dev.myshopify.com';
$stmt = $db->prepare("SELECT token FROM inventory_sync_tokens WHERE store = ?");
$stmt->bind_param('s',$store);
$stmt->execute();
$stmt->bind_result($token);
$stmt->fetch();
echo'<pre>';var_export($stmt->num_rows);echo'</pre>';

echo'<pre>';var_export($token);echo'</pre>';