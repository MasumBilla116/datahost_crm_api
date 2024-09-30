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
use Psr\Http\Message\RequestInterface as Request;
use App\Models\Website\WebsitePrivacyPoliciesPage;
use Psr\Http\Message\ResponseInterface as Response;

class WebsitePrivacyPolicyPagesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $privacy_policies;



    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->privacy_policies = new WebsitePrivacyPoliciesPage();
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
            case 'createPrivacyPolicies':
                $this->createPrivacyPolicies($request);
                break;

                case 'getPrivacyPoliciesInfo':
                    $this->getPrivacyPoliciesInfo();
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

    public function createPrivacyPolicies(Request $request)
    {


        $id = $this->params->id;
        if (!$id) {
            $privacy_policies = $this->privacy_policies
                ->insert([
                    "title" => $this->params->title,
                    "content" => $this->params->content,

                ]);
        } else {

            $privacy_policies = $this->privacy_policies->where(["id" => $this->params->id])
                ->update([
                    "title" => $this->params->title,
                    "content" => $this->params->content,
                ]);
        }


        $this->responseMessage = "Page has been created successfully";
        $this->outputData = $privacy_policies;
        $this->success = true;
    }



    public function getPrivacyPoliciesInfo()
    {

        $privacy_policies = $this->privacy_policies::orderBy('id', 'desc')->first();

        if (!$privacy_policies) {
            $this->success = false;
            $this->responseMessage = "Pages not found!";
            return;
        }


        $this->responseMessage = "privacy_policies info fetched successfully";
        $this->outputData = $privacy_policies;
        $this->success = true;

    }




    public function getPrivacyPoliciesInfoFrweb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $privacy_policies = $this->privacy_policies::orderBy('id', 'desc')->first();

        $this->responseMessage = "privacy_policies info fetched successfully";
        $this->outputData = $privacy_policies;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);


    }
}
