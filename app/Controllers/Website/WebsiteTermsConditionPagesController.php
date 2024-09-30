<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Website\WebsitePage;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Website\WebsiteAboutUsPage;

use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Website\WebsiteTermsConditionPage;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteTermsConditionPagesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $terms_condition;



    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->terms_condition = new WebsiteTermsConditionPage();
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
            case 'createTermsCondition':
                $this->createTermsCondition($request);
                break;

                case 'getTermsConditionInfo':
                    $this->getTermsConditionInfo();
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

    public function createTermsCondition(Request $request)
    {


        $id = $this->params->id;
        if (!$id) {
            $terms_condition = $this->terms_condition
                ->insert([
                    "title" => $this->params->title,
                    "content" => $this->params->content,

                ]);
        } else {

            $terms_condition = $this->terms_condition->where(["id" => $this->params->id])
                ->update([
                    "title" => $this->params->title,
                    "content" => $this->params->content,
                ]);
        }


        $this->responseMessage = "Page has been created successfully";
        $this->outputData = $terms_condition;
        $this->success = true;
    }



    public function getTermsConditionInfo()
    {

        $terms_condition = $this->terms_condition::orderBy('id', 'desc')->first();

        if (!$terms_condition) {
            $this->success = false;
            $this->responseMessage = "Pages not found!";
            return;
        }


        $this->responseMessage = "terms_condition info fetched successfully";
        $this->outputData = $terms_condition;
        $this->success = true;

    }




    public function getTermsConditionInfoFrweb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $terms_condition = $this->terms_condition::orderBy('id', 'desc')->first();

        $this->responseMessage = "terms_condition info fetched successfully";
        $this->outputData = $terms_condition;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);


    }
}
