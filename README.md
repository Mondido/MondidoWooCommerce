Mondido Payments 
=======================
WooCommerce plugin v2.0

Version 2.0 is a major update to handle metadata, invoicing, bank payments and webhook updates.


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
