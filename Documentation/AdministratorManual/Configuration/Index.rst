.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

Configuration
=============

There are some mandatory configurations you have to add to TypoScript.

sandbox
"""""""
.. container:: table-row

   Property
      plugin.tx_cartsaferpay.sandbox
   Data type
      boolean
   Description
      Defines which url is used for payment handling.
   Default
      1

jsonApi.username
""""""""""""""""
.. container:: table-row

   Property
      plugin.tx_cartsaferpay.jsonApi.username
   Data type
      Text
   Description
      Sets the JSON API username. Take the value from your Saferpay account.

jsonApi.password
""""""""""""""""
.. container:: table-row

   Property
      plugin.tx_cartsaferpay.jsonApi.password
   Data type
      Text
   Description
      Sets the JSON API password. Take the value from your Saferpay account.

customerId
""""""""""
.. container:: table-row

   Property
      plugin.tx_cartsaferpay.customerId
   Data type
      Text
   Description
      Take the customerId from your Saferpay account.

terminalId
""""""""""
.. container:: table-row

   Property
      plugin.tx_cartsaferpay.terminalId
   Data type
      Text
   Description
      Take the terminalId from your Saferpay account.

Furthermore, a corresponding payment method must be configured in the TypoScript.
For example, this could look like this:

::

   plugin.tx_cart.payments {
        ch {
            options {
                2 {
                    title = Saferpay
                    provider = SAFERPAY
                    extra = 0.00
                    taxClassId = 1
                    status = open
                    preventBuyerEmail = 1
                    preventSellerEmail = 1
                    available.from = 0.01
                }
            }
        }
   }

|

Note, that the configuration for provider must be SAFERPAY (all capital letters).
