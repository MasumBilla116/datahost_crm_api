<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Models\Inventory\ConsumptionVoucher;
use App\Models\Inventory\InventoryItem;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use Carbon\Carbon;
use App\Validation\Validator;
use Illuminate\Database\Capsule\Manager as DB;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class VoucherController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $consumptionVouchers;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->consumptionVouchers = new ConsumptionVoucher();
        $this->items = new InventoryItem();
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
            case 'createVoucher':
                $this->createVoucher($request, $response);
                break;
            case 'getAllVouchers':
                $this->getAllVouchers($request, $response);
                break;

                case 'getAllVouchersList':
                    $this->getAllVouchersList($request, $response);
                    break;
                // getAllVouchersList
            case 'getVoucherInfo':
                $this->getVoucherInfo($request, $response);
                break;
            case 'updateVoucher':
                $this->updateVoucher($request, $response);
                break;
            case 'deleteVoucher':
                $this->deleteVoucher($request, $response);
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
            case 'getEditHistory':
                $this->getEditHistory($request, $response);
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


    public function createVoucher(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "date" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $vouchers = $this->params->vouchers;
        $now = Carbon::now();

        $count = count($vouchers);

        if ($count == 0) {
            $this->success = false;
            $this->responseMessage = 'Add atleast one item';
            return;
        }

        DB::beginTransaction();

        $qty = 0;
        for ($i = 0; $i < $count; $i++) {
            $qty = $qty + $vouchers[$i]['item_qty'];

            $item = $this->items->where('status', 1)->find($vouchers[$i]['itemId']);
            $old_qty = $item->qty;
            $new_qty = $vouchers[$i]['item_qty'];
            if ($new_qty > $old_qty) {
                $this->success = false;
                $this->responseMessage = sprintf("You can not consume '%s' more than %d", $item->name, $old_qty);
                return;
            }
        }

        $date = $now->format('ym');
        $last_voucher = $this->consumptionVouchers->select('id')->orderBy('id', 'DESC')->first();
        $voucher_id = $last_voucher->id + 1;
        if ($voucher_id == null) {
            $voucher_id = 1;
        }
        $voucher_number = sprintf("ICV-%s000%d", $date, $voucher_id);

        $voucher = $this->consumptionVouchers->create([
            "voucher_number" => $voucher_number,
            "remarks" => $this->params->totalRemarks,
            "voucher_date" => $this->params->date,
            "total_item" => $count,
            "total_item_qty" => $qty,
            "edit_attempt" => 0,
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $voucherList = array();
        $itemHistory = array();

        $total_amount = 0;

        for ($j = 0; $j < $count; $j++) {

            $item = $this->items->where('status', 1)->find($vouchers[$j]['itemId']);

            $item_price = $item->unit_cost;
            $price_cal = $item_price * $vouchers[$j]['item_qty'];

            $total_amount += $price_cal;

            $old_qty = $item->qty;
            $new_qty = $vouchers[$j]['item_qty'];
            $final_qty = $old_qty - $new_qty;

            $editedItem = $item->update([
                "qty" => $final_qty,
            ]);

            $voucherList[] = array(
                'consumption_voucher_id' => $voucher->id,
                'inventory_item_id' => $vouchers[$j]['itemId'],
                'qty' => $vouchers[$j]['item_qty'],
                'remarks' => $vouchers[$j]['remarks'],
                'created_by' => $this->user->id,
                'status' => 1
            );

            $itemHistory[] = array(
                'inventory_item_id' => $vouchers[$j]['itemId'],
                'edit_attempt' => 0,
                'note' => 'Item Consumed',
                'reference' => $voucher->id,
                'ref_type' => 'consumption_voucher',
                'action_by' => $this->user->id,
                'old_qty' => $old_qty,
                'affected_qty' => $new_qty,
                'new_qty' =>  $final_qty,
                'status' => 1
            );
        }

        $accountAsset = DB::table('account_asset')->insert([
            "invoice" => $voucher->id,
            "sector" => 1,
            "inv_type" => "consumption_voucher",
            "debit" => 0.00,
            "credit" => $total_amount,
            "note" => "Items consumed",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $accountAsset = DB::table('account_expense')->insert([
            "invoice" => $voucher->id,
            "sector" => 1,
            "inv_type" => "consumption_voucher",
            "debit" => $total_amount,
            "credit" => 0.00,
            "note" => "Items consumed",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        DB::table('consumption_voucher_items')->insert($voucherList);
        DB::table('inventory_item_history')->insert($itemHistory);

        DB::commit();

        $this->responseMessage = "New Category created successfully";
        //$this->outputData = $voucher_id->id;
        $this->success = true;
    }

    public function getAllVouchers(Request $request, Response $response)
    {

        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;


        if ($filter == 'all') {
            $vouchers =  $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'daily') {
            $vouchers =  $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->whereDate('created_at', date('Y-m-d'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'weekly') {

            $vouchers = $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'monthly') {
            $vouchers = $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'yearly') {

            $vouchers = $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->whereYear('created_at', date('Y'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'custom' && $start_date && $end_date) {
            $vouchers = $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->whereBetween('created_at', [$start_date, $end_date])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $vouchers = $this->consumptionVouchers
                ->with(['creator', 'updator'])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        }


        $this->responseMessage = "Voucher list fetched successfully";
        $this->outputData = $vouchers;
        $this->success = true;
    }



    public function getAllVouchersList(Request $request, Response $response)
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query =  DB::table('consumption_vouchers');
        // ->with(['creator', 'updator']);
        // ->where('status', 1)
        // ->orderBy('id', 'desc')
        // ->get();
        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('consumption_vouchers.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('consumption_vouchers.status', '=', 0);
        }

            if (isset($filter['yearMonth'])) {
            $query->whereYear('consumption_vouchers.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
                ->whereMonth('consumption_vouchers.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        }
    
        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('consumption_vouchers.voucher_number', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_voucher =  $query->orderBy('consumption_vouchers.id', 'desc')
        ->offset(($pageNo - 1) * $perPageShow)
        ->limit($perPageShow)
        ->get();


    if ($pageNo == 1) {
        $totalRow = $query->count();
    }


        $this->responseMessage = "Voucher list fetched successfully";
        $this->outputData = [
            $pageNo => $all_voucher,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getVoucherInfo(Request $request, Response $response)
    {
        if (!isset($this->params->voucher_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $voucher = $this->consumptionVouchers->with('creator')->find($this->params->voucher_id);

        if ($voucher->status == 0) {
            $this->success = false;
            $this->responseMessage = "Voucher missing!";
            return;
        }

        if (!$voucher) {
            $this->success = false;
            $this->responseMessage = "Voucher not found!";
            return;
        }

        $voucher_list = DB::table('consumption_vouchers')
            ->join('consumption_voucher_items', 'consumption_vouchers.id', '=', 'consumption_voucher_items.consumption_voucher_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'consumption_voucher_items.inventory_item_id')
            ->select(
                'consumption_voucher_items.id',
                'inventory_items.id as itemCode',
                'inventory_items.code as itemCodeName',
                'inventory_items.id as itemId',
                'inventory_items.name as itemName',
                'consumption_voucher_items.remarks',
                'consumption_voucher_items.qty as item_qty',
            )
            ->where('consumption_voucher_items.status', '=', 1)
            ->where('consumption_vouchers.id', '=', $this->params->voucher_id)
            ->get();

        $this->responseMessage = "Voucher info fetched successfully";
        $this->outputData = $voucher;
        $this->outputData['voucher_list'] = $voucher_list;
        $this->success = true;
    }

    public function updateVoucher(Request $request, Response $response)
    {
        $voucher = $this->consumptionVouchers->where('status', 1)->find($this->params->voucher_id);

        if (!$voucher) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }

        $this->validator->validate($request, [
            "date" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $vouchers = $this->params->vouchers;
        $deletedVouchers = $this->params->deletedVouchers;

        $now = Carbon::now();

        $count = count($vouchers);
        $deletedCount = count($deletedVouchers);

        if ($count == 0) {
            $this->success = false;
            $this->responseMessage = 'Add atleast one item';
            return;
        }

        $old_voucher_total = 0;
        for ($j = 0; $j < $voucher->total_item; $j++) {
            $voucher_item = DB::table('consumption_voucher_items')->where('consumption_voucher_id', $this->params->voucher_id)->where('status', 1)->get();

            $item = $this->items->where('status', 1)->find($voucher_item[$j]->inventory_item_id);

            $item_price = $item->unit_cost;
            $old_price_cal = $item->unit_cost * $voucher_item[$j]->qty;

            $old_voucher_total += $old_price_cal;
        }

        $qty = 0;

        DB::beginTransaction();

        for ($i = 0; $i < $count; $i++) {
            $qty = $qty + $vouchers[$i]['item_qty'];

            $item = $this->items->where('status', 1)->find($vouchers[$i]['itemId']);

            $old_qty = $item->qty;
            $new_qty = $vouchers[$i]['item_qty'];
            if ($new_qty > $old_qty) {
                $this->success = false;
                $this->responseMessage = sprintf("You can not consume '%s' more than %d", $item->name, $old_qty);
                return;
            }
            if ($new_qty == 0) {
                $this->success = false;
                $this->responseMessage = sprintf("You can not consume '%s' 0 quantity", $item->name);
                return;
            }
        }

        $edit_count = 1;

        $voucher->remarks = $this->params->totalRemarks;
        $voucher->voucher_date = $this->params->date;
        $voucher->total_item = $count;
        $voucher->total_item_qty = $qty;
        $voucher->edit_attempt = $voucher->edit_attempt + $edit_count;
        $voucher->updated_by = $this->user->id;

        $voucher->save();

        $insertedVoucher = array();
        $insertItemHistory = array();

        // for($k = 0; $k < $old_voucher_count ; $k++){
        $total_amount = 0;

        for ($l = 0; $l < $count; $l++) {

            $old_voucher_item = DB::table('consumption_voucher_items')->where('consumption_voucher_id', $this->params->voucher_id)->where('inventory_item_id', $vouchers[$l]['itemId'])->where('status', 1)->first();

            $item = $this->items->where('status', 1)->find($vouchers[$l]['itemId']);

            $item_price = $item->unit_cost;
            $price_cal = $item_price * $vouchers[$l]['item_qty'];

            $total_amount += $price_cal;

            $old_qty = $item->qty;
            if ($old_voucher_item->inventory_item_id == $vouchers[$l]['itemId']) {
                $old_edited_qty = $old_voucher_item->qty;
            } else {
                $old_edited_qty = 0;
            }

            $new_qty = $vouchers[$l]['item_qty'];

            $diff =  $old_edited_qty - $new_qty;

            $final_qty = $old_qty + ($diff);

            if ($old_edited_qty != $new_qty) {
                $editedItem = $item->update([
                    "qty" => $final_qty,
                ]);
            }

            if ($old_voucher_item->inventory_item_id == $vouchers[$l]['itemId']) {

                $voucherUpdated = DB::table('consumption_voucher_items')
                    ->where('id', $old_voucher_item->id)
                    ->update([
                        'consumption_voucher_id' => $this->params->voucher_id,
                        'inventory_item_id' => $vouchers[$l]['itemId'],
                        'qty' => $vouchers[$l]['item_qty'],
                        'remarks' => $vouchers[$l]['remarks'],
                        'updated_by' => $this->user->id
                    ]);

                if ($old_edited_qty != $new_qty) {

                    $insertItemHistory[] = array(
                        'inventory_item_id' => $vouchers[$l]['itemId'],
                        'edit_attempt' => $voucher->edit_attempt,
                        'note' => 'Item edited in consumption voucher',
                        'reference' => $this->params->voucher_id,
                        'ref_type' => 'consumption_voucher',
                        'action_by' => $this->user->id,
                        'old_qty' => $old_qty,
                        'affected_qty' => $diff,
                        'new_qty' =>  $final_qty,
                        'status' => 1
                    );
                }
            } else {
                $insertedVoucher[] = array(
                    'consumption_voucher_id' => $this->params->voucher_id,
                    'inventory_item_id' => $vouchers[$l]['itemId'],
                    'qty' => $vouchers[$l]['item_qty'],
                    'remarks' => $vouchers[$l]['remarks'],
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                    'status' => 1
                );

                $insertItemHistory[] = array(
                    'inventory_item_id' => $vouchers[$l]['itemId'],
                    'edit_attempt' => $voucher->edit_attempt,
                    'note' => 'Item edited in consumption voucher',
                    'reference' => $this->params->voucher_id,
                    'ref_type' => 'consumption_voucher',
                    'action_by' => $this->user->id,
                    'old_qty' => $old_qty,
                    'affected_qty' => $new_qty,
                    'new_qty' =>  $final_qty,
                    'status' => 1
                );
            }
        }
        //}
        DB::table('consumption_voucher_items')->insert($insertedVoucher);
        DB::table('inventory_item_history')->insert($insertItemHistory);

        $updateItemHistory = array();

        if ($deletedCount > 0) {

            for ($m = 0; $m < $deletedCount; $m++) {

                $item = $this->items->where('status', 1)->find($deletedVouchers[$m]['itemId']);
                $old_voucher = DB::table('consumption_voucher_items')->where('consumption_voucher_id', $this->params->voucher_id)->where('inventory_item_id', $deletedVouchers[$m]['itemId'])->where('status', 1)->first();

                if ($old_voucher != null) {
                    $old_qty = $item->qty;
                    $new_qty = $old_voucher->qty;

                    $final_qty = $old_qty + $new_qty;

                    $editedItem = $item->update([
                        "qty" => $final_qty,
                    ]);

                    $voucherDeleted = DB::table('consumption_voucher_items')
                        ->where('inventory_item_id', $deletedVouchers[$m]['itemId'])
                        ->update([
                            'status' => 0,
                            'updated_by' => $this->user->id
                        ]);

                    $updateItemHistory[] = array(
                        'inventory_item_id' => $deletedVouchers[$m]['itemId'],
                        'edit_attempt' => $voucher->edit_attempt,
                        'note' => 'Item deleted from consumption voucher',
                        'reference' => $this->params->voucher_id,
                        'ref_type' => 'consumption_voucher',
                        'action_by' => $this->user->id,
                        'old_qty' => $old_qty,
                        'affected_qty' => $new_qty,
                        'new_qty' =>  $final_qty,
                        'status' => 1
                    );
                }
            }
        }

        DB::table('inventory_item_history')->insert($updateItemHistory);


        $new_voucher_total = $total_amount;
        $varryingAmt = $new_voucher_total - $old_voucher_total;

        if ($varryingAmt > 0) {
            $accountAsset = DB::table('account_asset')->insert([
                "invoice" => $voucher->id,
                "sector" => 1,
                "inv_type" => "consumption_voucher",
                "debit" => 0.00,
                "credit" => $total_amount,
                "note" => "Items consumed edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $accountExpense = DB::table('account_expense')->insert([
                "invoice" => $voucher->id,
                "sector" => 1,
                "inv_type" => "consumption_voucher",
                "debit" => $total_amount,
                "credit" => 0.00,
                "note" => "Items consumed edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        if ($varryingAmt < 0) {
            $accountAsset = DB::table('account_asset')->insert([
                "invoice" => $voucher->id,
                "sector" => 1,
                "inv_type" => "consumption_voucher",
                "debit" => $total_amount,
                "credit" => 0.00,
                "note" => "Items consumed edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $accountExpense = DB::table('account_expense')->insert([
                "invoice" => $voucher->id,
                "sector" => 1,
                "inv_type" => "consumption_voucher",
                "debit" => 0.00,
                "credit" => $total_amount,
                "note" => "Items consumed edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        DB::commit();

        $this->responseMessage = "New Voucher Updated successfully";
        $this->outputData = $old_price_cal;
        $this->success = true;
    }

    public function deleteVoucher()
    {
        if (!isset($this->params->voucher_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $voucher = $this->consumptionVouchers->find($this->params->voucher_id);

        if (!$voucher) {
            $this->success = false;
            $this->responseMessage = "Voucher not found!";
            return;
        }

        $voucher_items = DB::table('consumption_voucher_items')->where('consumption_voucher_id', $this->params->voucher_id)->where('status', 1)->get();
        $deletedItemHistory = array();

        $total_amount = 0;

        for ($i = 0; $i < $voucher->total_item; $i++) {
            $item = $this->items->where('status', 1)->find($voucher_items[$i]->inventory_item_id);

            $item_price = $item->unit_cost;
            $price_cal = $item_price * $voucher_items[$i]->qty;

            $total_amount += $price_cal;

            $old_qty = $item->qty;
            $new_qty = $voucher_items[$i]->qty;
            $final_qty = $old_qty + $new_qty;

            $editedItem = $item->update([
                "qty" => $final_qty,
            ]);

            $deletedItemHistory[] = array(
                'inventory_item_id' => $voucher_items[$i]->inventory_item_id,
                'edit_attempt' => $voucher->edit_attempt,
                'note' => 'Item deleted from consumption voucher',
                'reference' => $this->params->voucher_id,
                'ref_type' => 'consumption_voucher',
                'action_by' => $this->user->id,
                'old_qty' => $old_qty,
                'affected_qty' => $new_qty,
                'new_qty' =>  $final_qty,
                'status' => 1
            );
        }
        DB::table('inventory_item_history')->insert($deletedItemHistory);

        $accountAsset = DB::table('account_asset')->insert([
            "invoice" => $voucher->id,
            "sector" => 1,
            "inv_type" => "consumption_voucher",
            "debit" => $total_amount,
            "credit" => 0.00,
            "note" => "Items consumed canceled",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $accountExpense = DB::table('account_expense')->insert([
            "invoice" => $voucher->id,
            "sector" => 1,
            "inv_type" => "consumption_voucher",
            "debit" => 0.00,
            "credit" => $total_amount,
            "note" => "Items consumed canceled",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $deletedVoucher = $voucher->update([
            "remarks" => 'Canceled',
            "status" => 0,
        ]);

        $this->responseMessage = "Voucher Deleted successfully";
        $this->outputData = $deletedVoucher;
        $this->success = true;
    }

    public function getItemByCode(Request $request, Response $response)
    {
        $items = $this->items->where('status', 1)->find($this->params->id);

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $items;
        $this->success = true;
    }

    public function getCodeByItem(Request $request, Response $response)
    {
        $items = $this->items->find($this->params->id);

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $items;
        $this->success = true;
    }

    public function getItemByCategory()
    {
        $items = $this->items->where('inventory_category_id', $this->params->id)->where('status', 1)->get();

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $items;
        $this->success = true;
    }

    public function getEditHistory()
    {
        $voucher = $this->consumptionVouchers->find($this->params->voucher_id);

        $editHistory = array();

        for ($i = 0; $i <= $voucher->edit_attempt; $i++) {
            $history = DB::table('inventory_item_history')
                ->join('inventory_items', 'inventory_items.id', '=', 'inventory_item_history.inventory_item_id')
                ->select('inventory_item_history.*', 'inventory_items.name as itemName')
                ->where('reference', $this->params->voucher_id)->where('ref_type', 'consumption_voucher')->where('edit_attempt', $i)->get();

            $editHistory[] = $history;
        }

        $this->responseMessage = "Edit history list fetched successfully";
        $this->outputData = $editHistory;
        $this->success = true;
    }
}
