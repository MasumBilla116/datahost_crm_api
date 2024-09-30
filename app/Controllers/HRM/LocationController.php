<?php

namespace  App\Controllers\HRM;

use App\Auth\Auth;
use App\Models\HRM\City;
use App\Models\HRM\Country;
use App\Models\HRM\State;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class LocationController
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
        $this->country = new Country();
        $this->state = new State();
        $this->city = new City();
        $this->validator = new Validator();

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        switch ($action) {
            case 'allCountries':
                $this->allCountries();
                break;  
            case 'getState':
                $this->getState();
                break;  
            case 'getCity':
                $this->getCity();
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

    public function allCountries(){
        $countries = $this->country->get();
        if(!$countries){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All countries fetched successfully";
        $this->outputData = $countries;
        $this->success = true;
    }

    public function getState(){
        $country = $this->country->find($this->params->country_id);
        if(!$country){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "States fetched by Country successfully";
        $this->outputData = $country->states;
        $this->success = true;
    }

    public function getCity(){
        $state = $this->state->find($this->params->state_id);
        if(!$state){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Cities fetched by State successfully";
        $this->outputData = $state->cities;
        $this->success = true;
    }

}