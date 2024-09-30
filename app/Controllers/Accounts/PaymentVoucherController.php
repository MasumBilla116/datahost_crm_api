<?php

namespace  App\Controllers\Accounts;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Helpers\Accounting;
use App\Models\HRM\Employee;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;

use App\Models\Customers\Customer;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class PaymentVoucherController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $customerBookingGrp;
    protected $customerBooking;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
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

            case 'searchLedgerFrom':
                $this->searchLedgerFrom();
                break;
            case 'getAllPaymentVouchers':
                $this->getAllPaymentVouchers();
                break;

            case 'getAllPaymentVouchersList':
                $this->getAllPaymentVouchersList();
                break;
            case 'searchLedgerTo':
                $this->searchLedgerTo();
                break;
            case 'creatPaymentVoucher':
                $this->creatPaymentVoucher($request);
                break;

            case 'getPaymentVoucherInfo':
                $this->getPaymentVoucherInfo();
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

    public function getAllPaymentVouchers()
    {
        $data = [];
        DB::table('payment_vouchers')->orderBy('id', 'desc')->chunk(5, function ($records) use (&$data) {
            foreach ($records as $record) {
                $data[] = $record;
            }
        });

        if (count($data) > 0) {
            $this->responseMessage = "fetch all payment vouchers!";
            $this->outputData = $data;
            $this->success = true;
        } else {
            $this->responseMessage = "Data not found!";
            $this->outputData = [];
            $this->success = true;
        }
    }

    public function getAllPaymentVouchersList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;
        $query = DB::table('payment_vouchers');

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'cheque') {
            $query->where('payment_vouchers.payment_type', '=', "cheque");
        }

        if ($filter['status'] == 'cash') {
            $query->where('payment_vouchers.payment_type', '=', 'cash');
        }


                if (isset($filter['yearMonth'])) {
            $query->whereYear('payment_vouchers.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
                ->whereMonth('payment_vouchers.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('payment_vouchers.voucher_no', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('payment_vouchers.from_account', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('payment_vouchers.to_account', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_ledger =  $query->orderBy('payment_vouchers.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }


        $this->responseMessage = "All Ledger fetched successfully";
        // $this->outputData = $query;
        $this->outputData = [
            $pageNo => $all_ledger,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function searchLedgerFrom()
    {

        $ledger_from = [];

        if ($this->params->ledger_from == 'customers') {
            $ledger_from = DB::table('customers')->select('id', 'title', 'first_name', 'last_name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'suppliers') {
            $ledger_from = DB::table('supplier')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'employee') {
            $ledger_from = DB::table('employees')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'laundry_operators') {
            $ledger_from = DB::table('laundry_operators')->select('id', 'operator_name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'bank_account') {
            $ledger_from = DB::table('accounts')->select('id', 'account_name', 'balance')->where(['status' => 1, 'type' => 'BANK'])->get();
        } else if ($this->params->ledger_from == 'cash_in_hand') {
            $ledger_from = DB::table('accounts')->select('id', 'account_name', 'balance')->where(['status' => 1, 'type' => 'CASH'])->get();
        } else if ($this->params->ledger_from == 'general_ledger') {
            $ledger_from = DB::table('general_ledger')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else {
            $this->responseMessage = "Invalid ledger type!";
            $this->outputData = [];
            $this->success = false;
        }


        if (count($ledger_from) > 0) {
            $this->responseMessage = "Fetched all ledger!";
            $this->outputData = $ledger_from;
            $this->success = true;
        } else {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function searchLedgerTo()
    {
        $ledger_from = [];

        if ($this->params->ledger_from == 'customers') {
            $ledger_from = DB::table('customers')->select('id', 'title', 'first_name', 'last_name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'suppliers') {
            $ledger_from = DB::table('supplier')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'employee') {
            $ledger_from = DB::table('employees')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'laundry_operators') {
            $ledger_from = DB::table('laundry_operators')->select('id', 'operator_name', 'balance')->where(['status' => 1])->get();
        } else if ($this->params->ledger_from == 'bank_account') {
            $ledger_from = DB::table('accounts')->select('id', 'account_name', 'balance')->where(['status' => 1, 'type' => 'BANK'])->get();
        } else if ($this->params->ledger_from == 'cash_in_hand') {
            $ledger_from = DB::table('accounts')->select('id', 'account_name', 'balance')->where(['status' => 1, 'type' => 'CASH'])->get();
        } else if ($this->params->ledger_from == 'general_ledger') {
            $ledger_from = DB::table('general_ledger')->select('id', 'name', 'balance')->where(['status' => 1])->get();
        } else {
            $this->responseMessage = "Invalid ledger type!";
            $this->outputData = [];
            $this->success = false;
        }


        if (count($ledger_from) > 0) {
            $this->responseMessage = "Fetched all ledger!";
            $this->outputData = $ledger_from;
            $this->success = true;
        } else {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function creatPaymentVoucher(Request $request)
    {
        $this->validator->validate($request, [
            "voucher_type" => v::notEmpty(),
            "voucher_no" => v::notEmpty(),
            "date" => v::notEmpty(),
            "payment_type" => v::notEmpty(),
            "ledger_type_from" => v::notEmpty(),
            "ledger_type_to" => v::notEmpty(),
            "from_account" => v::notEmpty(),
            "to_account" => v::notEmpty(),
            "amount" => v::notEmpty(),
            "amount_word" => v::notEmpty(),

        ]);

        if ($this->params->payment_type == "cheque") {
            $this->validator->validate($request, [
                "bank_name" => v::notEmpty(),
                "cheque_no" => v::notEmpty(),
                "cheque_date" => v::notEmpty(),

            ]);
        }

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if ($this->params->payment_type == "cheque") {
            $cheque_date =  date('Y-m-d', strtotime($this->params->cheque_date));
        } else {
            $cheque_date =  null;
        }


        $result = DB::table('payment_vouchers')->insertGetId([
            "voucher_type" => $this->params->voucher_type,
            "voucher_no" => $this->params->voucher_no,
            "date" => date('Y-m-d', strtotime($this->params->date)),
            "payment_type" => $this->params->payment_type,
            "bank_name" => $this->params->bank_name,
            "cheque_no" => $this->params->cheque_no,
            "cheque_date" => $cheque_date,
            "ledger_type_from" => $this->params->ledger_type_from,
            "ledger_type_to" => $this->params->ledger_type_to,
            "from_account" => $this->params->from_account,
            "to_account" => $this->params->to_account,
            "amount" => $this->params->amount,
            "amount_word" => $this->params->amount_word,
            "created_by" => $this->user->id
        ]);


        $credited_note = "Payment taken";
        $debited_note = "Payment Given ";
        $credit = false;
        if ($this->params->ledger_type_from == "employee") {
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::accountEmployee($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }

        if ($this->params->ledger_type_to == "employee") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::accountEmployee($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }

        if ($this->params->ledger_type_from == "bank_account" || $this->params->ledger_type_from == "cash_in_hand") {
            $credit = false;
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::Accounts($this->params->from_id, $result, $this->params->voucher_type, $this->params->amount, $credited_note, $debited_note, $this->user->id, $credit);
        }


        if ($this->params->ledger_type_to == "bank_account" || $this->params->ledger_type_to == "cash_in_hand") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::Accounts($this->params->to_id, $result, $this->params->voucher_type, $this->params->amount, $credited_note, $debited_note, $this->user->id, $credit);
        }



        if ($this->params->ledger_type_from == "customers") {
            $credit = false;
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::accountCustomer($credit, $this->params->from_id, $result, $this->params->voucher_no, $this->params->voucher_type, $this->params->amount, 0, $credited_note, $debited_note, $this->user->id, false);
        }

        if ($this->params->ledger_type_to == "customers") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::accountCustomer($credit, $this->params->to_id, $result, $this->params->voucher_no, $this->params->voucher_type, $this->params->amount, 0, $credited_note, $debited_note, $this->user->id, true);
        }


        if ($this->params->ledger_type_from == "suppliers") {
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::accountSupplier($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }

        if ($this->params->ledger_type_to == "suppliers") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::accountSupplier($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }


        if ($this->params->ledger_type_from == "laundry_operators") {
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::accountLaundry($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }

        if ($this->params->ledger_type_to == "laundry_operators") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::accountLaundry($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }



        if ($this->params->ledger_type_from == "general_ledger") {
            $debited_note = "Payment Given to - "  . $this->params->ledger_type_to;
            Accounting::accountGeneralLedger($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }

        if ($this->params->ledger_type_to == "general_ledger") {
            $credit = true;
            $credited_note = "Payment taken from  - " . $this->params->ledger_type_from;
            Accounting::accountGeneralLedger($credit, $this->params->from_id, $this->params->to_id, $result, $this->params->voucher_type, $this->params->voucher_no, $this->params->amount, $credited_note, $debited_note, $this->user->id);
        }


        // 'PMNTVCR-'. $result.'-'.strtotime('now')
        if ($result) {
            $this->responseMessage = "Payment Voucher has been created!";
            $this->outputData = $result;
            $this->success = true;
        } else {
            $this->responseMessage = "Something went wrong. Try again!";
            $this->outputData = $result;
            $this->success = false;
        }
    }





    public function getPaymentVoucherInfo()
    {

        if (!isset($this->params->payment_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $payment = DB::table('payment_vouchers')->find($this->params->payment_id);

        if (!$payment) {
            $this->success = false;
            $this->responseMessage = "Payment Voucher not found!";
            return;
        }


        $this->responseMessage = "Payment Voucher fetched Successfully!";
        $this->outputData = $payment;
        $this->success = true;
    }



    public function creatPaymentVoucherCopy(Request $request)
    {
        $this->validator->validate($request, [
            "voucher_type" => v::notEmpty(),
            "voucher_no" => v::notEmpty(),
            "date" => v::notEmpty(),
            "payment_type" => v::notEmpty(),
            "ledger_type_from" => v::notEmpty(),
            "ledger_type_to" => v::notEmpty(),
            "from_account" => v::notEmpty(),
            "to_account" => v::notEmpty(),
            "amount" => v::notEmpty(),
            "amount_word" => v::notEmpty(),

        ]);

        if ($this->params->payment_type == "cheque") {
            $this->validator->validate($request, [
                "bank_name" => v::notEmpty(),
                "cheque_no" => v::notEmpty(),
                "cheque_date" => v::notEmpty(),

            ]);
        }

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if ($this->params->payment_type == "cheque") {
            $cheque_date =  date('Y-m-d', strtotime($this->params->cheque_date));
        } else {
            $cheque_date =  null;
        }


        $result = DB::table('payment_vouchers')->insert([
            "voucher_type" => $this->params->voucher_type,
            "voucher_no" => $this->params->voucher_no,
            "date" => date('Y-m-d', strtotime($this->params->date)),
            "payment_type" => $this->params->payment_type,
            "bank_name" => $this->params->bank_name,
            "cheque_no" => $this->params->cheque_no,
            "cheque_date" => $cheque_date,
            "ledger_type_from" => $this->params->ledger_type_from,
            "ledger_type_to" => $this->params->ledger_type_to,
            "from_account" => $this->params->from_account,
            "to_account" => $this->params->to_account,
            "amount" => $this->params->amount,
            "amount_word" => $this->params->amount_word,
            "created_by" => $this->user->id
        ]);



        if ($result) {
            $this->responseMessage = "Payment Voucher has been created!";
            $this->outputData = $result;
            $this->success = true;
        } else {
            $this->responseMessage = "Something went wrong. Try again!";
            $this->outputData = $result;
            $this->success = false;
        }
    }
}
