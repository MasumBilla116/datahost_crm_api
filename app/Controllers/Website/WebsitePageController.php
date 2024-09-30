<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Website\WebsiteMenu;
use App\Models\Website\WebsitePage;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;

use App\Models\Website\WebsitePageDetails;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class WebsitePageController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $pageDetails;
    protected $menu;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->pageDetails = new WebsitePageDetails();
        $this->menu = new WebsiteMenu();
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
            case 'createPage':
                $this->createPage($request);
                break;
            case 'getAllPage':
                $this->getAllPage($request, $response);
                break;
            case 'getPageInfo':
                $this->getPageInfo($request, $response);
                break;
            case 'editPage':
                $this->editPage($request, $response);
                break;
            case 'deletePage':
                $this->deletePage($request, $response);
                break;
                case 'getHomePageStngInfo':
                    $this->getHomePageStngInfo();
                    break;
                // getHomePageStngInfo
                case 'crtAndUptHeaderStngs':
                    $this->crtAndUptHeaderStngs($request, $response);
                    break;

                // crtAndUptHeaderStngs
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
            "title" => v::notEmpty(),
            "template_id" => v::notEmpty(),
            "menu_id" => v::notEmpty()
        ]);

        DB::beginTransaction();
        $data['menu_id'] = $this->params->menu_id;
        $data['template_id'] = $this->params->template_id;
        $data['page_name'] = $this->params->page_name;
        $data['created_by'] = $this->user->id;
        $page =  $this->page->create($data);

        $this->menu
            ->where(["id" => $this->params->menu_id])
            ->update([
                'template_id' => $this->params->template_id,
                'updated_by' => $this->user->id,
            ]);

        unset($this->params->action, $this->params->menu_id, $this->params->template_id, $this->params->page_name);
        $output_data = [];
        foreach ($this->params as $key => $obj) {
            $details["website_page_config_id"] = $page->id;
            $details["settings_name"] = $key;
            $details["settings_value"] = json_encode($obj);
            $output_data = $this->pageDetails->create($details);
            array_push($output_data, $page);
            unset($data['settings_name'], $data[$obj]);
        }

        $this->responseMessage = "Page has been updated successfully";
        $this->outputData = $output_data;
        $this->success = true;
        DB::commit();
    }

    public function deletePage()
    {
        $page = $this->page->find($this->params->id)->delete();
        if (!$page) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Page has been successfully deleted !";
        $this->success = true;
    }

    public function getAllPage()
    {
        $page = $this->page->with(['pageDetails', 'template', 'menu'])->orderBy('id', 'desc')->get();
        $this->responseMessage = "page fetched successfully";
        $this->outputData = $page;
        $this->success = true;
    }

    public function getPageInfo(Request $request, Response $response)
    {
        $page = $this->page->with(['pageDetails', 'template', 'menu'])->find($this->params->id);

        if (!$page) {
            $this->success = false;
            $this->responseMessage = "Page not found!";
            return;
        }

        $this->responseMessage = "Page info fetched successfully";
        $this->outputData = $page;
        $this->success = true;
    }

    public function editPage(Request $request, Response $response)
    {


        return true;
        // incomplete depend on front request

        $page = $this->page->find(1);

        if (!$page) {
            $this->success = false;
            $this->responseMessage = "Page not found";
            return;
        }

        $this->validator->validate($request, [
            "title" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        unset($this->params->action);


        $this->responseMessage = "Page Updated successfully";
        $this->outputData = "[]";
        $this->success = true;
    }


    public function crtAndUptHeaderStngs($request)
    {
        // Validate the request
        $this->validator->validate($request, [
            "headerOption" => v::notEmpty(),
        ]);

        // Check if validation failed
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // Define settings
        $settings = [
            [
                'key_name' => 'header_option',
                'name' => 'headerOption',
                'value' => $this->params->headerOption,
            ],
        ];


        foreach ($settings as $setting) {
            $existingRecord = DB::table('home_page_settings')
            ->where('key_name', $setting['key_name'])
            ->first();

            if ($existingRecord) {
                // Update existing record
                DB::table('home_page_settings')
                    ->where('key_name', $setting['key_name'])
                    ->update([
                        'value' => $setting['value'],
                        'updated_by' => $this->user->id,
                    ]);
            } else {
                // Insert new record
                DB::table('home_page_settings')->insert([
                    'key_name' => $setting['key_name'],
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                ]);
            }
        }

        $this->responseMessage = "Created successfully!";
        $this->success = true;
    }

    public function getHomePageStngInfo()
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
    }
}
