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

class ItemTypeController
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
            case 'createItemType':
                $this->createItemType($request, $response);
                break;
            case 'getAllItemTypes':
                $this->getAllItemTypes($request, $response);
                break;
            case 'editItemType':
                $this->editItemType($request, $response);
                break;
            case 'deleteItemType':
                $this->deleteItemType($request, $response);
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


    public function createItemType(Request $request, Response $response)
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

            $current_item_type = DB::table("item_types")->where(["item_type_name" => $this->params->name])->first();
            if ($current_item_type) {
                $this->success = false;
                $this->responseMessage = "This Unit Type already exists!";
                return;
            }

            $status = $this->params->status ?  1  : 0;

            $itemTypeId = DB::table("item_types")->insertGetId([
                "item_type_name" => $this->params->name,
                "status" => $status,
            ]);

            $itemType = [
                "id" => $itemTypeId,
                "item_type_name" => $this->params->name,
                "status" => $status
            ];
            DB::commit();
            $this->responseMessage = "Item Type created successfully";
            $this->outputData = $itemType;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Item Type creation failed";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function getAllItemTypes()
    {
        $itemQuery = DB::table("item_types")->orderBy("id", "desc");

        if (!empty($this->params->status)) {
            $itemQuery->where("status", $this->params->status);
        }

        $item = $itemQuery->get();

        if (empty($item)) {
            $item  = [];
        }

        $this->responseMessage = "Item Type fetched successfully";
        $this->outputData = $item;
        $this->success = true;
    }


    public function editItemType(Request $request, Response $response)
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
            $itemTypeId = $this->params->id;
            $itemTypeName = $this->params->name;

            DB::table("item_types")->where([
                "id" => $itemTypeId,
            ])->update([
                "item_type_name" => $itemTypeName,
                "status" => $status,
            ]);

            $itemType = [
                "id" => $itemTypeId,
                "item_type_name" => $itemTypeName,
                "status" => $status
            ];
            DB::commit();
            $this->responseMessage = "Item Type update successfully";
            $this->outputData = $itemType;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Item Type updation failed";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function deleteItemType(Request $request, Response $response)
    {

        try {

            $itemTypeId = $this->params->id;
            if (!isset($itemTypeId)) {
                $this->success = false;
                $this->responseMessage = "Parameter missing";
                return;
            }
            $unitType = DB::table("item_types")->where("id", $itemTypeId)->delete();

            if (!$unitType) {
                $this->responseMessage = "Item type  deleted failed";
                $this->outputData = [];
                $this->success = false;
            }

            $this->responseMessage = "Item type  Deleted successfully";
            $this->outputData = $itemTypeId;
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Item type  deleted failed";
            $this->outputData = [];
            $this->success = false;
        }
    }
}
