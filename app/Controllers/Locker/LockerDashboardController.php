<?php

namespace  App\Controllers\Locker;

use App\Auth\Auth;
use App\Models\Locker\Locker;
use App\Models\Locker\LockerEntries;
use App\Models\Locker\LockerLuggageItems;
use App\Models\Locker\LockerEntriesInfo;
use App\Models\Locker\LockerLuggageInfo;
use Illuminate\Database\Capsule\Manager as DB;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class LockerDashboardController
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
        $this->lockers = new LOCKER();
        $this->lockerEntries = new lockerEntries();
        $this->LockerEntriesInfo = new LockerEntriesInfo();
        $this->LockerLuggageInfo = new LockerLuggageInfo();
        $this->LockerLuggageItems = new LockerLuggageItems();
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
            case 'fetchTotalCount':
                $this->fetchTotalCount($request, $response);
                break;

                case 'getAllLockerEntryList':
                    $this->getAllLockerEntryList($request, $response);
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




    public function fetchTotalCount()
    {
        //varaible declaration 

        $totalLocker =  DB::table('lockers')->where("status", 1)->count("id");
        $totalAvailabelLocker =  DB::table('lockers')->where("status", 1)->where("availability", 'available')->count("id");
        $totalUnavailabelLocker =  DB::table('lockers')->where("status", 1)->where("availability", 'unavailable')->count("id");

        $this->responseMessage = "requested locker deleted Successfully!";
        $this->outputData = [
            "totalLocker" => $totalLocker,
            "totalAvailabelLocker" => $totalAvailabelLocker,
            "totalUnavailabelLocker" => $totalUnavailabelLocker,
        ];
        $this->success = true;
    }


    public function getAllLockerEntryList()
    {
        // $query = DB::table('locker_entries')
        // ->select('locker_entries.id', 'locker_entries.status', 'locker_entries.pickup_date', 'locker_entries.time', 'locker_entries.remarks', 'locker_entries.token', 'locker_entries.created_at', 'locker_entries.updated_at', 'customers.first_name', 'customers.last_name', 'customers.title', 'customers.mobile')
        // ->selectRaw('(SELECT COUNT(`locker_luggage_info`.`locker_entries_id`) FROM `locker_luggage_info` WHERE `locker_luggage_info`.`locker_entries_id` = `locker_entries`.`id` AND `locker_luggage_info`.`status` = 1) as total_item')
        // ->join('customers', 'locker_entries.guest_id', '=', 'customers.id')
        // ->where('locker_entries.status', 1)
        // ->orderBy('locker_entries.id', 'DESC')
        // ->get();

        $query = DB::table('lockers')->get();

    $this->responseMessage = "All lockers fetched Successfully!";
    $this->outputData = $query;
    $this->success = true;
    }
}
