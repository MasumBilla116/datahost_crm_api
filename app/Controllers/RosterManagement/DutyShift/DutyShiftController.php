<?php

namespace  App\Controllers\RosterManagement\DutyShift;

use App\Auth\Auth;
use App\Models\RosterManagement\DutyShift;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class DutyShiftController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->dutyShift = new DutyShift();
        $this->user = new ClientUsers();
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
         
            case 'allDutyShifts':
                $this->allDutyShifts();
                break;          
            case 'createDutyShift':
                $this->createDutyShift($request);
                break;          
            case 'dutyShiftInfo':
                $this->dutyShiftInfo();
                break;          
            case 'updateDutyShift':
                $this->updateDutyShift($request);
                break;          
            case 'deleteDutyShift':
                $this->deleteDutyShift();
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

    public function createDutyShift(Request $request){
        $this->validator->validate($request, [
            "name"=>v::notEmpty(),
            "start_time"=>v::notEmpty(),
            "end_time"=>v::notEmpty(),
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        unset($this->params->action); 
        $data = (array) $this->params;
        $data["created_by"] = $this->user->id;
        $dutyShift =  $this->dutyShift->create($data); 
    
        $this->responseMessage = "Duty Shift has been created successfully";
        $this->outputData = $dutyShift;
        $this->success = true;
    }
 
    public function dutyShiftInfo(){

        $dutyShift = $this->dutyShift->where('status',1)->find($this->params->id);

        if(!$dutyShift){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Duty Shift fetched successfully";
        $this->outputData = $dutyShift;
        $this->success = true;
    }

    public function updateDutyShift(Request $request){

        $this->validator->validate($request, [
            "name"=>v::notEmpty(),
            "start_time"=>v::notEmpty(),
            "end_time"=>v::notEmpty(),
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
 
        unset($this->params->action); 
        $data = (array) $this->params;
        $data["updated_by"] = $this->user->id;
        $dutyShift = $this->dutyShift->where('status',1)->find($this->params->id)->update($data);
 
        $this->responseMessage = "Duty Shift has been updated successfully";
        $this->outputData = $dutyShift;
        $this->success = true;
    }

   
    public function allDutyShifts(){

        $room_categories = $this->dutyShift->where('status',1)->get();

        if(!$room_categories){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All duty shift fetched successfully";
        $this->outputData = $room_categories;
        $this->success = true;
    }
 

    public function deleteDutyShift(){

        $dutyShift = $this->dutyShift->find($this->params->id)->update(['status' => 0]);

        if(!$dutyShift){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Duty shift has been successfully deleted !";
        $this->success = true;
    }
}