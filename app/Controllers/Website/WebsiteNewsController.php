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

class WebsiteNewsController
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
            case 'createNews':
                $this->createNews($request);
                break;

            case 'getAllNews':
                $this->getAllNews($request);
                break;
            case 'getNewsInfo':
                $this->getNewsInfo($request, $response);
                break;

            case 'updateNews':
                $this->updateNews($request);
                break;

            case 'deleteNews':
                $this->deleteNews($request);
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

    // create news
    public function createNews(Request $request)
    {

        $news = DB::table("news")
            ->insert([
                "title" => $this->params->title,
                "photos" => json_encode($this->params->upload_ids),
                "short_description" => $this->params->short_description,
                "long_description" => $this->params->long_description,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "News has been created successfully";
        $this->outputData = $news;
        $this->success = true;
    }



    // get single faq data
    public function getAllNews(Request $request)
    {

        $news = DB::table("news")->get();
        $this->responseMessage = "News fetch successflly";
        $this->outputData = $news;
        $this->success = true;
    }


    // get all faq
    public function getNewsInfo(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $news = DB::table("news")->where("id", $this->params->news_id)->first();

        $photos = json_decode($news->photos);
        $uploadsData = [];

        if (count($photos) > 0) {
            $ids = $photos;

            for ($i = 0; $i < count($ids); $i++) {
                $uploadsData[] = DB::table('uploads')->where("id", $ids[$i])->first();
            }
        }

        // Convert $news to an array
        $newsArray = json_decode(json_encode($news), true);

        $this->responseMessage = "slides info fetched successfully";
        $this->outputData = $newsArray;
        $this->outputData['photos'] = $photos;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;
    }

    // update
    public function updateNews(Request $request)
    {
        try {
            $news = DB::table("news")
                ->where("id", $this->params->news_id)
                ->update([
                    "title" => $this->params->title,
                    "photos" => $this->params->upload_ids,
                    "short_description" => $this->params->short_description,
                    "long_description" => $this->params->long_description,
                    "status" => $this->params->status,
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


    // delete single news
    public function deleteNews()
    {
        $faq = DB::table("news")->where("id", $this->params->news_id)->delete();
        $this->responseMessage = "Deleted successfully";
        $this->success = true;
    }
}
