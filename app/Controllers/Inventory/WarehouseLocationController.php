<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLevel;
use App\Models\Inventory\WarehouseLocation;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class warehouseLocationController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->warehouses = new Warehouse();
        $this->levels = new WarehouseLevel();
        $this->locations = new WarehouseLocation();
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
            case 'getLevelByWarehouse':
                $this->getLevelByWarehouse($request, $response);
                break;
            case 'getLocationByLevel':
                $this->getLocationByLevel($request, $response);
                break;
            case 'getLocationByLocation':
                $this->getLocationByLocation($request, $response);
                break;
            case 'createLocation':
                $this->createLocation($request, $response);
                break;
            case 'getAllLocation':
                $this->getAllLocation($request, $response);
                break;
            case 'getLocationInfo':
                $this->getLocationInfo($request, $response);
                break;
            case 'editLocation':
                $this->editLocation($request, $response);
                break;
            case 'deleteLocation':
                $this->deleteLocation($request, $response);
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

    public function getLevelByWarehouse(Request $request, Response $response)
    {
        $levels = $this->levels->where('status', 1)->where('warehouse_id', $this->params->id)->get();

        $this->responseMessage = "Level list fetched successfully";
        $this->outputData = $levels;
        $this->success = true;
    }

    public function getLocationByLevel(Request $request, Response $response)
    {
        $level = $this->levels->where('status', 1)->where('id', $this->params->levelId)->first();

        if ($level->level_number == 1) {
            $locations = '';
        } else {
            $level_number = ($level->level_number - 1);

            $last_locations = $this->locations->where('warehouse_id', $this->params->warehouseId)->where('level_number', $level_number)->where('status', 1)->first();

            if (!$last_locations) {
                $this->success = false;
                $this->responseMessage = "Please create parent level location for the selected level first!";
                return;
            }

            $locations = $this->locations->where('warehouse_id', $this->params->warehouseId)->where('level_number', 1)->where('parent_id', $this->params->current_id)->where('status', 1)->get();
        }

        $this->responseMessage = "Level list fetched successfully";
        $this->outputData = $locations;
        $this->success = true;
    }

    public function getLocationByLocation(Request $request, Response $response)
    {
        $level = $this->levels->where('status', 1)->where('id', $this->params->levelId)->first();

        if ($level->level_number == 1) {
            $locations = '';
        } else {
            $level_number = ($level->level_number - 1);

            $locations = $this->locations->where('warehouse_id', $this->params->warehouseId)->where('parent_id', $this->params->current_id)->where('status', 1)->get();
        }

        $this->responseMessage = "Level list fetched successfully";
        $this->outputData = $locations;
        $this->success = true;
    }

    public function createLocation(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "location_type" => v::notEmpty(),
            "levelId" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // $current_location = $this->locations->where(["level_number"=>1])->where('status',1)->first();
        // if (!$current_location) {
        //     $this->success = false;
        //     $this->responseMessage = "Can not add location before added level 1 location!";
        //     return;
        // }

        $level = $this->levels->find($this->params->levelId);

        if ($this->params->location_type == 'Individual-Location') {
            $this->validator->validate($request, [
                "title" => v::notEmpty(),
            ]);
            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }

            $title = $this->params->title;

            if ($level->level_number == 1) {
                $parent_id = $this->params->warehouseId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId;
            } else if ($level->level_number == 2) {
                $parent_id = $this->params->locationOneId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $parent_id;
            } else if ($level->level_number == 3) {
                $parent_id = $this->params->locationTwoId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $parent_id;
            } else if ($level->level_number == 4) {
                $parent_id = $this->params->locationThreeId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $this->params->locationTwoId . "." . $parent_id;
            } else if ($level->level_number == 5) {
                $parent_id = $this->params->locationFourId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $this->params->locationTwoId . "." . $this->params->locationThreeId . "." . $parent_id;
            }

            $location = $this->locations->create([
                "location_code" => 123,
                "warehouse_id" => $this->params->warehouseId,
                "parent_id" => $parent_id,
                "warehouse_level_id" => $this->params->levelId,
                "level_number" => $level->level_number,
                "title" => $title,
                "description" => $this->params->description,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

            $current_location = $this->locations->where('id', $location->id)->first();
            $editedLocation = $current_location->update([
                "location_code" => $location_code . "." . $current_location->id,
            ]);
        } else {
            $this->validator->validate($request, [
                "title_prefix" => v::notEmpty(),
                "range_start" => v::notEmpty(),
                "range_end" => v::notEmpty(),
            ]);
            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }
            $start = (int) $this->params->range_start;
            $end = (int) $this->params->range_end;
            $title_prefix = $this->params->title_prefix;

            if ($level->level_number == 1) {
                $parent_id = $this->params->warehouseId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId;
            } else if ($level->level_number == 2) {
                $parent_id = $this->params->locationOneId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $parent_id;
            } else if ($level->level_number == 3) {
                $parent_id = $this->params->locationTwoId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $parent_id;
            } else if ($level->level_number == 4) {
                $parent_id = $this->params->locationThreeId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $this->params->locationTwoId . "." . $parent_id;
            } else if ($level->level_number == 5) {
                $parent_id = $this->params->locationFourId;
                $location_code = "L" . $level->level_number . "." . $this->params->warehouseId . "." . $this->params->locationOneId . "." . $this->params->locationTwoId . "." . $this->params->locationThreeId . "." . $parent_id;
            }

            $rangeLocations = array();
            $currentLocations = array();

            for ($k = $start; $k <= $end; $k++) {
                //check duplicate location number
                $current_warehouse_loc = $this->locations->where('parent_id', $parent_id)->where('level_number', $level->level_number)->where('title', $title_prefix . ' ' . $k)->where('status', 1)->first();
                if ($current_warehouse_loc) {
                    $this->success = false;
                    $this->responseMessage = "Location with the same number " . $k . " already exists!";
                    return;
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                $rangeLocations[] = array(
                    "location_code" => 321,
                    "warehouse_id" => $this->params->warehouseId,
                    "parent_id" => $parent_id,
                    "warehouse_level_id" => $this->params->levelId,
                    "level_number" => $level->level_number,
                    "title" => $title_prefix . ' ' . $i,
                    "description" => $this->params->description,
                    "created_by" => $this->user->id,
                    "status" => 1,
                );
            }

            DB::table('warehouse_locations')->insert($rangeLocations);

            for ($j = $start; $j <= $end; $j++) {

                $test = DB::table('warehouse_locations')->where('parent_id', $parent_id)->where('level_number', $level->level_number)->where('title', $title_prefix . ' ' . $j)->first();

                $currentLocations[] = array($test->id);
                $updateRangeLoations = DB::table('warehouse_locations')
                    ->where('id', $test->id)
                    ->update([
                        'location_code' => $location_code . "." . $test->id
                    ]);
            }
        }

        $this->responseMessage = "New Location created successfully";
        $this->outputData = $currentLocations;
        $this->success = true;
    }

    public function getAllLocation(Request $request, Response $response)
    {
        $locations = $this->warehouses->where('status', 1)->with(['filter_locations' => function ($q) {
            $q->where('status', 1);
        }])->get();


        // $locations = $this->warehouses->with(['filter_locations' => function ($query) {
        //     $query->whereHas('childrenRecursive');
        // }])->where('status', 1)->get();

        $this->responseMessage = "Location list fetched successfully";
        $this->outputData = $locations;
        $this->success = true;
    }

    public function getLocationInfo(Request $request, Response $response)
    {
        $location = $this->locations->where('id', $this->params->id)->first();

        $this->responseMessage = "Location info fetched successfully";
        $this->outputData = $location;
        $this->success = true;
    }

    public function editLocation(Request $request, Response $response)
    {
        $location = $this->locations->where('id', $this->params->id)->first();
        if (!$location) {
            $this->success = false;
            $this->responseMessage = "location not found!";
            return;
        }

        $this->validator->validate($request, [
            "title" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $editedLocation = $location->update([
            "title" => $this->params->title,
            "status" => $this->params->status,
        ]);

        $this->responseMessage = "Location updated successfully";
        $this->outputData = $location;
        $this->success = true;
    }

    public function deleteLocation(Request $request, Response $response)
    {
        if (!isset($this->params->locationId)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $location = $this->locations->where('id', $this->params->locationId)->first();

        if (!$location) {
            $this->success = false;
            $this->responseMessage = "Location not found!";
            return;
        }

        $location->status = 0;
        $location->save();

        // $deletedLocation = $location->update([
        //     "status" => 0,
        // ]);

        $this->responseMessage = "Location Deleted successfully";
        $this->outputData = $location;
        $this->success = true;
    }
}
