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

class WebsiteContactUsController
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
            case 'createContact':
                $this->createContact($request);
                break;


            case 'getAllContact':
                $this->getAllContact();
                break;

            case 'getReviewInfo':
                $this->getReviewInfo();
                break;

            case 'updateReview':
                $this->updateReview($request);
                break;



            case 'deleteContact':
                $this->deleteContact();
                break;

            case 'updateSectionStatus':
                $this->updateSectionStatus($request);
                break;

            case 'updateReviewStatus':
                $this->updateReviewStatus();
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




    public function createContact(Request $request)
    {


        $contact_us = $this->contact_us
            ->create([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "subject" => $this->params->subject,
                "content" => $this->params->content,
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Contact has been created successfully";
        $this->outputData = $contact_us;
        $this->success = true;
    }




    public function updateReview(Request $request)
    {





        $review = $this->contact_us->where(['id' => $this->params->review_id])
            ->update([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "subject" => $this->params->subject,
                "content" => $this->params->content,
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Contact has been updated successfully !";
        $this->outputData = $review;
        $this->success = true;
    }



    public function getReviewInfo()
    {

        if (!isset($this->params->review_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $review = $this->contact_us
            ->find($this->params->review_id);



        if (!$review) {
            $this->success = false;
            $this->responseMessage = "Review not found!";
            return;
        }

        $this->responseMessage = "Review info fetched successfully";
        $this->outputData = $review;
        $this->success = true;
    }



    public function deleteContact()
    {
        if (!isset($this->params->contact_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $review = $this->contact_us->find($this->params->contact_id);

        if (!$review) {
            $this->success = false;
            $this->responseMessage = "reviews not found!";
            return;
        }

        $deletedReview = $this->contact_us->where('id', $this->params->contact_id)->delete();

        $this->responseMessage = "Reviews Deleted successfully";
        $this->outputData = $deletedReview;
        $this->success = true;
    }



    public function updateReviewStatus()
    {

        $review = $this->contact_us->where(['id' => $this->params->review_id])
            ->update([
                "status" => intval($this->params->status),
            ]);

        $this->responseMessage = "Review has been updated successfully !";
        $this->outputData = $review;
        $this->success = true;
    }




    public function getAllContact()
    {
        $review = DB::table("contact_us")
            ->orderBy('id', 'desc')
            // ->where('status', 1)
            ->get();

        $this->responseMessage = "Review list fetched successfully";
        $this->outputData = $review;
        $this->success = true;
    }



    public function createWebsiteContact(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);

        $contact_us = $this->contact_us->create([
            "name" => $this->params->name,
            "email" => $this->params->email,
            "subject" => $this->params->subject,
            "content" => $this->params->content,
            "contact_number" => $this->params->phone,
            "created_by" => 1,
            "status" => 1,
        ]);

        $this->responseMessage = "Successfully Created";
        $this->outputData = $contact_us;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }



    public function getAllContactfrWeb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $contact_us = DB::table("contact_us")
            ->orderBy('rating', 'desc')
            ->where('status', 1)
            ->get();
        $this->responseMessage = "Successfully Reviewd";
        $this->outputData = $contact_us;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function updateSectionStatus(Request $request)
    {

        $slider = $this->contact_us->where(['id' => $this->params->contact_id])
            ->update([
                "status" => intval($this->params->status),
            ]);

        $this->responseMessage = "Section has been updated successfully !";
        $this->outputData = $slider;
        $this->success = true;
    }
}
