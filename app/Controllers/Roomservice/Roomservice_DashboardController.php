<?php

namespace  App\Controllers\Roomservice;

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

class Roomservice_DashboardController
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

            case 'fetchDashboardTopRowData':
                $this->fetchDashboardTopRowData($request, $response);
                break;
            case 'FetchAllAssignTask':
                $this->FetchAllAssignTask($request, $response);
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






    public function fetchDashboardTopRowData()
    {
        $totalRooms = DB::table("room_types")->where("status", 1)->count("id");
        $housekeepers = DB::table("housekeepers")->where("status", 1)->count("id");
        $date = date('Y-m-d');
        $task = DB::table("housekeeping_slip")->where("is_complete", 0)->whereDate('date', $date)->count("id");

        $this->responseMessage = "requested customer fetched Successfully!";
        $this->outputData = [
            'totalRooms' => $totalRooms,
            'totalHousekeepers' => $housekeepers,
            'ongoingTask' => $task,
        ];
        $this->success = true;
    }



    public function FetchAllAssignTask()
    {
        $housekeepers = DB::table("housekeepers")
            ->join('housekeeper_task', 'housekeepers.id', '=', 'housekeeper_task.housekeeper_id')
            ->join("housekeeping_checklist", "housekeeping_checklist.id", "=", "housekeeper_task.task_id")
            ->join("housekeeping_slip", "housekeeping_slip.housekeeper_id", "=", "housekeepers.id")
            ->select("housekeepers.id as id", "housekeepers.name as name", "housekeeping_checklist.task_name as task_name", "housekeeping_slip.is_complete as is_complete", "housekeeper_task.task_date as task_date")
            ->orderBy("housekeeping_slip.date", "desc")
            ->limit(10)
            ->get();


        $this->responseMessage = "requested customer fetched Successfully!";
        $this->outputData = $housekeepers;
        $this->success = true;
    }
}
