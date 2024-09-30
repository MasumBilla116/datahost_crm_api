<?php

namespace  App\Controllers\Accounts;

use App\Auth\Auth;
use Carbon\Carbon;

/**
 * ?Model: Supplier Invoice 
 */

use App\Helpers\Helper;
use App\Models\Accounts\AccountAdjustment;
use App\Validation\Validator;
use App\Models\Accounts\Payslip;

use App\Response\CustomResponse;

/**
 * !Model: Supplier Invoice End
 */

/**
 * ?Model: Accounts AccountBank AccountCash Start
 */

use App\Models\Accounts\Accounts;
use App\Models\Users\ClientUsers;
use PHPMailer\PHPMailer\PHPMailer;
use App\Models\Accounts\AccountBank;

/**
 * !Model: Accounts AccountBank AccountCash END
 */

use App\Models\Accounts\AccountCash;
use Respect\Validation\Rules\Number;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Accounts\FundTransferSlip;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;
use App\Models\Purchase\Supplier;       //Table ======>  supplier

/**
 * !External Packages
 */

use App\Models\Purchase\Invoice;    //Table===========>  supplier_inv
use App\Models\Purchase\InvoiceItem;    //Table ======>  supplier_inv_item
use App\Models\Inventory\InventoryItem;       //Table ======>  Inventory Item

class AccountsController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    private $helper;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        /**
         * !Creating Instance Of Accounts
         * @param AccpountBank @param AccountCash
         */

        $this->accounts = new Accounts();
        $this->bank = new AccountBank();
        $this->cash = new AccountCash();
        $this->payslip = new Payslip();
        $this->transferfund = new FundTransferSlip();
        $this->adjustAccount = new AccountAdjustment();

        /**
         * !Invoice/InvoiceItem/Supplier/Invoice Model Instance ***
         */
        $this->invoice = new Invoice();
        $this->invoiceItem = new InvoiceItem();
        $this->supplier = new Supplier();
        $this->inventory = new InventoryItem();
        /**
         * !Invoice/InvoiceItem/Supplier/Invoice Model Instance ***
         */

        $this->validator = new Validator();

        /**
         * !User Instance
         * @param $this->user->id
         */
        $this->user = new ClientUsers();
        $this->helper = new Helper;
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
            case 'test':
                $this->test();
                break;
            case 'createAccounts':
                $this->createAccounts($request);
                break;
            case 'listAccounts':
                $this->listAccounts();
                break;

            case 'allListAccounts':
                $this->allListAccounts();
                break;

            case 'updateAccounts':
                $this->updateAccounts();
                break;
            case 'getById':
                $this->getById();
                break;
            case 'getAccountInfo':
                $this->getAccountInfo();
                break;
            case 'getAccounts':
                $this->getAccounts();
                break;
            case 'getAccountBalance':
                $this->getAccountBalance();
                break;
            case 'makePayments':
                $this->makePayments();
                break;
            case 'getPaySlip':
                $this->getPaySlip();
                break;

            case 'getPaySlipList':
                $this->getPaySlipList();
                break;
            case 'getPaySlipById':
                $this->getPaySlipById();
                break;
            case 'returnPayslip':
                $this->returnPayslip();
                break;
            case 'transferFund':
                $this->transferFund();
                break;
            case 'getTransferList':
                $this->getTransferList();
                break;

            case 'getAllTransferList':
                $this->getAllTransferList();
                break;
            case 'adjustmentAccount':
                $this->adjustmentAccount();
                break;
            case 'adjustmentList':
                $this->adjustmentList();
                break;
            case 'aftListById':
                $this->aftListById();
                break;
            case 'adjustmentDetails':
                $this->adjustmentDetails();
                break;

            case 'deleteAccount':
                $this->deleteAccount();
                break;

            case 'profitLoss':
                $this->profitLoss();
                break;





            default:
                $this->responseMessage = "Invalid request!";
                return $this->customResponse->is400Response($response, $this->responseMessage);
        }

        if (!$this->success) {
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    /**
     * !Getting AFT By ID
     */
    public function aftListById()
    {
        $id = $this->params->id;
        $this->responseMessage = "Transferd Fund Data List Fetched successfully!";
        $this->outputData = DB::select(DB::raw("
        SELECT tf.*,(select ac.`account_name` from `accounts` ac 
        where tf.from_account_id = ac.id) as `from`, 
        (select ac.`account_name` from `accounts` ac where tf.to_account_id = ac.id) 
        as `to` FROM `fund_transfer_slips` tf WHERE tf.id=$id
        "));
        $this->success = true;
    }

    /**
     * !Get Fund/Balance transfer List of data
     */
    public function getTransferList()
    {
        $transfer_lists = $this->transferfund->with(['fromAccount', 'creator', 'toAccount'])->where('active', 1)->orderBy('id', 'desc')->get();

        $this->responseMessage = "Transferd Fund Data List Fetched successfully!";
        $this->outputData = $transfer_lists;
        $this->success = true;
    }

    public function getAllTransferList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = $this->transferfund->with(['fromAccount', 'creator', 'toAccount']);
        // ->where('active', 1)->orderBy('id', 'desc')->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('fund_transfer_slips.active', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('fund_transfer_slips.active', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('fund_transfer_slips.slip_num', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_transferfund =  $query->orderBy('fund_transfer_slips.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Transferd Fund Data List Fetched successfully!";
        $this->outputData = [
            $pageNo => $all_transferfund,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    /**
     * !Transfer Fund/Balance
     */

    public function transferFund()
    {

        $from = $this->params->value["idfrom"];
        $to = $this->params->value["idto"];
        $amount = $this->params->value["amount"];
        $date = $this->params->value["date"];
        $remarks = $this->params->value["remarks"];

        $getSlipId = $this->helper->getLastID('fund_transfer_slips');

        $transfer_id = DB::table('fund_transfer_slips')   //Bank Table
            ->insertGetId([
                "from_account_id" => $from,
                "to_account_id" => $to,
                "amount" => $amount,
                "remarks" => $remarks,
                "slip_num" => "AFT-" . Carbon::create($date)->format('ym') . "-" . str_pad($getSlipId, 6, "0", STR_PAD_LEFT),
                "created_by" => $this->user->id,
                "active" => 1,
            ]);

        $typeFrom = strtolower($this->helper->getType($from)[0]->type);
        $typeTo = strtolower($this->helper->getType($to)[0]->type);

        if ($typeFrom == 'bank') {
            $from_account = $this->accounts->where("id", $from)->where('status', 1)->first();
            $old_from_balance = $from_account->balance;
            $new_from_balance = $old_from_balance - $amount;

            $this->accounts
                ->where(["id" => $from])
                ->update([
                    'balance' => $new_from_balance,
                ]);

            $to_account = $this->accounts->where("id", $to)->where('status', 1)->first();

            $acc_bank = $this->bank->create([
                "invoice" => $transfer_id,
                "inv_type" => "fund_transfer",
                "account_id" => $from,
                "debit" => 0.00,
                "credit" => $amount,
                "balance" => $new_from_balance,
                "note" => "Balance Transfered to " . $to_account->account_name,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        } else {
            $from_account = $this->accounts->where("id", $from)->where('status', 1)->first();
            $old_from_balance = $from_account->balance;
            $new_from_balance = $old_from_balance - $amount;

            $this->accounts
                ->where(["id" => $from])
                ->update([
                    'balance' => $new_from_balance,
                ]);

            $to_account = $this->accounts->where("id", $to)->where('status', 1)->first();

            $acc_bank = $this->cash->create([
                "invoice" => $transfer_id,
                "inv_type" => "fund_transfer",
                "account_id" => $from,
                "debit" => 0.00,
                "credit" => $amount,
                "balance" => $new_from_balance,
                "note" => "Balance Transfered to " . $to_account->account_name,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        if ($typeTo == 'bank') {
            $to_account = $this->accounts->where("id", $to)->where('status', 1)->first();
            $old_to_balance = $to_account->balance;
            $new_to_balance = $old_to_balance + $amount;

            $this->accounts
                ->where(["id" => $to])
                ->update([
                    'balance' => $new_to_balance,
                ]);

            $from_account = $this->accounts->where("id", $from)->where('status', 1)->first();

            $acc_bank = $this->bank->create([
                "invoice" => $transfer_id,
                "inv_type" => "fund_transfer",
                "account_id" => $to,
                "debit" => $amount,
                "credit" => 0.00,
                "balance" => $new_to_balance,
                "note" => "Balance Transfered from " . $from_account->account_name,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        } else {
            $to_account = $this->accounts->where("id", $to)->where('status', 1)->first();
            $old_to_balance = $to_account->balance;
            $new_to_balance = $old_to_balance + $amount;

            $this->accounts
                ->where(["id" => $to])
                ->update([
                    'balance' => $new_to_balance,
                ]);

            $from_account = $this->accounts->where("id", $from)->where('status', 1)->first();

            $acc_bank = $this->cash->create([
                "invoice" => $transfer_id,
                "inv_type" => "fund_transfer",
                "account_id" => $to,
                "debit" => $amount,
                "credit" => 0.00,
                "balance" => $new_to_balance,
                "note" => "Balance Transfered from " . $from_account->account_name,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        $this->responseMessage = "Amount Transfered Successfully!";
        $this->outputData = $transfer_id;
        $this->success = true;
    }

    public function adjustmentAccount()
    {

        $account = $this->params->value['adjust_account'];
        $amount = $this->params->value["amount"];
        $old_balance = $this->params->balance1;
        $remarks = $this->params->value["remarks"];
        $type = $this->params->value["adjustment_type"];

        if ($type == 'increase') {
            $new_balance = $old_balance + $amount;
            $debit = $amount;
            $credit = 0.00;
        }
        if ($type == 'decrease') {
            $new_balance = $old_balance - $amount;
            $debit = 0.00;
            $credit = $amount;
        }

        $getSlipId = $this->helper->getLastID('account_adjustments');

        $adjust_id = DB::table('account_adjustments')
            ->insertGetId([
                "slip_num" => "ADJ-" . Carbon::now()->format('ym') . "-" . str_pad($getSlipId, 6, "0", STR_PAD_LEFT),
                "account" => $account,
                "amount" => $amount,
                "type" => $type,
                "old_balance" => $old_balance,
                "new_balance" => $new_balance,
                "remarks" => $remarks,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $typeAccount = strtolower($this->helper->getType($account)[0]->type);

        if ($typeAccount == 'bank') {

            $this->accounts
                ->where(["id" => $account])
                ->update([
                    'balance' => $new_balance,
                ]);

            $acc_bank = $this->bank->create([
                "invoice" => $adjust_id,
                "inv_type" => "balance_adjusted",
                "account_id" => $account,
                "debit" => $debit,
                "credit" => $credit,
                "balance" => $new_balance,
                "note" => ($type == 'increase' ? "Balance Adjusted by Increment" : "Balance Adjusted by Decrement"),
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        } else {

            $this->accounts
                ->where(["id" => $account])
                ->update([
                    'balance' => $new_balance,
                ]);

            $acc_bank = $this->cash->create([
                "invoice" => $adjust_id,
                "inv_type" => "balance_adjusted",
                "account_id" => $account,
                "debit" => $debit,
                "credit" => $credit,
                "balance" => $new_balance,
                "note" => ($type == 'increase' ? "Balance Adjusted by Increment" : "Balance Adjusted by Decrement"),
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        $this->responseMessage = "Amount Adjusted Successfully!";
        $this->outputData = $adjust_id;
        $this->success = true;
    }

    public function adjustmentList()
    {
        $adjustment_lists = $this->adjustAccount->with(['account', 'creator'])->where('status', 1)->orderBy('id', 'desc')->get();

        $this->responseMessage = "Adjustment account Data List Fetched successfully!";
        $this->outputData = $adjustment_lists;
        $this->success = true;
    }


    public function profitLoss()
    {

        //Income Section
        $incomeData = [];

        $bookingsum = DB::table('customer_booking_master')->sum('total_paid');
        $restaurantsum = DB::table('restaurant_invoices')->sum('paid_amount');
        $transportsum = DB::table('vehicle_booking')->sum('total_amount');


        //Total Income section
        $incomeTotal = $bookingsum + $restaurantsum + $transportsum;


        $incomeData[] = [
            'referenceId' => 'reservation-1010',
            'description' => 'reservation',
            'amount' => $bookingsum
        ];

        $incomeData[] = [
            'referenceId' => 'restaurant-1010',
            'description' => 'restaurant',
            'amount' => $restaurantsum
        ];

        $incomeData[] = [
            'referenceId' => 'transportsum-1010',
            'description' => 'transport',
            'amount' => $transportsum
        ];




        $this->responseMessage = "Income";
        $this->outputData['income'] = $incomeData;
        $this->outputData['incomeTotal'] = $incomeTotal;
        $this->outputData['expense'] = $incomeData;
        $this->outputData['expenseTotal'] = $incomeTotal;
        $this->success = true;
    }

    public function adjustmentDetails()
    {
        $id = $this->params->id;
        $adjustmens = $this->adjustAccount->with(['account', 'creator'])->where('id', $id)->where('status', 1)->first();

        $this->responseMessage = "Transferd Fund Data List Fetched successfully!";
        $this->outputData = $adjustmens;
        $this->success = true;
    }

    /**
     * !Returning payslip
     */
    public function returnPayslip()
    {
        $payslip = DB::table('payment_slip')
            ->where(['id' => $this->params->id])
            ->select('*')
            ->get();
        /**
         * ?account_bank/account_cash(Insert with cancel note), 
         * ?account_supplier(Insert),
         * ?payment_slip (Update isCanceled=> 1)
         * ?Supplier (Update balance)
         * ?accounts table (Update balance)
         * !Hitting on total 6 tables
         */

        $accounts_id = $payslip[0]->account_id;
        $type = $payslip[0]->pay_type;
        $supplier_id = $payslip[0]->payee;
        $amount = $payslip[0]->amount;
        $slip = $payslip[0]->slip;

        /**
         * !payment_slip update is_cancelled to 1
         */
        $this->payslip //Table payment_slip
            ->where(["id" => $this->params->id])
            ->update([
                'is_cancelled' => 1,
            ]);

        /**
         * !Getting acctype and get balance
         */
        $type == 'cash' ?
            $getBalance = $this->helper->getBalanceById('account_cash', $accounts_id)
            :
            $getBalance = $this->helper->getBalanceById('account_bank', $accounts_id);
        $getBalance = $getBalance[0]->balance;

        /**
         * !accounts Table & account_cash/account_bank update balance
         */
        $type == 'bank' ?
            $this->accounts   //Table accounts
            ->where(["id" => $accounts_id])
            ->update([
                'balance' => DB::raw('balance +' . $amount),
            ])
            and
            DB::table('account_bank')   //Bank Table
            ->insert([
                "account_id" => $accounts_id,
                "debit" => $amount,
                "credit" => 0.00,
                "balance" => ($getBalance - $amount),
                "note" => "Payment Canceled with Banking Payment",
                "created_by" => $this->user->id,
                "status" => 1,
            ])
            :
            $this->accounts //Table accounts
            ->where(["id" => $accounts_id])
            ->update([
                'balance' => DB::raw('balance +' . $amount),
            ])
            and
            DB::table('account_cash')   //Cash Table
            ->insert([
                "account_id" => $accounts_id,
                "debit" => $amount,
                "credit" => 0.00,
                "balance" => ($getBalance + $amount),
                "note" => "Payment Canceled with Cash Payment",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        /**
         * !Updating Supplier Table
         * ?Credit Supplier Balance
         */

        $this->supplier
            ->where(["id" => $supplier_id])
            ->update([
                'balance' => DB::raw('balance -' . $amount),
            ]);

        /**
         * !Insertion account_supplier
         * @param supplier_id
         */

        $balance = $this->helper->getSupplierLastBalance($supplier_id)->balance;

        DB::table('account_supplier')   //account_supplier
            ->insert([
                'supplier_id' => $supplier_id,
                'invoice_id' => $slip,
                'inv_type' => "cancelation of payslip",
                'debit' => 0.00,
                'credit' => $amount,
                'balance' => $balance + $amount,
                'note' => "Cancel Payment for Supplier",
                'status' => 1,
                'created_by' => $this->user->id,
            ]);



        $this->responseMessage = "Payment Canceled Successfully!";
        $this->outputData = $this->payslip;
        $this->success = true;
    }

    /**
     * !Get Payslip By ID
     * ?View Pay Slip by Id
     */
    public function getPaySlipById()
    {
        $payslip = DB::table('payment_slip')
            ->join('supplier', 'supplier.id', '=', 'payment_slip.payee')
            ->join('accounts', 'accounts.id', '=', 'payment_slip.account_id')
            ->join('org_users', 'org_users.id', '=', 'payment_slip.created_by')
            ->select(
                'payment_slip.*',
                'supplier.name',
                'supplier.address',
                'supplier.country_name',
                'supplier.contact_number',
                'supplier.email',
                'accounts.account_name',
                'org_users.name as createdBy'
            )
            ->where(['payment_slip.id' => $this->params->id])
            ->get();
        $this->responseMessage = "Payslip Data Fetched successfully!";
        $this->outputData = $payslip;
        $this->success = true;
    }

    /**
     * !Getting Payslip
     * ?Datatable List of Payslip
     */
    public function getPaySlip()
    {

        $payslip = DB::table('payment_slip')
            ->join('supplier', 'supplier.id', '=', 'payment_slip.payee')
            ->select('payment_slip.*', 'supplier.name')
            ->get();

        $this->responseMessage = "Payslip List Fetched Successfully! ";
        $this->outputData = $payslip;
        $this->success = true;
    }




    public function getPaySlipList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = DB::table('payment_slip')
            ->join('supplier', 'supplier.id', '=', 'payment_slip.payee')
            ->select('payment_slip.*', 'supplier.name');
        // ->get();


        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('payment_slip.active', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('payment_slip.active', '=', 0);
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('supplier.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier.contact_number', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('payment_slip.slip', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_payment_slip =  $query->orderBy('payment_slip.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Payslip List Fetched Successfully! ";
        $this->outputData = [
            $pageNo => $all_payment_slip,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    /**
     * !Making payments to supplier
     * ?Generate payslip & Making payment
     */
    public function makePayments()
    {

        DB::beginTransaction();

        $account = $this->accounts->where('id', $this->params->value['id'])->where('status', 1)->first();

        if ($this->params->value["amount"] > $account->balance) {
            $this->success = false;
            $this->responseMessage = 'You can not pay more than ' . $account->balance . '';
            return;
        }

        $type = $this->params->value["acctype"];

        $type == 'cash' ? $getBalance = $this->helper->getBalanceById('account_cash', $this->params->value["id"])
            :
            $getBalance = $this->helper->getBalanceById('account_bank', $this->params->value["id"]);

        $getBalance = $getBalance[0]->balance;

        $balance = $this->helper->getSupplierLastBalance($this->params->supplier_id)->balance;
        $getPaySlipId = $this->helper->getLastID('payment_slip');

        $payslip = "PAY-" . Carbon::create($this->params->date)->format('ym') . "-" . str_pad($getPaySlipId, 4, "0", STR_PAD_LEFT);

        $payment_slip_id = DB::table('payment_slip')   //payment_slip
            ->insertGetId([
                'slip' => $payslip,
                'payee' => $this->params->supplier_id,
                'account_id' => $this->params->value['id'],
                'amount' => $this->params->value["amount"],
                'payment_type' => "supplier_payment",
                'pay_type' => $type,
                'payment_date' => $this->params->value["date"],
                'remark' => $this->params->value["remarks"],
                'is_cancelled' => 0,
                'active' => 1,
                'created_by' => $this->user->id,
            ]);

        $type == 'bank' ?
            ($this->accounts   //Table accounts
                ->where(["id" => $this->params->value["id"]])
                ->update([
                    'balance' => DB::raw('balance -' . $this->params->value["amount"]),
                ])
                and
                DB::table('account_bank')   //Bank Table
                ->insert([
                    "invoice" => $payment_slip_id,
                    "inv_type" => "balance_adjusted",
                    "account_id" => $this->params->value['id'],
                    "debit" => 0.00,
                    "credit" => $this->params->value["amount"],
                    "balance" => ($account->balance - $this->params->value["amount"]),
                    "note" => "Payment given to supplier",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]))
            : ($this->accounts //Table accounts
                ->where(["id" => $this->params->value["id"]])
                ->update([
                    'balance' => DB::raw('balance -' . $this->params->value["amount"]),
                ])
                and
                DB::table('account_cash')   //Cash Table
                ->insert([
                    "invoice" => $payment_slip_id,
                    "inv_type" => "balance_adjusted",
                    "account_id" => $this->params->value['id'],
                    "debit" => 0.00,
                    "credit" => $this->params->value["amount"],
                    "balance" => ($account->balance - $this->params->value["amount"]),
                    "note" => "Payment given to supplier",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]));

        /**
         * !Updating Supplier Table
         * ?Debit Supplier Balance
         */

        $this->supplier
            ->where(["id" => $this->params->supplier_id])
            ->update([
                'balance' => DB::raw('balance +' . $this->params->value["amount"]),
            ]);

        DB::table('account_supplier')   //account_supplier
            ->insert([
                'supplier_id' => $this->params->supplier_id,
                'invoice_id' => $getPaySlipId,
                'inv_type' => "payslip",
                'debit' => $this->params->value["amount"],
                'credit' => 0.00,
                'balance' => $balance + $this->params->value["amount"],
                'note' => "Payment Given to Supplier",
                'status' => 1,
                'created_by' => $this->user->id,
            ]);

        DB::commit();

        $this->responseMessage = "Payment Given  to Supplier Successfull!!";
        $this->outputData = $payment_slip_id;
        $this->success = true;
    }

    /**
     * !Getting Accounts Type List
     */
    public function getAccounts()
    {

        $type = $this->params->acctype;

        $type === "cash" ? $res = $this->accounts  //For Cash
            ->select('accounts.account_name as name', 'accounts.id')
            ->where(['accounts.type' => 'CASH'])
            ->get()
            : ($type === "bank" ? $res = $this->accounts  //For Bank
                ->select('accounts.account_name as name', 'accounts.id')
                ->where(['accounts.type' => 'BANK'])
                ->where(['accounts.status' => 1])
                ->get() : "");

        $type === "all" ?
            $res = $this->accounts  //For Cash
            ->select('accounts.account_name as name', 'accounts.id')
            // ->where(['accounts.type'=>'CASH'])
            ->get()
            :
            "";

        $this->responseMessage = "Accounts Type Fetched Successfully!!";
        $this->outputData = $res;
        $this->success = true;
    }

    /**
     * !Update Accounts Details By ID
     */

    public function updateAccounts()
    {

        $type = $this->params->value["acctype"];
        $type == 'cash' ? $getBalance = $this->helper->getBalanceById('account_cash', $this->params->id)
            : $getBalance = $this->helper->getBalanceById('account_bank', $this->params->id);

        $getBalance = $getBalance[0]->balance;


        /**
         * !1st part: A- Updation accounts B- Inserting data, to cash/bank table, upon acctype
         * !2nd part: A- Updating accounts B- Insertion data, to cash/bank table, upon acctype
         */
        $type == "cash" ? ($accountsTable = $this->accounts
            ->where(['id' => $this->params->id])
            ->update([
                'account_name' => $this->params->value["accountsname"],
                'balance' => $this->params->value["openingbalance"],
                'description' => $this->params->value["description"]
            ])
            and
            $cashTable[] = array(
                "inv_type" => "opening_balance",
                "account_id" => $this->params->id,
                "debit" => $this->params->value["openingbalance"],
                "credit" => 0.00,
                "balance" => $this->params->value["openingbalance"],
                "note" => "Cash Account Edited",
                "created_by" => $this->user->id,
                "updated_by" => $this->user->id,
                "status" => 1,
            )
            and
            DB::table('account_cash')->insert($cashTable)
        )
            : ($accountsTable = $this->accounts
                ->where('id', $this->params->id)
                ->update([
                    'account_name' => $this->params->value["accountsname"],
                    'account_no' => $this->params->value["accountsnumber"],
                    'bank' => $this->params->value["bankname"],
                    'branch' => $this->params->value["branchname"],
                    'account_type' => $this->params->value["type"],
                    'balance' => $this->params->value["openingbalance"],
                    'description' => $this->params->value["description"],
                    'pos_availability' => $this->params->value["pos_availability"]
                ])
                and
                $bankTable[] = array(
                    "inv_type" => "opening_balance",
                    "account_id" => $this->params->id,
                    "debit" => $this->params->value["openingbalance"],
                    "credit" => 0.00,
                    "balance" => $this->params->value["openingbalance"],
                    "note" => "Bank Account Edited!",
                    "created_by" => $this->user->id,
                    "updated_by" => $this->user->id,
                    "status" => 1,
                )
                and
                DB::table('account_bank')->insert($bankTable)
            );
        $under_header = DB::table("account_sectors")->select("title")->where("id", $this->params->value['under_head'])->first();
        //update account sector
        DB::table('account_sectors')
            ->where(['id' => $this->params->value['prev_under_head']])
            ->update([
                'account_type' => $this->params->value['sector_head'],
                'title' => $under_header->title,
                'parent_id' => $this->params->value['under_head'],
                'description' => $this->params->value['description'],
            ]);

        $this->responseMessage = "Accounts Data Updated Successfully!!";
        $this->outputData = "Duh... Data Updated!";
        $this->success = true;
    }

    /**
     * !Get Details By Accounts ID
     */
    public function getById()
    {

        // $res = $this->accounts
        //     ->select('*')
        //     // ->where('id','=',$this->params->id)
        //     ->where(['id' => $this->params->id])
        //     ->get();

        $res =  $this->accounts->select('accounts.*', 'se.id as sub_sector_header_id', 'se.title as sub_sector_header', 'sub_sec.id as main_sector_header_id', 'sub_sec.account_type as main_sector_header', 'sub_sec.title as sector_header')
            ->join('account_sectors as se', 'accounts.sector_id', '=', 'se.id')
            ->join('account_sectors as sub_sec', 'se.parent_id', '=', 'sub_sec.id')
            ->where('accounts.id', $this->params->id)
            ->get();
        // Now, you can access the result using $result

        $this->responseMessage = "Accounts Data Fetched Successfully!!";
        $this->outputData = $res;
        $this->success = true;
    }

    public function getAccountInfo()
    {

        $res = $this->accounts
            ->select('*')
            // ->where('id','=',$this->params->id)
            ->where(['id' => $this->params->id])
            ->first();
        $this->responseMessage = "Accounts Data Fetched Successfully!!";
        $this->outputData = $res;
        $this->success = true;
    }

    /**
     * !Listing Account Details
     */
    public function listAccounts()
    {

        // $data = $this->helper->getAccountsData();
        $data = DB::table("accounts")
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();
        $this->responseMessage = "Accounts Data fetched Successfully!!";
        $this->outputData = $data;
        $this->success = true;
    }


    public function allListAccounts()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        // $data = $this->helper->getAccountsData();
        $query = DB::table("accounts");
        // ->where('status', 1)
        // ->orderBy('id', 'desc')
        // ->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('accounts.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('accounts.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('accounts.account_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('accounts.account_no', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_account =  $query->orderBy('accounts.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();

        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Accounts Data fetched Successfully!!";
        $this->outputData = [
            $pageNo => $all_account,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    /**
     * !Creating New Accounts
     */

    public function createAccounts()
    {

        DB::beginTransaction();
        $this->params = (object)($this->params->value);

        try {
            //code...

            $type = $this->params->acctype;
            $lastID = $this->helper->getLastID('accounts');
            $account_sector_id = DB::table("account_sectors")
                ->select("id")
                ->orderBy('id', 'desc')
                ->limit(1)->first();
            /**
             * !accounts Table
             */
            $type == "cash" ? ($accountsTable = DB::table('accounts')->insert([
                "sector_id" => (int)$account_sector_id->id + 1,
                "account_name" => $this->params->accountsname,
                "type" => strtoupper($this->params->acctype),
                "account_no" => "NA",
                "bank" => "NA",
                "branch" => "NA",
                "account_type" => "NA",
                "opening_balance" => $this->params->openingbalance,
                "balance" => $this->params->openingbalance,
                "description" => $this->params->description,
                "pos_availability" => 0,
                "created_by" => $this->user->id,
                "status" => 1,
            ])
                and
                $cashTable[] = array(
                    "inv_type" => "opening_balance",
                    "account_id" => $lastID,
                    "debit" => $this->params->openingbalance,
                    "credit" => 0.00,
                    "balance" => $this->params->openingbalance,
                    "note" => "Cash Account created with opening balance",
                    "created_by" => $this->user->id,
                    "status" => 1,
                )
                // AND
                // $test = DB::table('accounts')->insertGetId($accountsTable)
                and
                DB::table('account_cash')->insert($cashTable)
                and
                //create account sector
                DB::table('account_sectors')->insert([
                    'account_type' => 'asset',
                    'title' => $this->params->accountsname,
                    'parent_id' => $this->params->under_sector,
                    'description' => $this->params->description,
                    "created_by" => $this->user->id,
                    'status' => 1,
                ])
                and
                // Create general ledger
                DB::table('general_ledger')->insert([
                    'name' => $this->params->accountsname,
                    'sector_head' => 'asset',
                    'sector_id' => $this->params->under_sector,
                    'opening_balance' => $this->params->openingbalance,
                    'balance' => $this->params->openingbalance,
                    'description' => $this->params->description,
                    "created_by" => $this->user->id,
                ])
            )
                : ($accountsTable = DB::table('accounts')->insert([
                    "sector_id" => (int)$account_sector_id->id + 1,
                    "account_name" => $this->params->accountsname,
                    "type" => strtoupper($this->params->acctype),
                    "account_no" => $this->params->accountsnumber,
                    "bank" => $this->params->bankname,
                    "branch" => $this->params->branchname,
                    "account_type" => $this->params->type,
                    "opening_balance" => $this->params->openingbalance,
                    "balance" => $this->params->openingbalance,
                    "description" => $this->params->description,
                    "pos_availability" => $this->params->status ?? 0,
                    "created_by" => $this->user->id,
                    "status" => 1,
                ])
                    and
                    $bankTable[] = array(
                        "inv_type" => "opening_balance",
                        "account_id" => $lastID,
                        "debit" => $this->params->openingbalance,
                        "credit" => 0.00,
                        "balance" => $this->params->openingbalance,
                        "note" => "Bank Account created with opening balance",
                        "created_by" => $this->user->id,
                        "status" => 1,
                    )
                    // AND
                    // $test = DB::table('accounts')->insertGetId($accountsTable)
                    and
                    DB::table('account_bank')->insert($bankTable)
                    and
                    //create account sector
                    DB::table('account_sectors')->insert([
                        'account_type' => $this->params->sector_head,
                        'title' => $this->params->bankname,
                        'parent_id' => $this->params->under_sector,
                        'description' => $this->params->description,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ])
                    and
                    // Create general ledger
                    DB::table('general_ledger')->insert([
                        'name' => $this->params->accountsname,
                        'sector_head' => $this->params->sector_head,
                        'sector_id' => $this->params->under_sector,
                        'opening_balance' => $this->params->openingbalance,
                        'balance' => $this->params->openingbalance,
                        'description' => $this->params->description,
                        "created_by" => $this->user->id,
                    ])
                );

            DB::Commit();
            $this->responseMessage = "New Accounts Created Successfully!!";
            $this->outputData = $accountsTable;
            $this->success = true;
        } catch (\Throwable $th) {
            DB::rollback();
            $this->responseMessage = "New Accounts Created Failed!!";
            $this->outputData = [];
            $this->success = true;
        }
    }

    public function getAccountBalance()
    {
        $balance = $this->accounts->where('id', $this->params->account_id)->where('status', 1)->first();

        $this->responseMessage = "Account Balance fetched!!";
        $this->outputData = $balance;
        $this->success = true;
    }

    public function test()
    {

        $this->params->type == 'cash' ? $getBalance = $this->helper->getBalanceById('account_cash', $this->params->id)
            : $getBalance = $this->helper->getBalanceById('account_bank', $this->params->id);


        $res = 5;
        $res === 5 ?
            ($b = 4 and $a = 3 and $c = 1 and
                $d = DB::table('account_bank')
                ->insert([
                    "account_id" => 1,
                    "debit" => 10,
                    "credit" => 0.00,
                    "balance" => 5,
                    "note" => "..............",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ])
                and
                $x = 0

            ) : ($b = 5 and $a = 4 && $c = 2 && $d = "null" && $x = 1
            );


        $this->responseMessage = "Ok";
        // $exp = explode("-",$date);
        // $this->outputData = $getBalance[0]->balance;
        $this->outputData = $x;
        // ->orderBy('created_at', 'desc')
        // ->first();
        $this->success = true;
    }


    public function deleteAccount()
    {
        if (!isset($this->params->id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $account = $this->accounts->find($this->params->id);

        if (!$account) {
            $this->success = false;
            $this->responseMessage = "Account not found!";
            return;
        }

        $deletedAccount = $account->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Account Deleted successfully";
        $this->outputData = $deletedAccount;
        $this->success = true;
    }
}

/**
 * !Analytical Operations By Mehadi .....
 * ?List & Operator Techniques
 */

/*$i = 0; $w = 0; $r = 0;
($i==9) ? ($w=4 AND $r=7) : ($w=7 AND $r=1);
echo "w = $w, r = $r\n";
// w = 7, r = 1

$i = 0; $w = 0; $r = 0;
($i==9) ? ($w=0 AND $r=7) : ($w=0 AND $r=1);
echo "w = $w, r = $r\n";
// w = 0, r = 0

$i = 0; $w = 0; $r = 0;
($i==9) ?
    (list($w, $r) = array(4, 7)) :
    (list($w, $r) = array(7, 1));
echo "w = $w, r = $r\n";
// w = 7, r = 1

$i = 0; $w = 0; $r = 0;
($i==9) ?
    (list($w, $r) = array(0, 7)) :
    (list($w, $r) = array(0, 1));
echo "w = $w, r = $r\n";
// w = 0, r = 1

*/