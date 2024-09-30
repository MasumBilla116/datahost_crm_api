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
class SupplierController
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
            case 'test':
                $this->run();
                break;
            case 'createSupplier':
                $this->createSupplier($request);
                break;
            case 'getAllSupplier':
                $this->getAllSupplier();
                break;
            case 'getAllSupplierList':
                $this->getAllSupplierList();
                break;
            case 'getSupplierByID':
                $this->getSupplierByID();
                break;
            case 'getSupplierDetailsByID':
                $this->getSupplierDetailsById();
                break;
            case 'updateSupplier':
                $this->updateSupplier();
                break;
            case 'delete':
                $this->deleteSupplier();
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

    /**Getting Supplier Details By Id */
    public function getSupplierDetailsById()
    {
        $supplierName = DB::table('supplier_invoice')
            ->join('supplier', 'supplier.id', '=', 'supplier_invoice.supplier_id')
            ->select('supplier.name as supplier_name')
            ->where(['supplier_invoice.id' => $this->params->id])
            // ->toSql();
            ->get();
        $this->responseMessage = "Supplier Fetched successfully!!";
        $this->outputData = $supplierName;
        $this->success = true;
    }

    /**Delete supplier */
    public function deleteSupplier()
    {
        if (isset($this->params->id)) {
            $supplier = $this->supplier
                ->where(["id" => $this->params->id])
                ->first(); // Fetch the supplier record
    
            if ($supplier) {
                if ($supplier->status == 0) {
                    $deleted = $this->supplier
                        ->where(["id" => $this->params->id])
                        ->delete();
    
                    if ($deleted) {
                        $this->responseMessage = "Supplier deleted successfully!";
                        $this->success = true;
                    } else {
                        $this->responseMessage = "Error deleting supplier!";
                        $this->success = false;
                    }
                } else {
                    // If status is not 0, update status to 0
                    $updated = $this->supplier
                        ->where(["id" => $this->params->id])
                        ->update(['status' => 0]);
    
                    if ($updated) {
                        $this->responseMessage = "Supplier status updated to deleted!";
                        $this->success = true;
                    } else {
                        $this->responseMessage = "Error updating supplier status!";
                        $this->success = false;
                    }
                }
            } else {
                $this->responseMessage = "Supplier not found!";
                $this->success = false;
            }
        } else {
            $this->responseMessage = "Error: 'id' not found in parameters!";
            $this->success = false;
        }
    }
    
    

    /**Updating Supplier */
    public function updateSupplier()
    {
        $supplier = $this->supplier
            ->where(["id" => $this->params->id])
            ->update([
                'name' => $this->params->name,
                'country_name' => $this->params->country_name,
                'type' => $this->params->type,
                'bank_acc_number' => $this->params->default_bank_account,
                'bank_name' =>  $this->params->bank_name,
                // 'tax_id'=> $this->params->tax_id,
                'address' => $this->params->address,
                'opening_balance' => $this->params->opening_balance,
                'contact_number' => $this->params->contact_number,
                'description' => $this->params->description,
                'sector_head' => $this->params->sector_head,
                'sector_id' => $this->params->sector_id,

            ]);


        $this->responseMessage = "Supplier Fetched Successfully!";
        $this->outputData = $supplier;
        $this->success = true;
    }

    /**Getting supplier by ID */

    public function getSupplierByID()
    {
        $supplier = $this->supplier
            ->select("supplier.*", 'supplier_invoice.supplier_id', DB::raw('SUM(supplier_invoice.total_amount) as amount'))
            ->join('supplier_invoice', 'supplier_invoice.supplier_id', '=', 'supplier.id')
            ->where(["supplier.id" => $this->params->id])
            ->groupBy('supplier_invoice.supplier_id')
            ->get();
    
        if ($supplier->isEmpty()) {
            $supplier = $this->supplier
                ->select('*')
                ->where(["supplier.id" => $this->params->id])
                ->get();
            // No need to set amount to 0 here, as it's not found in supplier_invoice
        }
    
        if ($supplier->isEmpty()) {
            $this->success = false; // Supplier not found
            $this->responseMessage = "Supplier not found!";
            return;
        }
    
        $this->success = true;
        $this->responseMessage = "Supplier Data fetched!";
        $this->outputData = $supplier;
    
        $this->responseMessage = "Supplier Fetched Successfully!";
    }
    

    /**Getting Supplier List */

    public function getAllSupplier()
    {


        $getAllSupplier =  Supplier::select('supplier.id', 'supplier.name', 'supplier.balance', 'supplier.status', 'supplier.contact_number')
            ->selectRaw('(SELECT COUNT(supplier_invoice.id) FROM supplier_invoice WHERE supplier_invoice.supplier_id = supplier.id) AS total_invoice')
            ->selectRaw('(SELECT org_users.name FROM org_users WHERE org_users.id = supplier.created_by) AS createdBy')
            ->where('supplier.status', 1)
            ->get();


        if (!$getAllSupplier) {
            $this->success = false;
            $this->responseMessage = "Supplier Data not found!";
            return;
        }
        $this->responseMessage = "Supplier Data fetched Successfully!";
        $this->outputData = $getAllSupplier;
        $this->success = true;
    }


    public function getAllSupplierList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query =  Supplier::select('supplier.id', 'supplier.name', 'supplier.balance', 'supplier.status', 'supplier.contact_number')
            ->selectRaw('(SELECT COUNT(supplier_invoice.id) FROM supplier_invoice WHERE supplier_invoice.supplier_id = supplier.id) AS total_invoice')
            ->selectRaw('(SELECT org_users.name FROM org_users WHERE org_users.id = supplier.created_by) AS createdBy');
        // ->where('supplier.status', 1)
        // ->get()

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('supplier.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('supplier.status', '=', 0);
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('supplier.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier.contact_number', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier.email', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('supplier.bank_acc_number', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_supplier =  $query->orderBy('supplier.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }
        $this->responseMessage = "Supplier Data fetched Successfully!";
        $this->outputData = [
            $pageNo => $all_supplier,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    /**Creating supplier */
    public function createSupplier(Request $request)
    {
        if (!isset($this->params)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        # =====> Validation Start
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "status" => v::notEmpty(),
            "sector_head" => v::notEmpty(),
            "sector_id" => v::notEmpty(),
        ]);
        //var_dump($this->validator);
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        # =====> Validation End


        $supplier = $this->supplier = $this->supplier->insertGetId([
            "name" => $this->params->name,
            "country_name" => $this->params->country_name,
            "type" => $this->params->type,
            "bank_acc_number" => $this->params->default_bank_account,
            "bank_name" => $this->params->bank_name,
            "tax_id" => $this->params->tax_id,
            "address" => $this->params->address,
            "contact_number" => $this->params->contact_number,
            "status" => $this->params->status,
            "opening_balance" => $this->params->opening_balance,
            'sector_head' => $this->params->sector_head,
            'sector_id' => $this->params->sector_id,
            "balance" => $this->params->opening_balance,
        ]);

        /**Getting Supplier ID supplier table */
        // $localInvoice = $local_invoice->toArray();
        // $localInvoice = $localInvoice[0]['local_invoice'];
        $getLastID = $this->helper->getLastID('supplier');
        // dd($idr);
        // var_dump($idr);
        // echo($idr);
        // $this->responseMessage = "Supplier Created Successfully!";
        // $this->outputData = $idr;
        // $this->success = true;
        // die();
        /**Getting Supplier ID supplier table */
        // echo $idr;
        // die();


        $this->accountSupplier = $this->accountSupplier
            ->insert([
                "supplier_id" => $supplier,
                "invoice_id" => $this->params->invoice_id,
                "inv_type" => "opening_balance",
                "note" => "Supplier created with opening balance",
                "debit" => $this->params->opening_balance,
                "credit" => 0.00,
                "balance" => $this->params->opening_balance,
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Supplier Created Successfully!";
        $this->outputData =  $this->params;
        // $this->outputData =  $idr;
        $this->success = true;
    }


    /**Faker Test */

    public function run()
    {
        $array = ["Regular", "Temporary", "Company"];
        $randomType = Arr::random($array);

        $this->supplier = DB::table('supplier')->insert([
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'country_name' => $this->faker->country,
            'type' => $randomType,
            'bank_acc_number' => $this->faker->bankAccountNumber,
            'bank_name' => Str::random(8),
            'tax_id' => $this->faker->numberBetween($min = 100000, $max = 900000),
            'address' => $this->faker->address,
            'contact_number' => $this->faker->phoneNumber,
            'status' => 1,
        ]);


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

        $this->responseMessage = "Ok";
        $this->outputData =  $this->supplier;
        $this->success = true;
    }
}
