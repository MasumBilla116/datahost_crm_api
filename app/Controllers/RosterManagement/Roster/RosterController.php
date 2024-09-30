<?php

namespace  App\Controllers\RosterManagement\Roster;

use App\Auth\Auth;
use App\Models\RosterManagement\Roster;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RosterController
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
        $this->roster = new Roster(); // table :rosters
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
         
            case 'allRosters':
                $this->allRosters();
                break;          
            case 'createRoster':
                $this->createRoster($request);
                break;          
            case 'rosterInfo':
                $this->rosterInfo();
                break;          
            case 'updateRoster':
                $this->updateRoster($request);
                break;          
            case 'deleteRoster':
                $this->deleteRoster();
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

    public function createRoster(Request $request){
        $this->validator->validate($request, [
            "name"=>v::notEmpty(),
            "duty_shift_id"=>v::notEmpty(),
            "start_date"=>v::notEmpty(),
            "end_date"=>v::notEmpty(),
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        unset($this->params->action); 
        $data = (array) $this->params;
        $data["created_by"] = $this->user->id;
        $roster =  $this->roster->create($data); 
    
        $this->responseMessage = "Roster has been created successfully";
        $this->outputData = $roster;
        $this->success = true;
    }
 
    public function rosterInfo(){

        $roster = $this->roster->with('dutyShift')->where('status',1)->find($this->params->id);

        if(!$roster){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Roster fetched successfully";
        $this->outputData = $roster;
        $this->success = true;
    }

    public function updateRoster(Request $request){
 
        $this->validator->validate($request, [
            "name"=>v::notEmpty(),
            "duty_shift_id"=>v::notEmpty(),
            "start_date"=>v::notEmpty(),
            "end_date"=>v::notEmpty(),
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
 
        unset($this->params->action); 
        $data = (array) $this->params;
        $data["updated_by"] = $this->user->id;
        $roster = $this->roster->where('status',1)->find($this->params->id)->update($data);
 
        $this->responseMessage = "Roster has been updated successfully";
        $this->outputData = $roster;
        $this->success = true;
    }

   
    public function allRosters(){

        $room_categories = $this->roster->with('dutyShift')->where('status',1)->get();

        if(!$room_categories){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster fetched successfully";
        $this->outputData = $room_categories;
        $this->success = true;
    }
 

    public function deleteRoster(){

        $roster = $this->roster->find($this->params->id)->update(['status' => 0]);

        if(!$roster){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Roster has been successfully deleted !";
        $this->success = true;
    }
}