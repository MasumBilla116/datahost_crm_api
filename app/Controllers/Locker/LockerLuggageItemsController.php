<?php

namespace  App\Controllers\Locker;

use App\Auth\Auth;
use App\Models\Locker\LockerLuggageItems;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class LockerLuggageItemsController
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
        $this->lockerLuggageItems = new lockerLuggageItems();
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
            case 'luggageItemsEntry':
                $this->luggageItemsEntry($request, $response);
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

    public function luggageItemsEntry(Request $request, Response $response)
    {
        
        $lockerLuggageItems = $this->lockerLuggageItems->create([
            "size" =>  'large',
            "type" =>  'combine',
            "created_by" => $this->user->id,
            "updated_by" =>  $this->user->id,
            "updated_at" =>  '2022-10-20 11:11:46',
            "created_at" =>  '2022-10-20 11:11:46',
        ]);
        
        $this->responseMessage = "luggage Items Entry Successfully!";
        $this->outputData = $lockerLuggageItems;
        $this->success = true;
        
    }

}