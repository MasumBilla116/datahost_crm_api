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
            case 'itemLowStock':
                $this->itemLowStock($request, $response);
                break;
            case 'itemStock':
                $this->itemStock($request, $response);
                break;
            case 'getLocationByWarehouse':
                $this->getLocationByWarehouse($request, $response);
                break;
            case 'getLocationInfo':
                $this->getLocationInfo($request, $response);
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
            "unit_cost" => v::notEmpty(),
            "unit_type" => v::notEmpty(),
            "item_type" => v::notEmpty(),
        ]);

        $warehouse_location_check = $this->warehouses->with("locations")->where('id', $this->params->warehouseId)->first();
        if (count($warehouse_location_check->locations) == 0) {
            $this->success = false;
            $this->responseMessage = "Please create location for the warehouse first!";
            return;
        }

        //locationList, locationtwoList, locationthreeList, locationfourList, locationfiveList
        if ($this->params->locationList != "" && !empty($this->params->locationOneId)) {
            $this->validator->validate($request, [
                "locationOneId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationtwoList != "" && !empty($this->params->locationTwoId)) {
            $this->validator->validate($request, [
                "locationTwoId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationthreeList != "" &&  !empty($this->params->locationThreeId)) {
            $this->validator->validate($request, [
                "locationThreeId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationfourList != "" && !empty($this->params->locationFourId)) {
            $this->validator->validate($request, [
                "locationFourId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationfiveList != "" && !empty($this->params->locationFiveId)) {
            $this->validator->validate($request, [
                "locationFiveId" => v::notEmpty(),
            ]);
        }

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if ($this->params->status == 'on') {
            $status = 1;
        } else {
            $status = 0;
        }

        $category = $this->categories->with('items')->find($this->params->category_id);
        $cat_count = $category->items->count();
        //$cat_count = $category->items()->select('code')->orderBy('id', 'DESC')->first();

        $cat_num = $cat_count + 1;

        $cat_name = $category->name;
        $name_length = strlen($cat_name);
        if ($name_length >= 3) {
            $cat_prefix = substr($cat_name, 0, 3);
        } else {
            $cat_prefix = $cat_name;
        }

        $code = strtoupper($cat_prefix) . '' . $cat_num;

        //check duplicate item code
        $current_item = $this->items->where("code", $code)->first();
        if ($current_item) {
            $code = $this->duplicateCode($cat_prefix, $current_item->code);
        }
        $warehouse_id = '';
        if (!empty($this->params->locationOneId)) {
            $warehouse_id = $this->params->locationOneId;
        }
        if (!empty($this->params->locationTwoId)) {
            $warehouse_id = $this->params->locationTwoId;
        }
        if (!empty($this->params->locationThreeId)) {
            $warehouse_id = $this->params->locationThreeId;
        }
        if (!empty($this->params->locationFourId)) {
            $warehouse_id = $this->params->locationFourId;
        }
        if (!empty($this->params->locationFiveId)) {
            $warehouse_id = $this->params->locationFiveId;
        }

        $item = $this->items->create([
            "code" => $code,
            "name" => $this->params->name,
            "inventory_category_id" => $this->params->category_id,
            "warehouse_location_id" => $warehouse_id,
            "description" => $this->params->description,
            "unit_cost" => $this->params->unit_cost,
            "unit_type" => $this->params->unit_type,
            "item_type" => $this->params->item_type,
            "opening_stock" => $this->params->opening_stock,
            "qty" => $this->params->opening_stock,
            "min_stock" => $this->params->min_stock,
            "created_by" => $this->user->id,
            "status" => $status,
        ]);

        $this->responseMessage = "New Category created successfully";
        $this->outputData = $item;
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
        $categories = $this->items->with(['inventoryCategory', 'creator', 'updator'])->where('status', 1)->orderBy('id', 'desc')->get();

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $categories;
        $this->success = true;
    }

    public function getAllItemList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = $this->items
            ->with(['inventoryCategory', 'creator', 'updator']);
        // ->where('status',1)
        // ->orderBy('id','desc')
        // ->get();

        // inventory_items

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('inventory_items.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('inventory_items.status', '=', 0);
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('inventory_items.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('inventory_items.code', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_inventory =  $query->orderBy('inventory_items.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = [
            $pageNo => $all_inventory,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getItemInfo(Request $request, Response $response)
    {
        if (!isset($this->params->item_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $item = $this->items->with(['inventoryCategory', 'warehouseLocation', 'creator', 'updator'])->find($this->params->item_id);

        if ($item->status == 0) {
            $this->success = false;
            $this->responseMessage = "Item missing!";
            return;
        }

        if (!$item) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }

        $this->responseMessage = "Item info fetched successfully";
        $this->outputData = $item;
        $this->success = true;
    }

    public function editItem(Request $request, Response $response)
    {
        if (!isset($this->params->item_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $item = $this->items->where('status', 1)->find($this->params->item_id);

        if (!$item) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "category_id" => v::notEmpty(),
            "unit_cost" => v::notEmpty(),
            "unit_type" => v::notEmpty(),
            "item_type" => v::notEmpty(),
        ]);

        if ($this->params->locationList != "" && $this->params->locationOneId == null) {
            $this->validator->validate($request, [
                "locationOneId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationtwoList != "" && $this->params->locationTwoId == null) {
            $this->validator->validate($request, [
                "locationTwoId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationthreeList != "" && $this->params->locationThreeId == null) {
            $this->validator->validate($request, [
                "locationThreeId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationfourList != "" && $this->params->locationFourId == null) {
            $this->validator->validate($request, [
                "locationFourId" => v::notEmpty(),
            ]);
        } else if ($this->params->locationfiveList != "" && $this->params->locationFiveId == null) {
            $this->validator->validate($request, [
                "locationFiveId" => v::notEmpty(),
            ]);
        }

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if ($this->params->locationOneId != null && $this->params->locationTwoId == null && $this->params->locationThreeId == null && $this->params->locationFourId == null && $this->params->locationFiveId == null) {
            $warehouse_id = $this->params->locationOneId;
        } else if ($this->params->locationOneId != null && $this->params->locationTwoId != null && $this->params->locationThreeId == null && $this->params->locationFourId == null && $this->params->locationFiveId == null) {
            $warehouse_id = $this->params->locationTwoId;
        }
        if ($this->params->locationOneId != null && $this->params->locationTwoId != null && $this->params->locationThreeId != null && $this->params->locationFourId == null && $this->params->locationFiveId == null) {
            $warehouse_id = $this->params->locationThreeId;
        }
        if ($this->params->locationOneId != null && $this->params->locationTwoId != null && $this->params->locationThreeId != null && $this->params->locationFourId != null && $this->params->locationFiveId == null) {
            $warehouse_id = $this->params->locationFourId;
        }
        if ($this->params->locationOneId != null && $this->params->locationTwoId != null && $this->params->locationThreeId != null && $this->params->locationFourId != null && $this->params->locationFiveId != null) {
            $warehouse_id = $this->params->locationFiveId;
        }

        $editedItem = $item->update([
            "name" => $this->params->name,
            "inventory_category_id" => $this->params->category_id,
            "warehouse_location_id" => $warehouse_id,
            "description" => $this->params->description,
            "unit_cost" => $this->params->unit_cost,
            "unit_type" => $this->params->unit_type,
            "item_type" => $this->params->item_type,
            "min_stock" => $this->params->min_stock,
            "wirehouse" => $this->params->wirehouse,
            "updated_by" => $this->user->id,
            "status" => $this->params->status,
        ]);

        $this->responseMessage = "Item Updated successfully";
        $this->outputData = $editedItem;
        $this->success = true;
    }

    public function deleteItem(Request $request, Response $response)
    {
        if (!isset($this->params->item_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'item_id' missing";
            return;
        }
    
        $item = $this->items->find($this->params->item_id);
    
        if (!$item) {
            $this->success = false;
            $this->responseMessage = "Item not found!";
            return;
        }
    
        if (isset($this->params->isStatus) && $this->params->isStatus == 1) {
            // If isStatus is 1, update item status to 0 to mark it as deleted
            $deletedItem = $item->update([
                "status" => 0,
            ]);
    
            if ($deletedItem) {
                $this->responseMessage = "Item Deleted successfully";
                $this->outputData = $this->params->item_id;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting item";
                $this->success = false;
            }
        } else {
            $deleted = $item->delete();
    
            if ($deleted) {
                $this->responseMessage = "Item deleted successfully";
                $this->outputData = $this->params->item_id;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting item";
                $this->success = false;
            }
        }
    }
    

    public function itemLowStock(Request $request, Response $response)
    {
        $items_with_lowStock = $this->items->whereColumn('qty', '<=', 'min_stock')->where('status', 1)->get();

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $items_with_lowStock;
        $this->success = true;
    }

    public function itemStock(Request $request, Response $response)
    {
        $categories = $this->params->category_id;
        $itemType = $this->params->item_type;
        $items_stock = $this->items->whereIn('inventory_category_id', $categories)->orWhere('item_type', $itemType)->where('status', 1)->get();

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $items_stock;
        $this->success = true;
    }

    public function getLocationByWarehouse(Request $request, Response $response)
    {
        $locations = $this->locations->with('warehouseLevel')->where('status', 1)->where('parent_id', $this->params->id)->get();

        $this->responseMessage = "Location list fetched successfully";
        $this->outputData = $locations;
        $this->success = true;
    }

    public function getLocationInfo(Request $request, Response $response)
    {
        $location = $this->locations->with('parentRecursive')->where('status', 1)->where('id', $this->params->id)->first();

        $this->responseMessage = "Location info fetched successfully";
        $this->outputData = $location;
        $this->success = true;
    }
}
