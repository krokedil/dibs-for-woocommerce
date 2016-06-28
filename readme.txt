=== DIBS for WooCommerce ===
Contributors: krokedil, niklashogefjord, slobodanmanic
Tags: ecommerce, e-commerce, woocommerce, dibs
Requires at least: 4.3
Tested up to: 4.5
Requires WooCommerce at least: 2.3
Tested WooCommerce up to: 2.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 2.2.1

DIBS for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via DIBS (via the D2/FlexWin platform).



== DESCRIPTION ==
DIBS Payment Services is a leading Scandinavian online payment service provider. DIBS can take payment in the following currencies: Danish Krona - Euro - US Dollar $ - English Pound £ - Swedish Krona - Australian Dollar - Canadian Dollar - Icelandic Krona - Japanese Yen - New Zealand Dollar - Norwegian Krona - Swiss Franc - Turkish Lire.

To get started with DIBS you will need an agreement with DIBS as well as your acquiring bank. Visit [DIBS](http://www.dibspayment.com/) website for more information.

When the order goes through, the user is taken to DIBS to make a secure payment. No SSL certificate is required on your site (even though it's strongly recommended). After payment the user is taken back to your thank you page.



== IMPORTANT NOTE ==
This plugin extends WooCommerce with a DIBS payment gateway. The plugin will only work if WooCommerce is activated.

You can test the DIBS gateway payment process by enable the DIBS Test Mode. Read more about the DIBS test process here http://tech.dibs.dk/10_step_guide/your_own_test/. 

In your DIBS account you should check the box "Skip step 3 - Payment approved" under ->Integration ->FlexWin. Otherwise the thank you page won't show any details such as Order number, Total amount and Payment method.



== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your DIBS settings.
6. Go to your account at http://www.dibspayment.com/ and:
	- Check the box "Perform verification of md5key" under ->Integration ->md5 keys. If you are using this plugin for recurring payments together with WooCommerce Subscriptions you should NOT check this box.
	- Make sure that the box "Orderid" and "All fields exclusive of card information response" is checked under ->Integration ->Return Values.
	- Check the box "Skip step 3 - Payment approved" under ->Integration ->FlexWin.