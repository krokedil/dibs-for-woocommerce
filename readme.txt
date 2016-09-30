=== DIBS for WooCommerce ===
Contributors: krokedil, niklashogefjord, slobodanmanic
Tags: ecommerce, e-commerce, woocommerce, dibs
Requires at least: 4.3
Tested up to: 4.6.1
Requires WooCommerce at least: 2.3
Tested WooCommerce up to: 2.6.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 2.3.1

DIBS for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via DIBS (via the D2/FlexWin platform).



== DESCRIPTION ==
DIBS Payment Services is a leading Scandinavian online payment service provider. DIBS can take payment in the following currencies: Danish Krona - Euro - US Dollar $ - English Pound £ - Swedish Krona - Australian Dollar - Canadian Dollar - Icelandic Krona - Japanese Yen - New Zealand Dollar - Norwegian Krona - Swiss Franc - Turkish Lire.

To get started with DIBS you will need an agreement with DIBS as well as your acquiring bank. Visit [DIBS](http://www.dibspayment.com/) website for more information.

When the order goes through, the user is taken to DIBS to make a secure payment. No SSL certificate is required on your site (even though it's strongly recommended). After payment the user is taken back to your thank you page.

More information on how to get started can be found in the [plugin documentation](http://docs.krokedil.com/documentation/dibs-for-woocommerce/).



== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your DIBS settings.
6. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/documentation/dibs-for-woocommerce/).


== CHANGELOG ==

= 2016.09.30	- version 2.3.1 =
* Tweak			- Added MobilePay support for Norway.
* Tweak			- Code refactoring - added payment gateway factory class.
* Fix			- Remove key_hmac (old code) to avoid php notices.
* Fix			- Added missing country and language settings in constructor for invoice payment.

= 2016.07.13	- version 2.3 =
* Feature		- Added support for Mobile Pay Online (for Denmark).
* Tweak			- Added support for WooCommerce Subscriptions customer payment method change (WCS 2.0 change).
* Fix			- Changed to hook 'woocommerce_subscription_failing_payment_method_updated_dibs' for handling updated payment method changes (WCS 2.0 compat).
* Fix			- Changed endpoint for DIBS Authorizations (Subscription renewal payments) to work together with Flexwin.
* Fix			- Bugfixes after code refactoring (separate is_available() for each payment method, remove subscription support & added separate payment icon for invoice payment).
* Fix			- Use update_post_meta to avoid duplicate post meta data in orders (mainly for subscription payments).
* Fix 			- Changed url to documentation page.

= 2016.06.27	- version 2.2.1 =
* Tweak         - Updated readme.txt.
* Tweak			- Changed plugin main file name for WordPress best practices.

= 2016.06.27	- version 2.2 =
* Tweak         - Removed payment window support.
* Misc			- Release on wordpress.org.