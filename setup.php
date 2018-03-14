<?php

/** 
 * This code has been adapted from 
 * https://github.com/cmcdonaldca/ohShopify.php
 */

/* Define requested scope (access rights) - checkout https://docs.shopify.com/api/authentication/oauth#scopes   */
define('SHOPIFY_SCOPE','read_products,write_products,read_inventory,write_inventory');

$headers = apache_request_headers();

if (isset($_GET['code'])) { // if the code param has been sent to this page... we are in Step 2
    // Step 2: do a form POST to get the access token
    $shopifyClient = new ShopifyClientWrapper($_GET['shop'], "", API_KEY, API_SECRET);

    // Now, request the token and store it in your session.
    $token = $shopifyClient->getAccessToken($_GET['code']);
    if ($token != '') {
        $shopifyClient->saveToken($token);
    }

    header("Location: https://{$_GET['shop']}/admin/products");
    exit;
}
// if they posted the form with the shop name
// else if (isset($_POST['shop'])) {
else if ( array_key_exists('Referer', $headers) && (strpos($headers['Referer'], 'https://apps.shopify.com') === 0)) {

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



$s = new ShopifyClientWrapper($_GET['shop'], "", API_KEY, API_SECRET);
if($token = $s->getSavedToken() )
    $s->setToken($token);
else
    die('Error: not installed perhaps');
