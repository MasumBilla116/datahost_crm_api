<?php

namespace  App\Controllers\Accounts;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Validation\Validator;
use App\Models\Purchase\Invoice;
use App\Response\CustomResponse;
use App\Models\Accounts\Accounts;
use App\Models\Accounts\AccountBank;
use App\Models\Accounts\AccountCash;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Accounts\AccountVoucher;
use App\Models\Inventory\ConsumptionVoucher;
use App\Models\Restaurant\RestaurantInvoice;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class AccountVoucherController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $invoices;
    protected $purchase_invoice;
    protected $consumptionVouchers;
    protected $restaurant_invoice;
    protected $accounts;
    protected $bank;
    protected $cash;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->invoices = new AccountVoucher();
        $this->purchase_invoice = new Invoice();
        $this->consumptionVouchers = new ConsumptionVoucher();
        $this->restaurant_invoice = new RestaurantInvoice();
        $this->accounts = new Accounts();
        $this->bank = new AccountBank();
        $this->cash = new AccountCash();
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
            
            case 'createInvoice':
                $this->createInvoice($request, $response);
                break;
            case 'getAllInvoices':
                $this->getAllInvoices($request, $response);
                break;

                case 'getAllInvoicesList':
                    $this->getAllInvoicesList($request, $response);
                    break;

                // getAllInvoicesList
            case 'getInvoiceInfo':
                $this->getInvoiceInfo($request, $response);
                break;
            case 'updateVoucher':
                $this->updateVoucher($request, $response);
                break;
            case 'deleteInvoice':
                $this->deleteInvoice($request, $response);
                break;
            case 'getItemByCode':
                $this->getItemByCode($request, $response);
                break;
            case 'getCodeByItem':
                $this->getCodeByItem($request, $response);
                break;
            case 'getItemByCategory':
                $this->getItemByCategory($request, $response);
                break;
            case 'getAllInvoiceByDate':
                $this->getAllInvoiceByDate($request, $response);
                break;
            case 'getAllLedgerByFiltered':
                $this->getAllLedgerByFiltered($request, $response);
                break;

                case 'getAllLedgerHistoryByFiltered':
                    $this->getAllLedgerHistoryByFiltered($request, $response);
                    break;

                // getAllLedgerHistoryByFiltered
            case 'makeRestaurantPayments':
                $this->makeRestaurantPayments($request, $response);
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

    public function createInvoice(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "invoice_date"=>v::notEmpty(),
            "voucher_type"=>v::notEmpty(),
            "account_id"=>v::notEmpty(),
        ]);
 
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $vouchers = $this->params->vouchers;
        $now = Carbon::now();

        $count = count($vouchers);

        if($count == 0){
            $this->success = false;
            $this->responseMessage = 'Add atleast one item';
            return; 
        }

        DB::beginTransaction();

        $date = $now->format('ym');
        $last_invoice = $this->invoices->select('id')->orderBy('id', 'DESC')->first();
        $invoice_id = $last_invoice->id + 1;
        if($invoice_id == null){
            $invoice_id = 1;
        }
        $invoice_number = sprintf("AV-%s000%d",$date,$invoice_id);
        
        
        $invoice = $this->invoices->create([
           "account_id" => $this->params->account_id,
           "voucher_type" => $this->params->voucher_type,
           "voucher_number" => $invoice_number,
           "remarks" => $this->params->invoice_remarks,
           "voucher_date" => $this->params->invoice_date,
           "total_debit" => $this->params->total_debit,
           "total_credit" => $this->params->total_credit,
           "created_by" => $this->user->id,
           "status" => 1,
        ]);

        $invoiceList = array();

        for($j =0; $j < $count; $j++){
           
           if($vouchers[$j]["amountType"] == 'debit'){
                $debit = $vouchers[$j]["amount"];
                $credit = 0;
           }
           if($vouchers[$j]["amountType"] == 'credit'){
               $credit = $vouchers[$j]["amount"];
               $debit = 0;
           }

           $invoiceList[] = array(
            'accounts_voucher_id' => $invoice->id,
            'sector_id' => $vouchers[$j]['sector'],
            'debit' => $debit,
            'credit' => $credit,
            'remarks' => $vouchers[$j]['remarks'],
            'created_by' => $this->user->id,
            'status' => 1
           );

           if($this->params->voucher_type == "asset"){
                $accountAsset = DB::table('account_asset')->insert([
                    "invoice" => $invoice->id,
                    "sector" => $vouchers[$j]['sector'],
                    "inv_type" => "accounts_voucher",
                    "debit" => $debit,
                    "credit" => $credit,
                    "note" => $vouchers[$j]['remarks'],
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }

            if($this->params->voucher_type == "liability"){
                $accountExpense = DB::table('account_liabilities')->insert([
                    "invoice" => $invoice->id,
                    "sector" => $vouchers[$j]['sector'],
                    "inv_type" => "accounts_voucher",
                    "debit" => $debit,
                    "credit" => $credit,
                    "note" => $vouchers[$j]['remarks'],
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }

            if($this->params->voucher_type == "revenue"){
                $accountExpense = DB::table('account_revenue')->insert([
                    "invoice" => $invoice->id,
                    "sector" => $vouchers[$j]['sector'],
                    "inv_type" => "accounts_voucher",
                    "debit" => $debit,
                    "credit" => $credit,
                    "note" => $vouchers[$j]['remarks'],
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }

            if($this->params->voucher_type == "expenditure"){
                $accountExpense = DB::table('account_expense')->insert([
                    "invoice" => $invoice->id,
                    "sector" => $vouchers[$j]['sector'],
                    "inv_type" => "accounts_voucher",
                    "debit" => $debit,
                    "credit" => $credit,
                    "note" => $vouchers[$j]['remarks'],
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }  
            

        }

        DB::table('accounts_voucher_items')->insert($invoiceList);

        $net_amount = $this->params->total_debit - $this->params->total_credit;

        $accountBalance = $this->accounts->where('id',$this->params->account_id)->first();
        
        $accountBalance->update([
            "balance" => $accountBalance->balance + $net_amount
        ]);

        if($accountBalance->type == "BANK"){
            $acc_bank = $this->bank->create([
                "invoice" => $invoice->id,
                "inv_type" => "accounts_voucher",
                "account_id" => $this->params->account_id,
                "debit" => $this->params->total_debit,
                "credit" => $this->params->total_credit,
                "balance" => $accountBalance->balance,
                "note" => $this->params->invoice_remarks,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        if($accountBalance->type == "CASH"){
            $acc_bank = $this->cash->create([
                "invoice" => $invoice->id,
                "inv_type" => "accounts_voucher",
                "account_id" => $this->params->account_id,
                "debit" => $this->params->total_debit,
                "credit" => $this->params->total_credit,
                "balance" => $accountBalance->balance,
                "note" => $this->params->invoice_remarks,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        DB::commit();

        $this->responseMessage = "New Voucher created successfully";
        $this->outputData = $invoice->id;
        $this->success = true;
    }

    public function getAllInvoices(Request $request, Response $response)
    {
        $invoices = $this->invoices->with(['account','creator'])->where('status',1)->orderBy('id','desc')->get();

        $this->responseMessage = "Invoice list fetched successfully";
        $this->outputData = $invoices;
        $this->success = true;
    }

    public function getAllInvoicesList(Request $request, Response $response)
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = $this->invoices->with(['account','creator']);
        // ->where('status',1)->orderBy('id','desc')->get();
        // accounts_voucher

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('accounts_voucher.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('accounts_voucher.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('accounts_voucher.voucher_number', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_invoices =  $query->orderBy('accounts_voucher.id', 'desc')
        ->offset(($pageNo - 1) * $perPageShow)
        ->limit($perPageShow)
        ->get();


    if ($pageNo == 1) {
        $totalRow = $query->count();
    }

        $this->responseMessage = "Invoice list fetched successfully";
                $this->outputData = [
            $pageNo => $all_invoices,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getInvoiceInfo(Request $request, Response $response)
    {
        if(!isset($this->params->invoice_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $invoice = $this->invoices->with('creator','account')->find($this->params->invoice_id);

        if($invoice->status == 0){
            $this->success = false;
            $this->responseMessage = "Invoice missing!";
            return;
        }

        if(!$invoice){
            $this->success = false;
            $this->responseMessage = "Invoice not found!";
            return;
        }

        $invoice_list = DB::table('accounts_voucher')
        ->join('accounts_voucher_items','accounts_voucher.id','=','accounts_voucher_items.accounts_voucher_id')
        ->join('account_sectors','account_sectors.id','=','accounts_voucher_items.sector_id')
        ->select('accounts_voucher_items.id',
                 'account_sectors.title as sectorName',
                 'accounts_voucher_items.remarks',
                 'accounts_voucher_items.sector_id as sector',
                 'accounts_voucher_items.debit as debit',
                 'accounts_voucher_items.credit as credit',
                )
        ->where('accounts_voucher_items.status','=',1)
        ->where('accounts_voucher.id','=',$this->params->invoice_id)
        ->get();

        $last_invoice = DB::table('accounts_voucher_items')->select('id')->orderBy('id', 'DESC')->first();
        $last_invoice_id = $last_invoice->id + 1;

        $this->responseMessage = "Invoice info fetched successfully";
        $this->outputData = $invoice;
        $this->outputData['invoice_list'] = $invoice_list;
        $this->outputData['last_invoice_id'] = $last_invoice_id;
        $this->success = true;
    }

    public function updateVoucher(Request $request, Response $response)
    {
        $invoice = $this->invoices->where('status',1)->find($this->params->invoice_id);

        if(!$invoice){
            $this->success = false;
            $this->responseMessage = "Voucher not found!";
            return;
        }

        $this->validator->validate($request, [
            "invoice_date"=>v::notEmpty(),
            "voucher_type"=>v::notEmpty(),
            "account_id"=>v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $old_total_debit = $invoice->total_debit;
        $old_total_credit = $invoice->total_credit;

        $vouchers = $this->params->vouchers;
        $deletedVouchers = $this->params->deletedVouchers;

        $now = Carbon::now();

        $count = count($vouchers);
        $deletedCount = count($deletedVouchers);

        if($count == 0){
            $this->success = false;
            $this->responseMessage = 'Add atleast one item';
            return; 
        }

        $qty = 0;
        for($i =0; $i < $count; $i++){
            $qty = $qty+$vouchers[$i]['item_qty'];
        }

        $edit_count = 1;

        if($this->params->customer_type == 'hotel-customer'){
            $customer_id = $this->params->customer_id;
            $guest = null;
        }
        else{
            $customer_id = null;
            $guest = $this->params->guest_customer;
        }

        DB::beginTransaction();

        $invoice->update([
            "remarks" => $this->params->invoice_remarks,
            "voucher_date" => $this->params->invoice_date,
            "total_debit" => $this->params->total_debit,
            "total_credit" => $this->params->total_credit,
            "updated_by" => $this->user->id,
         ]);

        $old_invoice_item = DB::table('accounts_voucher_items')->where('accounts_voucher_id', $this->params->invoice_id)->where('status',1)->get();

        $old_invoice_count = count($old_invoice_item);

        $insertedVoucher = array();

        for($k = 0; $k < $old_invoice_count ; $k++){
            if($deletedCount > 0){
                for($m = 0; $m < $deletedCount; $m++){
                        if($old_invoice_item[$k]->id == $deletedVouchers[$m]['id'])
                        {                        
                            $invoiceDeleted = DB::table('accounts_voucher_items')
                                                ->where('id', $old_invoice_item[$k]->id)
                                                ->update(['status' => 0,
                                                          'updated_by' => $this->user->id
                                                        ]);

                            if($old_invoice_item[$k]->debit == 0){
                                $debit = $old_invoice_item[$k]->credit;
                                $credit = 0;
                            }
                            if($old_invoice_item[$k]->credit == 0){
                                $credit = $old_invoice_item[$k]->debit;
                                $debit = 0;
                            }

                            if($this->params->voucher_type == "asset"){
                                $accountAsset = DB::table('account_asset')->insert([
                                    "invoice" => $invoice->id,
                                    "sector" => $old_invoice_item[$k]->sector_id,
                                    "inv_type" => "accounts_voucher",
                                    "debit" => $debit,
                                    "credit" => $credit,
                                    "note" => "Item removed from accounts voucher",
                                    "created_by" => $this->user->id,
                                    "status" => 1,
                                ]);
                            }
                
                            if($this->params->voucher_type == "liability"){
                                $accountExpense = DB::table('account_liabilities')->insert([
                                    "invoice" => $invoice->id,
                                    "sector" => $old_invoice_item[$k]->sector_id,
                                    "inv_type" => "accounts_voucher",
                                    "debit" => $debit,
                                    "credit" => $credit,
                                    "note" => "Item removed from accounts voucher",
                                    "created_by" => $this->user->id,
                                    "status" => 1,
                                ]);
                            }
                
                            if($this->params->voucher_type == "revenue"){
                                $accountExpense = DB::table('account_revenue')->insert([
                                    "invoice" => $invoice->id,
                                    "sector" => $old_invoice_item[$k]->sector_id,
                                    "inv_type" => "accounts_voucher",
                                    "debit" => $debit,
                                    "credit" => $credit,
                                    "note" => "Item removed from accounts voucher",
                                    "created_by" => $this->user->id,
                                    "status" => 1,
                                ]);
                            }
                
                            if($this->params->voucher_type == "expenditure"){
                                $accountExpense = DB::table('account_expense')->insert([
                                    "invoice" => $invoice->id,
                                    "sector" => $old_invoice_item[$k]->sector_id,
                                    "inv_type" => "accounts_voucher",
                                    "debit" => $debit,
                                    "credit" => $credit,
                                    "note" => "Item removed from accounts voucher",
                                    "created_by" => $this->user->id,
                                    "status" => 1,
                                ]);
                            }  
                        }
                }
            }

            for($l = 0; $l < $count; $l++){

                if($old_invoice_item[$k]->id != $vouchers[$l]['id']){

                    $item_exist_check = DB::table('accounts_voucher_items')->where('accounts_voucher_id', $this->params->invoice_id)
                                        ->where('id',$vouchers[$l]['id'])->where('status',1)->first();
                    if($item_exist_check == null){

                        if($vouchers[$l]["amountType"] == 'debit'){
                            $debit = $vouchers[$l]["amount"];
                            $credit = 0;
                        }
                        if($vouchers[$l]["amountType"] == 'credit'){
                           $credit = $vouchers[$l]["amount"];
                           $debit = 0;
                        }

                        $insertedVoucher[] = array(
                            'accounts_voucher_id' => $this->params->invoice_id,
                            'sector_id' => $vouchers[$l]['sector'],
                            'debit' => $debit,
                            'credit' => $credit,
                            'remarks' => $vouchers[$l]['remarks'],
                            'created_by' => $this->user->id,
                            'status' => 1
                        );
                        DB::table('accounts_voucher_items')->insert($insertedVoucher);

                        if($this->params->voucher_type == "asset"){
                            $accountAsset = DB::table('account_asset')->insert([
                                "invoice" => $invoice->id,
                                "sector" => $vouchers[$l]['sector'],
                                "inv_type" => "accounts_voucher",
                                "debit" => $debit,
                                "credit" => $credit,
                                "note" => "Voucher created with remarks: ".$vouchers[$l]['remarks'],
                                "created_by" => $this->user->id,
                                "status" => 1,
                            ]);
                        }
            
                        if($this->params->voucher_type == "liability"){
                            $accountExpense = DB::table('account_liabilities')->insert([
                                "invoice" => $invoice->id,
                                "sector" => $vouchers[$l]['sector'],
                                "inv_type" => "accounts_voucher",
                                "debit" => $debit,
                                "credit" => $credit,
                                "note" => "Voucher created with remarks: ".$vouchers[$l]['remarks'],
                                "created_by" => $this->user->id,
                                "status" => 1,
                            ]);
                        }
            
                        if($this->params->voucher_type == "revenue"){
                            $accountExpense = DB::table('account_revenue')->insert([
                                "invoice" => $invoice->id,
                                "sector" => $vouchers[$l]['sector'],
                                "inv_type" => "accounts_voucher",
                                "debit" => $debit,
                                "credit" => $credit,
                                "note" => "Voucher created with remarks: ".$vouchers[$l]['remarks'],
                                "created_by" => $this->user->id,
                                "status" => 1,
                            ]);
                        }
            
                        if($this->params->voucher_type == "expenditure"){
                            $accountExpense = DB::table('account_expense')->insert([
                                "invoice" => $invoice->id,
                                "sector" => $vouchers[$l]['sector'],
                                "inv_type" => "accounts_voucher",
                                "debit" => $debit,
                                "credit" => $credit,
                                "note" => "Voucher created with remarks: ".$vouchers[$l]['remarks'],
                                "created_by" => $this->user->id,
                                "status" => 1,
                            ]);
                        }  
                    }
                }
            }
        }

        $old_net_amount = $old_total_debit - $old_total_credit;

        $net_amount = $this->params->total_debit - $this->params->total_credit;

        $varryingAmt = $net_amount - $old_net_amount;

        $accountBalance = $this->accounts->where('id',$this->params->account_id)->first();
        
        $accountBalance->update([
            "balance" => $accountBalance->balance + $varryingAmt
        ]);

        if($varryingAmt > 0){
            if($accountBalance->type == "BANK"){
                $acc_bank = $this->bank->create([
                    "invoice" => $invoice->id,
                    "inv_type" => "accounts_voucher",
                    "account_id" => $this->params->account_id,
                    "debit" => $varryingAmt,
                    "credit" => 0,
                    "balance" => $accountBalance->balance,
                    "note" => "Balance updated with increment",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }
    
            if($accountBalance->type == "CASH"){
                $acc_bank = $this->cash->create([
                    "invoice" => $invoice->id,
                    "inv_type" => "accounts_voucher",
                    "account_id" => $this->params->account_id,
                    "debit" => $varryingAmt,
                    "credit" => 0,
                    "balance" => $accountBalance->balance,
                    "note" => "Balance updated with increment",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }
        }

        if($varryingAmt < 0){
            if($accountBalance->type == "BANK"){
                $acc_bank = $this->bank->create([
                    "invoice" => $invoice->id,
                    "inv_type" => "accounts_voucher",
                    "account_id" => $this->params->account_id,
                    "debit" => 0,
                    "credit" => abs($varryingAmt),
                    "balance" => $accountBalance->balance,
                    "note" => "Balance updated with decrement",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }
    
            if($accountBalance->type == "CASH"){
                $acc_bank = $this->cash->create([
                    "invoice" => $invoice->id,
                    "inv_type" => "accounts_voucher",
                    "account_id" => $this->params->account_id,
                    "debit" => 0,
                    "credit" => abs($varryingAmt),
                    "balance" => $accountBalance->balance,
                    "note" => "Balance updated with decrement",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
            }
        }
        

        DB::commit();

        $this->responseMessage = "Invoice Updated successfully";
        // $this->outputData = $item_exist_check;
        $this->success = true;
    }

    public function deleteInvoice()
    {
        if(!isset($this->params->invoice_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $invoice = $this->invoices->find($this->params->invoice_id);

        if(!$invoice){
            $this->success = false;
            $this->responseMessage = "Invoice not found!";
            return;
        }

        // $invoice_items = DB::table('restaurant_invoice_items')->where('restaurant_invoice_id', $this->params->invoice_id)->where('status',1)->get();

        // for($i = 0; $i < count($invoice_items); $i++){
        //     $deletedItem = DB::table('restaurant_invoice_items')
        //                             ->where('id', $invoice_items[$i]->id)
        //                             ->update([
        //                                       'status' => 0,
        //                                       'remarks' => 'Canceled',
        //                                     ]);
        // }
        
        $deletedInvoice = $invoice->update([
            "remarks" => 'Canceled',
            "status" => 0,
         ]);
 
         $this->responseMessage = "Invoice Deleted successfully";
         $this->outputData = $deletedInvoice;
         $this->success = true;
    }

    public function getAllInvoiceByDate(Request $request, Response $response)
    {
        $invoices = $this->invoices->with(['account','creator'])->where('voucher_date','>=',$this->params->start_date)->where('voucher_date','<=',$this->params->end_date)->where('status',1)->orderBy('id','desc')->get();

        $this->responseMessage = "Invoice list fetched successfully";
        $this->outputData = $invoices;
        $this->success = true;
    }

    public function getAllLedgerByFiltered(Request $request, Response $response)
    {

        $ledgerArr = [];
        if($this->params->account_type == 'asset'){

            if ($this->params->sector_id) {
                # code...
                $ledgers = DB::table('account_asset')
                    ->join('account_sectors','account_sectors.id','=','account_asset.sector')
                    ->select('account_asset.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_asset.sector',$this->params->sector_id)
                    ->where('account_asset.created_at','>=',$this->params->start_date)
                    ->where('account_asset.created_at','<=',$this->params->end_date)
                    ->where('account_asset.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_asset')
                ->join('account_sectors','account_sectors.id','=','account_asset.sector')
                ->select('account_asset.*',
                        'account_sectors.title as sectorName',
                        )
                // ->where('account_asset.sector',$this->params->sector_id)
                ->where('account_asset.created_at','>=',$this->params->start_date)
                ->where('account_asset.created_at','<=',$this->params->end_date)
                ->where('account_asset.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'liability'){

            if ($this->params->sector_id) {
                $ledgers = DB::table('account_liabilities')
                ->join('account_sectors','account_sectors.id','=','account_liabilities.sector')
                ->select('account_liabilities.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_liabilities.sector',$this->params->sector_id)
                ->where('account_liabilities.created_at','>=',$this->params->start_date)
                ->where('account_liabilities.created_at','<=',$this->params->end_date)
                ->where('account_liabilities.status',1)->orderBy('id','desc')
                ->get();
            }else{
                $ledgers = DB::table('account_liabilities')
                ->join('account_sectors','account_sectors.id','=','account_liabilities.sector')
                ->select('account_liabilities.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_liabilities.created_at','>=',$this->params->start_date)
                ->where('account_liabilities.created_at','<=',$this->params->end_date)
                ->where('account_liabilities.status',1)->orderBy('id','desc')
                ->get();
            }
            
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'revenue'){

            if ($this->params->sector_id) {
                $ledgers = DB::table('account_revenue')
                    ->join('account_sectors','account_sectors.id','=','account_revenue.sector')
                    ->select('account_revenue.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_revenue.sector',$this->params->sector_id)
                    ->where('account_revenue.created_at','>=',$this->params->start_date)
                    ->where('account_revenue.created_at','<=',$this->params->end_date)
                    ->where('account_revenue.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_revenue')
                ->join('account_sectors','account_sectors.id','=','account_revenue.sector')
                ->select('account_revenue.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_revenue.created_at','>=',$this->params->start_date)
                ->where('account_revenue.created_at','<=',$this->params->end_date)
                ->where('account_revenue.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'expenditure'){

            if ($this->params->sector_id) {
                $ledgers = DB::table('account_expense')
                    ->join('account_sectors','account_sectors.id','=','account_expense.sector')
                    ->select('account_expense.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_expense.sector',$this->params->sector_id)
                    ->where('account_expense.created_at','>=',$this->params->start_date)
                    ->where('account_expense.created_at','<=',$this->params->end_date)
                    ->where('account_expense.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_expense')
                ->join('account_sectors','account_sectors.id','=','account_expense.sector')
                ->select('account_expense.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_expense.created_at','>=',$this->params->start_date)
                ->where('account_expense.created_at','<=',$this->params->end_date)
                ->where('account_expense.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        $this->responseMessage = "ledgers list fetched successfully";
        $this->outputData['ledgers'] = $ledgerArr;
        $this->outputData['total_debit'] = $total_debit;
        $this->outputData['total_credit'] = $total_credit;
        $this->success = true;
    }




    public function getAllLedgerHistoryByFiltered(Request $request, Response $response)
    {

        $ledgerArr = [];
        if($this->params->account_type == 'asset'){

            if ($this->params->start_date && $this->params->end_date) {
                # code...
                $ledgers = DB::table('account_asset')
                    ->join('account_sectors','account_sectors.id','=','account_asset.sector')
                    ->select('account_asset.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_asset.sector',$this->params->sector_id)
                    ->where('account_asset.created_at','>=',$this->params->start_date)
                    ->where('account_asset.created_at','<=',$this->params->end_date)
                    ->where('account_asset.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_asset')
                ->join('account_sectors','account_sectors.id','=','account_asset.sector')
                ->select('account_asset.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_asset.sector',$this->params->sector_id)
                // ->where('account_asset.created_at','>=',$this->params->start_date)
                // ->where('account_asset.created_at','<=',$this->params->end_date)
                ->where('account_asset.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'liability'){

            if ($this->params->start_date && $this->params->end_date) {
                $ledgers = DB::table('account_liabilities')
                ->join('account_sectors','account_sectors.id','=','account_liabilities.sector')
                ->select('account_liabilities.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_liabilities.sector',$this->params->sector_id)
                ->where('account_liabilities.created_at','>=',$this->params->start_date)
                ->where('account_liabilities.created_at','<=',$this->params->end_date)
                ->where('account_liabilities.status',1)->orderBy('id','desc')
                ->get();
            }else{
                $ledgers = DB::table('account_liabilities')
                ->join('account_sectors','account_sectors.id','=','account_liabilities.sector')
                ->select('account_liabilities.*',
                        'account_sectors.title as sectorName',
                        )
                        ->where('account_liabilities.sector',$this->params->sector_id)
                // ->where('account_liabilities.created_at','>=',$this->params->start_date)
                // ->where('account_liabilities.created_at','<=',$this->params->end_date)
                ->where('account_liabilities.status',1)->orderBy('id','desc')
                ->get();
            }
            
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'revenue'){

            if ($this->params->start_date && $this->params->end_date) {
                $ledgers = DB::table('account_revenue')
                    ->join('account_sectors','account_sectors.id','=','account_revenue.sector')
                    ->select('account_revenue.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_revenue.sector',$this->params->sector_id)
                    ->where('account_revenue.created_at','>=',$this->params->start_date)
                    ->where('account_revenue.created_at','<=',$this->params->end_date)
                    ->where('account_revenue.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_revenue')
                ->join('account_sectors','account_sectors.id','=','account_revenue.sector')
                ->select('account_revenue.*',
                        'account_sectors.title as sectorName',
                        )
                        ->where('account_revenue.sector',$this->params->sector_id)
                // ->where('account_revenue.created_at','>=',$this->params->start_date)
                // ->where('account_revenue.created_at','<=',$this->params->end_date)
                ->where('account_revenue.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        if($this->params->account_type == 'expenditure'){

            if ($this->params->start_date && $this->params->end_date) {
                $ledgers = DB::table('account_expense')
                    ->join('account_sectors','account_sectors.id','=','account_expense.sector')
                    ->select('account_expense.*',
                            'account_sectors.title as sectorName',
                            )
                    ->where('account_expense.sector',$this->params->sector_id)
                    ->where('account_expense.created_at','>=',$this->params->start_date)
                    ->where('account_expense.created_at','<=',$this->params->end_date)
                    ->where('account_expense.status',1)->orderBy('id','desc')
                    ->get();
            }else{
                $ledgers = DB::table('account_expense')
                ->join('account_sectors','account_sectors.id','=','account_expense.sector')
                ->select('account_expense.*',
                        'account_sectors.title as sectorName',
                        )
                ->where('account_expense.sector',$this->params->sector_id)
                // ->where('account_expense.created_at','>=',$this->params->start_date)
                // ->where('account_expense.created_at','<=',$this->params->end_date)
                ->where('account_expense.status',1)->orderBy('id','desc')
                ->get();
            }
            
            $total_debit = 0;
            $total_credit = 0;
            foreach($ledgers as $key => $ledger){
                $total_debit += $ledger->debit;
                $total_credit += $ledger->credit;
                if($ledger->inv_type == 'accounts_voucher'){
                    $invoice = $this->invoices->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/accounts/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'purchase_invoice'){
                    $invoice = $this->purchase_invoice->find($ledger->invoice);
                    $reference = $invoice->local_invoice;
                    $referenceUrl = '/modules/purchase/invoice/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'consumption_voucher'){
                    $invoice = $this->consumptionVouchers->find($ledger->invoice);
                    $reference = $invoice->voucher_number;
                    $referenceUrl = '/modules/inventory/vouchers/details/'.$invoice->id;
                }
                if($ledger->inv_type == 'restaurant_invoice'){
                    $invoice = $this->restaurant_invoice->find($ledger->invoice);
                    $reference = $invoice->invoice_number;
                    $referenceUrl = '/modules/restaurant/food-order/details/'.$invoice->id;
                }
                $data['sl'] = $key+1;
                $data['sectorName'] = $ledger->sectorName;
                $data['date'] = $ledger->created_at;
                $data['note'] = $ledger->note;
                $data['reference'] = $reference;
                $data['referenceUrl'] = $referenceUrl;
                $data['credit'] = $ledger->credit;
                $data['debit'] = $ledger->debit;

                array_push($ledgerArr, $data);
            }
  
        }

        $this->responseMessage = "ledgers list fetched successfully";
        $this->outputData['ledgers'] = $ledgerArr;
        $this->outputData['total_debit'] = $total_debit;
        $this->outputData['total_credit'] = $total_credit;
        $this->success = true;
    }
}
