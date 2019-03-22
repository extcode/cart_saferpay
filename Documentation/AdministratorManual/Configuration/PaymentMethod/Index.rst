.. include:: ../../../Includes.txt

Payment Method Configuration
============================

The payment method for Saferpay is configured like any other payment method. There are all configuration options
from Cart available.

::

   plugin.tx_cart {
       payments {
           options {
               2 {
                   provider = SAFERPAY
                   title = Saferpay
                   extra = 0.00
                   taxClassId = 1
                   status = open
                   available.from = 0.01
               }
           }
       }
   }

|

.. container:: table-row

   Property
      plugin.tx_cart.payments.options.n.provider
   Data type
      string
   Description
      Defines that the payment provider for Saferpay should be used.
      This information is mandatory and ensures that the extension Cart Saferpay takes control and for the authorization of payment the user forwards to the Saferpay site.
