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

            $supplier_id = $this->params->supplierID;
            $products = $this->params->invoice;
            $inv_date = $this->params->inv_date;

            // make invoice 
            $now = Carbon::now();
            $date = $now->format('ym');
            $invoice = sprintf('INV-%s000%d', $date, "1");


            $purchase = array();
            $itemVariations  = array();
            $purchase_variation = array();

            foreach ($products  as $key => $value) {
                $qty = $value['qty'];
                $unitPrice = $value['unitPrice'];
                $total_price =  $qty * $unitPrice;
                $item_id = $value['itemId'];
                $sales_price = $value['salesPrice'];
                $unit_type_id = $value['unit_type_id'];

                $purchase[] = [
                    'purchase_invoice' => $invoice,
                    'supplier_id' => $supplier_id,
                    'item_id' => $item_id,
                    'brand_id' => null,
                    'unit_type_id' => $unit_type_id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $total_price,
                ];

                $itemVariations[] = [
                    "item_id" => $item_id,
                    "unit_type_id" => $unit_type_id,
                    "sales_price" => $sales_price,
                    "stock" => $qty,
                ];
            }

            DB::table("purchase")->insert($purchase);
            $purchaseIds =  DB::getPdo()->lastInsertId();

            DB::table("item_variations")->insert($itemVariations);
            $itemVariationIds =  DB::getPdo()->lastInsertId();


            for ($i = 0; $i < count($purchase); $i++) {
                $purchase_variation[] = [
                    "purchase_id" => $purchaseIds + $i,
                    "item_variation_id" => $itemVariationIds + $i
                ];
            }


            DB::table("purchase_variations")->insert($purchase_variation);

            DB::commit();
            $this->responseMessage = "Purchase create successfull";
            $this->outputData =  [];
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Invoice Item Creation fails!";
            $this->outputData =  [];
            $this->success = false;
        }
    }
}
