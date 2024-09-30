<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use App\Models\Website\WebsiteReview;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteReviewController
{
    // WebsiteContactUsController.php
    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $website_review;
    // Slides

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->website_review = new WebsiteReview();
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
            case 'createReview':
                $this->createReview($request);
                break;
            case 'getAllReview':
                $this->getAllReview();
                break;
            case 'getReviewInfo':
                $this->getReviewInfo();
                break;
            case 'updateReview':
                $this->updateReview($request);
                break;
            case 'deleteReview':
                $this->deleteReview();
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




    public function createReview(Request $request)
    {


        $review = $this->website_review
            ->create([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "review" => $this->params->review,
                "rating" => $this->params->rating,
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Review has been created successfully";
        $this->outputData = $review;
        $this->success = true;
    }




    public function updateReview(Request $request)
    {

        $review = $this->website_review->where(['id' => $this->params->review_id])
            ->update([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "review" => $this->params->review,
                "rating" => $this->params->rating,
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Review has been updated successfully !";
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
        $review = $this->website_review
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



    public function deleteReview()
    {
        if (!isset($this->params->review_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $review = $this->website_review->find($this->params->review_id);

        if (!$review) {
            $this->success = false;
            $this->responseMessage = "reviews not found!";
            return;
        }

        $deletedReview = $this->website_review->where('id', $this->params->review_id)->delete();

        $this->responseMessage = "Reviews Deleted successfully";
        $this->outputData = $deletedReview;
        $this->success = true;
    }



    public function updateReviewStatus()
    {

        $review = $this->website_review->where(['id' => $this->params->review_id])
            ->update([
                "status" => intval($this->params->status),
            ]);

        $this->responseMessage = "Review has been updated successfully !";
        $this->outputData = $review;
        $this->success = true;
    }




    public function getAllReview()
    {
        $review = DB::table("website_review")
            ->orderBy('rating', 'desc')
            // ->where('status', 1)
            ->get();

        $this->responseMessage = "Review list fetched successfully";
        $this->outputData = $review;
        $this->success = true;
    }


    public function websiteReview(Request $request, Response $response)
    {
        $this->user = Auth::user($request);
        $this->params = CustomRequestHandler::getAllParams($request);
        $website_review = '';
        if (is_array($this->user) && !empty($this->user)) {
            $website_review = $this->website_review->create([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "review" => $this->params->review,
                "rating" => $this->params->rating,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);
        } else {
            $website_review = $this->website_review->create([
                "name" => $this->params->name,
                "email" => $this->params->email,
                "review" => $this->params->review,
                "rating" => $this->params->rating,
                "status" => 0,
            ]);
        }

        $this->responseMessage = "Successfully Reviewd";
        $this->outputData = $website_review;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }



    public function getAllReviewfrWeb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $website_review = DB::table("website_review")
            ->orderBy('rating', 'desc')
            ->where('status', 1)
            ->get();
        $this->responseMessage = "Successfully Reviewd";
        $this->outputData = $website_review;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
