<?php

namespace  App\Controllers\Settings;


use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Settings\ConfigData;
use App\Models\Settings\UserSettings;
use Illuminate\Pagination\Paginator;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class UserSettingsController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $configData;
    protected $clientUsers;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->configData = new ConfigData();
        $this->clientUsers = new ClientUsers();
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

            case 'createOrUpdate':
                $this->createOrUpdate($request);
                break;                          
            case 'settingsInfo':
                $this->settingsInfo();
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

    //update or insert user settings
    public function createOrUpdate(Request $request){

        $this->validator->validate($request, [
            "type"=>v::notEmpty(),
            "value"=>v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $result = UserSettings::updateOrCreate(
            ['user_id' => $this->user->id, 'type' => $this->params->type],
            ['value' => $this->params->value, 'status' => 1]
        );

        $this->responseMessage = "updated new settings !";
        $this->outputData = $result; 
        $this->success = true;
    }

    public function settingsInfo(){

        $user = ClientUsers::find($this->user->id);
        if(!$user){
            $this->responseMessage = "invalid user !";
            $this->outputData = []; 
            $this->success = false;
            return;
        }

        $settingsData = $user->settings;

        $this->responseMessage = "successfully fetched user settings !";
        $this->outputData = $settingsData; 
        $this->success = true;
    }


}