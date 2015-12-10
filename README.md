# EJPratt

This program is designed to take paypal invoice data and compile an easy to read sheet for the finance department to use for deposits.  Becasue the API for paypal is a little bit weak you have to use both the REST api and the Classic API to actually get all the tax and payment information.  This is because some of the fields (notelby I think tax) is missing on the invoices pulled from the REST api.  But the transaction ID is the same accross both, so you can mix and match.

This requires the PHP Paypal api to be installed as well (and the key for your paypal etc... to be set in the bootstrap).
