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
use App\Models\Website\WebsiteReturnRefundPage;
use Psr\Http\Message\RequestInterface as Request;
use App\Models\Website\WebsitePrivacyPoliciesPage;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteReturnRefundController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $return_refund;



    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->return_refund = new WebsiteReturnRefundPage();
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
            case 'createReturnRefund':
                $this->createReturnRefund($request);
                break;

                case 'getReturnRefundInfo':
                    $this->getReturnRefundInfo();
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

    public function createReturnRefund(Request $request)
    {


        $id = $this->params->id;
        if (!$id) {
            $return_refund = $this->return_refund
                ->insert([
                    "title" => $this->params->title,
                    "content" => $this->params->content,

                ]);
        } else {

            $return_refund = $this->return_refund->where(["id" => $this->params->id])
                ->update([
                    "title" => $this->params->title,
                    "content" => $this->params->content,
                ]);
        }


        $this->responseMessage = "Page has been created successfully";
        $this->outputData = $return_refund;
        $this->success = true;
    }



    public function getReturnRefundInfo()
    {

        $return_refund = $this->return_refund::orderBy('id', 'desc')->first();

        if (!$return_refund) {
            $this->success = false;
            $this->responseMessage = "Pages not found!";
            return;
        }


        $this->responseMessage = "return_refund info fetched successfully";
        $this->outputData = $return_refund;
        $this->success = true;

    }




    public function getReturnRefundInfoFrweb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $return_refund = $this->return_refund::orderBy('id', 'desc')->first();

        $this->responseMessage = "return_refund info fetched successfully";
        $this->outputData = $return_refund;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);


    }
}
