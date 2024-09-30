<?php

namespace  App\Controllers\Payments;

use App\Auth\Auth;
use App\Helpers\Accounting;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use App\Models\Restaurant\RestaurantInvoice;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class PaymentSlipController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $invoices;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->invoices = new RestaurantInvoice();
        $this->user = new ClientUsers();
        $this->validator = new Validator();

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);

        switch ($action) {

            case 'createPaymentSlip':
                $this->createPaymentSlip($request);
                break;
            case 'roomServiceMakePayment':
                $this->roomServiceMakePayment($request);
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

    public function createPaymentSlip(Request $request)
    {

        $this->validator->validate($request, [
            "booking_master_id" => v::notEmpty(),
            "account_id" => v::notEmpty(),
            "payee" => v::notEmpty(),
            "amount" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $invoice_id = $this->params->booking_master_id;

        $payment = DB::table('payment_collection_slip')->insert([
            'invoice_id' => $invoice_id,
            'invoice_type' => $this->params->invoice_type,
            // 'record_id' => $this->params->record_id,
            'slip_number' => 'PAYSLIP-' . $invoice_id . '-' . strtotime('now'),
            'account_id' => $this->params->account_id,
            'payee' => $this->params->payee,
            'amount' => $this->params->amount,
            'reference' => $this->params->reference,
            'remark' => $this->params->remark,
            'payment_date' => date("Y-m-d"),
            'created_by' => $this->user->id,
            'status' => 1
        ]);

        if (!$payment) {
            $this->responseMessage = "Payment collection failed. Please try again !";
            $this->outputData = [];
            $this->success = false;
        }

        //if payment success, than calculate balance of every account related other table, Like, customers balance, booking master paid ,etc. 
        $booking_master = DB::table('customer_booking_master')->where(['id' => $invoice_id, 'status' => 1])->first();

        DB::table('customer_booking_master')->where(['id' => $invoice_id, 'status' => 1])->update([
            'total_paid' => ($booking_master->total_paid + $this->params->amount),
            'total_due' => ($booking_master->total_due - $this->params->amount),
        ]);

        $credited_note = "payment taken from customer";
        $debited_note = "";

        Accounting::Accounts($this->params->account_id, $invoice_id, $this->params->invoice_type, $this->params->amount, $credited_note, $debited_note, $this->user->id, true);
        //Balance for account customer
        $credited_note = "Payment taken from customer";
        $debited_note = "";
        $credit = true;

        Accounting::accountCustomer(false, $this->params->payee, $invoice_id, $booking_master->invoice_id, $this->params->invoice_type, $booking_master->net_amount, $this->params->amount, $credited_note, $debited_note, $this->user->id, true);



        foreach ($this->params->selectedRows as $row) {
            if (isset($row['restaurant_invoices'])) {
                $invoice = $this->invoices->find($row['restaurant_invoices']['id']);
                $dueAmount = $row['restaurant_invoices']['due_amount'];
                $invoice->update([
                    "paid_amount" => $dueAmount,
                    "is_paid" => 1,
                    "is_hold" => 0,
                ]);
            }

            if (isset($row['customer_hourly_due'])) {
                $invoiceId = $row['customer_hourly_due']['id'];
                // $dueAmount = 0;
                $dueAmount = $row['customer_hourly_due']['due_amount'];
                
                DB::table('customer_hourly_due')->where('id', $invoiceId)->update([
                    'paid' => $dueAmount,
                ]);
            }
            

            if (isset($row['vehicle_booking'])) {
                $vehicle_invoice = DB::table('vehicle_booking_items')
                                    ->where('id', $row['vehicle_booking']['id'])
                                    ->where('customer_id', $this->params->payee)
                                    ->where('status', 1)
                                    // ->where('is_paid', 0)
                                    ->first();
            
                if ($vehicle_invoice) {
                    DB::table('vehicle_booking_items')
                        ->where('id', $row['vehicle_booking']['id'])
                        ->update([
                            "is_paid" => 1,
                        ]);
                }
            }


            // if (isset($row['cust_room_service'])) {
            //     $vehicle_invoice = DB::table('cust_room_service_inv')
            //                         ->where('id', $row['cust_room_service']['id'])
            //                         ->where('inv_number', $row['cust_room_service']['inv_number'])
            //                         // ->where('status', 1)
            //                         // ->where('is_paid', 0)
            //                         ->first();
            //                         $dueAmount = $row['cust_room_service']['due_amount'];
            //     if ($vehicle_invoice) {
            //         DB::table('cust_room_service_inv')
            //             ->where('id', $row['cust_room_service']['id'])
            //             ->update([
            //                 "paid" => $dueAmount,
            //                 "due" => 0,
            //             ]);
            //     }
            // }

            if (isset($row['cust_room_service'])) {
                $invoiceId = $row['cust_room_service']['id'];
                // $dueAmount = 0;
                $dueAmount = $row['cust_room_service']['due_amount'];
                
                DB::table('cust_room_service_inv')->where('id', $invoiceId)->update([
                    'paid' => $dueAmount,
                    "due" => 0,
                ]);
            }
            
        }



        $this->responseMessage = "Payment has been collected successfully";
        $this->outputData = $payment;
        $this->success = true;
    }

    public function roomServiceMakePayment(Request $request)
    {
        $this->validator->validate($request, [
            "invoice_id" => v::notEmpty(),
            "account_id" => v::notEmpty(),
            "payee" => v::notEmpty(),
            "amount" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $invoice_id = $this->params->invoice_id;

        $payment = DB::table('payment_collection_slip')->insert([
            'invoice_id' => $invoice_id,
            'invoice_type' => $this->params->invoice_type,
            'record_id' => $this->params->record_id ?? null,
            'slip_number' => 'PAYSLIP-' . $invoice_id . '-' . strtotime('now'),
            'account_id' => $this->params->account_id,
            'payee' => $this->params->payee,
            'amount' => $this->params->amount,
            'reference' => $this->params->reference,
            'remark' => $this->params->remark,
            'payment_date' => date("Y-m-d"),
            'created_by' => $this->user->id,
            'status' => 1
        ]);

        if (!$payment) {
            $this->responseMessage = "Payment collection failed. Please try again !";
            $this->outputData = [];
            $this->success = false;
        }

        //if payment success, than calculate balance of every account related other table, Like, customers balance, booking master paid ,etc. 
        $invoice = DB::table('cust_room_service_inv')->where(['id' => $invoice_id, 'status' => 1])->first();

        DB::table('cust_room_service_inv')->where(['id' => $invoice_id, 'status' => 1])->update([
            'paid' => ($invoice->paid + $this->params->amount),
            'due' => ($invoice->due - $this->params->amount),
        ]);

        $credited_note = "payment taken from customer for room service";
        $debited_note = "";

        Accounting::Accounts($this->params->account_id, $invoice_id, $this->params->invoice_type, $this->params->amount, $credited_note, $debited_note, $this->user->id, true);
        //Balance for account customer
        $credited_note = "Payment taken from customer for room service";
        $debited_note = "";
        $credit = true;

        Accounting::accountCustomer(false, $this->params->payee, $invoice_id, $invoice->inv_number, $invoice->inv_type, $invoice->net_amount, $this->params->amount, $credited_note, $debited_note, $this->user->id, true);

        $this->responseMessage = "Payment has been collected successfully";
        $this->outputData = $payment;
        $this->success = true;
    }
}
