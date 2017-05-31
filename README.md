# About
This is a Shopify plugin which makes it easier to synchronize inventory between the [Booklog Inventory Management System](http://www.booklog.com/index.html) and the eCommerce platform [Shopify](https://www.shopify.com/). Once installed, the sync process is initiated by manually uploading a csv to Shopify.

# Setup and Installation
Download and extract the repo to a web-viewable location. Rename the `config-dist.php` file to `config.php`. 
### Make a Shopify Developer Account
In order to install the app in your Shopify store, you'll need to make it into an app. In order to do that you'll need to create a Shopify developer account at  https://app.shopify.com/services/partners . In your developer account, create a new account and set it to use the **Embedded App SDK** Set the *App URL* and *Redirection URL* to the url for the `index.php` file where you are hosting the plugin files. Once you create the plugin, it should give you the API key and Secret Key to use in `config.php`.

### Create the Database
Use the database schema file in the repo, create a new MySQL database. Add the connection information to the `config.php` file.

### Install The App in your Shopify Store
Since your app will be unlisted, you can't get to it from a search in the App Store. Instead, there should be a button at the top of your app settings page "Edit App Store Listing". Click that, then the "View app listing" button. Then the "GET" button will trigger the install process.

# Use
1. Export a **Inventory Detail Report** or **Standard Sales Report** from Booklog. In the Apps section of the Shopify admin, find our app page. 
2. Enter the appropriate field names to match the names of the columns in the export csv with the correct Shopify fields. 
3. Upload the file and submit. A detailed report will be shown on the screen when the process is complete. The report can also be downloaded in the future. Only one report is saved in the app, so each run of the sync process will overwrite the previous report. 

### Blacklist 
The admin interface has a section in which you can enter product barcodes which should be ignored during the sync process. 