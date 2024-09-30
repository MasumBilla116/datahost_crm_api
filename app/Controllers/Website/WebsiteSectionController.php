<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use App\Models\Website\WebsiteMenu;
use App\Models\Website\WebsitePages;
use App\Models\Website\WebsiteSlider;
use App\Models\Website\WebsiteSlides;
use App\Models\Website\WebsiteSection;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class WebsiteSectionController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $pages;
    protected $section;
    // Slides

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->pages = new WebsitePages();
        $this->section = new WebsiteSection();
        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);



        if (!$this->success) {
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }



    public function getAllHomeSection(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $sections = DB::table("sections")
            // ->orderBy('id', 'desc')
            ->join('pages', 'pages.id', '=', 'sections.page_id')
            ->select('sections.*', 'pages.title as page_name')
            ->where('sections.page_id', 2)
            ->get();



        $this->responseMessage = "sections list fetched successfully";
        $this->outputData = $sections;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function webLogo(Request $request, Response $response)
    {
        // config_data
        $configData = DB::table("config_data")->where('config_name', 'Dark Logo')->first();

        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "Section not found!";
            return;
        }

        $imageUrl = '';
        $photos = json_decode($configData->config_value);

        if (count($photos) > 0) {
            $imageUrl = DB::table('uploads')->where('uploads.id', '=', $photos[0])->value('file_path');
        }
        //   dd($imageUrl);
        //         return;
        $this->responseMessage = "Logo fetched successfully";
        $this->outputData = $imageUrl;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function notification($id, Response $response)
    {
        $customerInfo = DB::table("customers")->select("id")->where("uid", $id)->first();
        if (!empty($customerInfo)) {
            $notification = DB::table("notifications")->select("*")->where([
                "customer_id" => $customerInfo->id,
            ])->orderBy('id','desc')-> get();

            $this->responseMessage = "Notification fetched successfully";
            $this->outputData = $notification;
            $this->success = true;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }

        $this->responseMessage = "Notification fetched successfully";
        $this->outputData = [];
        $this->success = false;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function getUnreadnotification($id, Response $response)
    {
        $customerInfo = DB::table("customers")->select("id")->where("uid", $id)->first();
        if (!empty($customerInfo)) {
            $unreadNotificationCount = DB::table("notifications")
                ->where([
                    "customer_id" => $customerInfo->id,
                    "is_read" => 0
                ])
                ->count();


            $this->responseMessage = "Notification fetched successfully";
            $this->outputData = $unreadNotificationCount;
            $this->success = true;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }

        $this->responseMessage = "Notification fetched successfully";
        $this->outputData = [];
        $this->success = false;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function notificationRead($uid, $notificationId, Response $response)
    {
        $customerInfo = DB::table("customers")->select("id")->where("uid", $uid)->first();
        if (!empty($customerInfo)) {
            $notification = DB::table("notifications")->where([
                "customer_id" => $customerInfo->id,
                "id" => $notificationId
            ])->update([
                "is_read" => 1
            ]);

            $this->responseMessage = "Notification read successfully";
            $this->outputData = $notification;
            $this->success = true;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }

        $this->responseMessage = "Notification read successfully";
        $this->outputData = [];
        $this->success = false;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function getNearBySection(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $sections = DB::table("sections")->where("id", 4)->first();
        $photosId =  json_decode($sections->photos);

        $uploadPhotos = array();
        if (count($photosId) > 0) {
            for ($i = 0; $i < count($photosId); $i++) {
                $uploadPhotos[] = DB::table("uploads")->where("id", $photosId[$i])->first();
            }
        }

        $this->responseMessage = "Near by section fetch successfully";
        $this->outputData['section'] = $sections;
        $this->outputData['photos'] = $uploadPhotos;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }



    public function getLatestNewsSection(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $news = DB::table("sections")->where("id", 3)->where("status", 1)->first();

        // empty array
        $newsList = array();
        $uploadPhotos = array();

        if ($news !== "") {
            $newsList = DB::table("news")
                ->where("status", 1)
                ->get();

            foreach ($newsList as $key => $data) {
                $photosId = json_decode($data->photos);

                if (count($photosId) > 0) {
                    $uploadPhotos = [];

                    foreach ($photosId as $photoId) {
                        $uploadPhotos[] = DB::table("uploads")->where("id", $photoId)->first();
                    }
                    $newsList[$key]->uploadedPhotos = $uploadPhotos;
                }
            }
        }

        $this->responseMessage = "Latest News section fetch successfully";
        $this->outputData['section'] = $news ?? [];
        $this->outputData['news_list'] = $newsList;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function getNews($id, Request $request, Response $response)
    {
        $newsList = DB::table("news")->where("id", $id)->first();

        // // empty array 
        $uploadPhotos = array();
        if ($newsList !== "") {
            $photosId = json_decode($newsList->photos);

            if (count($photosId) > 0) {
                $uploadPhotos = [];

                foreach ($photosId as $photoId) {
                    $uploadPhotos[] = DB::table("uploads")->where("id", $photoId)->first();
                }
            }
        } else {
            $newsList = [];
        }


        $this->responseMessage = "Latest News section fetch successfully";
        $this->outputData['news'] = $newsList;
        $this->outputData['photos'] = $uploadPhotos;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function getHomePageStngInfo(Request $request, Response $response)
    {
        // Fetch the latest values for time, days, and percentageDeduction
        $homepageSettings = DB::table('home_page_settings')
            ->whereIn('key_name', [
                'header_option',

            ])
            ->orderBy('id', 'desc')
            ->get();

        if ($homepageSettings->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "Header settings not found!";
            return;
        }

        // Prepare the response data
        $header = [];
        foreach ($homepageSettings as $setting) {
            switch ($setting->key_name) {
                case 'header_option':
                    $header['headerOption'] = $setting->value;
                    break;
            }
        }

        $this->responseMessage = "Header info fetched successfully";
        $this->outputData = $header;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
