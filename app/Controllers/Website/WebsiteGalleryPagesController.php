<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Website\WebsitePage;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Website\WebsiteAboutUsPage;

use App\Models\Website\WebsiteGalleryPage;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteGalleryPagesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $galleryPage;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->galleryPage = new WebsiteGalleryPage();
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
            case 'createGalleryPage':
                $this->createGalleryPage($request);
                break;

            case 'getAllAboutUsPages':
                $this->getAllAboutUsPages();
                break;


            case 'getGalleryPageInfo':
                $this->getGalleryPageInfo();
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

    public function createGalleryPage(Request $request)
    {

        $id = $this->params->id;


        if (!$id) {

            $galleryPage = $this->galleryPage
                ->insert([
                    "section1_title" => $this->params->section1_title,
                    "section1_photos" => json_encode($this->params->section1_upload_ids),
                    "section1_isChecked" => $this->params->section1_isChecked,

                    "section2_title" => $this->params->section2_title,
                    "section2_photos" => json_encode($this->params->section2_upload_ids),
                    "section2_isChecked" => $this->params->section2_isChecked,


                    "section3_title" => $this->params->section3_title,
                    "section3_photos" => json_encode($this->params->section3_upload_ids),
                    "section3_isChecked" => $this->params->section3_isChecked,



                    "section4_title" => $this->params->section4_title,
                    "section4_photos" => json_encode($this->params->section4_upload_ids),
                    "section4_isChecked" => $this->params->section4_isChecked,



                    "section5_title" => $this->params->section5_title,
                    "section5_photos" => json_encode($this->params->section5_upload_ids),
                    "section5_isChecked" => $this->params->section5_isChecked,


                    "section6_title" => $this->params->section6_title,
                    "section6_photos" => json_encode($this->params->section6_upload_ids),
                    "section6_isChecked" => $this->params->section6_isChecked,

                    "section7_title" => $this->params->section7_title,
                    "section7_photos" => json_encode($this->params->section7_upload_ids),
                    "section7_isChecked" => $this->params->section7_isChecked,

                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
        } else {

            $galleryPage = $this->galleryPage->where(["id" => $this->params->id])
                ->update([
                    "section1_title" => $this->params->section1_title,
                    "section1_photos" => json_encode($this->params->section1_upload_ids),
                    "section1_isChecked" => $this->params->section1_isChecked,

                    "section2_title" => $this->params->section2_title,
                    "section2_photos" => json_encode($this->params->section2_upload_ids),
                    "section2_isChecked" => $this->params->section2_isChecked,


                    "section3_title" => $this->params->section3_title,
                    "section3_photos" => json_encode($this->params->section3_upload_ids),
                    "section3_isChecked" => $this->params->section3_isChecked,



                    "section4_title" => $this->params->section4_title,
                    "section4_photos" => json_encode($this->params->section4_upload_ids),
                    "section4_isChecked" => $this->params->section4_isChecked,



                    "section5_title" => $this->params->section5_title,
                    "section5_photos" => json_encode($this->params->section5_upload_ids),
                    "section5_isChecked" => $this->params->section5_isChecked,


                    "section6_title" => $this->params->section6_title,
                    "section6_photos" => json_encode($this->params->section6_upload_ids),
                    "section6_isChecked" => $this->params->section6_isChecked,

                    "section7_title" => $this->params->section7_title,
                    "section7_photos" => json_encode($this->params->section7_upload_ids),
                    "section7_isChecked" => $this->params->section7_isChecked,

                    "updated_by" => $this->user->id,
                    "status" => 1,
                ]);
        }


        $this->responseMessage = "Page has been created successfully";
        $this->outputData = $galleryPage;
        $this->success = true;
    }



    public function getAllAboutUsPages()
    {
        $galleryPage = DB::table("about_us_page")
            ->orderBy('id', 'desc')
            ->where('status', 1)
            ->get();

        $this->responseMessage = "Pages list fetched successfully";
        $this->outputData = $galleryPage;
        $this->success = true;
    }



    public function getGalleryPageInfo()
    {
        $galleryPage = $this->galleryPage::orderBy('id', 'desc')->first();
    
        if (!$galleryPage) {
            $this->success = false;
            $this->responseMessage = "slide not found!";
            return;
        }
    
        if ($galleryPage->status == 0) {
            $this->success = false;
            $this->responseMessage = "slide missing!";
            return;
        }
    
        // Initialize section uploads data variables
        $section1_uploadsData = null;
        $section2_uploadsData = null;
        $section3_uploadsData = null;
        $section4_uploadsData = null;
        $section5_uploadsData = null;
        $section6_uploadsData = null;
        $section7_uploadsData = null;
    
        // Fetch section photos and uploads data for each section
        $sectionPhotosAndUploadsData = [];
    
        for ($i = 1; $i <= 7; $i++) {
            $sectionPhotos = json_decode($galleryPage->{"section{$i}_photos"});
    
            if (count($sectionPhotos) > 0) {
                $ids = $sectionPhotos;
                $sectionUploadsData = [];
    
                for ($j = 0; $j < count($ids); $j++) {
                    $sectionUploadsData[] = DB::table('uploads')
                        ->where('uploads.user_id', '=', $this->user->id)
                        ->where('uploads.id', '=', $ids[$j])
                        ->first();
                }
    
                $sectionPhotosAndUploadsData["section{$i}_photos"] = $sectionPhotos;
                $sectionPhotosAndUploadsData["section{$i}_uploadsData"] = $sectionUploadsData;
    
                // Assign section uploads data variables if uploads exist
                if ($i === 1) {
                    $section1_uploadsData = $sectionUploadsData;
                } elseif ($i === 2) {
                    $section2_uploadsData = $sectionUploadsData;
                } elseif ($i === 3) {
                    $section3_uploadsData = $sectionUploadsData;
                } elseif ($i === 4) {
                    $section4_uploadsData = $sectionUploadsData;
                } elseif ($i === 5) {
                    $section5_uploadsData = $sectionUploadsData;
                } elseif ($i === 6) {
                    $section6_uploadsData = $sectionUploadsData;
                } elseif ($i === 7) {
                    $section7_uploadsData = $sectionUploadsData;
                }
            }
        }
    
        // Add section photos and uploads data to outputData
        $this->outputData = array_merge($galleryPage->toArray(), $sectionPhotosAndUploadsData);
    
        // Assign section uploads data variables to outputData conditionally
        $this->outputData['section1_uploadsData'] = $section1_uploadsData;
        $this->outputData['section2_uploadsData'] = $section2_uploadsData;
        $this->outputData['section3_uploadsData'] = $section3_uploadsData;
        $this->outputData['section4_uploadsData'] = $section4_uploadsData;
        $this->outputData['section5_uploadsData'] = $section5_uploadsData;
        $this->outputData['section6_uploadsData'] = $section6_uploadsData;
        $this->outputData['section7_uploadsData'] = $section7_uploadsData;
    
        // Set success and response message
        $this->success = true;
        $this->responseMessage = "galleryPage info fetched successfully";
    }
    



    public function getGalleryPageInfoFrweb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $galleryPage = $this->galleryPage::orderBy('id', 'desc')->first();



        $section1_photos = json_decode($galleryPage->section1_photos);
        if (count($section1_photos) > 0) {

            $ids = $section1_photos;
            $section1_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section1_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }


        $section2_photos = json_decode($galleryPage->section2_photos);
        if (count($section2_photos) > 0) {

            $ids = $section2_photos;
            $section2_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section2_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }





        $section3_photos = json_decode($galleryPage->section3_photos);


        if (count($section3_photos) > 0) {

            $ids = $section3_photos;
            $section3_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section3_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }



        $section4_photos = json_decode($galleryPage->section4_photos);


        if (count($section4_photos) > 0) {

            $ids = $section4_photos;
            $section4_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section4_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }



        $section5_photos = json_decode($galleryPage->section5_photos);


        if (count($section5_photos) > 0) {

            $ids = $section5_photos;
            $section5_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section5_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section6_photos = json_decode($galleryPage->section6_photos);


        if (count($section6_photos) > 0) {

            $ids = $section6_photos;
            $section6_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section6_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section7_photos = json_decode($galleryPage->section7_photos);


        if (count($section7_photos) > 0) {

            $ids = $section7_photos;
            $section7_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section7_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }


        $this->responseMessage = "aboutUsPage info fetched successfully";
        $this->outputData = $galleryPage;
        $this->outputData['section1_photos'] = $section1_photos;
        $this->outputData['section2_photos'] = $section2_photos;
        $this->outputData['section3_photos'] = $section3_photos;
        $this->outputData['section4_photos'] = $section4_photos;
        $this->outputData['section5_photos'] = $section5_photos;
        $this->outputData['section6_photos'] = $section6_photos;
        $this->outputData['section7_photos'] = $section7_photos;
        $this->outputData['section1_uploadsData'] = $section1_uploadsData;
        $this->outputData['section2_uploadsData'] = $section2_uploadsData;
        $this->outputData['section3_uploadsData'] = $section3_uploadsData;
        $this->outputData['section4_uploadsData'] = $section4_uploadsData;
        $this->outputData['section5_uploadsData'] = $section5_uploadsData;
        $this->outputData['section6_uploadsData'] = $section6_uploadsData;
        $this->outputData['section7_uploadsData'] = $section7_uploadsData;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
