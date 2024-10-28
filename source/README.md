# PaynetEasy Payment Module for OsCommerce v4

This is a Payment Module for OsCommerce v4, that gives you the ability to process payments through payment service providers running on PaynetEasy.

## Requirements

  * OsCommerce v4
  * PHP Versions >= 7.0.0  ![GitHub](https://img.shields.io/badge/php-%3E%3D7.0.0-lightgrey)

*Note:* this module has been tested only with OsCommerce v4+.

## Installation (App Shop / Local Storage)
  
  * Upload ```plugin-oscommerce.zip``` to a ```App Shop / Local Storage```

  * Click + sign in Action column to Install module from there.

## Installation (Manual)

  * Unpack zip.
  
  * Navigate project folder ```lib\common\modules\orderPayment``` and upload unpack contents from ```plugin-oscommerce``` there.

  * Move file ```ot_payneteasy.php``` from ```lib\common\modules\orderPayment``` to  ```lib\common\modules\orderTotal``` if installer doesn't move.

  * Move file ```StoredCards.php``` from ```lib\common\modules\orderPayment``` to  ```lib\frontend\design\boxes\account``` if installer doesn't move.

  * Move file ```stored-cards.tpl``` from ```lib\common\modules\orderPayment``` to  ```lib\frontend\themes\basic\boxes\account``` if installer doesn't move.

## Deletion 

  * Go to ```App Shop / Local Storage``` delete module from there

  * For Manual Navigate folder ```lib\common\modules\orderPayment``` delete payneteasy folder and file from there

  * Go to ```lib\common\modules\orderTotal``` delete ot_payneteasy.php from there.

  * Go to ```lib\frontend\design\boxes\account``` delete StoredCards.php from there.

  * Go to ```lib\frontend\themes\basic\boxes\account``` delete stored-cards.tpl from there. 

  * Remove Stored Cards Block references from these tables TABLE_DESIGN_BOXES, TABLE_DESIGN_BOXES_SETTINGS, design_boxes_cache    
    TABLE_TRANSLATION

## Configuration

  * Login inside the __Admin Panel__ and go to ```Modules``` -> ```Payment``` -> ```Online```
  * Check the Payment Module Panel ```PaynetEasy``` is visible in the list of installed Payment Method,
    apply filter Show not installed, if for In-Active module use Show inactive filter.
  * Click to ```PaynetEasy Payment Method``` and click the button ```Edit``` under the right side panel to expand the available settings
  * Set ```Enable PaynetPos``` to ```Yes```, set the correct credentials, select your prefered payment method and additional settings and click ```Update```

  #### Enable SurchargeFee
  * Next go to ```Modules``` -> ```Order structure```
  * Check the Module ```SurchargeFee``` is visible in the list of not installed, install it.
  * Click to ```SurchargeFee``` and click the button ```Edit``` under the right side panel to expand the available settings
  * Set ```Display Surcharge Fee``` to ```Yes```, Sort Order and click ```Update```
  * Drag ```SurchargeFee``` above the Total Module so that surcharge fee if enable must be calculated under grand total. 

## Test data

If you setup the module with default values, you can ask your payneteasy manager

### Test card details

Use the following test cards to make successful test payment:

  Test Card:

    * Visa - 4444555566661111- CVV 123 - non 3d secude approved 321 - 3d secure approved 777 - declined Expiry Date - 12/25
