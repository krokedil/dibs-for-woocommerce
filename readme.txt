=== WooCommerce DIBS Gateway ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, dibs
Requires at least: 3.8
Tested up to: 4.0
Requires WooCommerce at least: 2.1
Tested WooCommerce up to: 2.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

By Krokedil - http://krokedil.com/



== DESCRIPTION ==
DIBS Gateway is a plugin that extends WooCommerce, allowing you to take payments via DIBS FlexWin module (http://www.dibspayment.com/).

DIBS Payment Services is a leading Scandinavian online payment service provider. DIBS can take payment in the following currencies: 
- Danish Krona
- Euro
- US Dollar $
- English Pound £
- Swedish Krona
- Australian Dollar
- Canadian Dollar
- Icelandic Krona
- Japanese Yen
- New Zealand Dollar
- Norwegian Krona
- Swiss Franc
- Turkish Lire

To get started with DIBS you will need an agreement with DIBS as well as a redemption agreement with your bank. For more information about payment methods see http://www.dibspayment.com/products/internet/payment_methods/.

When the order goes through, the user is taken to DIBS to make a secure payment. No SSL certificate is required on your site. After payment the user is taken back to your thank you page.



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
	- Check the box "Perform verification of md5key" under ->Integration ->md5 keys
	- Make sure that the box "Orderid" and "All fields exclusive of card information response" is checked under ->Integration ->Return Values.
	- Check the box "Skip step 3 - Payment approved" under ->Integration ->FlexWin.