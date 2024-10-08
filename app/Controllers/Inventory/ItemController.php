<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use DateTime;
use Illuminate\Database\Capsule\Manager as DB;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class ItemController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $items;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->items = new InventoryItem();
        $this->categories = new InventoryCategory();
        $this->locations = new WarehouseLocation();
        $this->warehouses = new Warehouse();
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
            case 'createItem':
                $this->createItem($request, $response);
                break;
            case 'getAllItems':
                $this->getAllItems($request, $response);
                break;
            case 'getAllItemList':
                $this->getAllItemList($request, $response);
                break;
            case 'getItemInfo':
                $this->getItemInfo($request, $response);
                break;
            case 'editItem':
                $this->editItem($request, $response);
                break;
            case 'deleteItem':
                $this->deleteItem($request, $response);
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


    public function createItem(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "category_id" => v::notEmpty(),
            "item_type" => v::notEmpty(),
            "unit_type" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $status = $status = $this->params->status ?  1  : 0;

        $insertData = [
            "item_name" => ucfirst($this->params->name),
            "category_id" => $this->params->category_id,
            "item_type_id" => $this->params->item_type,
            "unit_type_id" => $this->params->unit_type,
            "status" => $status,
        ];
        $itemId =  DB::table('items')->insertGetId($insertData);
        $item = array_merge($insertData, ["id" => $itemId]);

        $this->responseMessage = "New Category created successfully";
        $this->outputData = $item;
        $this->success = true;
    }

    public function editItem(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "category_id" => v::notEmpty(),
            "item_type_id" => v::notEmpty(),
            "unit_type_id" => v::notEmpty(),
        ]);

        $status = $status = $this->params->status ?  1  : 0;

        $updateData = [
            "item_name" => ucfirst($this->params->name),
            "category_id" => $this->params->category_id,
            "item_type_id" => $this->params->item_type_id,
            "unit_type_id" => $this->params->unit_type_id,
            "status" => $status,
        ];

        DB::table('items')->where("id", $this->params->item_id)->update($updateData);
        $item = array_merge($updateData, ["id" => $this->params->item_id]);

        $this->responseMessage = "New Category created successfully";
        $this->outputData = $item;
        $this->success = true;
    }

    public function deleteItem(Request $request, Response $response)
    {
        try {

            if (!isset($this->params->item_id)) {
                $this->success = false;
                $this->responseMessage = "Parameter 'item_id' missing";
                return;
            }

            DB::table("items")->where("id", $this->params->item_id)->update([
                "status" => 0
            ]);

            $this->responseMessage = "Item Deleted successfully";
            $this->outputData = $this->params->item_id;
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Something is wrong";
            $this->outputData = [];
            $this->success = true;
        }
    }


    public function getAllItemList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = DB::table('items')
            ->select(
                'items.id as item_id',
                'items.item_name',
                'inventory_categories.name as category_name',
                'inventory_categories.id as category_id',
                'item_types.item_type_name',
                'item_types.id as item_type_id',
                'items.status',
                'unit_types.unit_type_name',
                'unit_types.id as unit_type_id',
            )
            ->join('inventory_categories', 'inventory_categories.id', '=', 'items.category_id')
            ->where("inventory_categories.status", 1)
            ->join('unit_types', 'unit_types.id', '=', 'items.unit_type_id')
            ->where("unit_types.status", 1)
            ->join('item_types', 'item_types.id', '=', 'items.item_type_id')
            ->where("item_types.status", 1);

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if (!empty($filter['status'])) {
            if ($filter['status'] == 'all') {
                $query->where('items.status', '=', 1);
            } else if ($filter['status'] == 'deleted') {
                $query->where('items.status', '=', 0);
            }
        }

        if (!empty($filter['search'])) {
            $search = $filter['search'];
            $query->where(function ($query) use ($search) {
                $query->orWhere('items.item_name', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_items =  $query->orderBy('items.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        if ($all_items->isEmpty()) {
            $this->responseMessage = "Item list fetched successfully";
            $this->outputData = [
                $pageNo => [],
                'total' => 0,
            ];
            $this->success = true;
        }

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = [
            $pageNo => $all_items,
            'total' => $totalRow,
        ];
        $this->success = true;
    }









    function duplicateCode($catPrefix, $catCode)
    {
        $pre_len = strlen($catPrefix);
        $code_prefix = substr($catCode, $pre_len);
        $new_num = $code_prefix + 1;
        $newCode = $catPrefix . '' . $new_num;

        $item = $this->items->where("code", $newCode)->first();
        if ($item) {
            return $this->duplicateCode($catPrefix, $newCode);
        } else {
            return $newCode;
        }
    }

    public function getAllItems()
    {
        $item = DB::table("items")->select(
            'items.id as item_id',
            'items.created_at',
            'items.item_name',
            'inventory_categories.name as category_name',
            'inventory_categories.id as category_id',
            'item_types.item_type_name',
            'item_types.id as item_type_id',
            'items.status',
            'unit_types.unit_type_name',
            'unit_types.id as unit_type_id',
        )

            ->join('inventory_categories', 'inventory_categories.id', '=', 'items.category_id')
            ->where("inventory_categories.status", 1)
            ->join('unit_types', 'unit_types.id', '=', 'items.unit_type_id')
            ->where("unit_types.status", 1)
            ->join('item_types', 'item_types.id', '=', 'items.item_type_id')
            ->where("item_types.status", 1)
            ->get();

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $item;
        $this->success = true;
    }



    public function getItemInfo(Request $request, Response $response)
    {
        if (!isset($this->params->item_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $item = DB::table("items")->select(
            'items.id as item_id',
            'items.created_at',
            'items.item_name',
            'inventory_categories.name as category_name',
            'inventory_categories.id as category_id',
            'item_types.item_type_name',
            'item_types.id as item_type_id',
            'items.status'
        )
            ->join('inventory_categories', 'inventory_categories.id', '=', 'items.category_id')
            ->where("inventory_categories.status", 1)
            ->join('item_types', 'item_types.id', '=', 'items.item_type_id')
            ->where("item_types.status", 1)
            ->where("items.id", $this->params->item_id)
            ->get();

        // if ($item->status == 0) {
        //     $this->success = false;
        //     $this->responseMessage = "Item missing!";
        //     return;
        // }

        if (!$item) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }

        $this->responseMessage = "Item info fetched successfully";
        $this->outputData = $item;
        $this->success = true;
    }
}
