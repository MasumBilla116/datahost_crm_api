<?php

namespace  App\Controllers\Purchase;

use App\Auth\Auth;
use App\Models\Purchase\Supplier;
use App\Models\Purchase\AccountSupplier;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

/**Seeding tester */
use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;


//Helper Function 
use App\Helpers\Helper;
//use Fzaninotto\Faker\Src\Faker\Factory;
//use Fzaninotto\Src\Faker;
use Faker\Factory;
use Faker;

/**Seeding tester */
class SUPPLIER_DashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    /** Supplier ini */
    public $supplier;
    public $accountSupplier;
    private $faker;

    //Helper
    private $helper;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        //Model Instance
        $this->supplier = new Supplier();
        $this->accountSupplier = new AccountSupplier();
        $this->user = new ClientUsers();
        /*Model Instance END */
        $this->validator = new Validator();
        //Helper
         $this->helper = new Helper;
        //Helper
        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
        $this->faker = Factory::create();
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);

        switch ($action) {

            case 'supplierDashbord':
                $this->supplierDashbord();
                break;

                case 'purchaseDashbord':
                    $this->purchaseDashbord();
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


    public function supplierDashbord()
    {
        $totalSupplier = DB::table('supplier')->where('status', 1)->count('id');

        if (!$totalSupplier) {
            $this->success = false;
            $this->responseMessage = "No supplier found!";
            return;
        }

        $totalSupplierBalance = DB::table('supplier')->where('status', 1)->sum('balance');

        if (!$totalSupplierBalance) {
            $this->success = false;
            $this->responseMessage = "No Roles found!";
            return;
        }


        $totalSupplierpays = DB::table('payment_slip')
        ->join('supplier', 'supplier.id', '=', 'payment_slip.payee')
        ->select('payment_slip.*', 'supplier.name')
        ->sum('amount');

        if (!$totalSupplierpays) {
            $this->success = false;
            $this->responseMessage = "No payment found!";
            return;
        }

        $totalPayment = DB::table('payment_slip')
        ->join('supplier', 'supplier.id', '=', 'payment_slip.payee')
        ->where('supplier.status', 1)
        ->count('payment_slip.id');

        if (!$totalPayment) {
            $this->success = false;
            $this->responseMessage = "No payment found!";
            return;
        }

        $this->responseMessage = "Supplier are fetched successfully !";
        $this->outputData['totalSupplier'] = $totalSupplier;
        $this->outputData['totalSupplierBalance'] = $totalSupplierBalance;
        $this->outputData['totalSupplierpays'] = $totalSupplierpays;
        $this->outputData['totalPayment'] = $totalPayment;
        $this->success = true;
    }

    public function purchaseDashbord()
    {
        $getAllInvoice = DB::table('supplier_invoice')
        ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
        ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
        ->where('supplier_invoice.status', 1)
        ->groupBy('supplier_invoice_item.supplier_invoice_id')
        ->count('supplier_invoice.id');

        if (!$getAllInvoice) {
            $this->success = false;
            $this->responseMessage = "No getAllInvoice  found!";
            return;
        }


        $PurchaseTotalAmount = DB::table('supplier_invoice')
        ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
        ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
        ->where('supplier_invoice.status', 1)
        ->groupBy('supplier_invoice_item.supplier_invoice_id')
        ->sum('supplier_invoice.total_amount');

        if (!$PurchaseTotalAmount) {
            $this->success = false;
            $this->responseMessage = "No PurchaseTotalAmount found!";
            return;
        }


        $getAllReturnInvoice = DB::table('supplier_invoice')
        ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
        ->join('supplier_invoice_item', 'supplier_invoice_item.supplier_invoice_id', '=', 'supplier_invoice.id')
        ->where('supplier_invoice.is_returned', 1)
        ->count('supplier_invoice.id');


        if (!$getAllReturnInvoice) {
            $this->success = false;
            $this->responseMessage = "No All Return Invoice found!";
            return;
        }

        $this->responseMessage = "Purchase are fetched successfully !";
        $this->outputData['getAllInvoice'] = $getAllInvoice;
        $this->outputData['PurchaseTotalAmount'] = $PurchaseTotalAmount;
        $this->outputData['getAllReturnInvoice'] = $getAllReturnInvoice;

        $this->success = true;

    }

}