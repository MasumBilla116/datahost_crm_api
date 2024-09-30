<?php

namespace  App\Controllers\Payments;

session_start();

use App\Auth\Auth;
use App\Helpers\Accounting;
use App\Helpers\Helper;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use App\Models\Customers\CustomerBookingGrp;
use App\Models\Customers\Customer;

use Respect\Validation\Validator as v;
use App\Models\Restaurant\RestaurantInvoice;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\TextUI\Help;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Bkash
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $invoices;
    protected $customer_booking;

    private $tokenUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant';
    private $createUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create';
    private $executeUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute';
    private $app_key = ""; // "0vWQuCRGiUX7EPVjQDr0EUAYtc";
    private $app_secret_key = ''; // "jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx";
    private $app_username = ''; //"01770618567";
    private $app_password = ''; //"D7DaC<*E*eG";
    private $app_version = 'sandbox'; // sandbox and live

    public function __construct()
    {
        $this->customer_booking = new CustomerBookingGrp();
        $this->customResponse = new CustomResponse();
        $this->invoices = new RestaurantInvoice();
        $this->user = new ClientUsers();
        $this->validator = new Validator();

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;


        $bkash = DB::table("payment_methods")->select("*")->where("method_name", "Bkash")->first();
        if (!empty($bkash)) {
            $this->app_key = $bkash->app_key;
            $this->app_secret_key = $bkash->app_secret_key;
            $this->app_username = $bkash->app_user;
            $this->app_password = $bkash->app_password;
        }
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);

        switch ($action) {
            case "createPayment":
                $this->createPayment($request, $response);
                break;
            default:
                $this->responseMessage = "Invalid request!";
                return $this->customResponse->is400Response($response, $this->responseMessage);
                break;
        }

        if (!$this->success) {
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function createPayment(Request $request, Response $response)
    {
        DB::beginTransaction();
        try {

            $this->params = CustomRequestHandler::getAllParams($request);

            $datetime = date("y") . date("m") . date("d") . time();
            $total_amount = $this->params->netPayable;
            $callbackURL = $this->params->callbackURL;
            $authToken = $this->params->authToken;
            $currency = $this->params->currency;

            $paymentData = array(
                "amount" => "$total_amount",
                "currency" => "BDT",
                "payerReference" => "123",
                "merchantInvoiceNumber" => "$datetime",
                "intent" => "sale",
                "mode" => "0011",
                "callbackURL" =>  "$callbackURL",
            );


            $header = array(
                'Content-Type:application/json',
                'Accept:application/json',
                'Authorization:' . $authToken,
                'X-APP-Key:' . $this->app_key,
            );

            $paymentDataEncoded = json_encode($paymentData);

            $url = curl_init($this->createUrl);
            curl_setopt($url, CURLOPT_HTTPHEADER, $header);
            curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($url, CURLOPT_POSTFIELDS, $paymentDataEncoded);
            curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);

            $resultData = curl_exec($url);
            curl_close($url);

            $result = json_decode($resultData);

            $bookingData =  $this->bookingCreation($request, $response);

            if ($bookingData == false) {
                $this->responseMessage = "Bkash create payment failed";
                $this->outputData = [];
                $this->success = false;
                return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
            }


            DB::table('online_payment_authtokens')->insert([
                "token" => $authToken,
                "amount" => $total_amount,
                "payment_id" => $result->paymentID,
                'account_invoice_ref' => $bookingData['account_customer_inv'],
                "room_booking_invoice" => $bookingData['room_booking_inv'],
                "successPageURL" => $this->params->successPageURL,
                "failedPageURL" => $this->params->failedPageURL
            ]);


            DB::table("online_payments")->insert([
                "total_amount" => $total_amount,
                "payment_amount" => 0,
                "due_amount" => $total_amount,
                'account_id' => $this->params->account_id,
                'payment_id' => $result->paymentID,
                'transaction_id' => null,
                'payment_method' => 'Bkash',
                'account_invoice_ref' => $bookingData['account_customer_inv'],
                "room_booking_invoice" => $bookingData['room_booking_inv'],
                'payment_status' => 'unpaid',
                'payment_type' => "online",
            ]);

            DB::commit();
            $this->responseMessage = "Bkash create payment";
            $this->outputData = $result;
            $this->success = true;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = $th;
            $this->success = false;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
    }



    public function paymentExecute(Request $request, Response $response)
    {
        $paymentID = $_GET['paymentID'];
        DB::beginTransaction();

        $paymentData = DB::table("online_payment_authtokens")->select("*")->where("payment_id", $paymentID)->first();
        $roomBooking =  DB::table("customer_booking_master")->select("*")->where([
            "invoice_id" => $paymentData->room_booking_invoice
        ])->first();
        try {

            if (!empty($_GET['status']) && strtolower($_GET['status']) === 'success') {

                $authToken =  $paymentData->token;

                // execute payment
                $header = array(
                    'Content-Type:application/json',
                    'Accept:application/json',
                    'Authorization:' . $authToken,
                    'X-APP-Key:' . $this->app_key
                );

                $paymentDataEncoded = json_encode(['paymentID' => $paymentID]);

                $url = curl_init($this->executeUrl);
                curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($url, CURLOPT_POSTFIELDS, $paymentDataEncoded);
                curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);

                $resultData = curl_exec($url);
                curl_close($url);
                $paymentExecute = json_decode($resultData);

                if (isset($paymentExecute->statusMessage) && !empty($paymentExecute->statusMessage) && strtolower($paymentExecute->statusMessage) == 'insufficient balance') {
                    return $response
                        ->withHeader('Location', $paymentData->failedPageURL . "?status=failed")
                        ->withStatus(302);
                }

                if (!empty($paymentExecute->transactionStatus) && (strtolower($paymentExecute->transactionStatus) == "completed")) {

                    $transactionID = $paymentExecute->trxID;
                    DB::table("online_payments")->where([
                        'payment_id' => $paymentID,
                    ])->update([
                        "payment_amount" => $paymentData->amount,
                        "due_amount" => 0,
                        'transaction_id' => $transactionID,
                        'payment_status' => 'paid',
                    ]);

                    // $onlinePayment =  DB::table("online_payments")->where([
                    //     'payment_id' => $paymentID,
                    // ])->first();

                     DB::table("customer_booking_master")->where([
                        "invoice_id" => $paymentData->room_booking_invoice,
                        // "payment_accounts_id"=>
                    ])->update([
                        "total_paid" => $paymentData->amount,
                        "total_due" => 0,
                        "advance_payment"=>$paymentData->amount,
                        "payment_status"=>1
                    ]);

                    $customer_booking_grp =DB::table("customer_booking_master")->where([
                        "invoice_id" => $paymentData->room_booking_invoice,
                        // "payment_accounts_id"=>
                    ])->first();
                   

                    // DB::table("account_bank")->insert([
                    //     'inv_type' => $paymentID,
                    // ]);



                    //Insert balance into account_customer table through accountCustomer function
                    $credited_note = "Payment taken from customer";
                    $debited_note = "Bill generated for booking";
                    $credit = false;



                    Helper::notification($roomBooking->customer_id, "Room Booking Online Payment", "Online Booking", "Your payment is success");


                    Accounting::Accounts($roomBooking->payment_accounts_id, $roomBooking->id, "booking",  $roomBooking->total_paid, $credited_note, $debited_note, $roomBooking->customer_id, true);
                    // Accounting::accountCustomer($credit, $roomBooking->customer_id, $roomBooking->id,  $roomBooking->invoice_id, "booking", $roomBooking->total_paid, $roomBooking->total_paid, $credited_note, $debited_note,  $roomBooking->customer_id, false);

                    $inv_no = strtotime('now');
                    $payment_slips_number = 'PAYSLIP-' . $roomBooking->id . '-' . $inv_no;

                    DB::table('payment_collection_slip')->insert([
                        'invoice_id' => $roomBooking->id,
                        'invoice_type' => "booking",
                        'slip_number' => $payment_slips_number,
                        'payee' => $roomBooking->customer_id,
                        "account_id" => $roomBooking->payment_accounts_id,
                        'amount' => $paymentData->amount,
                        'reference' => "Online Payment",
                        'payment_date' => date('Y-m-d', strtotime('now')),
                        'created_by' => $roomBooking->customer_id,
                        'status' => 1
                    ]);


                    $inv_encrypt = Helper::encrypt($paymentData->room_booking_invoice);

                    DB::commit();
                    return $response
                        ->withHeader('Location', $paymentData->successPageURL . "?rbinv=" . $inv_encrypt)
                        ->withStatus(302);
                }
                DB::rollback();
                Helper::notification($roomBooking->customer_id, "Room Booking Online Payment failed", "Online Booking failed", "Your payment is failed");

                return $response
                    ->withHeader('Location', $paymentData->failedPageURL . "?status=failed")
                    ->withStatus(302);
            } else {

                DB::rollback();
                Helper::notification($roomBooking->customer_id, "Room Booking Online Payment failed", "Online Booking failed", "Your payment is failed");
                return $response
                    ->withHeader('Location', $paymentData->failedPageURL . "?status=failed")
                    ->withStatus(302);
            }
        } catch (\Exception $th) {
            DB::rollback();
            Helper::notification($roomBooking->customer_id, "Room Booking Online Payment failed", "Online Booking failed", "Your payment is failed");
            return $response
                ->withHeader('Location', $paymentData->failedPageURL . "?status=failed")
                ->withStatus(302);
        }
    }


    public function initializePayment(Request $request, Response $response)
    {
        $url = curl_init($this->tokenUrl);
        $header = array(
            "Content-Type:application/json",
            "Accet:application/json",
            "username:" . $this->app_username,
            "password:" . $this->app_password,
        );

        $appData = [
            "app_key" => $this->app_key,
            "app_secret" => $this->app_secret_key
        ];

        $jsonAppData = json_encode($appData);
        curl_setopt($url, CURLOPT_HTTPHEADER, $header);
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $jsonAppData);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $responseData = curl_exec($url);
        curl_close($url);


        $this->responseMessage = "Bkash payment grand token";
        $this->outputData = json_decode($responseData);
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function bookingCreation(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);

        DB::beginTransaction();
        try {

            $customerId = $this->params->userId;
            if (!empty($this->params->request_from) &&  $this->params->request_from === 'web') {
                $customerId = Customer::where('uid', '=', $this->params->userId)->value('id');
            }

            if (empty($customerId)) {
                return false;
            }

            $from_date_ = date('Y-m-d', strtotime($this->params->date_from));
            $to_date_ = date('Y-m-d', strtotime($this->params->date_to));

            // if ($this->params->date_to != "") {
            //     if (strtotime($this->params->date_to) > strtotime($this->params->date_from)) {
            //         $to_date_ = date('Y-m-d', strtotime("-1 day", strtotime($this->params->date_to)));
            //     }
            // }

            $roomBooking_invoice = 'RB-' . '100' . '-' . strtotime('now');
            $customer_booking = $this->customer_booking->insertGetId([
                'channel_id' => !empty($this->params->channel_id) ? $this->params->channel_id : null,
                'booking_id' => !empty($this->params->booking_id) ? $this->params->booking_id : null,
                'platform' => $this->params->request_from,
                'invoice_type' => "booking",
                'customer_id' => $customerId,
                'payment_accounts_id' => $this->params->account_id,
                'date_from' => $from_date_,
                'date_to' =>  $to_date_,
                'invoice_id' => $roomBooking_invoice,
                'checkout_type' => '24hrs',
                'total_amount' => $this->params->netPayable,
                'promo_discount' => $this->params->promoDiscount,
                'additional_discount' => 00,
                'total_tax' => $this->params->tax ?? 00,
                'payment_type' => $this->params->payment_type,
                'payment_status'=>3,
                'payment_ref' => $this->params->payment_ref,
                'net_amount' => $this->params->netPayable,
                'total_paid' => 0, // @@@@ Unpaid
                'refund_amount' => 00,
                'total_due' => $this->params->netPayable, // @@@ Due amount
                "status" => 3,

            ]);

            Helper::notification($customerId, "Room Booking", "Booking", "Your room booking is pending");


            $customer = DB::table('customers')->where('uid', $this->params->userId)->first();
            if ($customer) {
                $customer_booking_grp = $this->customer_booking;
            }





            if ($this->params->checkout_type === 'hourly') {
                $tarrif = DB::table('room_prices_hourly')
                    ->where(['room_type_id' => $this->params->room_type_id, 'hour_slot_id' => $this->params->hourly_slot_id, 'date' => $from_date_])
                    ->first();
            } else {

                $tarrif = DB::table('room_price')
                    ->where('room_type_id', $this->params->room_type_id)
                    ->whereBetween('date', [$from_date_, $to_date_])
                    ->get();
            }




            if ($customer_booking) {
                // for ($x = 0, $i = $begin; $i <= $end; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {
                for ($x = 0, $i = $from_date_; $i < $to_date_; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {

                    if ($this->params->checkout_type === 'hourly') {
                        $tarrif_id = $tarrif->id;
                        $tarrif_amount = $tarrif->price;
                    } else {
                        $tarrif_id = $tarrif[$x]->id;
                        $tarrif_amount = $tarrif[$x]->price;
                    }

                    DB::table('customer_booking_days')->insertGetId(
                        array(
                            'booking_master_id' => $customer_booking,
                            'customer_id' => $customerId,
                            'room_id' => null,
                            'tower_id' => null,
                            'room_type_id' => $this->params->room_type_id,
                            'room_category_id' => null,
                            'date' => date('Y-m-d', strtotime($i)),
                            'adults' => $this->params->adults,
                            'childs' => $this->params->childs,
                            'tarrif_id' => $tarrif_id,
                            'tarrif_amount' => $tarrif_amount,

                            // 'additional_total_adult_amount' => $additional_adult_tarrif[$x],
                            // 'additional_total_child_amount' => $additional_child_tarrif[$x],
                            // 'total_amount' => $tarrif_amount + $additional_adult_tarrif[$x] + $additional_child_tarrif[$x]

                            'additional_total_adult_amount' => 0,
                            'additional_total_child_amount' => 0,
                            'total_amount' => $tarrif_amount,
                        )
                    );
                }
            }

            $inv_no = strtotime('now');
            $invoice_id = 'RB-' . $customer_booking . '-' . $inv_no;

            DB::commit();
            return  [
                "room_booking_inv" => $roomBooking_invoice,
                "account_customer_inv" => $invoice_id,
                "payment_slips_number" => "",
                "customer_booking" => $customer_booking
            ];
        } catch (\Exception $th) {
            DB::rollBack();
            return false;
        }
    }
}
