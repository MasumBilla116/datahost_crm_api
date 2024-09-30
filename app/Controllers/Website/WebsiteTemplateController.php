<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Models\Website\WebsiteTemplate;
use App\Models\Settings\TaxHead;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class WebsiteTemplateController
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
        $this->templates = new WebsiteTemplate();
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
            case 'createTemplate':
                $this->createTemplate($request);
                break;         
            case 'getAllTemplates':
                $this->getAllTemplates($request, $response);
                break;
            case 'getTemplateInfo':
                $this->getTemplateInfo($request, $response);
                break;
            case 'editTemplate':
                $this->editTemplate($request, $response);
                break;
            case 'deleteTemplate':
                $this->deleteTemplate($request, $response);
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

    public function createTemplate(Request $request){
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
        $templates =  $this->templates->create($data); 
    
        $this->responseMessage = "Template has been created successfully";
        $this->outputData = $templates;
        $this->success = true;
    }

    public function deleteTemplate(){
        $templates = $this->templates->find($this->params->id)->delete();
        if(!$templates){
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Template has been successfully deleted !";
        $this->success = true;
    }

    public function getAllTemplates()
    {
        $templates = $this->templates->all();
        $this->responseMessage = "templates fetched successfully";
        $this->outputData = $templates;
        $this->success = true;
    }

    public function getTemplateInfo(Request $request, Response $response)
    {
        $templates = $this->templates->find($this->params->id);

        if(!$templates){
            $this->success = false;
            $this->responseMessage = "Template not found!";
            return;
        }

        $this->responseMessage = "Template info fetched successfully";
        $this->outputData = $templates;
        $this->success = true;
    }

    public function editTemplate(Request $request, Response $response)
    {
        $templates = $this->templates->find($this->params->id);

        if(!$templates){
            $this->success = false;
            $this->responseMessage = "Template not found";
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
         $editedTemplate = $templates->update($data);
 
         $this->responseMessage = "Template Updated successfully";
         $this->outputData = $editedTemplate;
         $this->success = true;
    }
    
}
