<?php

namespace  App\Controllers\Users;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Capsule\Manager as DB;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class UserController
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
         

            case 'getUserInfo':
                $this->getUserInfo($request, $response);
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
    


    public function getUserInfo()
    {

        if (!isset($this->user->id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $user = DB::table('org_users')
        ->join('countries', 'org_users.country_id', '=', 'countries.id')
        ->join('cities', 'org_users.city_id', '=', 'cities.id')
        ->join('roles', 'org_users.role_id', '=', 'roles.id')
        ->select(
            'org_users.*',
            'countries.name as country_name',
            'roles.title as role',
            'cities.name as city_name',
        )
        ->where('org_users.id', '=', $this->user->id)
        ->get();
    

        if (!$user) {
            $this->success = false;
            $this->responseMessage = "User not found!";
            return;
        }


        $this->responseMessage = "User fetched Successfully!";
        $this->outputData = $user;
        $this->success = true;
    }



}