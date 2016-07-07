<?php
// define('STORE_NAME','bates-college-store-dev');
// define('API_KEY','APIKEYGOESHERE');
// define('API_SECRET','APIKEYGOESHERE');
// define('API_PASSWORD','APIKEYGOESHERE');

define('STORE_NAME',$_GET['shop']);
// define('API_KEY','APIKEYGOESHERE');
// define('API_SECRET','APIKEYGOESHERE');

// get this stuff @https://app.shopify.com/services/partners/api_clients/1386347

// PROD app
// define('API_KEY','APIKEYGOESHERE');
// define('API_SECRET','secretkeyhash');

// DEV app
define('API_KEY','APIKEYGOESHERE');
define('API_SECRET','secretkeyhash');

// The db for tokens

// dev
// define('DB_USER','shopify');
// define('DB_NAME','shopify');
// define('DB_PASS','dbpassword');
// define('DB_HOST', 'dbpassword');

//prod
define('DB_USER','shopify');
define('DB_NAME','shopify');
define('DB_PASS','dbpassword');
define('DB_HOST', 'bates.edu');
