<?php

namespace  App\Controllers\Pos;

use App\Helpers\Helper;

use App\Auth\Auth;
use App\Models\Purchase\Invoice;
use App\Models\Purchase\InvoiceItem;
use App\Models\Purchase\Supplier;

use App\Models\Inventory\InventoryItem;

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
class   PosController
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
            case 'getAllItems':
                $this->getAllItems($request, $response);
                break;
            case 'addSales':
                $this->addSales($request, $response);
                break;

            case 'getAllInvoicesList':
                $this->getAllInvoicesList($request, $response);
                break;

            case 'getInvoiceDetails':
                $this->getInvoiceDetails($request, $response);
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





    public function addSales()
    {
        DB::beginTransaction();
        try {
            $allItems = $this->params->items;
            $delivaryCharge = $this->params->deliveryCharge ?? 0;
            $customer_id = $this->params->customer_id;
            $invoide_date = $this->params->inv_date;
            $discount = $this->params->discount ?? 0;
            $creationType = $this->params->craetionType;
            $discountAmount = $this->params->discountAmount ?? 0;
            $payment_status = 0;
            $total_amount = 0;

            if ($creationType === "payment") {
                $paid_amount = $this->params->payableAmount;
                $payment_status = 1;
            } else {
                $paid_amount = 0;
            }


            $lastInvoice = DB::table('sales')->select("count_id")->orderBy("id", "DESC")->first();
            if (empty($lastInvoice)) {
                $lastInvoiceNumber = 1;
            } else {
                $lastInvoiceNumber  = $lastInvoice->count_id + 1;
            }
            $sales_invoice = "SINV-" . str_pad($lastInvoiceNumber, 8, "0", STR_PAD_LEFT);

            $sales_id = DB::table("sales")->insertGetId([
                "count_id" => $lastInvoiceNumber,
                "sales_invoice" => $sales_invoice,
                "total_items" => count($allItems),
                "total_sales_price" => 0,
                "paid_amount" => $paid_amount,
                "customer_id" => $customer_id,
                "discount" => $discount,
                "discount_amount" => $discountAmount,
                "delivary_charge" => $delivaryCharge,
                "created_at" => date("Y-m-d h:m:i", strtotime($invoide_date)),
                "payment_status" => $payment_status
            ]);

            $total_sales_price = 0;
            $salesIems = [];
            $itemVariationUpdates = [];

            $itemIds = array_column($allItems, "id");
            $items = DB::table('item_variations')->whereIn("id", $itemIds)->get()->keyBy(function ($item) {
                return $item->id;
            });


            foreach ($allItems as $key => $item) {

                $sales_price = $item['unit_price'];
                $qty = $item['qty'];
                $total_price = ($sales_price * $qty);
                $total_sales_price += $total_price;
                $item_id =  $item['id'];


                $salesIems[] = [
                    "sales_id" => $sales_id,
                    "item_id" => $item_id,
                    "purchase_id" => 0,
                    "item_variation_id" => $item_id,
                    "quantity" => $qty,
                    "sales_price" => $sales_price,
                    "total_price" => $total_price,
                ];


                if (!empty($items[$item_id])) {
                    $update_stock = $items[$item_id]->stock - $qty;
                    $itemVariationUpdates[] = [
                        "id" => $item_id,
                        "stock" => $update_stock,
                    ];
                }
            }

            $total_amount = (($total_sales_price - $discountAmount) + $delivaryCharge);

            DB::table('sales')->where([
                "id" => $sales_id
            ])->update([
                "total_sales_price" => $total_sales_price,
                "total_amount" => $total_amount,
            ]);

            DB::table("sales_items")->insert($salesIems);


            // @@ fetch customer
            $customer = DB::table("customers")->where("id", $customer_id)->first();

            $newBalance = 0;

            if (!empty($customer)) {
                if (($customer->balance < 0) && ($paid_amount == 0)) {
                    $newBalance = $customer->balance -  $total_amount;
                } else  if (($customer->balance < 0) && ($paid_amount > 0)) {
                    $newBalance = $customer->balance;
                } else if (($customer->balance >= 0) && ($paid_amount == 0)) {
                    $newBalance = $customer->balance -  $total_amount;
                } else if (($customer->balance >= 0) && ($paid_amount > 0)) {
                    $newBalance = $customer->balance;
                }
            }

            DB::table("customers")->where("id", $customer->id)->update([
                "balance" => $newBalance,
            ]);

            if ($paid_amount == 0) {
                $debit = 0;
                $credit = 0;
            } else {
                $debit =  $total_amount;
                $credit = 0;
            }

            DB::table('account_customer')->insert([
                "customer_id" => $customer->id,
                "invoice_id" => $sales_id,
                "inv_type" => "Sales",
                "reference" => $sales_invoice,
                "debit" => $debit,
                "credit" => $credit,
                "balance" => $newBalance,
                "note" => "Product sales"
            ]);


            foreach ($itemVariationUpdates as $key => $item) {
                DB::table('item_variations')->where([
                    "id" => $item['id']
                ])->update([
                    "stock" => $item['stock']
                ]);
            }


            DB::commit();
            $this->outputData = [];
            $this->responseMessage = "Category fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Fail to load category: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getAllInvoicesList(Request $request, Response $response)
    {
        try {
            $pageNo = $_GET['page'];
            $perPageShow = $_GET['perPageShow'];
            $totalRow = 0;

            $filter = $this->params->filterValue;
            $status = $filter['status'];
            $search = $filter['search'];

            $salesItemQuery = DB::table('sales')
                ->select(
                    "sales.id",
                    "sales.sales_invoice",
                    "sales.total_items",
                    "sales.total_amount",
                    "sales.payment_status",
                    "sales.created_at",
                    "customers.first_name",
                    "customers.last_name"

                )
                ->join("customers", "customers.id", "=", "sales.customer_id");

            if (!empty($status)) {
                $salesItemQuery->where("sales.payment_status", (int)$status);
            }

            if (!empty($search)) {
                $salesItemQuery->where("sales.sales_invoice", "LIKE", "%" . $search . "%")
                    ->orWhere("customers.first_name", "LIKE", "%" . $search . "%")
                    ->orWhere("customers.last_name", "LIKE", "%" . $search . "%")
                    ->orWhere("customers.mobile", "LIKE", "%" . $search . "%");
            }


            $salesItems = $salesItemQuery->get();

            $totalRow = $salesItemQuery->count();

            $this->outputData = [
                $pageNo =>  $salesItems,
                "total" => $totalRow
            ];

            $this->responseMessage = "Category fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Fail to load category: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getInvoiceDetails(Request $request, Response $response)
    {
        try {

            $invoiceId = $this->params->invoice_id;

            $salesItemQuery = DB::table('sales')
                ->select(
                    "sales.id",
                    "sales.sales_invoice",
                    "sales.total_items",
                    "sales.total_amount",
                    "sales.payment_status",
                    "sales.created_at",
                    "sales.discount",
                    "sales.discount_amount",
                    "sales.delivary_charge",
                    "sales.extra_charge",
                    "customers.first_name",
                    "customers.last_name",
                    "customers.mobile",
                    "customers.address",
                    "sales_items.quantity as sales_item_qty",
                    "sales_items.sales_price",
                    "sales_items.total_price as sales_total_price",
                    "item_variations.item_name",
                    "item_variations.item_code",
                )
                ->join("customers", "customers.id", "=", "sales.customer_id")
                ->join("sales_items", "sales_items.sales_id", "=", "sales.id")
                ->join('item_variations', "item_variations.id", "=", "sales_items.item_variation_id");


            $salesItems = $salesItemQuery->where("sales.id", $invoiceId)->get();


            $this->outputData = $salesItems;
            $this->responseMessage = "Sales items fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Sales items fetch failed: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getSalesReturnInvoice(Request $request, Response $response) {}

    public function getAllItems(Request $request, Response $response)
    {
        try {
            $categoryId = $this->params->category_id ?? '';
            $search = $this->params->search ?? '';

            $itemQuery = DB::table('item_variations');

            if (!empty($categoryId)) {
                $itemQuery->where("category_id", $categoryId);
            }

            if (!empty($search)) {
                $itemQuery->where("item_name", "LIKE", "%" . $search . "%")
                    ->orWhere("item_code", "LIKE", "%" . $search . "%");
            }


            $items = $itemQuery->get();

            $this->outputData = $items;
            $this->responseMessage = "Category fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Fail to load category: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }
}
