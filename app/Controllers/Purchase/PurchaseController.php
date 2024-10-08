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
            case 'getAllPaymentTypes':
                $this->getAllPaymentTypes($request, $response);
                break;
            case 'getPurchaseInvoiceList':
                $this->getPurchaseInvoiceList($request, $response);
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

            // Initialize totals
            $total_amount = 0;
            $total_quantity = 0;
            $creditPrice = array();

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

                // Item variation
                $itemVariations[] = [
                    "item_id" => $item_id,
                    "unit_type_id" => $unit_type_id,
                    "sales_price" => $sales_price,
                    "stock" => $qty,
                ];

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
            $invoice = DB::table("purchase")->select("purchase.*", "supplier.name")
                ->join("supplier", "supplier.id", "=", "purchase.supplier_id")
                ->orderBy("purchase.id", "desc")
                ->get();

            if (!empty($invoice)) {
                $this->outputData = $invoice;
            }

            $this->responseMessage = "Purchase invoice fetch successfully";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Something is wrong";
            $this->outputData =  [];
            $this->success = false;
        }
    }
}
