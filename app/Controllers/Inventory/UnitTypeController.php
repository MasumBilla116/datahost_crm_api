<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Models\Inventory\InventoryCategory;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class UnitTypeController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $categories;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->categories = new InventoryCategory();
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
            case 'createUnitType':
                $this->createUnitType($request, $response);
                break;
            case 'getAllUnitTypes':
                $this->getAllUnitTypes($request, $response);
                break;
            case 'getUnitType':
                $this->getUnitType($request, $response);
                break;
            case 'editUnitType':
                $this->editUnitType($request, $response);
                break;
            case 'deleteUnitType':
                $this->deleteUnitType($request, $response);
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


    public function createUnitType(Request $request, Response $response)
    {
        DB::beginTransaction();

        try {


            $this->validator->validate($request, [
                "name" => v::notEmpty(),
            ]);



            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }

            $current_unit_type = DB::table("unit_types")->where(["unit_type_name" => $this->params->name])->first();
            if ($current_unit_type) {
                $this->success = false;
                $this->responseMessage = "This Unit Type already exists!";
                return;
            }

            $status = $this->params->status ?  1  : 0;

            $unitTypeId = DB::table("unit_types")->insertGetId([
                "unit_type_name" => $this->params->name,
                "status" => $status,
            ]);

            $unitType = [
                "id" => $unitTypeId,
                "unit_type_name" => $this->params->name,
                "status" => $status
            ];
            DB::commit();
            $this->responseMessage = "Unit Type created successfully";
            $this->outputData = $unitType;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Unit Type creation failed";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getAllUnitTypes()
    {
        $unitTypeQuery = DB::table("unit_types")->orderBy("id", "desc");

        if (!empty($this->params->status)) {
            $unitTypeQuery->where("status", $this->params->status);
        }

        $unitType = $unitTypeQuery->get();

        $this->responseMessage = "Unit Type fetched successfully";
        $this->outputData = $unitType;
        $this->success = true;
    }

    public function getSubCategories()
    {
        $categories = $this->categories->with(['childrenRecursive'])->where('status', 1)->where('parent_id', '=', 0)->get();

        $this->responseMessage = "Categories list fetched successfully";
        $this->outputData = $categories;
        $this->success = true;
    }

    public function getUnitType(Request $request, Response $response)
    {
        if (!isset($this->params->category_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $category = $this->categories->find($this->params->category_id);

        if ($category->status == 0) {
            $this->success = false;
            $this->responseMessage = "Category missing!";
            return;
        }

        if (!$category) {
            $this->success = false;
            $this->responseMessage = "Category not found!";
            return;
        }

        $this->responseMessage = "Category info fetched successfully";
        $this->outputData = $category;
        $this->success = true;
    }

    public function editUnitType(Request $request, Response $response)
    {
        DB::beginTransaction();

        try {


            $this->validator->validate($request, [
                "name" => v::notEmpty(),
                "id" => v::notEmpty(),
            ]);

            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }

            $status = $this->params->status ?  1  : 0;
            $unitTypeId = $this->params->id;
            $unitTypeName = $this->params->name;

            DB::table("unit_types")->where([
                "id" => $unitTypeId,
            ])->update([
                "unit_type_name" => $unitTypeName,
                "status" => $status,
            ]);

            $unitType = [
                "id" => $unitTypeId,
                "unit_type_name" => $unitTypeName,
                "status" => $status
            ];
            DB::commit();
            $this->responseMessage = "Unit Type update successfully";
            $this->outputData = $unitType;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Unit Type updation failed";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function deleteUnitType(Request $request, Response $response)
    {

        try {

            $unitTypeId = $this->params->id;
            if (!isset($unitTypeId)) {
                $this->success = false;
                $this->responseMessage = "Parameter missing";
                return;
            }
            $unitType = DB::table("unit_types")->where("id", $unitTypeId)->delete();

            if (!$unitType) {
                $this->responseMessage = "Unit type  deleted failed";
                $this->outputData = [];
                $this->success = false;
            }

            $this->responseMessage = "Unit type  Deleted successfully";
            $this->outputData = $unitTypeId;
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Unit type  deleted failed";
            $this->outputData = [];
            $this->success = false;
        }
    }
}
