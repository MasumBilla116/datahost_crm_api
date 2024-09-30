<?php

namespace  App\Controllers\Settings;

use App\Auth\Auth;
use App\Models\Settings\TaxHead;
use App\Requests\CustomRequestHandler;
use Illuminate\Database\Capsule\Manager as DB;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class TaxController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $taxes;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->taxes = new TaxHead();
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
            case 'createTax':
                $this->createTax($request, $response);
                break;
            case 'getAllTax':
                $this->getAllTax($request, $response);
                break;
            case 'getAllGroupTax':
                $this->getAllGroupTax($request, $response);
                break;
            case 'getAllParentTax':
                $this->getAllParentTax($request, $response);
                break;
            case 'getTaxInfo':
                $this->getTaxInfo($request, $response);
                break;
            case 'fetchAllTax':
                $this->fetchAllTax();
                break;
            case 'getTax':
                $this->getTax();
                break;
            case 'editTax':
                $this->editTax($request, $response);
                break;
            case 'deleteTax':
                $this->deleteTax($request, $response);
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


    public function createTax(Request $request, Response $response)
    {
        $this->validator->validate($request, [
           "name"=>v::notEmpty(),
           "tax"=>v::notEmpty(),
           "tax_type"=>v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate tax
        $current_tax = $this->taxes->where(["name"=>$this->params->name])->where('status',1)->first();
        if ($current_tax) {
            $this->success = false;
            $this->responseMessage = "Tax with the same name already exists!";
            return;
        }

        if($this->params->subtax_ids){
            $is_group = 1;
            $subtax = json_encode($this->params->subtax_ids);
        }
        else{
            $is_group = 0;
            $subtax = null;
        }

        $tax = $this->taxes->create([
           "name" => $this->params->name,
           "tax" => $this->params->tax,
           "tax_type" => $this->params->tax_type,
           "is_group" => $is_group,
           "subtax_ids" => $subtax,
           "created_by" => $this->user->id,
           "status" => 1,
        ]);

        //for many to many
        if($this->params->subtax_ids){
            $tax->TaxSubtaxes()->attach($this->params->subtax_ids);
        }

        $this->responseMessage = "New Tax created successfully";
        $this->outputData = $tax;
        $this->success = true;
    }

    public function getAllTax()
    {
        $taxes = $this->taxes->where('status',1)->where('is_group', '=', 0)->get();

        $this->responseMessage = "taxes list fetched successfully";
        $this->outputData = $taxes;
        $this->success = true;
    }

    public function getAllGroupTax()
    {
        $taxes = $this->taxes->with('TaxSubtaxes')->where('status',1)->where('is_group', '=', 1)->get();

        $this->responseMessage = "taxes list fetched successfully";
        $this->outputData = $taxes;
        $this->success = true;
    }

    public function getAllParentTax()
    {
        $taxes = $this->taxes->where('status',1)->where('is_group', '=', 0)->get();

        $this->responseMessage = "taxes list fetched successfully";
        $this->outputData = $taxes;
        $this->success = true;
    }

    public function getTaxInfo(Request $request, Response $response)
    {
        if(!isset($this->params->tax_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $tax = $this->taxes->with('TaxSubtaxes')->find($this->params->tax_id);

        // $subtax_ids = json_decode($tax->subtax_ids);

        // foreach($subtax_ids as $ids){
        //     $tax_sub = $this->taxes->find($ids);
        //     $sub_tax_arr[] = $tax_sub;
        // }

        if($tax->status == 0){
            $this->success = false;
            $this->responseMessage = "tax missing!";
            return;
        }

        if(!$tax){
            $this->success = false;
            $this->responseMessage = "tax not found!";
            return;
        }

        $this->responseMessage = "tax info fetched successfully";
        $this->outputData = $tax;
        $this->success = true;
    }

    //Fetch all taxes
    public function fetchAllTax(){
        $taxes = DB::table('tax_heads')->where('status',1)->get();

        $this->responseMessage = "taxes fetched successfully";
        $this->outputData = $taxes;
        $this->success = true;
    }

    public function getTax()
    {
        if(!isset($this->params->tax_name)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $taxInfo = DB::table('tax_heads')->where(['name'=>$this->params->tax_name,'status'=>1])->first();

        if(!$taxInfo){
            $this->success = false;
            $this->responseMessage = "tax not found!";
            return;
        }

        $this->responseMessage = "tax info fetched successfully";
        $this->outputData = $taxInfo;
        $this->success = true;
    }

    public function editTax(Request $request, Response $response)
    {
        if(!isset($this->params->tax_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $tax = $this->taxes->with('TaxSubtaxes')->where('status',1)->find($this->params->tax_id);

        if(!$tax){
            $this->success = false;
            $this->responseMessage = "Tax not found";
            return;
        }

        $this->validator->validate($request, [
           "name"=>v::notEmpty(),
           "tax"=>v::notEmpty(),
         ]);
 
         if ($this->validator->failed()) {
             $this->success = false;
             $this->responseMessage = $this->validator->errors;
             return;
         }

         //check duplicate Tax
         $current_tax = $this->taxes->where(["name"=>$this->params->name])->where('status',1)->first();
         if ($current_tax && $current_tax->id != $this->params->tax_id) {
             $this->success = false;
             $this->responseMessage = "Tax with the same name has already exists!";
             return;
         }

        if($this->params->subtax_ids){
            $is_group = 1;
            $subtax = json_encode($this->params->subtax_ids);
        }
        else{
            $is_group = 0;
            $subtax = null;
        }

         $editedTax = $tax->update([
            "name" => $this->params->name,
            "tax" => $this->params->tax,
            "tax_type" => $this->params->tax_type,
            "is_group" => $is_group,
            "subtax_ids" => $subtax,
            "updated_by" => $this->user->id,
            "status" => 1,
         ]);

        //for many to many
        $tax->TaxSubtaxes()->sync($this->params->subtax_ids);
 
         $this->responseMessage = "Tax Updated successfully";
         $this->outputData = $editedTax;
         $this->success = true;
    }

    public function deleteTax(Request $request, Response $response)
    {
        if(!isset($this->params->tax_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $tax = $this->taxes->with(['TaxSubtaxes','TaxHead'])->find($this->params->tax_id);

        if(!$tax){
            $this->success = false;
            $this->responseMessage = "Tax not found!";
            return;
        }
        
        $deletedTax = $tax->update([
            "status" => 0,
        ]);

        $tax->TaxHead()->detach();

        
        foreach($tax->TaxHead as $taxHead){
            $new_tax = 0;
            foreach($taxHead->TaxSubtaxes as $sub_tax){
                $new_tax += $sub_tax->tax;
            }
            $taxHead->update([
                "tax" => $new_tax,
            ]);
        }
 
        $this->responseMessage = "Tax Deleted successfully";
        $this->outputData = $new_tax;
        $this->success = true;
    }
    
}
