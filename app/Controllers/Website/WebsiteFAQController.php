<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use App\Models\Website\WebsiteReview;
use App\Models\Website\WebsiteContact;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteFAQController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $contact_us;
    // Slides

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->contact_us = new WebsiteContact();
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
            case 'createFAQ':
                $this->createFAQ($request);
                break;

            case 'getAllFAQ':
                $this->getAllFAQ();
                break;

            case 'getFAQ':
                $this->getFAQ($request);
                break;

            case 'updateFAQ':
                $this->updateFAQ($request);
                break;

            case 'deleteFAQ':
                $this->deleteFAQ($request);
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

    // create new faq
    public function createFAQ(Request $request)
    {

        $faq = DB::table("faqs")
            ->insert([
                "question" => $this->params->question,
                "answer" => $this->params->answer,
            ]);

        $this->responseMessage = "FAQ has been created successfully";
        $this->outputData = $faq;
        $this->success = true;
    }

    // get single faq data
    public function getFAQ(Request $request)
    {
        $faq = DB::table("faqs")->where("id", $this->params->id)->first();
        $this->responseMessage = "FAQ fetch successflly";
        $this->outputData = $faq;
        $this->success = true;
    }

    // update
    public function updateFAQ(Request $request)
    {
        try {
            $faq = DB::table("faqs")
                ->where("id", $this->params->id)
                ->update([
                    'question' => $this->params->question,
                    'answer' => $this->params->answer
                ]);
            $this->responseMessage = "Data update successfully";
            $this->success = true;
        } catch (\Exception $th) {
            // echo $th;
            $this->responseMessage = "Data update failed";
            $this->outputData = [];
            $this->success = false;
        }
    }


    // get all faq
    public function getAllFAQ()
    {
        $faq = DB::table("faqs")->orderBy("id", "desc")->get();
        $this->responseMessage = "FAQ has been fetch successfully";
        $this->outputData = $faq;
        $this->success = true;
    }

    // for web
    // get all faq
    public function allFAQ(Response $response)
    {
        $faq = DB::table("faqs")->orderBy("id", "desc")->get();
        $this->responseMessage = "FAQ has been fetch successfully";
        $this->outputData = $faq;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    // delete single faq
    public function deleteFAQ()
    {
        $faq = DB::table("faqs")->where("id", $this->params->id)->delete();
        $this->responseMessage = "Deleted successfully";
        $this->success = true;
    }
}
