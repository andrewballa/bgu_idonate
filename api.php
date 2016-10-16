<?php
include("xmlrpc-2.0/lib/xmlrpc.inc");
$client = new xmlrpc_client("https://dp325.infusionsoft.com/api/xmlrpc"); //bgu https://hq171.infusionsoft.com/api/xmlrpc
$client->return_type = "phpvals";
$client->setSSLVerifyPeer(FALSE);
$key = "bc29d63e074cb34cceee0df381062c88";
//bgu 1fb3245bda5f2517cf678c1cf28946a1
//bethanygateway bc29d63e074cb34cceee0df381062c88 - passphrase abc123

##################################################
###     FUNCTIONS TO EXECUTE XML API CALLS     ###
##################################################

function buildXmlCall_query($tableName, $howManyRecords, $pageToReturn, $struct_SearchFields, $array_FieldsToReturn)
{
    global $key;

    //api call to find product, returns an Array
    $call = new xmlrpcmsg("DataService.query",array(
        php_xmlrpc_encode($key),
        php_xmlrpc_encode($tableName), //which table to find from
        php_xmlrpc_encode($howManyRecords), //how many records
        php_xmlrpc_encode($pageToReturn), //which page to retrieve, 0 is default
        php_xmlrpc_encode($struct_SearchFields), //the field(s) to search on
        php_xmlrpc_encode($array_FieldsToReturn), //the fields to return
    ));
    return $call;
}

function buildXmlCall_Add($tableName,$struct_itemsToAdd)
{
    global $key;

    $call = new xmlrpcmsg("DataService.add", array(
        php_xmlrpc_encode($key),
        php_xmlrpc_encode($tableName), //which table to add too
        php_xmlrpc_encode($struct_itemsToAdd),
    ));

    return $call;
}

function buildXmlCall_Update($tableName, $rowId, $struct_itemsToUpdate)
{
    global $key;

    $call = new xmlrpcmsg("DataService.update", array(
        php_xmlrpc_encode($key),
        php_xmlrpc_encode($tableName), //which table to update
        php_xmlrpc_encode($rowId), //ID of row to update
        php_xmlrpc_encode($struct_itemsToUpdate),
    ));

    return $call;
}

function executeApiCall($xmlCall)
{
    global $client;
    //Send the call
    $result=$client->send($xmlCall);

    if(!$result->faultCode()) {
        return $result->value();
    }
    else if($result->faultCode()) {
        //if there's an error, write the error message and the xmlcall to a log file
        $vardump = var_export(php_xmlrpc_decode($xmlCall), true);
        $filecontents = "INFUSIONSOFT API ERROR MESSAGE: " . date("Y-m-d H:i:s") . "\r\n". $result->faultString() . "\r\n\r\n" . $xmlCall->method() . "\r\n\r\n" . $vardump;

        //file_put_contents('logfile.txt',$filecontents,FILE_APPEND); //remove in prod

        createErrorLog($filecontents);
        return "ERROR";
    }
    else
    {
        $vardump = var_export(php_xmlrpc_decode($xmlCall), true);
        $filecontents = "ERROR: " . date("Y-m-d H:i:s") . "\r\n". $result->faultString() . "\r\n\r\n" . $xmlCall->method() . "\r\n\r\n" . $vardump;
        createErrorLog($filecontents);
    }
}

function createErrorLog($filecontents)
{
    $errorfile = fopen("errorlog/error_log_".date("Y-m-d H:i:s").".txt", "w") or die("Unable to open file!");
    fwrite($errorfile, $filecontents);
    fclose($errorfile);
}


//Function to add or update a contact
function addUpdateContact($idonateObj){
    global $client, $key;

    //Create our contact array
    $array_contact = array(
        "FirstName"		    => $idonateObj->create[0]->transaction->contact->firstname,
        "LastName" 		    => $idonateObj->create[0]->transaction->contact->lastname,
        "Email" 		    => $idonateObj->create[0]->transaction->contact->email,
        "Phone1"            => $idonateObj->create[0]->transaction->contact->phone,
        "StreetAddress1"    => $idonateObj->create[0]->transaction->address->street,
        "StreetAddress2"    => $idonateObj->create[0]->transaction->address->street2,
        "City"              => $idonateObj->create[0]->transaction->address->city,
        "State"             => $idonateObj->create[0]->transaction->address->state,
        "PostalCode"        => $idonateObj->create[0]->transaction->address->zip,
    );

    $duplicateCheckField = "Email";

    //Build the Call
    $apiCall_addcontact = new xmlrpcmsg("ContactService.addWithDupCheck",array(
        php_xmlrpc_encode($key),
        php_xmlrpc_encode($array_contact),
        php_xmlrpc_encode($duplicateCheckField),
    ));

    //Send the call
    $result_addcontact= executeApiCall($apiCall_addcontact);

    if($result_addcontact!="ERROR") {
        echo "ContactId: " . $result_addcontact;
        return $result_addcontact;
    }
    else {
        echo $result_addcontact->faultString();
        return null;
    }
}

//Function to add or update an product
function addUpdateProduct($idonateObj){

    $productId = 0;
    $productName = $idonateObj->create[0]->transaction->designation_title;
    $productSku = $idonateObj->create[0]->transaction->designation_fund_id;
    $prodCategoryTitle = $idonateObj->create[0]->transaction->campaign_title;

    $productValues = array(
        "ProductName" => $productName,
        "Sku" => $productSku,
    );

    $call = buildXmlCall_query("Product",10,0,array("Sku"=>$productSku),array('Id'));
    $result_findProd = executeApiCall($call);

    if($result_findProd!="ERROR")
    {
        $call2 = null;
        $successMsg = "";
        $productId = $result_findProd[0][Id];

        if($productId==null) //product doesnt exits, build xml to add it
        {
            $call2 = buildXmlCall_Add("Product",$productValues);
            $successMsg = "New Product Added: ";
        }
        else //product already exists, build xml to update it
        {
            $call2 = buildXmlCall_Update("Product",$productId,$productValues);
            $successMsg = "Product Updated: ";
        }

        $result_addUpdateProd = executeApiCall($call2); //add or update the product

        if($result_addUpdateProd!="ERROR") {
            $productId = $result_addUpdateProd;
            echo $successMsg . $productId ; //remove in prod
            addUpdateProdCategory($prodCategoryTitle,$productId); //call function to add product category
            return $productId;
        }
        else
        {
            echo "Error in adding or updating product";//remove in prod
            return null;
        }
    }
    else
    {
        echo "Error in finding product";//remove in prod
        return null;
    }

}

//Function to add or update a category
function addUpdateProdCategory($categoryName, $productId){

    /*####this section makes an API call to add product category and gets the result####*/
    $categoryValues  = array("CategoryDisplayName" => $categoryName);
    $categoryId = 0;

    $call = buildXmlCall_query("ProductCategory",10,0,$categoryValues,array('Id'));
    $result_findCategory = executeApiCall($call);

    if($result_findCategory!="ERROR")
    {
        $call2 = null;
        $categoryId = $result_findCategory[0][Id];
        $successMsg = "";

        if($categoryId==null) //category doesn't exist, build xml to add it
        {
            $call2 = buildXmlCall_Add("ProductCategory",$categoryValues);
            $successMsg = "Category Added: ";
        }
        else //category already exists, build xml to update it
        {
            $call2 = buildXmlCall_Update("ProductCategory",$categoryId,$categoryValues);
            $successMsg = "Category Updated: ";
        }

        $result_addUpdateCategory = executeApiCall($call2); //add or update category

        if($result_addUpdateCategory!="ERROR")
        {
            $categoryId = $result_addUpdateCategory;
            $prodCategoryAssignVal  = array(
                "ProductId" => $productId,
                "ProductCategoryId" => $categoryId, //Id of "iDonate" category.
            );

            $call3 = buildXmlCall_query("ProductCategoryAssign",10,0,$prodCategoryAssignVal,array("Id"));
            $result_findProdCatAssign = executeApiCall($call3);
            echo $successMsg . $categoryId;

            if($result_findProdCatAssign[0][Id]==null) //product isn't assinged to category, add a record to ProductCategoryAssign table
            {
                $call4 = buildXmlCall_Add("ProductCategoryAssign",$prodCategoryAssignVal);
                $result_addProdCatAssign = executeApiCall($call4);
                echo "  ProdCatAssignId: " . $result_addProdCatAssign;
                $orderId = addOrder();
            }
            else
            {
                echo "Product is already assigned to category"; //remove in prod
            }
        }
        else
        {
            echo "Error in adding or updating category";//remove in prod
        }
    }

}

//Function to create an order for a contact
function addOrder($idonateObj, $contactId, $productId) {
    global $client, $key;

    $ordertitle = "iDonate - " . $idonateObj->create[0]->transaction->campaign_title; //$idonateObj->create[0]->transaction->description;
    $note = $idonateObj->create[0]->transaction->description; //$idonateObj->create[0]->transaction->designation_note;
    $donationAmount = $idonateObj->create[0]->transaction->client_proceeds;
    $donationType = $idonateObj->create[0]->transaction->type;
    $donationStatus = "iDonate Donation Status:" . $idonateObj->create[0]->transaction->status;

    $dateobj  = strtotime($idonateObj->create[0]->transaction->created);
    $orderDate = date('Ymd\TH:i:s',$dateobj);

    //create an invoice
    $call = new xmlrpcmsg("InvoiceService.createBlankOrder", array(
        php_xmlrpc_encode($key), 				#The encrypted API key
        php_xmlrpc_encode((int)$contactId),				#The Contact ID
        php_xmlrpc_encode($ordertitle),				#The Order Description
        php_xmlrpc_encode($orderDate, array('auto_dates')),		#The Order Date
        php_xmlrpc_encode(0),				#The Lead Affiliate ID
        php_xmlrpc_encode(0),				#The Sale Affiliate ID
    ));
    $invoiceId = executeApiCall($call);

    if($invoiceId!="ERROR") {
        //add line item (product) to the invoice
        $call2 = new xmlrpcmsg("InvoiceService.addOrderItem", array(
            php_xmlrpc_encode($key),                #The encrypted API key
            php_xmlrpc_encode((int)$invoiceId),     #The Invoice ID
            php_xmlrpc_encode((int)$productId),     #The Product ID
            php_xmlrpc_encode(4),                   #The Type of Item - FINANCECHARGE = 6; PRODUCT = 4; SERVICE = 3;SHIPPING = 1; SPECIAL = 7; TAX = 2; UNKNOWN = 0; UPSELL = 5;
            php_xmlrpc_encode($donationAmount),     #donation amount
            php_xmlrpc_encode(1),                   #quantitiy
            php_xmlrpc_encode($ordertitle),         #line item title
            php_xmlrpc_encode($note),               #Item Notes
        ));
        $lineItemId = executeApiCall($call2);

        if($lineItemId==true)
        {
            //manually updated the invoice as PAID in Infusionsoft (since it was already paid in iDonate)
            $call3 = new xmlrpcmsg("InvoiceService.addManualPayment", array(
                php_xmlrpc_encode($key),
                php_xmlrpc_encode($invoiceId),
                php_xmlrpc_encode($donationAmount),
                php_xmlrpc_encode($orderDate, array('auto_dates')),
                php_xmlrpc_encode($donationType),       //type of donation, e.g. cash, credit card etc
                php_xmlrpc_encode($donationStatus),     //whether donation is completed or pending in iDonate
                php_xmlrpc_encode(false),               //Whether this payment should count towards affiliate commissions.
            ));
            $paymentSuccess = executeApiCall($call3);
            return "OrderId: ". $invoiceId. "Payment Successful:" . $paymentSuccess;
        }
    }


}




############################################
###   Execute actions on the page        ###
############################################

$getIdonateData = file_get_contents('php://input'); //this is the idonate data that is POSTed to this page, we capture it into a variable

if($getIdonateData!=null) {
    $idonateArray = json_decode($getIdonateData);

    if(!json_last_error()) {
        $contactId = addUpdateContact($idonateArray);
        if ($contactId != null) {
            $productId = addUpdateProduct($idonateArray);
            if ($productId != null) {
                $paymentResult = addOrder($idonateArray, $contactId, $productId);
                echo $paymentResult;
            }
        }
    }
    else
    {
        $msg = "Something went wrong, here is the iDonate data: \r\n";
        createErrorLog($msg . $getIdonateData);
    }
}
else
{
    echo "There is no iDonate data coming in.";
}

?>

