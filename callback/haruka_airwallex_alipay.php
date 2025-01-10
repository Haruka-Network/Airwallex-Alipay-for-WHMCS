<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $_SERVER['HTTP_X_TIMESTAMP'] . $payload,  $gatewayParams['AirwallexWebhook']);

if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE'])) {
    http_response_code(400);
    exit();
}

try {
    if (isset($payload)) {
        $data = json_decode($payload, true);
        if ($data['name'] == 'payment_intent.succeeded') {
            $object = $data['data']['object'];
            if ($object['latest_payment_attempt']['payment_method']['type'] == 'alipaycn'){
                $invoiceId = $object['metadata']['invoice_id'];
                $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
                checkCbTransID($object['id']);
                echo "Pass the checkCbTransID check\n";
                logTransaction($gatewayParams['name'], $data, 'Callback successful');
                
                addInvoicePayment(
                    $invoiceId,
                    $object['id'],
                    $object['amount'],
                    0,
                    $gatewayModuleName
                );
                echo "Success to addInvoicePayment\n";
            } else {
                echo 'Received unhandled payment method type: ' . $object['latest_payment_attempt']['payment_method']['type'];
            }
        } else {
            echo 'Received unhandled event type: ' . $event->type;
        }
    }
} catch (Exception $e) {
    logTransaction($gatewayParams['name'], $e, 'error-callback');
    http_response_code(400);
    echo $e;
}