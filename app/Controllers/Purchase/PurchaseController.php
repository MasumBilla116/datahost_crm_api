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
class   PurchaseController
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

        /*Model Instance END */
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
            case 'purchase':
                $this->purchase($request, $response);
                break;
            case 'returnPurchaseProduct':
                $this->returnPurchaseProduct($request, $response);
                break;

            case 'getAllPaymentTypes':
                $this->getAllPaymentTypes($request, $response);
                break;
            case 'getPurchaseInvoiceList':
                $this->getPurchaseInvoiceList($request, $response);
                break;
            case 'deleteInvoice':
                $this->deleteInvoice($request, $response);
                break;

            case 'createPurchaseRequisition':
                $this->createPurchaseRequisition($request, $response);
                break;
            case 'fetchPurchaseRequisitionList':
                $this->fetchPurchaseRequisitionList($request, $response);
                break;
            case 'getPurchaseRequisitionDetails':
                $this->getPurchaseRequisitionDetails($request, $response);
                break;

            case 'getPurchaseRequisitionEditInfo':
                $this->getPurchaseRequisitionEditInfo($request, $response);
                break;

            case 'getPurchaseRequisitionInfo':
                $this->getPurchaseRequisitionInfo($request, $response);
                break;
            case 'getReturnInvoiceDetails':
                $this->getReturnInvoiceDetails($request, $response);
                break;
            case 'getAllPurchaseReturnInvoice':
                $this->getAllPurchaseReturnInvoice($request, $response);
                break;

            case 'deletePurchaseRequisition':
                $this->deletePurchaseRequisition($request, $response);
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




    public function purchase(Request $request, Response $response)
    {
        DB::beginTransaction();
        try {
            // Validation
            $this->validator->validate($request, [
                "supplierID" => v::notEmpty(),
                "invoice" => v::notEmpty(),
                "payment_type_id" => v::notEmpty(),
            ]);

            // Early return if validation fails
            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }

            // Set variables
            $supplier_id = $this->params->supplierID;
            $products = $this->params->invoice;
            $inv_date = date("Y-m-d", strtotime($this->params->inv_date)); // Avoid redundant date calls
            $payment_type_id = $this->params->payment_type_id;

            // Generate Invoice Number
            $now = date("ym");
            $lastPurchaseInvoice = DB::table("purchase")
                ->where("purchase_invoice", "LIKE", "PINV-" . $now . "%")
                ->orderBy("id", "DESC")
                ->first();
            $purchaseInvoiceNumber = !empty($lastPurchaseInvoice)
                ? (int)(substr($lastPurchaseInvoice->purchase_invoice, -4)) + 1
                : 1;
            $purchaseInvoice = "PINV-" . $now . str_pad($purchaseInvoiceNumber, 8, "0", STR_PAD_LEFT);

            // Declare arrays for bulk inserts
            $purchase = [];
            $itemVariations = [];
            $purchase_variation = [];
            $account_asset = [];
            $accountSupplier = [];
            $account_liabilities = [];
            $prev_purchase_variation = [];
            $items = [];
            $categories = [];
            $item_types = [];

            // Initialize totals
            $total_amount = 0;
            $total_quantity = 0;
            $creditPrice = array();

            // Fetch all relevant item variations in one query
            $item_ids = array_column($products, 'itemId');
            $unit_type_ids = array_column($products, 'unit_type_id');
            $sales_prices = array_column($products, 'salesPrice');

            // print_r($item_ids);
            $existingVariations = DB::table('item_variations')
                ->whereIn('item_id', $item_ids)
                ->whereIn('unit_type_id', $unit_type_ids)
                ->whereIn('sales_price', $sales_prices)
                ->get()
                ->keyBy(function ($item) {
                    return $item->item_id . '_' . $item->unit_type_id . '_' . $item->sales_price;
                });

            $lastItemCount = DB::table('item_variations')->select("count_id")->orderBy("id", "DESC")->first();
            $itCodeCount = $lastItemCount->count_id + 1;

            $items  = DB::table("items")->whereIn("id", $item_ids)->get()->keyBy(function ($item) {
                return $item->id;
            });

            $item_type_ids = $items->pluck("item_type_id")->all();
            $item_types = DB::table("item_types")->whereIn("id", $item_type_ids)->get()->keyBy(function ($item) {
                return $item->id;
            });


            // Loop through products and prepare insert arrays
            foreach ($products as $key => $value) {
                $qty = $value['qty'];
                $unitPrice = $value['unitPrice'];
                $total_price =  $qty * $unitPrice;
                $item_id = $value['itemId'];
                $sales_price = $value['salesPrice'];
                $unit_type_id = $value['unit_type_id'];

                // Purchase data
                $purchase[] = [
                    'purchase_invoice' => $purchaseInvoice,
                    'supplier_id' => $supplier_id,
                    'item_id' => $item_id,
                    'unit_type_id' => $unit_type_id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $total_price,
                    "purchase_date" => $inv_date,
                    "payment_type_id" => $payment_type_id,
                ];


                $key = $item_id . "_" . $unit_type_id . "_" . $sales_price;


                if (!empty($existingVariations[$key])) {
                    $existingVariation = $existingVariations[$key];
                    $update_stock = $existingVariation->stock + $qty;
                    $prev_purchase_variation[] = [
                        'item_id' => $item_id,
                        'unit_type_id' => $unit_type_id,
                        'sales_price' => $sales_price,
                        'stock' => $update_stock
                    ];
                } else {
                    $itemCode = "IT" . str_pad($itCodeCount, 8, "0", STR_PAD_LEFT);
                    // Item variation
                    $itemVariations[] = [
                        "item_name" =>  $items[$item_id]->item_name . " - " . $item_types[$items[$item_id]->item_type_id]->item_type_name,
                        "category_id" => $items[$item_id]->category_id,
                        "item_code" => $itemCode,
                        "count_id" => $itCodeCount,
                        "item_id" => $item_id,
                        "unit_type_id" => $unit_type_id,
                        "sales_price" => $sales_price,
                        "stock" => $qty,
                    ];
                    $itCodeCount++;
                }


                // Account transactions
                $account_asset[] = [
                    "sector" => 1,
                    "inv_type" => "purchase_invoice",
                    "debit" => $total_price,
                    "credit" => 0.00,
                    "note" => "Items purchased from supplier",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ];

                $accountSupplier[] = [
                    'supplier_id' => $supplier_id,
                    'inv_type' => "purchase",
                    'debit' => 0.00,
                    'credit' => $total_price,
                    'note' => "Due for purchase",
                    'status' => 1,
                    'created_by' => $this->user->id,
                ];

                $account_liabilities[] = [
                    "sector" => 10,
                    "inv_type" => "purchase_invoice",
                    "debit" => $total_price,
                    "credit" => 0.00,
                    "note" => "Items purchased from supplier",
                    "created_by" => $this->user->id,
                    "status" => 1,
                ];

                // Calculate total amount and quantity
                $creditPrice[] = $total_price;
                $total_amount += $total_price;
                $total_quantity += $qty;
            }

            // Update supplier balance
            DB::table('supplier')
                ->where("id", $supplier_id)
                ->decrement('balance', $total_amount);

            // Insert all data in bulk
            DB::table("purchase")->insert($purchase);
            $purchaseIds = DB::getPdo()->lastInsertId();

            DB::table("item_variations")->insert($itemVariations);
            $itemVariationIds = DB::getPdo()->lastInsertId();

            // update previous purchase item variations
            foreach ($prev_purchase_variation as $variation) {
                DB::table("item_variations")
                    ->where([
                        'item_id' => $variation['item_id'],
                        'unit_type_id' => $variation['unit_type_id'],
                        'sales_price' => $variation['sales_price']
                    ])
                    ->update(['stock' => $variation['stock']]);
            }

            // Link purchase and item variation
            foreach ($purchase as $i => $p) {
                $purchase_variation[] = [
                    "purchase_id" => $purchaseIds + $i,
                    "item_variation_id" => $itemVariationIds + $i
                ];

                $account_asset[$i]["invoice"] = $purchaseIds + $i;
                $accountSupplier[$i]['invoice_id'] = $purchaseIds + $i;
                $account_liabilities[$i]['invoice'] = $purchaseIds + $i;
                $accountSupplier[$i]['balance'] = $total_amount - $creditPrice[$i];
            }

            // Insert purchase variations and account entries
            DB::table("purchase_variations")->insert($purchase_variation);
            DB::table('account_asset')->insert($account_asset);
            DB::table('account_supplier')->insert($accountSupplier);
            DB::table('account_liabilities')->insert($account_liabilities);

            // Commit the transaction
            DB::commit();
            $this->responseMessage = "Purchase created successfully";
            $this->outputData = [];
            $this->success = true;
        } catch (\Exception $e) {
            // Rollback on failure
            DB::rollback();
            $this->responseMessage = "Invoice item creation failed: " . $e->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }



    public function returnPurchaseProduct(Request $request, Response $response)
    {
        DB::beginTransaction();
        try {
            $returnItems = $this->params->return_items;
            $purchaseReturnItems = [];
            $manageStocks = [];
            $purchaseIds = [];
            $itemIds = [];
            $total_items = 0;
            $total_amount = 0;



            // Generate Invoice Number
            $now = date("ym");
            $lastPurchaseInvoice = DB::table("purchase_return_history")
                ->where("purchase_return_invoice", "LIKE", "PRINV-" . $now . "%")
                ->orderBy("id", "DESC")
                ->first();
            $purchaseInvoiceNumber = !empty($lastPurchaseInvoice)
                ? (int)(substr($lastPurchaseInvoice->purchase_return_invoice, -4)) + 1
                : 1;
            $purchaseReturnInvoice = "PRINV-" . $now . str_pad($purchaseInvoiceNumber, 8, "0", STR_PAD_LEFT);

            $purchaseReturnId = DB::table("purchase_return_history")->insertGetId([
                "purchase_return_invoice" => $purchaseReturnInvoice,
                "quantity" => 0,
                "total_price" => 0,
            ]);

            // Prepare data for bulk operations and track necessary IDs
            foreach ($returnItems as $item) {
                $purchase_id = $item['purchase_id'];
                $item_id = $item['id'];
                $qty = $item['return_qty'];
                $unit_price = $item['item_unitPrice'];
                $total_unit_price = $qty * $unit_price;

                // Collect purchase return data
                $purchaseReturnItems[] = [
                    "purchase_return_id" => $purchaseReturnId,
                    "purchase_id" => $purchase_id,
                    // "item_variation_id" => $item_id,
                    "quantity" => $qty,
                    "unit_price" => $unit_price,
                    "total_price" => $total_unit_price
                ];

                // Collect stock management data
                $manageStocks[] = [
                    "item_id" => $item_id,
                    "qty" => $qty,
                ];

                // Track unique IDs to minimize DB queries
                $purchaseIds[] = $purchase_id;
                $itemIds[] = $item_id;

                $total_items += $qty;
                $total_amount += $total_unit_price;
            }

            DB::table("purchase_return_history")->where([
                "purchase_return_invoice" => $purchaseReturnInvoice,
                "id" => $purchaseReturnId,
            ])->update([
                "quantity" => $total_items,
                "total_price" => $total_amount,
            ]);


            // Insert all purchase return items in one query
            DB::table("purchase_return_items")->insert($purchaseReturnItems);

            // Fetch all related purchase and item data in one query
            $purchases = DB::table("purchase")
                ->whereIn("id", $purchaseIds)
                ->get()
                ->keyBy("id"); // Use id as the key for quick lookup

            $items = DB::table("item_variations")
                ->whereIn("id", $itemIds)
                ->get()
                ->keyBy("id");

            // Prepare bulk update data for purchases and stocks
            $purchaseUpdates = [];
            $stockUpdates = [];

            foreach ($purchaseReturnItems as $row) {
                if (isset($purchases[$row["purchase_id"]])) {
                    $purchase = $purchases[$row["purchase_id"]];
                    $newQty = $purchase->quantity - $row['quantity'];
                    $newTotalPrice = $purchase->total_price - $row['total_price'];

                    $purchaseUpdates[] = [
                        "id" => $row["purchase_id"],
                        "quantity" => $newQty,
                        "total_price" => $newTotalPrice,
                    ];
                }
            }

            foreach ($manageStocks as $row) {
                if (isset($items[$row["item_id"]])) {
                    $item = $items[$row["item_id"]];
                    $newQty = $item->stock - $row['qty'];

                    $stockUpdates[] = [
                        "id" => $row["item_id"],
                        "stock" => $newQty,
                    ];
                }
            }

            // Bulk update purchases
            foreach ($purchaseUpdates as $update) {
                DB::table("purchase")->where("id", $update['id'])->update([
                    "quantity" => $update['quantity'],
                    "total_price" => $update['total_price'],
                ]);
            }

            // Bulk update stocks
            foreach ($stockUpdates as $update) {
                DB::table("item_variations")->where("id", $update['id'])->update([
                    "stock" => $update['stock'],
                ]);
            }

            DB::commit();
            $this->responseMessage = "Purchase return successfully";
            $this->outputData = $purchaseReturnId;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollback();
            $this->responseMessage = "Purchase return failed: " . $e->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function getAllPurchaseReturnInvoice(Request $request, Response $response)
    {
        DB::beginTransaction();
        try {

            $invoice = DB::table("purchase_return_history")
                ->orderBy("id", "DESC")
                ->get();

            DB::commit();
            $this->responseMessage = "Purchase return successfully";
            $this->outputData = $invoice;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollback();
            $this->responseMessage = "Purchase return failed: " . $e->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getReturnInvoiceDetails(Request $request, Response $response)
    {
        DB::beginTransaction();
        try {
            $invoice_id = $this->params->return_invoice_id;

            $invoice = DB::table("purchase_return_history")
                ->select(
                    "purchase_return_history.purchase_return_invoice",
                    "purchase_return_history.total_price as return_total_price",
                    "purchase_return_history.quantity as total_return_quantity",
                    "purchase_return_history.created_at",
                    "purchase_return_items.quantity as return_quantity",
                    "purchase_return_items.unit_price",
                    "purchase_return_items.total_price",
                    "items.item_name",
                    "item_types.item_type_name",
                    "supplier.name as supplier_name",
                    "supplier.address as supplier_address",
                    "supplier.contact_number as supplier_contact_number",
                )

                ->join("purchase_return_items", "purchase_return_items.purchase_return_id", "=", "purchase_return_history.id")
                ->join("purchase", "purchase.id", "=", "purchase_return_items.purchase_id")
                ->join("supplier", "supplier.id", "=", "purchase.supplier_id")
                ->join("items", "items.id", "=", "purchase.item_id")
                ->join("item_types", "item_types.id", "=", "items.item_type_id")
                ->where("purchase_return_history.id", $invoice_id)
                ->get();

            DB::commit();
            $this->responseMessage = "Purchase return successfully";
            $this->outputData = $invoice;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollback();
            $this->responseMessage = "Purchase return failed: " . $e->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function getAllPaymentTypes(Request $request, Response $response)
    {
        try {
            $allPaymentTypes = DB::table("payment_types")->get();

            if (!empty($allPaymentTypes)) {
                $this->outputData = $allPaymentTypes;
            }

            $this->responseMessage = "Payment methods fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Something is wrong";
            $this->outputData =  [];
            $this->success = false;
        }
    }



    public function getPurchaseInvoiceList()
    {
        try {

            $pageNo = $_GET['page'];
            $perPageShow = $_GET['perPageShow'];
            $totalRow = 0;
            $filter = $this->params->filterValue;


            $invoiceQuery = DB::table("purchase")->select("purchase.*", "supplier.name")
                ->join("supplier", "supplier.id", "=", "purchase.supplier_id")
                ->where("purchase.status", 1);


            if ($filter['status'] === "deleted") {
                $invoiceQuery->where("purchase.status", 0);
            } else if ($filter['status'] === "daily") {
                $invoiceQuery->whereDate("purchase.purchase_date", date("Y-m-d"));
            } else if ($filter['status'] === "weekly") {
                $invoiceQuery->whereBetween("purchase.purchase_date", [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } else if ($filter['status'] === "monthly") {
                $invoiceQuery->whereYear("purchase.purchase_date", date("Y"))
                    ->whereMonth("purchase.purchase_date", date("m"));
            } else if ($filter['status'] === "yearly") {
                $invoiceQuery->whereYear("purchase.purchase_date", date("Y"));
            }


            if (!empty($filter['search'])) {
                $search = $filter['search'];
                $invoiceQuery->where(function ($query) use ($search) {
                    $query->orWhere("supplier.name", "LIKE", "%" . $search . "%")
                        ->orWhere("purchase.purchase_invoice", "LIKE", "%" . $search . "%");
                });
            }


            $invoice = $invoiceQuery->orderBy("purchase.id", "desc")
                ->offset(($pageNo - 1) * $perPageShow)
                ->limit($perPageShow)
                ->get();


            $totalRow = $invoiceQuery->count();
            $this->outputData = [
                $pageNo => $invoice,
                "total" => $totalRow
            ];

            $this->responseMessage = "Purchase invoice fetch successfully";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Something is wrong " . $th->getMessage();
            $this->outputData =  [];
            $this->success = false;
        }
    }



    public function deleteInvoice()
    {
        try {
            $purchaseId = $this->params->id;
            if (empty($purchaseId)) {
                $this->responseMessage = "Missing your items";
                $this->outputData =  [];
                $this->success = false;
                return;
            }

            DB::table("purchases")->where("id", $purchaseId)->update(["status" => 0]);

            $this->responseMessage = "Purchase invoice deleted successfull";
            $this->outputData =  [];
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Something is wrong";
            $this->outputData =  [];
            $this->success = false;
        }
    }


    // @@ purchase requisition
    public function createPurchaseRequisition(Request $request, Response $response)
    {

        DB::beginTransaction();
        try {

            $this->validator->validate($request, [
                "invoice" => v::notEmpty(),
                "requisition_title" => v::notEmpty()
            ]);

            // Early return if validation fails
            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }


            $requisition_title = $this->params->requisition_title;
            $items = $this->params->invoice;
            $totalItem  = count($items);
            $request_date = $this->params->request_date;
            $approved_date = $this->params->approved_date;
            $status = $this->params->requisition_status;
            $remarks = $this->params->remarks;
            $purchase_requisition_id = $this->params->purchase_requisition_id;

            if (strtolower($status) === "approve") {
                $approved_date = $request_date;
            }

            if (!empty($purchase_requisition_id)) {
                $requisitionId = $purchase_requisition_id;

                DB::table("purchase_requisitions")
                    ->where([
                        "id" => $purchase_requisition_id
                    ])
                    ->update([
                        "requisition_title" => $requisition_title,
                        "quantity" =>  $totalItem,
                        "request_date" => $request_date,
                        "approved_date" => $approved_date,
                        "status" => $status,
                        'remark' => $remarks
                    ]);

                DB::table('purchase_requisition_items')->where([
                    "purchase_requisition_id" => $purchase_requisition_id
                ])->delete();
            } else {
                $requisitionId = DB::table("purchase_requisitions")->insertGetId([
                    "requisition_title" => $requisition_title,
                    "quantity" =>  $totalItem,
                    "request_date" => $request_date,
                    "approved_date" => $approved_date,
                    "status" => $status,
                    'remark' => $remarks
                ]);
            }



            $requisitionItems = array();
            foreach ($items as $key => $item) {
                $requisitionItems[] = [
                    "purchase_requisition_id" => $requisitionId,
                    "item_id" => $item['itemId'],
                    "quantity" => $item['qty'],
                ];
            }

            DB::table("purchase_requisition_items")->insert($requisitionItems);
            // Commit the transaction
            DB::commit();
            $this->responseMessage = "Purchase created successfully";
            $this->outputData = [];
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Invoice item creation failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function fetchPurchaseRequisitionList(Request $request, Response $response)
    {
        try {
            $pageNo = $_GET['page'];
            $perPageShow = $_GET['perPageShow'];
            $totalRow = 0;
            $filter = $this->params->filterValue;


            $requisitionQuery = DB::table("purchase_requisitions");


            if ($filter['status'] === "Approve") {
                $requisitionQuery->where("status", "Approve");
            } else if ($filter['status'] === "Cancel") {
                $requisitionQuery->where("status", "Cancel");
            } else {
                $requisitionQuery->where("status", "Pending");
            }


            if (!empty($filter['search'])) {
                $search = $filter['search'];
                $requisitionQuery->where(function ($query) use ($search) {
                    $query->orWhere("title", "LIKE", "%" . $search . "%")
                        ->orWhere("request_date", "LIKE", "%" . $search . "%")
                        ->orWhere("approved_date", "LIKE", "%" . $search . "%");
                });
            }


            $requisition = $requisitionQuery->orderBy("id", "desc")
                ->offset(($pageNo - 1) * $perPageShow)
                ->limit($perPageShow)
                ->get();


            $totalRow = $requisitionQuery->count();
            $this->outputData = [
                $pageNo => $requisition,
                "total" => $totalRow
            ];
            $this->responseMessage = "Purchase Requisition fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Purchase Requisition fetch failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function getPurchaseRequisitionDetails(Request $request, Response $response)
    {
        try {
            $requisition_id = $this->params->purchase_requisition_id;

            if (empty($requisition_id)) {
                $this->outputData = [];
                $this->responseMessage = "Paramiter is missiong";
                $this->success = false;
            }

            $requisitionDetails = DB::table("purchase_requisitions")->select(
                "purchase_requisitions.*",
                "items.item_name",
                "purchase_requisition_items.quantity"
            )
                ->join("purchase_requisition_items", "purchase_requisition_items.purchase_requisition_id", "=", "purchase_requisitions.id")
                ->where('purchase_requisitions.id', $requisition_id)
                ->join("items", "items.id", "=", "purchase_requisition_items.item_id")
                ->get();



            $this->outputData = $requisitionDetails;
            $this->responseMessage = "Purchase Requisition fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Purchase Requisition fetch failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }



    public function getPurchaseRequisitionEditInfo(Request $request, Response $response)
    {
        try {
            $purchaseRequisitionId = $this->params->requisition_id;

            if (empty($purchaseRequisitionId)) {
                $this->responseMessage = "Purchase Requisition params is missing ";
                $this->outputData = [];
                $this->success = false;
                return;
            }

            $purchaseRequisitionInfo = DB::table("purchase_requisitions")
                ->select(
                    "purchase_requisitions.id as purchase_requisition_id",
                    "purchase_requisitions.requisition_title",
                    "purchase_requisitions.quantity as total_quantity",
                    "purchase_requisitions.request_date",
                    "purchase_requisitions.approved_date",
                    "purchase_requisitions.remark",
                    "purchase_requisitions.status",
                    "purchase_requisition_items.quantity",
                    "purchase_requisition_items.item_id",
                    "items.item_name",
                    "item_types.item_type_name"
                )
                ->join("purchase_requisition_items", "purchase_requisition_items.purchase_requisition_id", "=", "purchase_requisitions.id")
                ->join("items", "items.id", "=", "purchase_requisition_items.item_id")
                ->join("item_types", "item_types.id", "=", "items.item_type_id")
                ->where("purchase_requisitions.id", $purchaseRequisitionId)
                ->get();


            $this->outputData = $purchaseRequisitionInfo;
            $this->responseMessage = "Purchase Requisition fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Purchase Requisition fetch failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function getPurchaseRequisitionInfo(Request $request, Response $response)
    {
        try {

            $purchaseRequisitionInfo = DB::table("purchase_requisitions")
                ->select(
                    "purchase_requisitions.id as purchase_requisition_id",
                    "purchase_requisitions.requisition_title",
                    "purchase_requisitions.quantity as total_quantity",
                    "purchase_requisitions.request_date",
                    "purchase_requisitions.approved_date",
                    "purchase_requisitions.remark",
                    "purchase_requisitions.status",
                    "purchase_requisition_items.quantity",
                    "purchase_requisition_items.item_id",
                    "items.item_name",
                    "items.unit_type_id",
                    "item_types.item_type_name"
                )
                ->join("purchase_requisition_items", "purchase_requisition_items.purchase_requisition_id", "=", "purchase_requisitions.id")
                ->join("items", "items.id", "=", "purchase_requisition_items.item_id")
                ->join("item_types", "item_types.id", "=", "items.item_type_id")
                // ->where([
                //     "purchase_requisitions.status" => "Approve"
                // ])
                ->groupBy("purchase_requisition_items.purchase_requisition_id")
                ->get();


            $this->outputData = $purchaseRequisitionInfo;
            $this->responseMessage = "Purchase Requisition fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Purchase Requisition fetch failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function deletePurchaseRequisition(Request $request, Response $response)
    {
        try {
            $purchaseRequisitionId = $this->params->id;

            DB::table("purchase_requisitions")->where("id", $purchaseRequisitionId)->delete();
            DB::table("purchase_requisition_items")->where("purchase_requisition_id", $purchaseRequisitionId)->delete();


            $this->outputData = [];
            $this->responseMessage = "Purchase Requisition deleted successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Purchase Requisition deleted failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }
}
