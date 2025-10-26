<?php

namespace App\Http\Controllers\Gateway\Coingate;

use App\Constants\Status;
use App\Models\Deposit;
use App\Http\Controllers\Controller;
use CoinGate\Client;
use CoinGate\Merchant\Order;
use App\Http\Controllers\Gateway\PaymentController;
use App\Lib\CurlRequest;

class ProcessController extends Controller
{
    /*
     * Coingate Gateway 505
     */

    public static function process($deposit)
    {
        $coingateAcc = json_decode($deposit->gatewayCurrency()->gateway_parameter);

        $client = new Client();
        $client->setApiKey($coingateAcc->api_key);
        $client->setEnvironment('live');

        $postParams = array(
            'order_id' => $deposit->trx,
            'price_amount' => round($deposit->final_amount,2),
            'price_currency' => $deposit->method_currency,
            'receive_currency' => $deposit->method_currency,
            'callback_url' => route('ipn.'.$deposit->gateway->alias),
            'cancel_url' => route('home').$deposit->failed_url,
            'success_url' => route('home').$deposit->success_url,
            'title' => 'Payment to ' . gs('site_name'),
            'token' => $deposit->trx
        );

        try {
            $order = $client->order->create($postParams);
        } catch (\Exception $e) {
            $send['error'] = true;
            $send['message'] = $e->getMessage();
            return json_encode($send);
        }
        if ($order) {
            $send['redirect'] = true;
            $send['redirect_url'] = $order->payment_url;
        } else {
            $send['error'] = true;
            $send['message'] = 'Unexpected Error! Please Try Again';
        }
        $send['view'] = '';
        return json_encode($send);
    }

    public function ipn()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $url = 'https://api.coingate.com/v2/ips-v4';
        $response = CurlRequest::curlContent($url);
        if (strpos($response, $ip) !== false) {
            $deposit = Deposit::where('trx', $_POST['token'])->orderBy('id', 'DESC')->first();
            if ($_POST['status'] == 'paid' && $_POST['price_amount'] == $deposit->final_amount && $deposit->status == Status::PAYMENT_INITIATE) {
                PaymentController::userDataUpdate($deposit);
            }
        }
    }
}
