=== DIBS for WooCommerce ===
Contributors: krokedil, niklashogefjord, slobodanmanic, boozebrorsan
Tags: ecommerce, e-commerce, woocommerce, dibs
Requires at least: 4.3
Tested up to: 4.8.1
Requires WooCommerce at least: 2.4
Tested WooCommerce up to: 2.6.8
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 2.4.5

DIBS for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via DIBS (via the D2/FlexWin platform).



== DESCRIPTION ==
DIBS Payment Services is a leading Scandinavian online payment service provider. DIBS can take payment in the following currencies: Danish Krona - Euro - US Dollar $ - English Pound £ - Swedish Krona - Australian Dollar - Canadian Dollar - Icelandic Krona - Japanese Yen - New Zealand Dollar - Norwegian Krona - Swiss Franc - Turkish Lire.

To get started with DIBS you will need an agreement with DIBS as well as your acquiring bank. Visit [DIBS](http://www.dibspayment.com/) website for more information.

= How does it work? =
* When an order is placed, the customer is taken to DIBS to make a secure payment.
* After the customer completes their payment the order is confirmed and the user is taken to the thank you page on your site.
* Because DIBS handle the payment process for you, no SSL certificate is required (but recommended) on your site.

= Multiple payment methods available =
The extension comes with the following payment methods that can be activated/deactivated individually:

* Card payment.
* Invoice payment (via Afterpay/Arvato).
* MobilePay Online (DK & NO).
* MasterPass.

= Subscription support =
The payment gateway also support recurring payments (with the card payment method) via the [WooCommerce Subscriptions](http://woocommerce.com/products/woocommerce-subscriptions/) extension.

= Get started =
More information on how to get started can be found in the [plugin documentation](http://docs.krokedil.com/documentation/dibs-for-woocommerce/).



== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your DIBS settings.
6. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/documentation/dibs-for-woocommerce/).


== CHANGELOG ==

= 2017.10.16	- version 2.5.0 =
* Misc			- WC 3.0 compat/removed deprecated notices.
* Tweak			- Added WC 3.2+ new plugin headers ( WC requires at least & WC tested up to).
* Tweak			- Added more payment types to credit card payment type.
* Fix			- Fixed Materpass WC 3.0 bug.
* Fix 			- PHP Notice:  Undefined variable: dibs_language.
* Fix 			- PHP Notice: Order properties should not be accessed directly on /order-pay page.

= 2017.08.10	- version 2.4.5 =
* Tweak			- WPML compatibility to return customer to correct language for thank you page.
* Fix			- Only run override_checkout_fields on frontend for MasterPass.

= 2017.02.02	- version 2.4.4
* Fix			- Makes the plugin translatable through WordPress.org (https://translate.wordpress.org/projects/wp-plugins/dibs-for-woocommerce).

= 2016.12.07	- version 2.4.3 =
* Tweak			- Added possibility to send payment window language to DIBS based on current site language in WP.
* Fix			- Make a real pre-athorization in DIBS when subscription signup contains a free trial. Previously 1 kr was charged.

= 2016.10.26	- version 2.4.2 =
* Fix			- Get DIBS ticket number from subscription in process_subscription_payment if it isn't stored in parent order (might happen if customer have changed/updated their card).


= 2016.10.19	- version 2.4.1 =
* Fix			- Fixed fatal error when trying to instantiate WC_Gateway_Dibs_MasterPass in WC_Gateway_Dibs_MasterPass_Functions class.

= 2016.10.17	- version 2.4 =
* Feature		- Added support for MasterPass payment method.
* Tweak			- Added support for sending calcfee as parameter in card payment (http://tech.dibspayment.com/D2/Hosted/Input_parameters/Standard).
* Tweak			- Added admin notice class to check if the old MasterPass plugin (https://krokedil.se/produkt/dibs-d2-masterpass/) is active. This plugin now replaces the old one.
* Fix			- PHP notice fix.

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