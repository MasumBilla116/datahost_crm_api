<?php

namespace  App\Controllers\Facilities;

use App\Auth\Auth;
use App\Models\Facilities\Facilities;
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

class FacilitiesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $facilities;
    // facilities

    public function __construct()
    {
        $this->customResponse = new CustomResponse();

        $this->facilities = new Facilities();
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
            case 'createFacilities':
                $this->createFacilities($request);
                break;

            case 'getAllFacilities':
                $this->getAllFacilities();
                break;

            case 'getFacilitiesInfo':
                $this->getFacilitiesInfo();
                break;

            case 'updateFacilities':
                $this->updateFacilities($request);
                break;


            case 'deleteFacilities':
                $this->deleteFacilities();
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





    public function createFacilities(Request $request)
    {

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            // "image" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        //    dd($this->params->image);

        $facilities = $this->facilities
            ->create([
                "name" => $this->params->name,
                "sort_description" => $this->params->sort_description,
                "long_description" => $this->params->long_description,
                "photos" => json_encode($this->params->upload_ids),
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Facilities has been created successfully";
        $this->outputData = $facilities;
        $this->success = true;
    }



    public function getAllFacilities()
    {
        $facilitiesList = DB::table("facilities")
            ->orderBy('id', 'desc')
            ->where('status', 1)
            ->get();

        $this->responseMessage = "Facilities list fetched successfully";
        $this->outputData = $facilitiesList;
        $this->success = true;
    }




    public function getFacilitiesInfo()
    {
        if (!isset($this->params->facilities_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $facilities = $this->facilities
            ->find($this->params->facilities_id);

        if ($facilities->status == 0) {
            $this->success = false;
            $this->responseMessage = "slide missing!";
            return;
        }

        if (!$facilities) {
            $this->success = false;
            $this->responseMessage = "slide not found!";
            return;
        }

        $photos = json_decode($facilities->photos);


        if (count($photos) > 0) {

            $ids = $photos;
            $uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }

        $this->responseMessage = "facilities info fetched successfully";
        $this->outputData = $facilities;
        $this->outputData['photos'] = $photos;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;
    }



    public function deleteFacilities()
    {
        if (!isset($this->params->facilities_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $facilities = $this->facilities->find($this->params->facilities_id);

        if (!$facilities) {
            $this->success = false;
            $this->responseMessage = "facilities not found!";
            return;
        }


        $deletedFacilities = $this->facilities->where(['id' => $this->params->facilities_id, 'status' => 1])->delete();


        $this->responseMessage = "facilities Deleted successfully";
        $this->outputData = $deletedFacilities;
        $this->success = true;
    }



    public function updateFacilities(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "name" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $facilities = $this->facilities->where(['id' => $this->params->facilities_id, 'status' => 1])
            ->update([
                "name" => $this->params->name,
                "sort_description" => $this->params->sort_description,
                "long_description" => $this->params->long_description,
                "photos" => json_encode($this->params->upload_ids),
                "updated_by" => $this->user->id,
            ]);


        $this->responseMessage = "Facilities has been updated successfully !";
        $this->outputData = $facilities;
        $this->success = true;
    }


    public function checkResortFacilitiesPermission(Request $request, Response $response)
    {
        $facilitiesList = DB::table("sections")
            ->where("title", "RESORT FACILITIES")
            ->where('status', 1)
            ->first();

        $this->responseMessage = "Facilities";
        $this->outputData = $facilitiesList;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function getAllFacilitiesForWeb(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $facilitiesList = DB::table("facilities")
            ->orderBy('id', 'desc')
            ->where('status', 1)
            ->get();

        foreach ($facilitiesList as $facility) {
            $photos = json_decode($facility->photos);

            if (count($photos) > 0) {
                $ids = $photos;
                $uploadsData = [];

                for ($i = 0; $i < count($ids); $i++) {
                    $uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
                }

                $facility->uploadsData = $uploadsData;
            }
        }

        $this->responseMessage = "Facilities list fetched successfully";
        $this->outputData = $facilitiesList;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }



    public function getfacilityDetails(Request $request, Response $response, $facilityId)
    {
        // Assuming $facilityId is the parameter for the specific facility you want to retrieve

        $this->params = CustomRequestHandler::getAllParams($request);

        $facility = DB::table("facilities")
            ->where('id', $facilityId)
            ->where('status', 1)
            ->first();

        if (!$facility) {
            // Facility not found, handle the response accordingly
            $this->responseMessage = "Facility not found";
            $this->success = false;
            return $this->customResponse->is400Response($response, $this->responseMessage);
        }

        $photos = json_decode($facility->photos);

        if (count($photos) > 0) {
            $ids = $photos;
            $uploadsData = [];

            for ($i = 0; $i < count($ids); $i++) {
                $uploadsData[] = DB::table('uploads')->where('id', $ids[$i])->first();
            }

            $facility->uploadsData = $uploadsData;
        }

        $this->responseMessage = "Facility data fetched successfully";
        $this->outputData = $facility;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
