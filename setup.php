<?php


/* Define requested scope (access rights) - checkout https://docs.shopify.com/api/authentication/oauth#scopes   */
define('SHOPIFY_SCOPE','read_products,write_products');
$headers = apache_request_headers();

if (isset($_GET['code'])) { // if the code param has been sent to this page... we are in Step 2
    // Step 2: do a form POST to get the access token
    $shopifyClient = new ShopifyClient($_GET['shop'], "", API_KEY, API_SECRET);
    session_unset();

    // Now, request the token and store it in your session.
    $token = $shopifyClient->getAccessToken($_GET['code']);
    if ($token != '') {
        $shop = $_GET['shop'];
            
        $f = file('tokens',FILE_SKIP_EMPTY_LINES);
        // echo'<pre>';var_export($f);echo'</pre>';

        $f[] = "{$shop}|{$token}";
        $f = implode("\n",$f);
        chmod('tokens',0777);
        $fhandle = fopen('tokens','a+');
        fwrite($fhandle,$f);
        fclose($fhandle);
        // chmod('tokens',0644);
    }

    header("Location: https://{$shop}/admin/products");
    exit;
}
// if they posted the form with the shop name
// else if (isset($_POST['shop'])) {
else if ( strpos($headers['Referer'], 'https://apps.shopify.com') === 0) {

    // Step 1: get the shopname from the user and redirect the user to the
    // shopify authorization page where they can choose to authorize this app
    // $shop = isset($_POST['shop']) ? $_POST['shop'] : $_GET['shop'];
    $shop = $_GET['shop'];
    $shopifyClient = new ShopifyClient($shop, "", API_KEY, API_SECRET);

    // get the URL to the current page
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") { $pageURL .= "s"; }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER['SERVER_PORT'] != "443") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["SCRIPT_NAME"];
    } else {
        if(strpos('webpve-apache.bates.edu',$_SERVER['SERVER_NAME'])!==FALSE)
            $pageURL .= ltrim($_SERVER['SCRIPT_NAME'],'/');
        else
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"];
    }

    // redirect to authorize url
    header("Location: " . $shopifyClient->getAuthorizeUrl(SHOPIFY_SCOPE, $pageURL));
    exit;

}

$f = file('tokens',FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES );
// echo'<pre>';var_export($f);echo'</pre>';
// die;
foreach($f as $row) {
    list($store,$token) = explode('|', $row);
    if($store == STORE_NAME) {
        $s = new ShopifyClientWrapper(STORE_NAME,trim($token),API_KEY,API_SECRET);
        break;
    }
}

if( ! isset($s) )
    die('Error: not installed perhaps');
