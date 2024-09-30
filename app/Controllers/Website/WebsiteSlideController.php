<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Models\Website\WebsiteSlides;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use App\Models\Website\WebsiteMenu;
use App\Models\Website\WebsiteSlider;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class WebsiteSlideController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $sliders;
    protected $slides;
    // Slides

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->sliders = new WebsiteSlider();
        $this->slides = new WebsiteSlides();
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
            case 'createSlider':
                $this->createSlider($request);
                break;

            case 'getAllSliders':
                $this->getAllSliders();
                break;
            case 'getSliderInfo':
                $this->getSliderInfo();
                break;

            case 'updateSlider':
                $this->updateSlider($request);
                break;
            case 'deleteSlider':
                $this->deleteSlider();
                break;
            case 'createSlides':
                $this->createSlides($request);
                break;

            case 'getAllSlides':
                $this->getAllSlides();
                break;
                // updateSlides

            case 'updateSlides':
                $this->updateSlides($request);
                break;
            case 'getSlidesInfo':
                $this->getSlidesInfo();
                break;


            case 'deleteSlides':
                $this->deleteSlides();
                break;

                // case 'homeSliderInfo':
                //     $this->homeSliderInfo();
                //     break;

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

    public function createSlider(Request $request)
    {

        $this->validator->validate($request, [
            "title" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }



        $slide = $this->sliders
            ->create([
                "title" => $this->params->title,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Slide has been created successfully";
        $this->outputData = $slide;
        $this->success = true;
    }

    public function getAllSliders()
    {
        $website_sliders = DB::table("website_sliders")
            ->orderBy('website_sliders.id', 'desc')
            ->where('website_sliders.status', 1)
            ->get();

        $this->responseMessage = "website_sliders list fetched successfully";
        $this->outputData = $website_sliders;
        $this->success = true;
    }



    public function getSliderInfo()
    {
        if (!isset($this->params->slider_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $slider = $this->sliders
            ->find($this->params->slider_id);

        if ($slider->status == 0) {
            $this->success = false;
            $this->responseMessage = "slider missing!";
            return;
        }

        if (!$slider) {
            $this->success = false;
            $this->responseMessage = "slider not found!";
            return;
        }

        $this->responseMessage = "slider info fetched successfully";
        $this->outputData = $slider;
        $this->success = true;
    }



    public function updateSlider(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "title" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $slider = $this->sliders->where(['id' => $this->params->slider_id, 'status' => 1])
            ->update([
                'title' => $this->params->title,
                'updated_by' => $this->user->id
            ]);

        $this->responseMessage = "Slider has been updated successfully !";
        $this->outputData = $slider;
        $this->success = true;
    }




    public function deleteSlider()
    {
        if (!isset($this->params->slider_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $sliders = $this->sliders->find($this->params->slider_id);

        if (!$sliders) {
            $this->success = false;
            $this->responseMessage = "slides not found!";
            return;
        }

        $deletedSlider = $sliders->update([
            "status" => 0,
        ]);

        $this->responseMessage = "slides Deleted successfully";
        $this->outputData = $deletedSlider;
        $this->success = true;
    }



    public function createSlides(Request $request)
    {

        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            // "image" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        //    dd($this->params->image);

        $slides = $this->slides
            ->create([
                "slider_id" => $this->params->slider_id,
                "title" => $this->params->title,
                "description" => $this->params->description,
                "link" => $this->params->link,
                "photos" => json_encode($this->params->upload_ids),
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Slide has been created successfully";
        $this->outputData = $slides;
        $this->success = true;
    }


    public function getAllSlides()
    {
        $website_sliders = DB::table("website_slides")
            ->orderBy('website_slides.id', 'desc')
            ->where('website_slides.status', 1)
            ->get();

        $this->responseMessage = "website_sliders list fetched successfully";
        $this->outputData = $website_sliders;
        $this->success = true;
    }


    public function getSlidesInfo()
    {
        if (!isset($this->params->slides_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $slides = $this->slides
            ->find($this->params->slides_id);

        if ($slides->status == 0) {
            $this->success = false;
            $this->responseMessage = "slide missing!";
            return;
        }

        if (!$slides) {
            $this->success = false;
            $this->responseMessage = "slide not found!";
            return;
        }

        $photos = json_decode($slides->photos);


        if (count($photos) > 0) {

            $ids = $photos;
            $uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }

        $this->responseMessage = "slides info fetched successfully";
        $this->outputData = $slides;
        $this->outputData['photos'] = $photos;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;
    }



    public function deleteSlides()
    {
        if (!isset($this->params->slides_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $slide = $this->slides->find($this->params->slides_id);

        if (!$slide) {
            $this->success = false;
            $this->responseMessage = "slides not found!";
            return;
        }


        $deletedSlides = $this->slides->where(['id' => $this->params->slides_id, 'status' => 1])->delete();
        // $deletedSlides = $slide->update([
        //     "status" => 0,
        // ]);

        $this->responseMessage = "slides Deleted successfully";
        $this->outputData = $deletedSlides;
        $this->success = true;
    }


    public function updateSlides(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "title" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $slider = $this->slides->where(['id' => $this->params->slides_id, 'status' => 1])
            ->update([
                "slider_id" => $this->params->slider_id,
                "title" => $this->params->title,
                "description" => $this->params->description,
                "link" => $this->params->link,
                "photos" => json_encode($this->params->upload_ids),
                "updated_by" => $this->user->id,
            ]);



        $this->responseMessage = "Slider has been updated successfully !";
        $this->outputData = $slider;
        $this->success = true;
    }

    //Home Slider info
    public function homeSliderInfo(Request $request, Response $response)
    {
        $slider = $this->sliders->with('slides')->find(1);

        $slides = $slider->slides;

        if (count($slides) > 0) {

            $uploadsData = array();

            for ($i = 0; $i < count($slides); $i++) {
                $photo_id = json_decode($slides[$i]->photos);
                $uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $photo_id)->first();
            }
        }

        $this->responseMessage = "fetched sliders info";
        $this->outputData = $slider;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
