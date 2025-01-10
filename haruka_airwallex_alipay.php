<?php

use GuzzleHttp\Client;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function haruka_airwallex_alipay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Haruka Airwallex Alipay',
        ),
        'AirwallexSandbox' => array(
            'FriendlyName' => '测试模式',
            'Type' => 'yesno',
            'Description' => '勾选以启用测试模式',
        ),
        'AirwallexClientID' => array(
            'FriendlyName' => 'CLIENT ID',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从 Airwallex 获取到的 CLIENT ID',
        ),
        'AirwallexAPIToken' => array(
            'FriendlyName' => 'API 密钥',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从 Airwallex 获取到的 API 密钥',
        ),
        'AirwallexWebhook' => array(
            'FriendlyName' => 'Webhook 密钥',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从 Airwallex 获取到的 Webhook 密钥',
        ),
        'RefundFixed' => array(
            'FriendlyName' => '退款扣除固定金额',
            'Type' => 'text',
            'Size' => 30,
            'Default' => '0.00',
            'Description' => '$'
        ),
        'RefundPercent' => array(
            'FriendlyName' => '退款扣除百分比金额',
            'Type' => 'text',
            'Size' => 30,
            'Default' => '0.00',
            'Description' => '%'
        )
    );
}

function haruka_airwallex_alipay_link($params)
{
    try {
        $client = new Haruka_Airwallex_Alipay($params);
        $orderData = $client->createPaymentIntents($params);
        $data = $client->updatePaymentIntents($orderData['id']);

        if ($data['status'] == "REQUIRES_CUSTOMER_ACTION") {
            $actionUrl = htmlspecialchars($data['next_action']['url'], ENT_QUOTES, 'UTF-8');
            return '<button class="btn btn-primary" onclick="window.location.href=\'' . $actionUrl . '\'">' . $params['langpaynow'] . '</button>';
        }
    } catch (Exception $e) {
        return '<div class="alert alert-danger text-center" role="alert">支付网关错误，请联系客服进行处理</div>';
    }
    return '<div class="alert alert-danger text-center" role="alert">发生错误，请创建工单联系客服处理</div>';
}

function haruka_airwallex_alipay_refund($params)
{
    $amount = round(($params['amount'] - $params['RefundFixed']) / ($params['RefundPercent'] / 100 + 1), 2);
    try {
        $client = new Haruka_Airwallex_Alipay($params);
        $data = $client->createRefund($params,$amount);
        
        return array(
            'status' => $data['FAILED'] !== 'FAILED' ? 'success' : 'declined',
            'rawdata' => $data,
            'transid' => $params['transid'],
            'fees' => $params['amount'] - $amount,
        );
    } catch (Exception $e) {
        return array(
            'status' => 'error',
            'rawdata' => $e->getMessage(),
            'transid' => $params['transid'],
            'fees' => $params['amount'] - $amount,
        );
    }
}

class Haruka_Airwallex_Alipay {
    private $clientId;
    private $apiToken;
    private $client;

    public function __construct($params)
    {
        $this->clientId = $params['AirwallexClientID'];
        $this->apiToken = $params['AirwallexAPIToken'];

        $base_uri = $params['AirwallexSandbox'] ? 'https://api-demo.airwallex.com/' : 'https://api.airwallex.com/';
        $this->$client = new Client(['base_uri' => $base_uri . 'api/v1/']);
    }

    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    private function login() {
        try {
            $response = $this->$client->request('POST', 'authentication/login', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-client-id' => $this->clientId,
                    'x-api-key' => $this->apiToken
                ]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['token'];
        } catch (Exception $e) {
            throw $e;
        }
    }
    public function createRefund($params,$amount) {
        try {
            $token = $this->login();
            $response = $this->$client->request('POST', 'pa/refunds/create', [
                'json' => [
                    'amount' => $amount,
                    'payment_intent_id' => $params['transid'],
                    'reason' => 'Customer request',
                    'request_id'=> $this->generateUUID(),
                    'metadata' => [
                        'invoice_id' => $params['invoiceid'],
                        'original_amount' => $params['amount']
                    ]
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw $e;
        }
    }
    public function createPaymentIntents($params) {
        try {
            $token = $this->login();
            $response = $this->$client->request('POST', 'pa/payment_intents/create', [
                'json' => [
                    'amount' => $params['amount'],
                    'currency' => $params['currency'],
                    'customer' => [
                        'email' => $params['clientdetails']['email'],
                        'first_name' => $params['clientdetails']['firstname'],
                        'last_name' => $params['clientdetails']['lastname'],
                        'phone_number' => $params['clientdetails']['phonenumber'],
                    ],
                    'descriptor' => $params["description"],
                    'request_id' => $this->generateUUID(),
                    'return_url' => $params['systemurl'] . 'viewinvoice.php?id=' . $params['invoiceid'],
                    'metadata' => [
                        'invoice_id' => $params['invoiceid'],
                        'original_amount' => $params['amount']
                    ],
                    'merchant_order_id' => $params['invoiceid']
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw $e;
        }
    }
    public function updatePaymentIntents($orderId) {
        try {
            $token = $this->login();
            $response = $this->$client->request('POST', 'pa/payment_intents/' . $orderId . '/confirm', [
                'json' => [
                    'request_id' => $this->generateUUID(),
                    'payment_method' => [
                        'type' => 'alipaycn',
                        'alipaycn' => [
                            'flow' => 'qrcode'
                        ]
                    ]
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);
            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            throw $e;
        }
    }
}