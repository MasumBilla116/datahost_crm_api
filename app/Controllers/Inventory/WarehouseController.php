<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Inventory\Warehouse;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class WarehouseController
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
            case 'createWarehouse':
                $this->createWarehouse($request, $response);
                break;
            case 'getAllWarehouse':
                $this->getAllWarehouse($request, $response);
                break;
            case 'getWarehouseInfo':
                $this->getWarehouseInfo($request, $response);
                break;
            case 'updateWarehouse':
                $this->updateWarehouse($request, $response);
                break;
            case 'deleteWarehouse':
                $this->deleteWarehouse($request, $response);
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


    public function createWarehouse(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "levelName" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $num_of_levels = sizeof($this->params->levelName);
        $level_name = $this->params->levelName;

        //check duplicate warehouse
        $current_warehouse = $this->warehouses->where(["name" => $this->params->name])->where('status', 1)->first();
        if ($current_warehouse) {
            $this->success = false;
            $this->responseMessage = "Warehouse with the same name already exists!";
            return;
        }

        for ($j = 0; $j < $num_of_levels; $j++) {
            if ($level_name[$j]['name'] == "") {
                $this->success = false;
                $this->responseMessage = "Level " . ($j + 1) . " can not be empty!";
                return;
            }
        }

        if ($this->params->status == 'on') {
            $status = 1;
        } else {
            $status = 0;
        }

        $warehouse = $this->warehouses->create([
            "name" => $this->params->name,
            "location_details" => $this->params->location_details,
            "num_of_levels" => $num_of_levels,
            "created_by" => $this->user->id,
            "status" => $status,
        ]);

        $warehouseLevels = array();

        for ($i = 0; $i < $num_of_levels; $i++) {
            $warehouseLevels[] = array(
                'warehouse_id' => $warehouse->id,
                'level_number' => ($i + 1),
                'label' => $level_name[$i]['name'],
                'created_by' => $this->user->id,
                'status' => 1
            );
        }

        DB::table('warehouse_levels')->insert($warehouseLevels);

        $this->responseMessage = "New Warehouse created successfully";
        $this->outputData = $level_name[1]['name'];
        $this->success = true;
    }

    public function getAllWarehouse()
    {
        $warehouse = $this->warehouses->with(['creator', 'updator'])->where('status', 1)->orderBy("id", "desc")->get();

        $this->responseMessage = "Warehouse list fetched successfully";
        $this->outputData = $warehouse;
        $this->success = true;
    }

    public function getWarehouseInfo(Request $request, Response $response)
    {
        if (!isset($this->params->warehouse_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $warehouse = $this->warehouses->find($this->params->warehouse_id);

        if ($warehouse->status == 0) {
            $this->success = false;
            $this->responseMessage = "Warehouse missing!";
            return;
        }

        if (!$warehouse) {
            $this->success = false;
            $this->responseMessage = "Warehouse not found!";
            return;
        }

        $level_list = DB::table('warehouses')
            ->join('warehouse_levels', 'warehouses.id', '=', 'warehouse_levels.warehouse_id')
            ->select('warehouse_levels.level_number as id', 'warehouse_levels.label as name')
            ->where('warehouse_levels.status', '=', 1)
            ->where('warehouses.id', '=', $this->params->warehouse_id)
            ->get();

        $this->responseMessage = "Voucher info fetched successfully";
        $this->outputData = $warehouse;
        $this->outputData['level_list'] = $level_list;
        $this->success = true;
    }

    public function updateWarehouse(Request $request, Response $response)
    {
        $warehouse = $this->warehouses->where('status', 1)->find($this->params->warehouse_id);

        if (!$warehouse) {
            $this->success = false;
            $this->responseMessage = "Warehouse not found!";
            return;
        }

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "levelName" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $level_name = $this->params->levelName;

        $num_of_levels = sizeof($this->params->levelName);

        for ($j = 0; $j < $num_of_levels; $j++) {
            if ($level_name[$j]['name'] == "") {
                $this->success = false;
                $this->responseMessage = "Level " . ($j + 1) . " can not be empty!";
                return;
            }
        }

        $editedWarehouse = $warehouse->update([
            "name" => $this->params->name,
            "location_details" => $this->params->location_details,
            "num_of_levels" => $num_of_levels,
            "updated_by" => $this->user->id
        ]);

        $old_level_item = DB::table('warehouse_levels')->where('warehouse_id', $this->params->warehouse_id)->where('status', 1)->get();

        $insertedLevels = array();

        for ($j = 0; $j < $num_of_levels; $j++) {

            if ($old_level_item[$j]->level_number == $level_name[$j]['id']) {

                $levelUpdated = DB::table('warehouse_levels')
                    ->where('id', $old_level_item[$j]->id)
                    ->update([
                        'label' => $level_name[$j]['name'],
                        'updated_by' => $this->user->id
                    ]);
            } else {
                $insertedLevels[] = array(
                    'warehouse_id' => $this->params->warehouse_id,
                    'level_number' => $level_name[$j]['id'],
                    'label' => $level_name[$j]['name'],
                    'status' => 1,
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                );
            }
        }

        DB::table('warehouse_levels')->insert($insertedLevels);

        $this->responseMessage = "New Warehouse Updated successfully";
        $this->outputData = $editedWarehouse;
        $this->success = true;
    }

    public function deleteWarehouse()
    {
        if (!isset($this->params->warehouse_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $warehouse = $this->warehouses->find($this->params->warehouse_id);

        if (!$warehouse) {
            $this->success = false;
            $this->responseMessage = "Warehouse not found!";
            return;
        }

        $deletedWarehouse = $warehouse->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Voucher Deleted successfully";
        $this->outputData = $deletedWarehouse;
        $this->success = true;
    }
}
