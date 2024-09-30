<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Models\Website\WebsiteMenu;
use App\Models\Settings\TaxHead;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class WebsiteMenuController
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
        $this->menus = new WebsiteMenu();
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
            case 'createMenu':
                $this->createMenu($request);
                break;         
            case 'getAllMenus':
                $this->getAllMenus($request, $response);
                break;
            case 'getMenuInfo':
                $this->getMenuInfo($request, $response);
                break;
            case 'editMenu':
                $this->editMenu($request, $response);
                break;
            case 'deleteMenu':
                $this->deleteMenu($request, $response);
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

    public function createMenu(Request $request){
        $this->validator->validate($request, [
            "title"=>v::notEmpty()
         ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        unset($this->params->action); 
        $data = (array) $this->params;
        $data["created_by"] = $this->user->id;
        $menus =  $this->menus->create($data); 
    
        $this->responseMessage = "Menu has been created successfully";
        $this->outputData = $menus;
        $this->success = true;
    }

    public function deleteMenu(){
        $menus = $this->menus->find($this->params->id)->delete();
        if(!$menus){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Menu has been successfully deleted !";
        $this->success = true;
    }

    public function getAllMenus()
    {
        $menus = $this->menus->all();
        $this->responseMessage = "menus fetched successfully";
        $this->outputData = $menus;
        $this->success = true;
    }

    public function getMenuInfo(Request $request, Response $response)
    {
        $menus = $this->menus->find($this->params->id);

        if(!$menus){
            $this->success = false;
            $this->responseMessage = "Menu not found!";
            return;
        }

        $this->responseMessage = "Menu info fetched successfully";
        $this->outputData = $menus;
        $this->success = true;
    }

    public function editMenu(Request $request, Response $response)
    {
        $menus = $this->menus->find($this->params->id);

        if(!$menus){
            $this->success = false;
            $this->responseMessage = "MEnu not found";
            return;
        }

        $this->validator->validate($request, [
            "title"=>v::notEmpty()
         ]);
 
         if ($this->validator->failed()) {
             $this->success = false;
             $this->responseMessage = $this->validator->errors;
             return;
         }
         unset($this->params->action); 
         $data = (array) $this->params;
         $data["updated_by"] = $this->user->id;
         $editedMenu = $menus->update($data);
 
         $this->responseMessage = "Menu Updated successfully";
         $this->outputData = $editedMenu;
         $this->success = true;
    }
    
}
