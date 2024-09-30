<?php

namespace  App\Controllers\Settings;

use App\Auth\Auth;
use App\Models\Settings\CancelationCharge;
use App\Models\Settings\ServiceCharge;
use App\Models\Settings\TaxHead;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class ServiceController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $services;
    protected $cancelationCharge;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->services = new ServiceCharge();
        $this->cancelationCharge = new CancelationCharge();
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
            case 'getAllService':
                $this->getAllService($request, $response);
                break;
            case 'getServiceInfo':
                $this->getServiceInfo($request, $response);
                break;
            case 'editService':
                $this->editService($request, $response);
                break;
            case 'updateOrCreateCancelationCharge':
                $this->updateOrCreateCancelationCharge($request);
                break;
            case 'chargeInfo':
                $this->chargeInfo();
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

    public function getAllService()
    {
        $service = $this->services->where('id',1)->get();

        $this->responseMessage = "service fetched successfully";
        $this->outputData = $service;
        $this->success = true;
    }

    public function getServiceInfo(Request $request, Response $response)
    {
        $service = $this->services->find(1);

        if(!$service){
            $this->success = false;
            $this->responseMessage = "tax not found!";
            return;
        }

        $this->responseMessage = "service info fetched successfully";
        $this->outputData = $service;
        $this->success = true;
    }

    public function editService(Request $request, Response $response)
    {
        $service = $this->services->find(1);

        if(!$service){
            $this->success = false;
            $this->responseMessage = "Tax not found";
            return;
        }

        $this->validator->validate($request, [
           "type"=>v::notEmpty(),
           "amount"=>v::notEmpty(),
         ]);
 
         if ($this->validator->failed()) {
             $this->success = false;
             $this->responseMessage = $this->validator->errors;
             return;
         }

        $editedService = $service->update([
            "type" => $this->params->type,
            "calculation_type" => $this->params->calculation_type,
            "amount" => $this->params->amount,
            "description" => $this->params->description,
            "updated_by" => $this->user->id,
        ]);
 
         $this->responseMessage = "Service Updated successfully";
         $this->outputData = $editedService;
         $this->success = true;
    }

    public function updateOrCreateCancelationCharge(Request $request){

        if(!is_numeric($this->params->charge)){
            $this->success = false;
            $this->responseMessage = 'value must not be empty';
            return;
        }

        $cancelationCharge = CancelationCharge::first();
        if($cancelationCharge == null){
            $cancelationCharge = $this->cancelationCharge;
            $cancelationCharge->charge = $this->params->charge;
            $cancelationCharge->created_by = $this->user->id;
            $cancelationCharge->save();
        }
        else{
            $cancelationCharge->charge = $this->params->charge;
            $cancelationCharge->updated_by = $this->user->id;
            $cancelationCharge->save();
        }

        $this->responseMessage = "Cancelation Charge was added successfully";
        $this->outputData = $cancelationCharge;
        $this->success = true;
    }

    public function chargeInfo(){
        $cancelationCharge = CancelationCharge::first();
        if($cancelationCharge == null){
            $this->responseMessage = "Not found any cancelation charge";
            $this->outputData = [];
            $this->success = false;
        }

        $this->responseMessage = "success";
        $this->outputData = $cancelationCharge;
        $this->success = true;
    }
    
}
