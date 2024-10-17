<?php

namespace  App\Controllers\Purchase;

use App\Helpers\Helper;

use App\Auth\Auth;
use App\Models\Purchase\Invoice;    //Table===========>  supplier_inv
use App\Models\Purchase\InvoiceItem;    //Table ======>  supplier_inv_item
use App\Models\Purchase\Supplier;       //Table ======>  supplier

use App\Models\Inventory\InventoryItem;       //Table ======>  Inventory Item

use Carbon\Carbon;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use App\Models\Users\ClientUsers;
use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

/**Seeding tester */

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;


//use Fzaninotto\Faker\Src\Faker\Factory;
//use Fzaninotto\Src\Faker;
use Faker\Factory;
use Faker;

/**Seeding tester */
class   InvoiceController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    /** Invoice ini */
    public $invoice;
    public $invoiceItem;
    private $faker;
    public $supplier;
    private $inventory;

    private $helper;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        //Model Instance
        $this->invoice = new Invoice();
        $this->invoiceItem = new InvoiceItem();
        $this->supplier = new Supplier();

        $this->inventory = new InventoryItem();
        /*Model Instance END */
        $this->validator = new Validator();
        $this->user = new ClientUsers();
        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
        $this->faker = Factory::create();

        $this->helper = new Helper;
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);

        switch ($action) {
            case 'test':
                $this->run();
                break;
            case 'createSupplierInvoice':
                $this->createSupplierInvoice($request);
                break;
            case 'createSupplierInvoiceItem':
                $this->createSupplierInvoiceItem($request, $response);
                break;
            case 'getAllSupplierInvoice':
                $this->getAllSupplierInvoice();
                break;

            case 'getAllSupplierInvoiceList':
                $this->getAllSupplierInvoiceList();
                break;

                // getAllSupplierInvoiceList
            case 'getAllSupplierReturnInvoice':
                $this->getAllSupplierReturnInvoice(); //rajme
                break;
            case 'getInvoiceByID':
                $this->getInvoiceByID();
                break;
            case 'updateInvoice':
                $this->updateInvoice();
                break;
            case 'deleteInvoice':
                $this->deleteInvoice();
                break;
            case 'getInvoiceNumber':
                $this->getInvoiceNumber();
                break;
            case 'getInvoiceDetails':
                $this->getInvoiceDetails();
                break;
            case 'getReturnInvoiceDetails';
                $this->getReturnInvoiceDetails(); //rajme
                break;
            case 'getInvoiceDetailsAll':
                $this->getInvoiceDetailsAll();
                break;
            case 'getItemDetailsByID':
                $this->getItemDetailsByID();
                break;
            case 'getInvoiceDetailsBySupplierID':
                $this->getInvoiceDetailsBySupplierID();
                break;
            case 'returnSupplierInvoice':
                $this->returnSupplierInvoice(); //rajme
                break;
            case 'cancelReturnSupplierInvoice':
                $this->cancelReturnSupplierInvoice(); //rajme
                break;
            case 'viewSupplierLedger':
                $this->viewSupplierLedger();
                break;
            case 'getIdByInvId':
                $this->getIdByInvId();
                break;
            case 'getInvDetailsBySupplierId':
                $this->getInvDetailsBySupplierId();
                break;
            case 'editHistory':
                $this->editHistory();
                break;
            case 'getInvoiceBySupplierId':
                $this->getInvoiceBySupplierId();
                break;
            case 'getSupplierDetailsByDate':
                $this->getSupplierDetailsByDate();
                break;
            case 'getSupplierBalance':
                $this->getSupplierBalance();
                break;
            case 'getItemInvoiceByID':
                $this->getItemInvoiceByID();
                break;
            case 'getCatInvoiceByID':
                $this->getCatInvoiceByID();
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
     * !Mamun GetItemInvoiceByID & getCateInvoiceByID
     */
    public function getItemInvoiceByID()
    {
        $invoiceItems = [];
        $itemIDs = $this->params->itemID;

        if (COUNT($itemIDs)) {

            foreach ($itemIDs as $key => $value) {
                $invoiceItem = $this->invoiceItem
                    ->join('inventory_items', 'inventory_items.id', '=', 'supplier_invoice_item.item_id')
                    ->where(["item_id" => $value])
                    ->select('supplier_invoice_item.*', 'inventory_items.name')
                    ->get();
                $invoiceItems[] = $invoiceItem;
            }

            $this->responseMessage = "Itemwise Invoices Fetched Successfully!";
            $this->outputData = $invoiceItems;
            $this->success = true;
        } else {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }
    }


    public function getCatInvoiceByID()
    {

        $invoiceItemsID = [];
        $catIDs = $this->params->catID;

        $inventoryCategory = $this->inventoryCategory->with("supplierInvoiceItems")->whereIn('id', $catIDs)->get();

        $this->responseMessage = "Category fwise Invoices Fetched Successfully!";
        $this->outputData = $inventoryCategory;
        $this->success = true;
    }

    /**
     * !Getting Supplier Balance
     */

    public function getSupplierBalance()
    {
        $res = $this->supplier
            ->select('supplier.balance as balance')
            ->where(['id' => $this->params->supplier_id])
            ->get();
        $this->responseMessage = "Supplier Invoice Fetched Successfully!";
        $this->outputData = $res;
        $this->success = true;
    }

    /**
     * !Getting Supplier Details by
     */

    public function getSupplierDetailsByDate()
    {
        $from = $this->params->from;
        $to = $this->params->to;
        $result = DB::select(DB::raw("SELECT * FROM `supplier_invoice`
        WHERE `invoice_date` BETWEEN '$from' AND '$to'"));
        $this->responseMessage = "Supplier Invoice Fetched Successfully!";
        $this->outputData = $result;
        $this->success = true;
    }

    /**
     * !Getting Supplier Invoice By Supplier ID
     */
    public function getInvoiceBySupplierId()
    {
        $invoice = $this->invoice
            ->select("supplier_invoice.*")
            ->where(["supplier_invoice.supplier_id" => $this->params->id])->get();

        if (!$invoice) {
            $this->success = false;
            $this->responseMessage = "Supplier Invoice not found!";
            return;
        }

        // $this->validator->validate($request, [
        //     "role_id"=>v::notEmpty(),
        // ]);

        $this->responseMessage = "Supplier Invoice Fetched Successfully!";
        $this->outputData = $invoice;
        // $this->outputData = $this->params->id;
        $this->success = true;
    }

    /**
     * !Show All Edit History By Invoice ID
     * @param id
     */

    public function editHistory()
    {
        $res = $this->helper->editHistory($this->params->supplier_invoice_id);
        $this->responseMessage = "Edit History Fetched Successfully!";
        $this->outputData = $res;
        $this->success = true;
    }


    /**Getting ID by INV ID */


    public function getInvDetailsBySupplierId()
    {

        $res = $this->helper->getInvDetailsBySupplierId('supplier_invoice_item', $this->params->supplier_invoice_id);
        $this->responseMessage = "Invoice Details Fetched Successfully!";
        $this->outputData = $res;
        $this->success = true;
    }

    public function getIdByInvId()
    {
        $result = $this->helper->getIdByInvoiceID('supplier_invoice', $this->params->invoice_id);
        $this->responseMessage = "Supplier ID fetched Successfully!";
        $this->outputData = $result[0]->id;
        $this->success = true;
    }


    /**Generating Supplier Ledger By Month & ID */
    public function viewSupplierLedger()
    {
        $supplierID = $this->params->supplier_id;
        $dateFrom = date($this->params->date_from);
        $dateTo = date($this->params->date_to);

        // $arr[] = array(
        //     "supplier_id"=>$supplierID,
        //     "date_from" => $dateFrom,
        //     "date_to" => $dateTo
        // );

        $result = DB::table('account_supplier')
            ->where('supplier_id', $supplierID)
            // ->where('created_at :> ', $dateFrom)
            // ->where('created_at :<', $dateTo )
            // ->whereBetween('created_at', ['NOW() - INTERVAL 30 DAY', 'NOW()'] )
            // ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('*')
            // ->toSql();
            ->get();

        $this->responseMessage = "Supplier Ledger fetched Successfully!";
        $this->outputData = $result;
        $this->success = true;
    }

    /**Get Invoice Details By Supplier ID */

    public function getInvoiceDetailsBySupplierID()
    {
        // $getInvDetails = DB::select(DB::raw(""));

        //#### Working part start
        //$getInvDetails = DB::table('supplier_invoice') //$this->invoice
        // ->join('supplier_invoice','supplier_invoice_item.supplier_invoice_id','=','supplier_invoice.id')
        //  ->join('supplier_invoice_item','supplier_invoice.id','=','supplier_invoice_item.supplier_invoice_id')
        //  ->select('supplier_invoice.id as supplier_invoice_id',
        //  'supplier_invoice.supplier_invoice','supplier_invoice.local_invoice',
        //  'supplier_invoice.total_item_qty as total_item_qty',
        //  'supplier_invoice.total_amount as total_amount',

        //  )
        //  ->where(['supplier_invoice.supplier_id'=>$this->params->supplier_id])
        // ->groupBy('supplier_invoice_item.supplier_invoice_id')
        //  ->toSql();
        //##### Working part end

        $id = $this->params->supplier_id;



        $getInvDetails = DB::select(DB::raw(
            "select `supplier_invoice`.`id` as `supplier_invoice_id`, 
            `supplier_invoice`.`supplier_invoice`, `supplier_invoice`.`local_invoice`, 
            `supplier_invoice`.`total_item_qty` as `total_item_qty`, 
            `supplier_invoice`.`total_amount` as `total_amount`,
            (select count(supplier_invoice_item.supplier_invoice_id) 
            from supplier_invoice_item WHERE 
            supplier_invoice.id=supplier_invoice_item.supplier_invoice_id) as total_item
            from `supplier_invoice`  where (`supplier_invoice`.`supplier_id` = $id)"
        ));

        /*$getInvDetails = DB::select(DB::raw(
            "select `supplier_invoice_item`.*, 
            (select supplier_invoice.id from supplier_invoice where 
                        supplier_invoice.id=supplier_invoice_item.supplier_invoice_id ) as supplier_inv_id,
            (select supplier_invoice.local_invoice from supplier_invoice where 
                        supplier_invoice.id=supplier_invoice_item.supplier_invoice_id ) as local_invoice,
                        (select supplier_invoice.supplier_invoice from supplier_invoice where 
                        supplier_invoice.id=supplier_invoice_item.supplier_invoice_id ) as supplier_invoice,
                        (select supplier_invoice.total_item_qty from supplier_invoice where 
                        supplier_invoice.id=supplier_invoice_item.supplier_invoice_id ) as total_item_qty,
                        (select supplier_invoice.total_amount from supplier_invoice where 
                        supplier_invoice.id=supplier_invoice_item.supplier_invoice_id ) as total_amount,
                        (select count(supplier_invoice_item.supplier_invoice_id) 
                        from supplier_invoice_item where 
                        supplier_invoice_item.supplier_invoice_id = supplier_invoice.id) 
                        as total_item from `supplier_invoice_item` 
                        inner join `supplier_invoice` on 
                        `supplier_invoice_item`.`supplier_invoice_id` = `supplier_invoice`.`id` 
                        where (`supplier_invoice`.`supplier_id` = $id) 
                        "));*/
        // $getInvDetails = DB::table('supplier_invoice')
        // ->join('supplier_invoice_item','supplier_invoice.local_invoice','=','supplier_invoice_item.local_invoice')
        // ->select('supplier_invoice.local_invoice','supplier_invoice_item.*')
        // ->where('supplier_invoice.supplier_id','=',$this->params->supplier_id)
        // ;

        //$a = DB::table($getInvDetails)->get();

        // $getInvDetails = DB::table(DB::raw('supplier_invoice','supplier_invoice_item')
        // ->select('supplier_invoice.local_invoice')
        // ->where('supplier_invoice.supplier_id','=',$this->params->supplier_id)
        // );


        $this->responseMessage = "Supplier Invoice Updated Successfully!";
        $this->outputData = $getInvDetails;
        $this->success = true;
    }

    /**Getting Item Details By ID */
    public function getItemDetailsByID()
    {
        $local_invoice = $this->invoice
            ->select('local_invoice')
            ->where(["id" => $this->params->id])
            ->get();
        /**Getting Invoice number from supplier_invoice table */
        $localInvoice = $local_invoice->toArray();
        $localInvoice = $localInvoice[0]['local_invoice'];
        /**Getting Invoice number from supplier_invoice table */
        $invoiceItem = $this->invoiceItem
            ->select('*')
            ->where(["local_invoice" => $localInvoice])
            ->get();

        $this->responseMessage = "Item Details Fetched Successfully!";
        // $this->outputData = $this->params;
        $this->outputData = $invoiceItem;
        $this->success = true;
    }

    /**Delete supplier Invoice */

    public function deleteInvoice()
    {

        $supplier_invoice = $this->invoice->where('id', $this->params->id)->where('status', 1)->first();
        //   $invoiceItem = $this->invoiceItem->where('');
        $local_invoice = $this->invoice
            ->select('local_invoice')
            ->where(["id" => $this->params->id])
            ->get();
        // dd ($local_invoice->toArray());

        /**Getting Invoice number from supplier_invoice table */
        $localInvoice = $local_invoice->toArray();
        $localInvoice = $localInvoice[0]['local_invoice'];
        /**Getting Invoice number from supplier_invoice table */

        // $invoiceItem = $this->invoiceItem
        // ->select('*')
        // ->where(["local_invoice"=>$localInvoice])
        // ->get();

        //   $invoiceItem = $this->invoiceItem->where('local_invoice', $localInvoice)->delete();

        //   $invoice = $this->invoice->where('local_invoice', $localInvoice)->delete();


        //accounting adjustment
        $supplier_balance = $this->supplier->where('id', $supplier_invoice->supplier_id)->where('status', 1)->first();

        DB::table('supplier')
            ->where(["id" => $supplier_invoice->supplier_id])
            ->update([
                'balance' => ($supplier_balance->balance + $supplier_invoice->total_amount),
            ]);

        $accountSupplier = DB::table('account_supplier')->insert([
            'supplier_id' => $supplier_invoice->supplier_id,
            'invoice_id' => $supplier_invoice->id,
            'inv_type' => "purchase_invoice",
            'debit' => $supplier_invoice->total_amount,
            'credit' => 0.00,
            'balance' => ($supplier_balance->balance + $supplier_invoice->total_amount),
            'note' => "Canceled purchase",
            'status' => 1,
            'created_by' => $this->user->id,
        ]);

        $accountAsset = DB::table('account_asset')->insert([
            "invoice" => $supplier_invoice->supplier_id,
            "sector" => 1,
            "inv_type" => "purchase_invoice",
            "debit" => 0.00,
            "credit" => $supplier_invoice->total_amount,
            "note" => "Items canceled from supplier",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $accountLiabilities = DB::table('account_liabilities')->insert([
            "sector" => 10,
            "invoice" => $supplier_invoice->supplier_id,
            "inv_type" => "purchase_invoice",
            "debit" => 0.00,
            "credit" => $supplier_invoice->total_amount,
            "note" => "Items canceled from supplier",
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $deletedInvoice = $supplier_invoice->update([
            "remarks" => 'Canceled',
            "status" => 0,
        ]);

        $this->responseMessage = "Invoice Canceled successfully!!";
        $this->outputData = $supplier_balance->balance;
        $this->success = true;
    }

    /**Updating Supplier -------------------> frontend: purchase/invoice/update/[id].tsx */
    public function updateInvoice()
    {
        $purchase_invoice = $this->invoice->where('status', 1)->find($this->params->supplier_invoice_id);

        if (!$purchase_invoice) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }

        $invoice = $this->params->invoice; //edited items
        $deletedInvoice = $this->params->deletedInvoice; //deleted items
        $newInvoice = $this->params->newInvoice; //new items

        $now = Carbon::now();

        $count = count($invoice);
        $deletedCount = count($deletedInvoice);
        $newCount = count($newInvoice);

        if ($count == 0) {
            $this->success = false;
            $this->responseMessage = 'Add atleast one item';
            return;
        }

        $old_invoice_total = $purchase_invoice->total_amount;
        $new_invoice_total = $this->params->totalAmount;
        $varryingAmt = $new_invoice_total - $old_invoice_total;

        //Table :: Supply Invoice ----------------------------------------------------->
        $updatedlInvoice = $this->invoice
            ->where('id', $this->params->supplier_invoice_id)
            ->update([
                'total_amount' => $this->params->totalAmount,
                'remarks' => $this->params->totalRemarks,
                'local_invoice' => $this->params->localInvoice,
                'created_at' => $this->params->inv_date,
                'supplier_id' => $this->params->supplierID,
                'supplier_invoice' => $this->params->inv_id,
                "edit_attempt" => DB::raw('edit_attempt + 1')
            ]);

        $editAttempt = $this->helper->getLastEditItem($this->params->supplier_invoice_id);

        /**
         * !edited items 
         * ?Inventory item, Inventory_item_history, Supplier_invoice history, Supply Invoice Item
         * @param ItemId, @param Insertion, @param Insertion, @param supplier_invoice_id
         */
        for ($i = 0; $i < $count; $i++) {

            if ($this->params->invoice[$i]["update"]) {

                $oldItem = $this->helper->getItemFromSupplierInvoice("qty,unit_price", "supplier_invoice_item", $this->params->invoice[$i]['id']);
                $diff = $this->params->invoice[$i]['item_qty'] - $oldItem[0]->qty;

                $newRate = ($oldItem[0]->unit_price * $oldItem[0]->qty) + ($this->params->invoice[$i]['unitPrice'] * $this->params->invoice[$i]['item_qty']);
                $newQty = $oldItem[0]->qty + $this->params->invoice[$i]['item_qty'];
                $newPrice = $newRate / $newQty;

                $oldItemInventory =  $this->helper->getItemFromSupplierInvoice("qty,unit_cost", "inventory_items", $this->params->invoice[$i]['itemId']);

                //Table :: Inventory item --------------------------------------------------->
                $inventory = $this->inventory
                    ->where(["id" => $this->params->invoice[$i]['itemId']])
                    ->update([
                        'qty' => DB::raw('qty + ' . $diff),
                        'unit_cost' => $newPrice,
                    ]);

                // Table :: Inventory item history -------------------------------------->
                DB::table('inventory_item_history')
                    ->insert([
                        "edit_attempt" => $editAttempt[0]->edit_attempt,
                        'reference' => $this->params->invoice[$i]['supplier_invoice_id'],
                        'inventory_item_id' => $this->params->invoice[$i]['itemId'],
                        'note' => 'Items edited in purchase invoice',
                        "ref_type" => "supplier_purchase_invoice",
                        "action_by" => $this->user->id,
                        "old_qty" => $oldItemInventory[0]->qty,
                        "affected_qty" => $diff,
                        "new_qty" => $oldItemInventory[0]->qty + $diff,
                        "old_price" => $oldItemInventory[0]->unit_cost,
                        "new_price" => $newPrice,
                        "status" => 1,
                    ]);

                //Table :: Supplier invoice history -------------------------------------->
                DB::table('supplier_invoice_history')
                    ->insert([
                        "edit_attempt" => $editAttempt[0]->edit_attempt,
                        'reference' => $this->params->invoice[$i]['supplier_invoice_id'],
                        'invoice_item_id' => $this->params->invoice[$i]['itemId'],
                        'note' => 'Item edited into purchase invoice',
                        "action_by" => $this->user->id,
                        "old_qty" => $oldItem[0]->qty,
                        "affected_qty" => $diff,
                        "new_qty" => $this->params->invoice[$i]["item_qty"],
                        "old_price" => $oldItem[0]->unit_price,
                        "new_price" => $this->params->invoice[$i]['unitPrice'],
                        "status" => 1,
                    ]);

                //Table :: Supply Invoice Item ----------------------------------------->
                $editedInvoice = $this->invoiceItem
                    ->where('id', $this->params->invoice[$i]['id'])
                    ->update([
                        // 'item_name' => $this->params->invoice[$i]['itemName'],
                        'qty' => $this->params->invoice[$i]['itemCode'],
                        'item_id' => $this->params->invoice[$i]['itemId'],
                        'qty' => $this->params->invoice[$i]['item_qty'],
                        'remarks' => $this->params->invoice[$i]['item_remarks'],
                        'created_at' => $this->params->invoice[$i]['created_at'],
                        'unit_price' => $this->params->invoice[$i]['unitPrice'],
                        'status' => $this->params->invoice[$i]['status']
                    ]);
            }
        }
        /**
         * !deleted items
         */
        for ($l = 0; $l < $deletedCount; $l++) {
            $oldItemDel = $this->helper->getItemFromSupplierInvoice("qty,unit_price", "supplier_invoice_item", $this->params->deletedInvoice[$l]['id']);

            $diffDel = $this->params->deletedInvoice[$l]['item_qty'] - $oldItemDel[0]->qty;

            $oldItemInventoryDeleted =  $this->helper->getItemFromSupplierInvoice("qty,unit_cost", "inventory_items", $this->params->deletedInvoice[$l]['itemId']);

            //Table :: Inventory item history -------------------------------------->
            DB::table('inventory_item_history')
                ->insert([
                    "edit_attempt" => $editAttempt[0]->edit_attempt,
                    'ref_type' => "supplier_purchase_invoice",
                    'reference' => $this->params->deletedInvoice[$l]['supplier_invoice_id'],
                    'inventory_item_id' => $this->params->deletedInvoice[$l]['itemId'],
                    'note' => 'Item deleted from purchase invoice',
                    "action_by" => $this->user->id,
                    "old_qty" => $oldItemInventoryDeleted[0]->qty,
                    "affected_qty" => -$oldItemDel[0]->qty,
                    "new_qty" => $oldItemInventoryDeleted[0]->qty - $oldItemDel[0]->qty,
                    "old_price" => $oldItemInventoryDeleted[0]->unit_cost,
                    "new_price" => $oldItemInventoryDeleted[0]->unit_cost,
                    "status" => 1,
                ]);

            //Table :: Supplier invoice history -------------------------------------->
            DB::table('supplier_invoice_history')
                ->insert([
                    "edit_attempt" => $editAttempt[0]->edit_attempt,
                    'reference' => $this->params->deletedInvoice[$l]['supplier_invoice_id'],
                    'invoice_item_id' => $this->params->deletedInvoice[$l]['itemId'],
                    'note' => 'Item deleted from purchase invoice',
                    "action_by" => $this->user->id,
                    "old_qty" => $oldItemDel[0]->qty,
                    "affected_qty" => -$oldItemDel[0]->qty,
                    "new_qty" => $oldItemDel[0]->qty - $this->params->deletedInvoice[$l]["item_qty"],
                    "old_price" => $oldItemDel[0]->unit_price,
                    "new_price" => $this->params->deletedInvoice[$l]['unitPrice'],
                    "status" => 1,
                ]);

            $editedDeletedInvoice = $this->invoiceItem
                ->where('id', $this->params->deletedInvoice[$l]['id'])
                ->update(['status' => 0]);

            //Table :: Inventory -------------------------------------->
            $inventory = $this->inventory
                ->where(["id" => $this->params->deletedInvoice[$l]['itemId']])
                ->update([
                    'qty' => DB::raw('qty - ' . $this->params->deletedInvoice[$l]['item_qty']),
                    // 'unit_cost' => $newPrice,
                ]);
        }
        /**
         * !new items
         */
        for ($k = 0; $k < $newCount; $k++) {

            $newAddedInvoice = $this->invoiceItem
                ->insert([
                    'item_id' => $this->params->newInvoice[$k]['itemId'],
                    'supplier_invoice_id' => $this->params->supplier_invoice_id,
                    'qty' => $this->params->newInvoice[$k]['qty'],
                    'unit_price' => $this->params->newInvoice[$k]['unitPrice'],
                    'created_at' => $this->params->newInvoice[$k]['date'],
                    'remarks' => $this->params->newInvoice[$k]['item_remarks'],
                    'status' => $this->params->newInvoice[$k]['status'],
                ]);

            $oldItem = $this->helper->getItem("qty,unit_cost", "inventory_items", $this->params->newInvoice[$k]['itemId']);
            $oldQty = $oldItem[0]->qty;
            $oldPrice = $oldItem[0]->unit_cost;

            $purchasedQty = $this->params->newInvoice[$k]["qty"];
            $purchaseRate = $this->params->newInvoice[$k]["unitPrice"];

            $newRate = ($oldPrice * $oldQty) + ($purchaseRate * $purchasedQty);
            $newQty = $oldQty + $purchasedQty;
            $newPrice = $newRate / $newQty;

            $oldItemInventoryAdded =  $this->helper->getItemFromSupplierInvoice("qty,unit_cost", "inventory_items", $this->params->newInvoice[$k]['itemId']);

            //Table :: Inventory item --------------------------------------------------->
            $inventory = $this->inventory
                ->where(["id" => $this->params->newInvoice[$k]['itemId']])
                ->update([
                    'qty' => DB::raw('qty +' . $this->params->newInvoice[$k]["qty"]),
                    'unit_cost' => $newPrice,
                ]);

            //Table :: Inventory item history -------------------------------------->
            DB::table('inventory_item_history')
                ->insert([
                    "edit_attempt" => $editAttempt[0]->edit_attempt,
                    'ref_type' => "supplier_purchase_invoice",
                    'reference' => $this->params->newInvoice[$k]['supplier_invoice_id'],
                    'inventory_item_id' => $this->params->newInvoice[$k]['itemId'],
                    'note' => 'Item added into purchase invoice',
                    "action_by" => $this->user->id || 1,
                    "old_qty" => $oldItemInventoryAdded[0]->qty,
                    "affected_qty" => $this->params->newInvoice[$k]["qty"],
                    "new_qty" => $this->params->newInvoice[$k]["qty"] + $oldItemInventoryAdded[0]->qty,
                    "old_price" => $oldItemInventoryAdded[0]->unit_cost,
                    "new_price" =>  $newPrice,
                    "status" => 1,
                ]);

            //Table :: Supplier invoice history -------------------------------------->
            DB::table('supplier_invoice_history')
                ->insert([
                    "edit_attempt" => $editAttempt[0]->edit_attempt,
                    'reference' => $this->params->newInvoice[$k]['supplier_invoice_id'],
                    'invoice_item_id' => $this->params->newInvoice[$k]['itemId'],
                    'note' => 'Item Added into purchase invoice',
                    "action_by" => $this->user->id,
                    "old_qty" => 0,
                    "affected_qty" => $this->params->newInvoice[$k]["qty"],
                    "new_qty" => $this->params->newInvoice[$k]["qty"],
                    "old_price" => $oldPrice,
                    "new_price" => $this->params->newInvoice[$k]['unitPrice'],
                    "status" => 1,
                ]);
        }

        // $val = $this->helper->getLastSupplierAccountBalance('account_supplier', $this->params->supplierID);

        $val = DB::table('account_supplier')
            ->where('supplier_id', '=', $this->params->supplierID)
            ->where('status', '=', 1)
            ->orderBy('id', 'DESC')
            ->first();
        DB::table('supplier')
            ->where(["id" => $this->params->supplierID])
            ->update([
                'balance' => ($val->balance - ($varryingAmt)),
            ]);

        if ($varryingAmt > 0) {

            //Table :: Account Supplier --------------------------------------------------->

            $accountSupplier = DB::table('account_supplier')->insert([
                'supplier_id' => $this->params->supplierID,
                'invoice_id' => $this->params->supplier_invoice_id,
                'inv_type' => "purchase_invoice",
                'debit' => 0.00,
                'credit' => abs($varryingAmt),
                'balance' => ($val->balance - ($varryingAmt)),
                'note' => "Purchase Invoice Edited",
                'status' => 1,
                'created_by' => $this->user->id,
            ]);

            $accountAsset = DB::table('account_asset')->insert([
                "invoice" => $this->params->supplier_invoice_id,
                "sector" => 1,
                "inv_type" => "purchase_invoice",
                "debit" => abs($varryingAmt),
                "credit" => 0.00,
                "note" => "Purchase Invoice Edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $accountLiabilities = DB::table('account_liabilities')->insert([
                "sector" => 10,
                "invoice" => $this->params->supplier_invoice_id,
                "inv_type" => "purchase_invoice",
                "debit" => abs($varryingAmt),
                "credit" => 0.00,
                "note" => "Purchase Invoice Edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        if ($varryingAmt < 0) {

            //Table :: Account Supplier --------------------------------------------------->

            $accountSupplier = DB::table('account_supplier')->insert([
                'supplier_id' => $this->params->supplierID,
                'invoice_id' => $this->params->supplier_invoice_id,
                'inv_type' => "purchase",
                'debit' => abs($varryingAmt),
                'credit' => 0.00,
                'balance' => ($val->balance - ($varryingAmt)),
                'note' => "Purchase Invoice Edited",
                'status' => 1,
                'created_by' => $this->user->id,
            ]);

            $accountAsset = DB::table('account_asset')->insert([
                "invoice" => $this->params->supplier_invoice_id,
                "sector" => 1,
                "inv_type" => "purchase_invoice",
                "debit" => 0.00,
                "credit" => abs($varryingAmt),
                "note" => "Purchase Invoice Edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $accountLiabilities = DB::table('account_liabilities')->insert([
                "sector" => 10,
                "invoice" => $this->params->supplier_invoice_id,
                "inv_type" => "purchase_invoice",
                "debit" => 0.00,
                "credit" => abs($varryingAmt),
                "note" => "Purchase Invoice Edited",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        }

        $this->responseMessage = "Supplier Invoice Updated Successfully!";
        $this->outputData = $varryingAmt;
        $this->success = true;
    }



    /**Getting supplier by ID */

    public function getInvoiceByID()
    {
        $invoice = $this->invoice
            ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
            ->select("supplier_invoice.*", "supplier.name as supplier_name", "supplier.type as type")
            ->where(["supplier_invoice.id" => $this->params->id])->get();

        if (!COUNT($invoice)) {
            $this->success = false;
            $this->responseMessage = "Supplier Invoice not found!";
            return;
        }

        // $this->validator->validate($request, [
        //     "role_id"=>v::notEmpty(),
        // ]);

        $this->responseMessage = "Supplier Invoice Fetched Successfully!";
        $this->outputData = $invoice;
        // $this->outputData = $this->params->id;
        $this->success = true;
    }

    /**Getting Supplier List */
    public function getAllSupplierInvoice()
    {



        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;


        if ($filter == 'all') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'daily') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->whereDate('supplier_invoice.created_at', date('Y-m-d'))
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'weekly') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->whereBetween('supplier_invoice.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->whereDate('supplier_invoice.created_at', date('Y-m-d'))
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'monthly') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->whereYear('supplier_invoice.created_at', date('Y'))
                ->whereMonth('supplier_invoice.created_at', date('m'))
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'yearly') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->whereYear('supplier_invoice.created_at', date('Y'))
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'custom' && $start_date && $end_date) {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->whereBetween('supplier_invoice.created_at', [$start_date, $end_date])
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else if ($filter == 'deleted') {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->where('supplier_invoice.status', 0)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        } else {
            $getAllInvoice = DB::table('supplier_invoice')
                ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
                ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
                ->select(
                    'supplier_invoice.*',
                    'supplier.name as name',
                    'supplier_invoice_item.supplier_invoice_id',
                    DB::raw('count(supplier_invoice_item.id) as total_item')

                )
                ->where('supplier_invoice.status', 1)
                ->orderBy('supplier_invoice.id', 'desc')
                ->groupBy('supplier_invoice_item.supplier_invoice_id')
                ->get();
        }




        $this->responseMessage = "Supplier Invoice Data fetched Successfully!";
        $this->outputData = $getAllInvoice;
        $this->success = true;
    }


    public function getAllSupplierInvoiceList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('supplier_invoice')
            ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
            ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
            ->select(
                'supplier_invoice.*',
                'supplier.name as name',
                'supplier_invoice_item.supplier_invoice_id',
                DB::raw('count(supplier_invoice_item.id) as total_item')

            )
            ->where('supplier_invoice.status', 1)
            ->groupBy('supplier_invoice_item.supplier_invoice_id');

        if ($filter['status'] == 'all') {
            $query->where('supplier_invoice.status', '=', 1);
        }

        if ($filter['status'] == 'daily') {
            $query->whereDate('supplier_invoice.created_at', date('Y-m-d'));
        }

        if ($filter['status'] == 'weekly') {
            $query->whereBetween('supplier_invoice.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->whereDate('supplier_invoice.created_at', date('Y-m-d'));
        }


        if ($filter['status'] == 'monthly') {
            $query->whereYear('supplier_invoice.created_at', date('Y'))
                ->whereMonth('supplier_invoice.created_at', date('m'));
        }

        if ($filter['status'] == 'yearly') {
            $query->whereYear('supplier_invoice.created_at', date('Y'));
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('supplier_invoice.local_invoice', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier_invoice.supplier_invoice', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier.name', 'LIKE', '%' . $search . '%', 'i');
            });
        }



        $all_invoice_list =  $query->orderBy('supplier_invoice.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "All Employee fetched successfully";
        // $this->outputData = $query;
        $this->outputData = [
            $pageNo => $all_invoice_list,
            'total' => $totalRow,
        ];
        $this->success = true;
    }


    /**Getting Supplier Return Invoice List */
    public function getAllSupplierReturnInvoice()
    {

        /** Getting return items with returnd condition */

        $getAllReturnInvoice = DB::select(DB::raw("SELECT si.id, si.status,
            (select s.name from supplier s where si.supplier_id = s.id) as name,
            si.supplier_invoice,
            si.total_amount, 
            si.local_invoice, 
            si.created_by, 
            si.created_at,
            si.is_returned,
            si.total_item_qty,
            si.total_returned_qty,
            (select COUNT(`sii`.item_id) from managebeds.supplier_invoice_item sii WHERE
            sii.supplier_invoice_id = si.id and sii.is_returned = 1) as total_item
            from supplier_invoice si where si.is_returned = 1"));

        /** Checking and get invoice data */

        if (!$getAllReturnInvoice) {
            $getAllReturnInvoice = DB::select(DB::raw("SELECT si.id, 
                si.supplier_invoice,si.total_amount, 
                si.local_invoice, si.created_by, si.total_item_qty, si.is_returned,
                from supplier_invoice si"));
            if (!$getAllReturnInvoice) {
                $this->success = false;
                $this->responseMessage = "Supplier Invoice Data not found!";
            }
        }
        $this->responseMessage = "Supplier Returned Invoice Data fetched Successfully!";
        $this->outputData = $getAllReturnInvoice;
        $this->success = true;
    }

    /**
     * !All Invoice Details with disabled items
     */
    public function getInvoiceDetailsAll()
    {
        if (!isset($this->params->supplier_invoice_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $inv = $this->invoice->find($this->params->supplier_invoice_id);

        if ($inv->status == 0) {
            $this->success = false;
            $this->responseMessage = "Invoice missing!";
            return;
        }

        if (!$inv) {
            $this->success = false;
            $this->responseMessage = "Invoice not found!";
            return;
        }

        $inv_list = $this->helper->getInvoiceDetailsAll($this->params->supplier_invoice_id);

        // DB::table('supplier_invoice')
        // // ->where('inventory_item.id','=','supplier_invoice_item.item_id')
        // ->join('supplier_invoice_item','supplier_invoice.id','=','supplier_invoice_item.supplier_invoice_id')
        // ->join('inventory_items','inventory_items.id','=','supplier_invoice_item.item_id')
        // ->select('supplier_invoice.local_invoice','supplier_invoice.invoice_date','supplier_invoice_item.supplier_invoice_id','supplier_invoice_item.id','supplier_invoice.supplier_id','inventory_items.id as itemCode','inventory_items.code as itemCodeName',
        // 'inventory_items.id as itemId','inventory_items.name as itemName',
        // 'supplier_invoice_item.qty as item_qty', 'supplier_invoice.remarks as common_remarks',
        // 'supplier_invoice_item.remarks as item_remarks', 'supplier_invoice.created_at',
        // 'supplier_invoice_item.unit_price as unitPrice',
        // 'supplier_invoice.total_amount as totalAmount', 'supplier_invoice_item.status as status', DB::raw('select unit_type from inventory_items where inventory_items.id = supplier_invoice_item.item_id as pieace'))
        // ->where('supplier_invoice_item.status','=', 1)
        // ->where('supplier_invoice.id','=', $this->params->supplier_invoice_id)
        // ->get();

        $this->responseMessage = "Supplier All Invoice Details fetched Successfully!";
        $this->outputData = $inv_list;
        $this->success = true;
    }

    /**Getting Invoice Details For --------------- purchase/invoice/update */
    public function getInvoiceDetails()
    {
        if (!isset($this->params->supplier_invoice_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $purchaseid = $this->params->supplier_invoice_id;
        $inv = DB::table("purchase")->where("id", $purchaseid)->first();

        if ($inv->status == 0) {
            $this->success = false;
            $this->responseMessage = "Invoice missing!";
            return;
        }

        if (!$inv) {
            $this->success = false;
            $this->responseMessage = "Invoice not found!";
            return;
        }

        $inv_list = DB::table("purchase")
            ->select(
                "purchase.id as purchase_id",
                "purchase.purchase_invoice",
                "purchase.unit_price",
                "purchase.quantity",
                "purchase.purchase_date",
                "item_variations.stock",
                "item_variations.id",
                "items.item_name",
                "items.id as item_id",
                "item_types.item_type_name",
                "supplier.name as supplier_name",
                "supplier.contact_number",
                "supplier.address",
                "payment_types.type as payment_type",
            )
            ->join('purchase_variations as pv1', "pv1.purchase_id", '=', "purchase.id")
            ->join("payment_types", "payment_types.id", "=", "purchase.payment_type_id")
            ->join('item_variations', 'item_variations.id', '=', 'pv1.item_variation_id')
            ->join("items", "items.id", "=", "item_variations.item_id")
            ->join("item_types", "item_types.id", "=", "items.item_type_id")
            ->join("supplier", "supplier.id", "=", "purchase.supplier_id")
            ->get();



        $this->responseMessage = "Supplier Invoice Details fetched Successfully!";
        $this->outputData = $inv_list;
        $this->success = true;
    }

    /**Getting Invoice Details For --------------- Updpurchase/invoice/update */

    /**Getting Return Invoice Details For --------------- */
    public function getReturnInvoiceDetails()
    {
        if (!isset($this->params->supplier_invoice_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $inv = $this->invoice->find($this->params->supplier_invoice_id);

        if ($inv->status == 0) {
            $this->success = false;
            $this->responseMessage = "Invoice missing!";
            return;
        }

        if (!$inv) {
            $this->success = false;
            $this->responseMessage = "Invoice not found!";
            return;
        }

        $inv_list = $this->helper->getReturnInvoiceDetails($this->params->supplier_invoice_id);
        $this->responseMessage = "Supplier Invoice Details fetched Successfully!";
        $this->outputData = $inv_list;
        $this->success = true;
    }

    /**Create Supplier Invoice Item inv-item.tsx ---------- page frontend*/
    public function createSupplierInvoiceItem(Request $request, Response $response)
    {
        DB::beginTransaction();

        try {
            $this->validator->validate($request, [
                "supplierID" => v::notEmpty(),
                "invoice" => v::notEmpty(),
            ]);
            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }

            $invoiceList = array();
            $supplierInv = array();
            $amount = 0;
            $qty = 0;
            //$supplierID = "";
            $lastSupplierInvoiceID = $this->helper->getLastID('supplier_invoice');
            //$lastSupplierInvoiceID += 1;
            $now = Carbon::now();
            $date = $now->format('ym');

            $last_voucher = DB::table('supplier_invoice')
                ->select('id')
                ->orderBy('id', 'DESC')
                ->first();

            $voucher_id = $last_voucher->id + 1;
            if ($voucher_id == null) {
                $voucher_id = 1;
            }

            $invoice_number = sprintf('LINV-%s000%d', $date, $voucher_id);
            $invoice_id = sprintf('INV-%s000%d', $date, $voucher_id);



            /**Common Invoice Insertion */
            // @@ supplier_invoice as purchase 
            $supplier_invoice_id_gen =   DB::table('supplier_invoice')->insertGetId([
                'supplier_id' => $this->params->supplierID,
                'local_invoice' => $invoice_number,
                'status' => $this->params->status,
                'created_at' => $this->params->inv_date,
                'supplier_invoice' => $invoice_id,
                'total_amount' => $amount,
                'invoice_date' => $this->params->inv_date,
                'edit_attempt' => 0,
                'remarks' => $this->params->totalRemarks,
                'total_item_qty' => $qty,
                'created_by' => $this->user->id,
            ]);


            // print_r($this->params->invoice);
            // return;

            foreach ($this->params->invoice as $key => $value) {

                // $supplierID = $this->invoice->where(["supplier_id" => $value['supplierID']])->first();

                $now = Carbon::now();

                $oldItem = $this->helper->getItem("qty,unit_cost", "inventory_items", $value["itemId"]);

                $oldQty = $oldItem[0]->qty;
                $oldPrice = $oldItem[0]->unit_cost;

                $purchasedQty = $value["qty"];
                $purchaseRate = $value["unitPrice"];

                $newRate = ($oldPrice * $oldQty) + ($purchaseRate * $purchasedQty);
                //          (1500 * 1) + (2000*1) = 1500 + 2000 = 3500
                $newQty = $oldQty + $purchasedQty;  // 1 + 1 = 2

                $newPrice = $newRate / $newQty; // 3500 / 2 =  1750

                $this->inventory
                    ->where(["id" => $value["itemId"]])
                    ->update([
                        'qty' => DB::raw('qty +' . $value["qty"]),
                        'unit_cost' => $newPrice,
                    ]);

                /**Inventory History */
                $inventory_history[] = array(
                    "inventory_item_id" => $value["itemId"],
                    "edit_attempt" => 0,
                    "note" => "purchase_invoice",
                    "reference" => $lastSupplierInvoiceID,
                    "ref_type" => "supplier_purchase_invoice",
                    "action_by" => $this->user->id,
                    "old_qty" => $oldQty,
                    "affected_qty" => $purchasedQty,
                    "new_qty" => ($oldQty + $purchasedQty),
                    "old_price" => $oldPrice,
                    "new_price" => $purchaseRate,
                    "status" => 1,
                );
                /**Inventory History End */

                /**
                 * !Supplier Invoice History
                 */
                DB::table('supplier_invoice_history')
                    ->insert([
                        "edit_attempt" => 0,
                        'reference' => $lastSupplierInvoiceID,
                        'invoice_item_id' => $value["itemId"],
                        'note' => 'Item Created into purchase invoice',
                        "action_by" => $this->user->id,
                        "old_qty" => 0,
                        "affected_qty" => $purchasedQty,
                        "new_qty" => $purchasedQty,
                        "old_price" => $oldPrice,
                        "new_price" => $purchaseRate,
                        "status" => 1,
                    ]);


                //<============ supplier_invoice_item table ================>
                $invoiceList[] = array(
                    'status' => $this->params->status,
                    'created_at' => $this->params->inv_date,
                    'unit_price' => $value["unitPrice"],
                    'previous_purchase_rate' => $oldPrice,
                    'qty' => $value["qty"],
                    'previous_qty' => $oldQty,
                    'item_id' => $value["itemId"],
                    'supplier_invoice_id' => $supplier_invoice_id_gen,
                    'remarks' => $value["remarks"],
                    'created_by' => $this->user->id,
                );
                $amount += $value["total"];
                $qty += $value["qty"];
            }   //End loop

            $getSlipId = $this->helper->getLastID('supplier_invoice');

            //supplier_invoice'
            // $supplierInv[] = array(
            //     'supplier_id' => $this->params->supplierID,
            //     'local_invoice' => "LP-".Carbon::now()->format('ym')."-".str_pad($getSlipId,6,"0",STR_PAD_LEFT),
            //     'status' => $this->params->status,
            //     'created_at' => $this->params->inv_date,
            //     'supplier_invoice' => $this->params->inv_id,
            //     'total_amount' => $amount,
            //     'invoice_date' => $this->params->inv_date,
            //     'edit_attempt' => 0,
            //     'remarks' => $this->params->totalRemarks,
            //     'total_item_qty' => $qty,
            //     'created_by' => $this->user->id,
            // );

            // $val = $this->helper->getLastSupplierAccountBalance('account_supplier',$this->params->supplierID);
            // $val = $this->helper->getLastSupplierAccountBalance($this->params->supplierID);
            $val = DB::table('account_supplier')
                // ->select('balance')
                ->where('supplier_id', '=', $this->params->supplierID)
                ->where('status', '=', 1)
                ->orderBy('id', 'DESC')
                ->first();

            //Supplier balance adjust

            DB::table('supplier')
                ->where(["id" => $this->params->supplierID])
                ->update([
                    'balance' => ($val->balance - $amount),
                ]);

            $accountSupplier[] = array(
                'supplier_id' => $this->params->supplierID,
                'invoice_id' => $lastSupplierInvoiceID,
                'inv_type' => "purchase",
                'debit' => 0.00,
                'credit' => $amount,
                'balance' => ($val->balance - $amount),
                'note' => "Due for purchase",
                'status' => 1,
                'created_by' => $this->user->id,
            );

            $accountAsset = DB::table('account_asset')->insert([
                "invoice" => $lastSupplierInvoiceID,
                "sector" => 1,
                "inv_type" => "purchase_invoice",
                "debit" => $amount,
                "credit" => 0.00,
                "note" => "Items purchased from supplier",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $accountLiabilities = DB::table('account_liabilities')->insert([
                "sector" => 10,
                "invoice" => $lastSupplierInvoiceID,
                "inv_type" => "purchase_invoice",
                "debit" => $amount,
                "credit" => 0.00,
                "note" => "Items purchased from supplier",
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            /**DB pushing */

            /**Item Wise insertion */
            DB::table('supplier_invoice_item')->insert($invoiceList);

            $lastSupId = DB::table('supplier_invoice')
                // ->select('balance')
                ->where('supplier_id', '=', $this->params->supplierID)
                ->where('status', '=', 1)
                ->orderBy('id', 'DESC')
                ->first();

            $lastSupplierInvoiceid = $lastSupplierInvoiceID;
            $lastSupplierInvoiceid += 1;

            /**Account Supplier Insertion  */
            DB::table('account_supplier')->insert($accountSupplier);

            /**Operation Inventory_history */
            DB::table('inventory_item_history')->insert($inventory_history);

            DB::commit();

            $this->responseMessage = "Supplier Invoice Item Created Successfully!";
            $this->outputData =  $invoiceList;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Invoice Item Creation fails!";
            $this->outputData =  [];
            $this->success = false;
        }
    }


    /**Create Supplier Invoice Item inv-item.tsx ---------- page frontend*/

    /**
     * !Return Supplier Invoice Items
     */
    public function returnSupplierInvoice()
    {

        /**supplier_inv_item table */
        $invoiceList = array();
        $supplierInv = array();
        $amount = 0;
        $qty = 0;

        $lastSupplierInvoiceID = $this->helper->getLastID('supplier_invoice');

        $supplier_ID = $this->params->supplierID;
        $item_qty = $this->params->qty;
        $itemId = $this->params->itemId;
        $unitPrice = $this->params->unitPrice;
        $invoiceitemId = $this->params->invoiceitemId;
        $invoiceId = $this->params->invoiceId;
        $totalAmount = $this->params->totalAmount;
        $returnType = $this->params->returnType;
        $statusCode = $this->params->statusCode;
        $itemStatusCode = $this->params->itemStatuscode;

        $supplierID = $this->invoice->where(["supplier_id" => $supplier_ID])->first();

        #======================> Updating Inventory Table End

        $oldItem = $this->helper->getItem("qty,unit_cost", "inventory_items", $itemId);

        $stockQty = $oldItem[0]->qty;
        $stockPrice = $oldItem[0]->unit_cost;

        $returnpurchasedQty = $item_qty;
        $returnpurchaseRate = $unitPrice;

        $newRate = ($stockPrice * $stockQty) - ($returnpurchaseRate * $returnpurchasedQty);
        $newQty = $stockQty - $returnpurchasedQty;

        $newPrice = $newRate / $newQty;

        $inventory = $this->inventory
            ->where(["id" => $itemId])
            ->update([
                'qty' => DB::raw('qty -' . $item_qty),
                'unit_cost' => $newPrice,
            ]);

        /**Inventory History */
        $inventory_history[] = array(
            "inventory_item_id" => $itemId,
            "edit_attempt" => 0,
            "note" => "purchase_return",
            "reference" => $invoiceId,
            "ref_type" => "supplier_purchase_return",
            "action_by" => $this->user->id,
            "old_qty" => $stockQty,
            "affected_qty" => $returnpurchasedQty,
            "new_qty" => ($stockQty - $returnpurchasedQty),
            "old_price" => $stockPrice,
            "new_price" => $returnpurchaseRate,
            "status" => 1,
        );

        /**update supplier_invoice_item from table */

        $supplier_invoice_item = $this->invoiceItem
            ->where(["id" => $invoiceitemId])
            ->update([
                'previous_qty' => $stockQty,
                'is_returned' => 1,
                'returned_qty' => DB::raw('returned_qty +' . $item_qty),
                'return_amount' => DB::raw('return_amount +' . ($returnpurchaseRate * $returnpurchasedQty)),
                'status' => $itemStatusCode,
            ]);


        /**update invoice supplier_invoice from table */

        $invoice = $this->invoice
            ->where(["id" => $invoiceId])
            ->update([
                'is_returned' => 1,
                'total_returned_qty' => DB::raw('total_returned_qty +' . $returnpurchasedQty),
                'return_type' => $returnType,
                'return_amount' => DB::raw('return_amount +' . ($returnpurchaseRate * $returnpurchasedQty)),
                'status' => $statusCode,
            ]);

        /** update supplier from table */

        $supplier_balance = $this->supplier
            ->where(["id" => $supplier_ID])
            ->update([
                'balance' => DB::raw('balance +' . ($returnpurchaseRate * $returnpurchasedQty)),
            ]);

        /** Get updated balance for supplier */ foreach ($this->params->invoice as $key => $value) {

            $supplierID = $this->invoice->where(["supplier_id" => $value['supplierID']])->first();

            $now = Carbon::now();

            $oldItem = $this->helper->getItem("qty,unit_cost", "inventory_items", $value["itemId"]);

            $oldQty = $oldItem[0]->qty;
            $oldPrice = $oldItem[0]->unit_cost;

            $purchasedQty = $value["qty"];
            $purchaseRate = $value["unitPrice"];

            $newRate = ($oldPrice * $oldQty) + ($purchaseRate * $purchasedQty);
            $newQty = $oldQty + $purchasedQty;

            $newPrice = $newRate / $newQty;

            $inventory = $this->inventory
                ->where(["id" => $value["itemId"]])
                ->update([
                    'qty' => DB::raw('qty +' . $value["qty"]),
                    'unit_cost' => $newPrice,
                ]);

            /**Inventory History */
            $inventory_history[] = array(
                "inventory_item_id" => $value["itemId"],
                "edit_attempt" => 0,
                "note" => "purchase_invoice",
                "reference" => $lastSupplierInvoiceID,
                "ref_type" => "supplier_purchase_invoice",
                "action_by" => $this->user->id,
                "old_qty" => $oldQty,
                "affected_qty" => $purchasedQty,
                "new_qty" => ($oldQty + $purchasedQty),
                "old_price" => $oldPrice,
                "new_price" => $purchaseRate,
                "status" => 1,
            );
            /**Inventory History End */

            /**
             * !Supplier Invoice History
             */
            DB::table('supplier_invoice_history')
                ->insert([
                    "edit_attempt" => 0,
                    'reference' => $lastSupplierInvoiceID,
                    'invoice_item_id' => $value["itemId"],
                    'note' => 'Item Created into purchase invoice',
                    "action_by" => $this->user->id,
                    "old_qty" => 0,
                    "affected_qty" => $purchasedQty,
                    "new_qty" => $purchasedQty,
                    "old_price" => $oldPrice,
                    "new_price" => $purchaseRate,
                    "status" => 1,
                ]);


            //<============ supplier_invoice_item table ================>
            $invoiceList[] = array(
                'status' => $this->params->status,
                'created_at' => $this->params->inv_date,
                'unit_price' => $value["unitPrice"],
                'previous_purchase_rate' => $oldPrice,
                'qty' => $value["qty"],
                'previous_qty' => $oldQty,
                'item_id' => $value["itemId"],
                'supplier_invoice_id' => $lastSupplierInvoiceID,
                'remarks' => $value["remarks"],
                'created_by' => $this->user->id,
            );
            $amount += $value["total"];
            $qty += $value["qty"];
        }   //End loop

        $getSlipId = $this->helper->getLastID('supplier_invoice');

        //supplier_invoice'
        $supplierInv[] = array(
            'supplier_id' => $this->params->supplierID,
            'local_invoice' => "LP-" . Carbon::now()->format('ym') . "-" . str_pad($getSlipId, 6, "0", STR_PAD_LEFT),
            'status' => $this->params->status,
            'created_at' => $this->params->inv_date,
            'supplier_invoice' => $this->params->inv_id,
            'total_amount' => $amount,
            'invoice_date' => $this->params->inv_date,
            'edit_attempt' => 0,
            'remarks' => $this->params->totalRemarks,
            'total_item_qty' => $qty,
            'created_by' => $this->user->id,
        );

        $val = $this->helper->getLastSupplierAccountBalance('account_supplier', $this->params->supplierID);

        //Supplier balance adjust
        // $this->supplier
        //     ->where(["id"=>$this->params->supplierID])
        //     ->update([
        //         'balance' => DB::raw ('balance +'.$this->params->value["amount"]),
        //     ]);

        $accountSupplier[] = array(
            'supplier_id' => $this->params->supplierID,
            'invoice_id' => $lastSupplierInvoiceID,
            'inv_type' => "purchase",
            'debit' => 0.00,
            'credit' => $amount,
            'balance' => $val[0]->balance + $amount,
            'note' => "Due for purchase",
            'status' => 1,
            'created_by' => $this->user->id,
        );

        /**DB pushing */

        /**Item Wise insertion */
        DB::table('supplier_invoice_item')->insert($invoiceList);

        /**Common Invoice Insertion */
        DB::table('supplier_invoice')->insert($supplierInv);

        /**Account Supplier Insertion  */
        DB::table('account_supplier')->insert($accountSupplier);

        /**Operation Inventory_history */
        DB::table('inventory_item_history')->insert($inventory_history);

        $val = $this->helper->getLastSupplierAccountBalance('account_supplier', $this->params->supplierID);

        /** Account supplier history */

        $accountSupplier[] = array(
            'supplier_id' => $this->params->supplierID,
            'invoice_id' => $invoiceId,
            'inv_type' => "return",
            'debit' => DB::raw('debit +' . ($returnpurchaseRate * $returnpurchasedQty)),
            'credit' => 0.00,
            'balance' => $val[0]->balance + ($returnpurchaseRate * $returnpurchasedQty),
            'note' => "Purchase return",
            'status' => 1,
            'created_by' => $this->user->id,
        );

        /**DB pushing */

        /**Operation Inventory_history */

        DB::table('inventory_item_history')->insert($inventory_history);

        /**Operation Account_supplier_history */

        DB::table('account_supplier')->insert($accountSupplier);


        $this->responseMessage = "Purchase Successfully Return!";
        $this->outputData =  $inventory_history;

        $this->success = true;
    }

    /**Cancel Supplier Invoice Return ---------- page frontend*/
    public function cancelReturnSupplierInvoice()
    {

        /**supplier_inv_item table */
        $cancelinvoiceList = array();
        $cancelsupplierInv = array();
        $amount = 0;
        $qty = 0;

        $lastSupplierInvoiceID = $this->helper->getLastID('supplier_invoice');


        $supplier_ID = $this->params->supplierID;
        $return_qty = $this->params->return_qty;
        $itemId = $this->params->itemId;
        $unitPrice = $this->params->unitPrice;
        $invoiceitemId = $this->params->invoiceitemId;
        $invoiceId = $this->params->invoiceId;
        $totalRetAmmount = $this->params->totalRetAmmount;
        $itemStatusCode = $this->params->itemStatuscode;



        $supplierID = $this->invoice->where(["supplier_id" => $supplier_ID])->first();


        #======================> Updating Inventory Table End

        $oldItem = $this->helper->getItem("qty,unit_cost", "inventory_items", $itemId);



        $stockQty = $oldItem[0]->qty;
        $stockPrice = $oldItem[0]->unit_cost;

        $returnpurchasedQty = $return_qty;
        $returnpurchaseRate = $unitPrice;

        $newRate = ($stockPrice * $stockQty) - ($returnpurchaseRate * $returnpurchasedQty);
        $newQty = $stockQty - $returnpurchasedQty;

        $newPrice = $newRate / $newQty;

        $inventory = $this->inventory
            ->where(["id" => $itemId])
            ->update([
                'qty' => DB::raw('qty +' . $return_qty),
            ]);

        /**Inventory History */
        $inventory_history[] = array(
            "inventory_item_id" => $itemId,
            "edit_attempt" => 0,
            "note" => "cancel_purchase_return",
            "reference" => $invoiceId,
            "ref_type" => "cancel_supplier_purchase_return",
            "action_by" => $this->user->id,
            "old_qty" => $stockQty,
            "affected_qty" => $returnpurchasedQty,
            "new_qty" => ($stockQty + $returnpurchasedQty),
            "old_price" => $stockPrice,
            "new_price" => $returnpurchaseRate,
            "status" => 1,
        );
        /**Inventory History End */

        /**update supplier_invoice_item from table */

        $supplier_invoice_item = $this->invoiceItem
            ->where(["id" => $invoiceitemId])
            ->update([
                'previous_qty' => $stockQty,
                'is_returned' => 0,
                'returned_qty' => DB::raw('returned_qty -' . ($returnpurchaseRate * $returnpurchasedQty)),
                'return_amount' => 0,
                'status' => 1,
            ]);
        /**update supplier from table */

        $supplier_balance = $this->supplier
            ->where(["id" => $supplier_ID])
            ->update([
                'balance' => DB::raw('balance -' . ($returnpurchaseRate * $returnpurchasedQty)),
            ]);
        /**update invoice supplier_invoice from table */

        $invoice = $this->invoice
            ->where(["id" => $invoiceId])
            ->update([
                'is_returned' => 0,
                'total_returned_qty' => 0,
                'return_type' => "",
                'return_amount' => 0.00,
                'status' => 1,

            ]);
        /** Get updated balance for supplier */

        $val = $this->helper->getLastSupplierAccountBalance('account_supplier', $this->params->supplierID);

        $accountSupplier[] = array(
            'supplier_id' => $this->params->supplierID,
            'invoice_id' => $invoiceId,
            'inv_type' => "cancel_return",
            'debit' => 0.00,
            'credit' => DB::raw('credit +' . ($returnpurchaseRate * $returnpurchasedQty)),
            'balance' => $val[0]->balance - ($returnpurchaseRate * $returnpurchasedQty),
            'note' => "Cancel purchase return",
            'status' => 1,
            'created_by' => $this->user->id,

        );


        /**DB pushing */

        /**Operation Inventory_history */

        DB::table('inventory_item_history')->insert($inventory_history);

        /**Operation Account_supplier_history */

        DB::table('account_supplier')->insert($accountSupplier);

        $totalAmmount = 0;


        $this->responseMessage = "Purchase Return Successfully Cancel!";
        $this->outputData =  $inventory_history;

        $this->success = true;
    }

    /**Getting Invoice Number */
    public function getInvoiceNumber()
    {
        // $getLastId = $this->invoice->orderBy(DB::raw("CONVERT(inv_number, CHAR)"),'DESC')->get()->first();
        $getLastId = $this->invoice->orderBy('id', 'DESC')->get()->first();
        //var_dump($getLastId->id);

        if (!!!$getLastId->id) {
            $getLastId = 1;
        } else {
            $getLastId = $getLastId->id;
        }

        $this->responseMessage = "Last Invoice ID Fetched Successfully!";
        $this->outputData = $getLastId;
        // $this->outputData = $getLastId->inv_number;
        $this->success = true;
    }

    /**Creating supplier no needed XXXXXXXXXXXXXXX ======================= */
    public function createSupplierInvoice()
    {

        // if(!isset($this->params)){
        //     $this->success = false;
        //     $this->responseMessage = "Parameter missing";
        //     return;
        // }

        # =====> Validation Start
        // $this->validator->validate($request, [
        //     "item_id"=>v::notEmpty(),
        //     "qty"=>v::notEmpty(),
        //     "status"=>v::notEmpty(),
        //  ]);
        //var_dump($this->validator);
        //  if ($this->validator->failed()) {
        //     $this->success = false;
        //     $this->responseMessage = $this->validator->errors;
        //     return;
        // }
        # =====> Validation End


        $invoice = DB::table('supplier_invoice')->insert([
            'invoice_number' => $this->params->invoice_number,
            'supplier_id' => $this->params->supplier_id,
            'supplier_invoice' => $this->params->invoice_ref,
            'remarks' => $this->params->invoice_remarks,
            'total_amount' => $this->params->amount,
            'total_item_qty' => $this->params->total_item_qty,
            'isReturned' => $this->params->isReturned,
            'return_type' => $this->params->return_type,
            'return_amount' => $this->params->return_amount,
            'status' => 1,
        ]);

        $this->responseMessage = "Supplier Invoice Created Successfully!";
        $this->outputData =  $invoice;
        // $this->outputData =  $this->invoice;
        $this->success = true;
    }
    /**Creating supplier no needed XXXXXXXXXXXXXXX ======================= */

    /**Faker Test */
    public function run()
    {



        // $this->invoice = DB::table('supplier_inv')->insert([
        //     'inv_number' => $this->faker->buildingNumber,
        //     'supplier_id' => $this->faker->buildingNumber,
        //     'supplier_inv_number' => $this->faker->numberBetween($min = 1, $max = 9),
        //     'remarks' => $this->faker->text,
        //     'amount' => $this->faker->text,
        //     'total_item_qty' => $this->faker->text,
        //     'isReturned' => $this->faker->text,
        //     'return_type' => $this->faker->text,
        //     'return_amount' => $this->faker->text,

        // $array = ["type1", "type2", "type3"];
        // $randomType = Arr::random($array);

        // $this->invoice = DB::table('supplier_inv')->insert([
        //     'inv_number' => $this->faker->randomNumber,
        //     'supplier_id' => $this->faker->randomDigit,
        //     'supplier_inv_number' => $this->faker->randomDigit,
        //     'remarks' => $this->faker->text,
        //     'amount' => $this->faker->randomDigit,
        //     'total_item_qty' => $this->faker->randomDigit,
        //     'isReturned' => $this->faker->boolean,
        //     'return_type' => $randomType,
        //     'return_amount' => $this->faker->randomDigit,

        //     'status' => 1,
        // ]);

        // $this->supplier = DB::table('supplier')->insert([
        //     'name' => Str::random(10),
        //     'email' => Str::random(10).'@gmail.com',
        //     'country_id' => rand(10,1000),
        //     'type' => $randomType,
        //     'bank_acc_number' => Str::random(5).rand(100,10000),
        //     'bank_name' => Str::random(8),
        //     'tax_id' => Str::random(3).rand(1000,100000),
        //     'address' => Str::random(3).rand(1000,100000),
        //     'contact_number' => rand(1000000,100000000),
        //     'status' => 1,
        // ]);


        // generate data by accessing properties
        //$res = $this->faker->name;
        // 'Lucy Cechtelar';
        //echo $this->faker->address;
        // "426 Jordy Lodge
        // Cartwrightshire, SC 88120-6700"
        //echo $this->faker->text;
        //var_dump($this->faker);
        //die();


        // $this->responseMessage = "Ok";
        // $this->outputData =  $this->helper->additionNumber(1,2);
        // $this->success = true;

        // $invoiceList = array();
        // $inventoryList = array();

        // foreach ($this->params->invoice as $key => $value) {

        //     $inventoryList[] = $this->inventory
        //     ->where(["id"=> $value["itemId"]])
        //     ->update([
        //         'qty' => DB::raw ('qty +'.$value["qty"]),
        //     ]);

        //      //var_dump($inventoryList);

        // }

        //$getAllInventory = $this->inventory->all();
        // $oldQty = $this->helper->getItem("qty,unit_cost","inventory_items","3");

        // echo $oldQty[0]->qty;
        // dd ($oldQty);
        // echo $oldQty[0]->unit_cost;

        $this->responseMessage = "Ok";
        // $this->outputData =  $this->helper->additionNumber(1,2);
        $val = $this->helper->getLastSupplierAccountBalance('account_supplier', '23');
        $this->outputData =  $val[0]->balance;
        $this->success = true;
    }
}
