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
            $delivaryCharge = $this->params->deliveryCharge;
            $customer_id = $this->params->customer_id;
            $invoide_date = $this->params->inv_date;
            $paid_amount = $this->params->payableAmount;
            $discount = $this->params->discount;
            $creationType = $this->params->craetionType;
            $discountAmount = $this->params->discountAmount;

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


            DB::table('sales')->where([
                "id" => $sales_id
            ])->update([
                "total_sales_price" => $total_sales_price
            ]);

            DB::table("sales_items")->insert($salesIems);


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
