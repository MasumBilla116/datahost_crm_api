<?php

namespace  App\Controllers\Purchase;

use App\Auth\Auth;
use App\Models\Purchase\Quotation;

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

//use Fzaninotto\Faker\Src\Faker\Factory;
//use Fzaninotto\Src\Faker;
use Faker\Factory;
use Faker;

/**Seeding tester */
class QuotationController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    /** Supplier ini */
    public $quotation;
    private $faker;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        //Model Instance
        $this->quotation = new Quotation();
        /*Model Instance END */
        $this->validator = new Validator();

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
            case 'createQuotation':
                $this->createQuotation($request);
                break;
            case 'getAllQuotation':
                $this->getAllQuotation();
                break;
            case 'getSupplierByID':
                $this->getQuotationByID();
                break;
            case 'updateSupplier':
                $this->updateQuotation();
                break;
            case 'delete':
                $this->deleteQuotation();
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

    /**Delete supplier */
    public function deleteQuotation(){
        $supplier = $this->supplier->where('id', $this->params->id)->delete();
        // $department = $this->departments->where(["id"=>$this->params->department_id])->delete();

        if(!$supplier){
            $this->success = false;
            $this->responseMessage = "Couldn't remove successfully, Please contact with Admin.";
            return;
        }

        $this->responseMessage = "Supplier removed successfully!!";
        //$this->outputData = $this->params->department;
        //$this->outputData['creator'] = $department->creator;
        $this->success = true;
    }

    /**Updating Supplier */
    public function updateQuotation(){
        $supplier = $this->supplier
        ->where(["id"=>$this->params->id])
        ->update([
            'name'=> $this->params->name,
            'country_name'=> $this->params->country_name,
            'type'=> $this->params->type,
            'bank_acc_number'=> $this->params->default_bank_account,
            'bank_name' =>  $this->params->bank_name,
            'tax_id'=> $this->params->tax_id,
            'address'=> $this->params->address,
            'contact_number'=> $this->params->contact_number,
            'description'=> $this->params->description,
            'status'=> $this->params->status
        ]);


        $this->responseMessage = "Supplier Fetched Successfully!";
        $this->outputData = $supplier;
        $this->success = true;
    }

    /**Getting supplier by ID */

    public function getQuotationID() {
        $supplier = $this->supplier->select("*")
        ->where(["id"=>$this->params->id])->get();
        if(!$supplier){
            $this->success = false;
            $this->responseMessage = "Supplier not found!";
            return;
        }

        // $this->validator->validate($request, [
        //     "role_id"=>v::notEmpty(),
        // ]);

        $this->responseMessage = "Supplier Fetched Successfully!";
        $this->outputData = $supplier;
        $this->success = true;
    }

    /**Getting Supplier List */

    public function getAllQuotation(){
        $getAllSupplier = $this->supplier->all();
        if(!$getAllSupplier){
            $this->success = false;
            $this->responseMessage = "Supplier Data not found!";
            return;
        }
        $this->responseMessage = "Supplier Data fetched Successfully!";
        $this->outputData = $getAllSupplier;
        $this->success = true;
    }

    /**Creating supplier */
    public function createQuotation(Request $request){

        if(!isset($this->params)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        # =====> Validation Start
        $this->validator->validate($request, [
            "item_id"=>v::notEmpty(),
            "qty"=>v::notEmpty(),
            "status"=>v::notEmpty(),
         ]);
         //var_dump($this->validator);
        //  if ($this->validator->failed()) {
        //     $this->success = false;
        //     $this->responseMessage = $this->validator->errors;
        //     return;
        // }
        # =====> Validation End


       $this->quotation = DB::table('quotation')->insert([
            'item_id' => $this->faker->buildingNumber,
            'org_user_id' => $this->faker->buildingNumber,
            'qty' => $this->faker->numberBetween($min = 1, $max = 9),
            'remarks' => $this->faker->text,
            'status' => 1,
        ]);

        $this->responseMessage = "Quotation Created Successfully!";
        $this->outputData =  $this->quotation;
        $this->success = true;
    }


    /**Faker Test */

    public function run (){
        $this->quotation = DB::table('quotation')->insert([
            'item_id' => $this->faker->buildingNumber,
            'org_user_id' => $this->faker->buildingNumber,
            'qty' => $this->faker->numberBetween($min = 1, $max = 9),
            'remarks' => $this->faker->text,
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