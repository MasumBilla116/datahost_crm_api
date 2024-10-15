<?php

namespace  App\Controllers\Stock;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Validation\Validator;

use App\Response\CustomResponse;

use App\Models\Inventory\Warehouse;

use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class StockController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $items;
    protected $locations;
    protected $warehouses;
    protected $categories;

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
            case 'getAllStocks':
                $this->getAllStocks($request, $response);
                break;

            case 'getAllStocksList':
                $this->getAllStocksList($request, $response);
                break;

                // getAllStocksList


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


    public function getAllStocks()
    {


        $filter = $this->params->filterValue;
        $categoryId = $this->params->categoryId;

        if ($categoryId) {
            $categories = $this->items
                ->with(['inventoryCategory', 'creator', 'updator'])
                ->where('inventory_category_id', $categoryId)
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'one-time-usable') {
            $categories = $this->items
                ->with(['inventoryCategory', 'creator', 'updator'])
                ->where('item_type', 'one-time-usable')
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'long-time-usable') {
            $categories = $this->items
                ->with(['inventoryCategory', 'creator', 'updator'])
                ->where('item_type', 'long-time-usable')
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'depreciable-item') {
            $categories = $this->items
                ->with(['inventoryCategory', 'creator', 'updator'])
                ->where('item_type', 'depreciable-item')
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $categories = $this->items
                ->with(['inventoryCategory', 'creator', 'updator'])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        }

        $this->responseMessage = "Item list fetched successfully";
        $this->outputData = $categories;
        $this->success = true;
    }



    public function getAllStocksList()
    {

        try {


            $pageNo = $_GET['page'];
            $perPageShow = $_GET['perPageShow'];
            $totalRow = 0;
            $filter = $this->params->filterValue;


            $query = DB::table("item_variations")
            ->select(
                "items.item_name",
                "purchase.unit_price",
            )
            ->join('purchase',"purchase.id",'=',"item_variations.")
            ->join("items","items.id","=","item_variations.item_id")
            // ->where('status',1);

            if (!$query) {
                $this->success = false;
                $this->responseMessage = "No data found!";
                return;
            }

            if ($filter['status'] == 'all') {
                $query->where('status', '=', 1);
            }

            if ($filter['status'] == 'deleted') {
                $query->where('status', '=', 0);
            }

            if ($filter['status'] == 'one-time-usable') {
                $query->where('item_type', 'one-time-usable');
            }

            if ($filter['status'] == 'long-time-usable') {
                $query->where('item_type', 'long-time-usable');
            }

            if ($filter['status'] == 'depreciable-item') {
                $query->where('item_type', 'depreciable-item');
            }


            if (isset($filter['search'])) {
                $search = $filter['search'];

                $query->where(function ($query) use ($search) {
                    $query->orWhere('name', 'LIKE', '%' . $search . '%', 'i');
                });
            }

            $all_list =  $query->orderBy('inventory_items.id', 'desc')
                ->offset(($pageNo - 1) * $perPageShow)
                ->limit($perPageShow)
                ->get();


            if ($pageNo == 1) {
                $totalRow = $query->count();
            }


            $this->responseMessage = "Stock list fetch successfully";
            $this->outputData = [
                $pageNo => $all_list,
                'total' => $totalRow,
            ];
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Stock list fetch successfully";
            $this->outputData = [
                $pageNo => [],
                'total' => 0,
            ];
            $this->success = true;
        }
    }
}
