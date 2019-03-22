.. include:: ../../../Includes.txt

Main Configuration
==================

The plugin needs to know the merchant e-mail address.

::

   plugin.tx_cartsaferpay {
       sandbox = 1

       username =
       password =
       customerId =
       terminalId =
   }

|

.. container:: table-row

   Property
         plugin.tx_cartsaferpay.sandbox
   Data type
         boolean
   Description
         This configuration determines whether the extension is in live or in sandbox mode.
   Default
         The default value is chosen so that the plugin is always in sandbox mode after installation, so that payment can be tested with Saferpay.

.. container:: table-row

   Property
         plugin.tx_cartsaferpay.username
   Data type
         string
   Description
         Sets the JSON API username. Take the value from your Saferpay account.

.. container:: table-row

   Property
         plugin.tx_cartsaferpay.password
   Data type
         string
   Description
         Sets the JSON API password. Take the value from your Saferpay account.

.. container:: table-row

   Property
         plugin.tx_cartsaferpay.customerId
   Data type
         string
   Description
         Take the customerId from your Saferpay account.

.. container:: table-row

   Property
         plugin.tx_cartsaferpay.terminalId
   Data type
         string
   Description
         Take the terminalId from your Saferpay account.
