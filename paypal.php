Last Edited December 12th 2015
<?php

//Error Reporting
//var_dump();
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

//phpinfo();

/*
Written by Michael Andreae, September 2014 

NOTE if a user pay'd a bill in 2 installments this would NOT capture their second payment.
We don't every really accept dual payments so I did not include a case for it

This is the PHP api, under lib/PayPal seems to be the only spot to really get good information on the objects
https://github.com/paypal/rest-api-sdk-php/tree/stable

This is the api guide but it seems to be mostly curl
https://developer.paypal.com/webapps/developer/docs/api/#common-invoicing-objects

Bootstrap file has the needed config files, the config.init whatever isn't being used.  Note bootstrap is where the api key lives

Common isn't really needed, it's a demo file mostly
*/

//These need to be set by user
//$start = '2014-03-01';
//$end = '2014-09-16';


$start = $_GET['startDate'];
$end = $_GET['endDate'];

if(!file_exists('bootstrap.php')) {
	echo "The 'vendor' folder is missing. You must run 'composer update --no-dev' to resolve application dependencies.\nPlease see the README for more information.\n";
	exit(1);
}

require 'payment.php';
require 'bootstrap.php';
use PayPal\Api\Invoice;
use PayPal\Api\Invoices;
use PayPal\Api\Search;
use PayPal\Api\Payment;
use Paypal\Api\Transaction;
use PayPal\Api\Item;
use PayPal\Transport\PPRestCall;
use PayPal\Api\InvoiceItem;
use PayPal\Api\Sale;


$searchParam = new Search();
$searchParam->status = array("PAID");
$searchParam->start_payment_date = $start . " PDT";
$searchParam->end_payment_date = $end . " PDT";

//Creating an invoice object to perform a search call
$invoiceSearch = new Invoice;
//Creates a new PayPal object for all paypal calls
$paypal = new Paypal($start, $end);

/*Calls to the classicp paypal api using the downloaded invoices sdk.*/

try {
    $invoices = $invoiceSearch->search($searchParam, $apiContext);		
  } catch (exception $e) {
    print "cannot do invoice search";
    print $e;
}

$invoiceIDs = array();

//This is some invoice information, but doesn't include transaction or item list
try {
    $All_Invoices = $invoices->getInvoices();
   } catch (exception $e) {
    print "cannot do get invoices";
    print $e;
}
foreach ($All_Invoices as $invoice) {
	$invoiceIDs[] = $invoice->getId();	
}

/* 
This takss the invoice numbers found by the classic aip and uses the REST api to get the needed transaction data

Looks up an invoice and returns the tax and transaction number
If the invoice search returned the tax element this step wouldn't be neccissary,
but as it stands the Invoice:getitems($invoiceID) returns an error from paypal....

This is basically to get the HST value
*/

function getInvoiceDetails ($invoiceNumber, $apiContext, $paypal) {

	try {
	    	$invoice = Invoice::get($invoiceNumber, $apiContext);
	} catch (Exception $ex) {
	    	ResultPrinter::printError("Get Invoice", "Invoice", $invoice->getId(), $invoiceId, $ex);
		exit(1);
	}

	$taxSum = 0;
	foreach ($invoice->getItems() as $item) {

		if ($item->getTax()) {
			$taxSum += $item->getTax()->getAmount()->getValue();	
		}
	}

	$tID = 0;
	//This is the key value to hook up with the classis paypal API
	$tID = $paypal->request($invoice->getPayments()['0']->getTransactionId())['L_TRANSACTIONID0'];
	return array('date' => $invoice->getPayments()['0']->getDate(),
		'memo' => $invoice->getMerchantMemo(),
		'number' => $invoice->getNumber(),
		'tax' => $taxSum,		
		'tID' => $tID,
	);	
}

$invoiceArray = array();

//Based on the classis API this calls the REST api to get the details (or the other way around?)

foreach ($invoiceIDs as $id) {
	$invoiceArray[] = getInvoiceDetails($id, $apiContext, $paypal);
}

//This is a list of all the transactions including fee, but without taxes
$transactionArray = $paypal->request();

foreach ($transactionArray as $id => $transaction) {
	foreach ($invoiceArray as $invoice) {	
		//print "<br> invoice TID: " . $invoice['tID'] . " Transaction tID : " . $transaction['tID'];
		//print_r( array_values(array_keys($transaction)));
	
		if ($invoice['tID'] == $transaction['tID']) {

			$transactionArray[$id]['memo'] = $invoice['memo'];
			$transactionArray[$id]['tax'] = $invoice['tax'];
			$transactionArray[$id]['number'] = $invoice['number'];
		}
	}	
}


//this is now the list of all transactions, but without fee and missing the direct payments
usort ($transactionArray, function ($a, $b){ if ($a['date'] > $b['date']) return false; return true;});

print "printing page\n";
/* THE DISPLAY OF THE DATA ON A NICE HTML PAGE */?>
<H2> Paypal Invoices </H2>
<H3> From: <?=$start?> to: <?=$end?></H3>
<table>
	<tr>
		<!--Headings-->
		<th> Invoice </th>
		<th> Type </th>
		<th> Name </th>
		<th> Paymet Date </th>
		<th> Total</th>
		<th> HST</th>
		<th> Gross </th>
		<th> Fee </th>
		<th> Net </th>
	</tr>


	<?php
	/*For loop for each row to calculate the row values and sum for total*/
	$net = 0;
	$tax = 0;
	$ILL = 0;
	$SC = 0;
	$unknown = 0;
	$fee = 0;
	$total = 0;
	$gross = 0;
	foreach ($transactionArray as $details) {
		
		$total += $details['net'] - $details['tax'];
		$gross += $details['net'] - $details['fee'];
		$net += $details['net'];
		$tax += $details['tax'];
		$fee += $details['fee'];
		if ($details['memo'] == "SC") {$SC += $details['net'] - $details['tax']; }
		elseif ($details['memo'] == "ILL") {$ILL += $details['net'] - $details['tax']; }
		else {$unknown += $details['net']; }

		?>
		<tr>
			<!--each row-->
			<td> <?= $details['number']?> </td>			
			<td> <?= $details['memo']?> </td>			
			<td> <?= $details['name']?> </td>
			<td> <?= substr($details['date'], 0 ,10)?> </td>						
			<td> <?= money_format("$%i", $details['net'] - $details['tax'])?> </td>			
			<td> <?= money_format("$%i", $details['tax'])?> </td>			
			<td> <?= money_format("$%i", $details['net'] - $details['fee'])?> </td>			
			<td> <?= money_format("$%i", $details['fee'])?> </td>			
			<td> <?= money_format("$%i", $details['net'])?> </td>			
		</tr>
	<?php } ?>


	<tr style="font-weight:bold">
		<!--Totals at the bottom-->
		<td>TOTAL</td>
		<td></td>
		<td></td>
		<td></td>
		<td><?= money_format("$%i", $total) ?></td>
		<td><?= money_format("$%i", $tax) ?></td>
		<td><?= money_format("$%i", $gross) ?></td>
		<td><?= money_format("$%i", $fee) ?></td>
		<td><?= money_format("$%i", $net) ?></td>			
	</tr>
</table>	

<br>

<h2> Deposit </h2>
<br>
TOTAL DEPOSIT SHOULD BE:<b> <?= money_format("$%i", $net) ?></b>
<br>
Of which <?= money_format("$%i", $tax) ?> is HST.

<h2> Pratt records of income by source </h2>

<br>
ILL is <?= money_format("$%i", $ILL) ?>
<br>
SC is <?= money_format("$%i", $SC) ?>
<br>
Other untracked income (most likely membership) <?= money_format("$%i", $unknown) 

?>

<hr>

<input type="button" onClick="location.href='index.php'" value="new Search"/>


