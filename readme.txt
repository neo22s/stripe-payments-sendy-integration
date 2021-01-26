=== Stripe Payments Sendy Integration ===
Contributors: deambulando 
Donate link: https://paypal.me/chema/
Tags: stripe, sendy, email newsletter, newsletter, aws, aws ses, ses, email, stripe, payment, sendy.co
Requires at least: 5.0
Tested up to: 5.6
Requires PHP: 7.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Sendy.co integration for Stripe Payments plugin. https://wordpress.org/plugins/stripe-payments/

== Description ==

http://sendy.co/?ref=9v05z integration for Stripe Payments plugin. 

First you need to install latest version of Stripe Payments plugin. https://wordpress.org/plugins/stripe-payments/

This Stripe Payments plugin can be integrated with Sendy.co using this addon. Sendy.co is a popular email marketing self-hosted software that sends newsletter emails using Amazon AWS SES,

The Sendy.co Integration allows you to add customers to your Sendy.co list. Each product created using Stripe Payments has a field where you can enter a Sendy.co List. The email address of your customer will be sent to the specified list after a successful transaction.

== Installation ==

Configuring Sendy.co Integration

    Make sure the Sendy.co Integration addon is activated.
    Click on the ‘Settings’ menu under the ‘Stripe Payments’ plugin.
    Click on the ‘Sendy.co’ tab that appears after activating this addon.
    Select the ‘Enable Sendy.co Integration’ checkbox. This gives you the ability to collect data from any of the products created using Stripe Payments.
    Enter in your Sendy.co API Key. This can be found in the settings of your installation.
    ‘Save Changes’ that you have made.

Disable Double Opt-in: When a customer gets added to your Sendy.co list, he will get an email that tells him to confirm the signup. You can disable that option by checking the “Disable Double Opt-In” field in this addon’s settings.

This integration addon allows you to collect the email address of your customers from the checkout of specific products. For each product that you wish to collect customer email addresses, you will need to enter a Sendy.co ID list in the product configuration.

This addon allows you to categorize your customer’s email based upon the product or service they purchase.

    Edit the product in question.
    Go to the section titled ‘Sendy.co Integration’.
    Specify the Sendy.co List ID where you would like emails of customers who purchase this product to be added to.
    ‘Update’ the changes you have made to the product.

Customer’s Email Address

Your customers will not have to enter any extra information when the Sendy.co Integration is enabled. The email address sent to your Sendy.co List is the email your customers enter into the Stripe Payments popup (as shown below).



== Changelog ==

= 0.0.1 =
- First testing release.


== Frequently Asked Questions ==

If user emails are not added to the list after successful payment – this means something is not right. Enabling debug log should help you to figure out what’s wrong.

To enable debug log:

    Go to Stripe Payments -> Settings
    On General Settings tab, scroll down to Debug section and check “Enable Debug Logging” checkbox.
    Click “Save Changes”.


== Upgrade Notice ==
None.


== Screenshots == 
1. Stripe Plugin Settings