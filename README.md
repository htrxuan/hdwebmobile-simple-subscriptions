# HDWebmobile Simple Subscriptions

Sell recurring subscription products -- renewal orders are generated on schedule, no card data ever stored.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-simple-subscriptions/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

HDWebmobile Simple Subscriptions lets a merchant mark any simple product as a recurring subscription (renews every N days, weeks, months, or years). When a customer's order for that product is paid, a subscription record is created; from then on, a scheduled job automatically generates a new order at each renewal and emails the customer a link to pay it. Customers manage their own subscriptions (view status, cancel) from a new "Subscriptions" tab in My Account.

## Why this plugin exists

A competing WooCommerce Subscriptions plugin had a PHP Object Injection vulnerability (CVE-2026-18391): with High-Performance Order Storage enabled, it called PHP's `unserialize()` on data without validating it first, and an unauthenticated attacker could supply a crafted serialized payload that instantiated a gadget-chain object from a bundled dependency -- remote code execution. This plugin closes that entire vulnerability class by construction, not by sanitizing the unserialize call more carefully:

* This plugin never calls PHP's `serialize()` or `unserialize()` anywhere in its code, on any data, under any circumstance. There is nothing to feed a gadget chain because there is no deserialization step to attack.
* Every piece of subscription state -- the renewal interval, the next renewal date, the status -- is stored as its own plain scalar database column (an integer, a date, a short string), never as a serialized blob in a text field.
* Renewal orders are only ever created through WooCommerce's own trusted `wc_create_order()`/`WC_Order` API, the same as a real checkout would, never by hand-assembling order data.
* This plugin never stores a customer's payment card or token at all. Renewals don't auto-charge -- they generate a new order and email the customer a secure link to pay it via WooCommerce's own checkout, the same way any other order would be paid.
* A compare-and-swap update on the renewal date makes each billing cycle's renewal idempotent -- if the scheduled job is ever retried or overlaps itself, only one renewal order can ever be created for a given cycle, never a duplicate charge request.

## Features

* Mark any simple product as a subscription with a configurable interval (every N days/weeks/months/years)
* Automatic subscription creation when a qualifying order is paid
* A daily scheduled job (via WooCommerce's own Action Scheduler) creates renewal orders and emails customers a pay-now link
* "Subscriptions" tab in My Account: customers see status and next renewal date, and can cancel any time
* Admin subscriptions list under WooCommerce > HDWebmobile, with manual cancel/reactivate for customer service

## Development

Standard WordPress plugin structure:

```
hdwebmobile-simple-subscriptions.php    Bootstrap
includes/class-hdsub-activator.php
includes/class-hdsub-admin.php
includes/class-hdsub-core.php
includes/class-hdsub-hub.php
includes/class-hdsub-myaccount.php
includes/class-hdsub-order.php
includes/class-hdsub-product.php
includes/class-hdsub-renewal.php
includes/class-hdsub-repository.php
```

Part of the [HDWebmobile](https://hdwebmobile.com/plugins/) suite of focused, single-purpose WooCommerce plugins.

## License

GPLv2 or later. See [LICENSE](LICENSE).

