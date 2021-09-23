=== Nets D2 for WooCommerce ===
Contributors: krokedil, niklashogefjord, slobodanmanic, boozebrorsan
Tags: ecommerce, e-commerce, woocommerce, dibs
Requires at least: 4.3
Tested up to: 5.4.1
Requires PHP: 7.0
WC requires at least: 4.0.0
WC tested up to: 4.1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: trunk

Nets D2 for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Nets (via the D2 platform).



== DESCRIPTION ==
Nets Payment Services is a leading Scandinavian online payment service provider. Nets can take payment in the following currencies: Danish Krona - Euro - US Dollar $ - English Pound £ - Swedish Krona - Australian Dollar - Canadian Dollar - Icelandic Krona - Japanese Yen - New Zealand Dollar - Norwegian Krona - Swiss Franc - Turkish Lire.

To get started with Nets you will need an agreement with Nets as well as your acquiring bank. Visit [Nets](https://www.nets.eu/en/payments/) website for more information.

= How does it work? =
* When an order is placed, the customer is taken to Nets to make a secure payment.
* After the customer completes their payment the order is confirmed and the user is taken to the thank you page on your site.
* Because Nets handle the payment process for you, no SSL certificate is required (but recommended) on your site.

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

= 2020.05.15	- version 2.7.0 =
* Tweak         - Made recurring payments more compatible with new WooCommerce subscription flow.
* Tweak         - Declare support for WC 4.1.0 & WP 5.4.1.
* Tweak         - Change the name of the plugin to Nets D2 for WooCommerce.

= 2019.05.06	- version 2.6.2 =
* Fix           - Fixed error in MasterPass class related to WC_Session.
* Fix           - WC version deprecated notice fix. Could cause issues with cancel order redirect from DIBS payment window.

= 2019.01.07	- version 2.6.1 =
* Tweak			- Update DIBS ticket number in subscription during every subscription renewal payment.
* Fix			- Add maketicket (new DIBS subscription) even if it is a manuall subscription renewal. This can happen if customer pays for a renewal maually and selects DIBS as payment method.

= 2018.12.12	- version 2.6.0 =
* Feature		- Added Dankort app as new separate payment method.
* Tweak			- Plugin WordPress 5.0 compatible.
* Tweak			- Changed URL to docs about refunds/API credentials in settings page.
* Fix			- Code cleaning.

= 2018.09.11	- version 2.5.5 =
* Tweak			- Improved get_order_id() function to get correct order id during callbacks from DIBS.
* Fix			- Modify redirct url to checkout page for subscriptions with free trial (previous customer ended up on My account page).

= 2018.07.16	- version 2.5.4 =
* Tweak			- Improved compatibility with plugins adding sequential order numbers features and devs changing order number via the woocommerce_order_number filter.

= 2018.06.10	- version 2.5.3 =
* Tweak			- Use get_site_url() instead of site_url() for callback url's. Better support for https.
* Fix			- Session error fix (could be triggered in WC 3.3+).

= 2017.11.21	- version 2.5.2 =
* Tweak			- Grab DIBS ticket number from subscription first (instead of parent order) on subscription renewal orders. Could cause problem when customer had changed card on a subscription.

= 2017.11.06	- version 2.5.1 =
* Tweak			- Removed V-DK as paytype sent to DIBS for card payments.
* Tweak			- Don't send Order on-hold email for DIBS status code 12 (capture pending).

= 2017.10.16	- version 2.5.0 =
* Misc			- WC 3.0 compat/removed deprecated notices. Removed 2.x support.
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
