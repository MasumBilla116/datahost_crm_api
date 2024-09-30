<?php

namespace  App\Controllers\Customers;

use App\Auth\Auth;
use App\Helpers\Accounting;
use App\Validation\Validator;
use App\Models\Customers\Client;
use App\Response\CustomResponse;
use App\Models\Accounts\Accounts;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Models\RBM\TowerFloorRoom;
use App\Requests\CustomRequestHandler;
use Carbon\Carbon;

use Respect\Validation\Validator as v;
use App\Models\Accounts\AccountCustomer;
use App\Models\Customers\CustomerBooking;
use App\Models\Customers\CustomerBookingGrp;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CUST_DashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $customer;
    protected $customerBookingGrp;
    protected $customerBooking;
    protected $towerFloorRoom;
    protected $accountCustomer;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->customer = new Customer();
        $this->customerBookingGrp = new CustomerBookingGrp();
        $this->customerBooking = new CustomerBooking();
        $this->towerFloorRoom = new TowerFloorRoom();
        $this->accountCustomer = new AccountCustomer();

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

            case 'allCustomer':
                $this->allCustomer($request, $response);
                break;
            case 'countCustomer':
                $this->countCustomer($request, $response);
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



    public function countCustomer()
    {
        $general_cust = DB::table("customers")->where("customer_type", 0)->count("id");
        $corporate_cust = DB::table("customers")->where("customer_type", 1)->count("id");
        $this->responseMessage = "requested customer fetched Successfully!";
        $this->outputData = ['general_cust_count' => $general_cust, 'corporate_cust_count' => $corporate_cust];
        $this->success = true;
    }


    public function allCustomer()
    {
        $customers = $this->customer
            ->select('customers.*')
            ->limit(10)
            ->get();

        if (!COUNT($customers)) {
            $this->success = false;
            $this->responseMessage = "customer not found!";
            return;
        }

        $this->responseMessage = "requested customer fetched Successfully!";
        $this->outputData = $customers;
        $this->success = true;
    }
}
