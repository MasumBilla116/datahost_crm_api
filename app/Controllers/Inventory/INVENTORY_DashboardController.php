<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Inventory\Warehouse;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryCategory;
use Illuminate\Database\Capsule\Manager as DB;

use App\Models\Inventory\WarehouseLocation;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class INVENTORY_DashboardController
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
            case 'inventoryDashbord':
                $this->inventoryDashbord($request, $response);
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


    public function inventoryDashbord()
    {
        $totalItems = DB::table('inventory_items')->where('status', 1)->count('id');

        if (!$totalItems) {
            $this->success = false;
            $this->responseMessage = "No items found!";
            return;
        }
        $totalItemsUnitCost = DB::table('inventory_items')->where('status', 1) ->sum('unit_cost');

        if (!$totalItemsUnitCost) {
            $this->success = false;
            $this->responseMessage = "No items found!";
            return;
        }
        $totalCategories = DB::table('inventory_categories') ->where('status',1)->count('id');
        if (!$totalCategories) {
            $this->success = false;
            $this->responseMessage = "No items found!";
            return;
        }
        $this->responseMessage = "Purchase are fetched successfully !";
        $this->outputData['categories'] = $totalCategories;
        $this->outputData['totalItems'] = $totalItems;
        $this->outputData['totalItemsUnitCost'] = $totalItemsUnitCost;

        $this->success = true;


    }

    
}
