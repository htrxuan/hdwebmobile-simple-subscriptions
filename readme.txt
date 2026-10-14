=== HDWebmobile Simple Subscriptions ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, subscriptions, recurring payments, recurring orders, membership
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell recurring subscription products -- renewal orders are generated on schedule, no card data ever stored.

== Description ==

HDWebmobile Simple Subscriptions lets a merchant mark any simple product as a recurring subscription (renews every N days, weeks, months, or years). When a customer's order for that product is paid, a subscription record is created; from then on, a scheduled job automatically generates a new order at each renewal and emails the customer a link to pay it. Customers manage their own subscriptions (view status, cancel) from a new "Subscriptions" tab in My Account.

= Security by design =
A serious class of bug in subscription tools is PHP Object Injection (CWE-502): calling PHP's `unserialize()` on data without validating it first, so an unauthenticated attacker can supply a crafted serialized payload that instantiates a gadget-chain object from a bundled dependency -- remote code execution. This plugin closes that entire vulnerability class by construction, not by sanitizing the unserialize call more carefully:

* This plugin never calls PHP's `serialize()` or `unserialize()` anywhere in its code, on any data, under any circumstance. There is nothing to feed a gadget chain because there is no deserialization step to attack.
* Every piece of subscription state -- the renewal interval, the next renewal date, the status -- is stored as its own plain scalar database column (an integer, a date, a short string), never as a serialized blob in a text field.
* Renewal orders are only ever created through WooCommerce's own trusted `wc_create_order()`/`WC_Order` API, the same as a real checkout would, never by hand-assembling order data.
* This plugin never stores a customer's payment card or token at all. Renewals don't auto-charge -- they generate a new order and email the customer a secure link to pay it via WooCommerce's own checkout, the same way any other order would be paid.
* A compare-and-swap update on the renewal date makes each billing cycle's renewal idempotent -- if the scheduled job is ever retried or overlaps itself, only one renewal order can ever be created for a given cycle, never a duplicate charge request.

= Key Features =
* Mark any simple product as a subscription with a configurable interval (every N days/weeks/months/years)
* Automatic subscription creation when a qualifying order is paid
* A daily scheduled job (via WooCommerce's own Action Scheduler) creates renewal orders and emails customers a pay-now link
* "Subscriptions" tab in My Account: customers see status and next renewal date, and can cancel any time
* Admin subscriptions list under WooCommerce > HDWebmobile, with manual cancel/reactivate for customer service

= Limitations (please read before installing) =
* Renewals are not auto-charged -- the customer receives an email with a link and pays each renewal order manually, since this plugin deliberately never stores payment card/token data
* Only logged-in customers can hold a subscription -- guest checkouts don't create one, since there's no account to attach it to
* Simple products only in this version (no subscription variations)

= Enjoying this plugin? =
If it helps your site, please consider [leaving an honest review on WordPress.org](https://wordpress.org/support/plugin/hdwebmobile-simple-subscriptions/reviews/#new-post) -- it helps other WordPress users find it. Found a problem or have an idea? Please [open a support topic](https://wordpress.org/support/plugin/hdwebmobile-simple-subscriptions/) first so it can be fixed.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-simple-subscriptions` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Edit any simple product, check "Subscription" in the General tab, and set how often it renews.

== How to Use ==

= 1. Mark a product as a subscription =
Edit the product, go to the General tab, check "Subscription", and set "Renews every" (e.g. 1 Month).

= 2. Customers subscribe automatically =
As soon as a customer's order containing that product reaches Processing or Completed, a subscription is created for their account -- nothing else to configure.

= 3. Renewals happen on schedule =
Each day, any subscription due for renewal gets a new order created automatically, and the customer receives an email with a link to pay it.

= 4. Customers manage their own subscriptions =
"Subscriptions" appears in the My Account menu, showing status and next renewal date, with a Cancel button.

= 5. Customer service =
Under WooCommerce > HDWebmobile > Subscriptions, an admin can view every subscription and manually cancel or reactivate one.

== Screenshots ==

1. The "Subscription" fields on the product edit screen.
2. The "Subscriptions" tab in My Account.
3. The admin subscriptions list under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: subscription products with configurable renewal interval, automatic subscription creation on paid orders, scheduled renewal-order generation with idempotent compare-and-swap advancing, My Account subscriptions tab, admin subscriptions list with manual cancel/reactivate.
