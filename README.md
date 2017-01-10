Mondido Payments 
=======================
WooCommerce plugin v3.4.9

FAQ: https://github.com/Mondido/WooCommerce/wiki/FAQ   

The Mondido WooCommerce plugin supports multiple payment methods such as Cards, Invoice, Direct Bank, PayPal and Swish.
The subscription service does NOT require WooCommerce Subscriptions, and are free of any additional costs.   

## Changelog
2017-01-10
- Version 3.4.9
- Editable checkout text
- Fix for double "complete" on wewbhook and redirect callbacks


2016-12-22 (XMAS EDITION 2)
- Version 3.4.7
- Editable button text

2016-12-22 (XMAS EDITION)
- Version 3.4.5
- Fixed amount for items

2016-12-20 
- Version 3.4.4
- Added support for surcharges and extra fees by adding an Item to the transaction after payment. This is implemented using the surcharges rules in the Mondido Rule Engine.


2016-09-01
- Version 3.4
- Fixed logos and checkout bugs


2016-08-26
- Version 3.3
- Added logging and email refactoring


2016-08-10
- Version 3.2
- Refactored [] to array() (seems like you like old PHP versions huh?)
- Fix for handling wrong API passwords

2016-08-08
- Version 3.1
- Icons in checkout
- CSS styling of Icons
- Added PayPal as option

2016-06-15
- version 3
- Now supports subscriptions

2016-06-02
- version 2.6
- Fixed vat_amount and customer_ref for order


2016-04-14
- version 2.5
- Added settings for more payment methods
- Added notification if API password is not set
- Added ad-script to see google ads conversion
- Updated metadata and items

2016-03-11
- Removed PHP notice warning
- Added client and admin mail notifications


2016-01-18
- Updated payment logo

Version 2.1 is a major update to handle metadata, invoicing, bank payments and webhook updates.


1. Get your mondido account at: https://mondido.com  
2. Click in the "Download ZIP" button in the right menu of this page  
3. Upload it into your WooCommerce Plugin page:  
    ![Step 1](https://raw.githubusercontent.com/anderson-mondido/WooCommerce/screenshots/screenshots/add_new_plugin_1.png)  
    ![Step 2](https://raw.githubusercontent.com/anderson-mondido/WooCommerce/screenshots/screenshots/add_new_plugin_2.png)  
    ![Step 3](https://raw.githubusercontent.com/anderson-mondido/WooCommerce/screenshots/screenshots/add_new_plugin_3.png)  
4. Make sure it is active  
    ![Step 4](https://raw.githubusercontent.com/anderson-mondido/WooCommerce/screenshots/screenshots/add_new_plugin_4.png)  
5. Enable Mondido in WooCommerce and Configure your Credentials  
    ![Step 5](https://raw.githubusercontent.com/anderson-mondido/WooCommerce/screenshots/screenshots/add_new_plugin_5.png)  

## Changelog
2015-12-04
- Added spinner on confirmation page to make the experience smooth
- Added JavaScript navigation to move back from payment page to checkout
- Added Swedish translation


## Changelog
2015-11-02
- Updated Metadata structure
- Added items
- Added support for Authorize (as default)
- Added API password 
- Added Capture button in Orders view
- Added Payment details in Orders view
- Added webhook endpoint (http://www.myshop.com/index.php/checkout/order-received)
- Added shipping address updates in webhook callback (for invoice payments)
- Added more robust callback handling
- Added support for standard refunds
- Added more notifications in Orders view


2015-05-25
- Applied filter on metadata
- Updated readme

2015-02-07
- Fixed multiple callback bug
- Updated readme

2015-01-12
- Updated the hash recipe to match more secure settings on the admin console.
- Removed static currency selector
- Added Auto confirm
