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

class WebsitePagesController
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
    protected $pages;
    protected $section;
    // Slides

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->sliders = new WebsiteSlider();
        $this->slides = new WebsiteSlides();
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

        switch ($action) {
            case 'createPage':
                $this->createPage($request);
                break;
            case 'getAllPages':
                $this->getAllPages();
                break;

            case 'getAllPagesfrDrpdwn':
                $this->getAllPagesfrDrpdwn();
                break;

            case 'getPageInfo':
                $this->getPageInfo();
                break;

            case 'updatePage':
                $this->updatePage($request);
                break;

            case 'deletePage':
                $this->deletePage();
                break;

            case 'createSections':
                $this->createSections($request);
                break;

            case 'getAllSections':
                $this->getAllSections();
                break;

            case 'getSectionInfo':
                $this->getSectionInfo();
                break;

            case 'updateSections':
                $this->updateSections($request);
                break;

            case 'updateSectionStatus':
                $this->updateSectionStatus($request);
                break;

                // updateSectionStatus

            case 'deleteSection':
                $this->deleteSection();
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

    public function createPage(Request $request)
    {

        $this->validator->validate($request, [
            "title" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }



        $pages = $this->pages
            ->create([
                "title" => $this->params->title,
                "created_by" => $this->user->id,
                "description" => $this->params->description,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Pages has been created successfully";
        $this->outputData = $pages;
        $this->success = true;
    }


    public function getAllPages()
    {
        $pages = DB::table("pages")
            ->orderBy('id', 'desc')
            // ->where('status', 1)
            ->get();

        $this->responseMessage = "Pages list fetched successfully";
        $this->outputData = $pages;
        $this->success = true;
    }

    public function getAllPagesfrDrpdwn()
    {
        $pages = DB::table("pages")
            ->orderBy('id', 'desc')
            ->where('status', 1)
            ->get();

        $this->responseMessage = "Pages list fetched successfully";
        $this->outputData = $pages;
        $this->success = true;
    }



    public function getPageInfo()
    {

        if (!isset($this->params->page_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $page = $this->pages
            ->find($this->params->page_id);

        if ($page->status == 0) {
            $this->success = false;
            $this->responseMessage = "page missing!";
            return;
        }

        if (!$page) {
            $this->success = false;
            $this->responseMessage = "page not found!";
            return;
        }

        $this->responseMessage = "page info fetched successfully";
        $this->outputData = $page;
        $this->success = true;
    }



    public function updatePage(Request $request)
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


        $page = $this->pages->where(['id' => $this->params->page_id, 'status' => 1])
            ->update([
                'title' => $this->params->title,
                'updated_by' => $this->user->id,
                "description" => $this->params->description,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Page has been updated successfully !";
        $this->outputData = $page;
        $this->success = true;
    }


    public function deletePage()
    {
        if (!isset($this->params->page_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $page = $this->pages->find($this->params->page_id);

        if (!$page) {
            $this->success = false;
            $this->responseMessage = "pages not found!";
            return;
        }

        $deletedPage = $page->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Pages Deleted successfully";
        $this->outputData = $deletedPage;
        $this->success = true;
    }



    public function createSections(Request $request)
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

        $section = $this->section
            ->create([
                "page_id" => $this->params->page_id,
                "title" => $this->params->title,
                "sort_description" => $this->params->sort_description,
                "long_description" => $this->params->long_description,
                "link" => $this->params->link,
                "photos" => json_encode($this->params->upload_ids),
                "created_by" => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Slide has been created successfully";
        $this->outputData = $section;
        $this->success = true;
    }







    public function getAllSections()
    {
        $sections = DB::table("sections")
            // ->orderBy('id', 'desc')
            ->join('pages', 'pages.id', '=', 'sections.page_id')
            ->select('sections.*', 'pages.title as page_name')
            ->get();

        $this->responseMessage = "sections list fetched successfully";
        $this->outputData = $sections;
        $this->success = true;
    }



    public function getSectionInfo()
    {
        if (!isset($this->params->section_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $section = $this->section
            ->find($this->params->section_id);



        if (!$section) {
            $this->success = false;
            $this->responseMessage = "Section not found!";
            return;
        }

        $photos = json_decode($section->photos);


        if (count($photos) > 0) {

            $ids = $photos;
            $uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }

        $this->responseMessage = "Sections info fetched successfully";
        $this->outputData = $section;
        $this->outputData['photos'] = $photos;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;
    }




    public function updateSections(Request $request)
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

        $slider = $this->section->where(['id' => $this->params->section_id])
            ->update([
                "page_id" => $this->params->page_id,
                "title" => $this->params->title,
                "sort_description" => $this->params->sort_description,
                "long_description" => $this->params->long_description,
                "link" => $this->params->link,
                "photos" => json_encode($this->params->upload_ids),
                "updated_by" => $this->user->id,
                "status" => intval($this->params->status),
            ]);

        $this->responseMessage = "Section has been updated successfully !";
        $this->outputData = $slider;
        $this->success = true;
    }


    public function deleteSection()
    {
        if (!isset($this->params->section_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $section = $this->section->find($this->params->section_id);

        if (!$section) {
            $this->success = false;
            $this->responseMessage = "section not found!";
            return;
        }


        $deletedSection = $this->section->where(['id' => $this->params->section_id, 'status' => 1])->delete();

        $this->responseMessage = "section Deleted successfully";
        $this->outputData = $deletedSection;
        $this->success = true;
    }



    public function updateSectionStatus(Request $request)
    {

        $slider = $this->section->where(['id' => $this->params->section_id])
            ->update([
                "status" => intval($this->params->status),
            ]);

        $this->responseMessage = "Section has been updated successfully !";
        $this->outputData = $slider;
        $this->success = true;
    }
}
