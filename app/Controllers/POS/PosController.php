<?php

namespace  App\Controllers\Pos;

use App\Helpers\Helper;

use App\Auth\Auth;
use App\Models\Purchase\Invoice;
use App\Models\Purchase\InvoiceItem;
use App\Models\Purchase\Supplier;

use App\Models\Inventory\InventoryItem;

use Carbon\Carbon;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use App\Models\Users\ClientUsers;
use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

/**Seeding tester */

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;


//use Fzaninotto\Faker\Src\Faker\Factory;
//use Fzaninotto\Src\Faker;
use Faker\Factory;
use Faker;

/**Seeding tester */
class   PosController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    /** Invoice ini */
    public $invoice;
    public $invoiceItem;
    private $faker;
    public $supplier;
    private $inventory;

    private $helper;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();

        /*Model Instance END */
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
            case 'getAllItems':
                $this->getAllItems($request, $response);
                break;

            default:
                $this->responseMessage = "Invalid request!";
                return $this->customResponse->is400Response($response, $this->responseMessage);
        }

        if (!$this->success) {
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }




    public function getAllItems(Request $request, Response $response)
    {
        try {
            $categoryId = $this->params->category_id ?? '';
            $search = $this->params->search ?? '';

            $itemQuery = DB::table('item_variations');

            if (!empty($categoryId)) {
                $itemQuery->where("category_id", $categoryId);
            }

            if (!empty($search)) {
                $itemQuery->where("item_name", "LIKE", "%" . $search . "%")
                    ->orWhere("item_code", "LIKE", "%" . $search . "%");
            }


            $items = $itemQuery->get();

            $this->outputData = $items;
            $this->responseMessage = "Category fetch successfull";
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Fail to load category: " . $th->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }
}
