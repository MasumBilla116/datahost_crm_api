<?php

namespace  App\Controllers\HRM;

use DateTime;
use Exception;

use DateInterval;
use App\Auth\Auth;
use App\Models\HRM\Holiday;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class HolidayController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $holidays;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->holidays = new Holiday();
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
            case 'create':
                $this->createHoliday($request, $response);
                break;
            case 'getHolidays':
                $this->getHolidays();
                break;
            case 'getHolidayList':
                $this->getHolidayList();
                break;
            case 'getHolidayInfo':
                $this->getHolidayInfo($request, $response);
                break;
            case 'editHoliday':
                $this->editHoliday($request, $response);
                break;
            case 'deleteHoliday':
                $this->deleteHoliday($request, $response);
                break;
            case 'createLeave':
                $this->createLeave($request, $response);
                break;
            case 'getAllLeaveType':
                $this->getAllLeaveType();
                break;
            case 'getTypeInfo':
                $this->getTypeInfo($request, $response);
                break;
            case 'deleteLeaveType':
                $this->deleteLeaveType();
                break;
            case 'updateTypeInfo':
                $this->updateTypeInfo($request, $response);
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


    public function createHoliday(Request $request, Response $response)
    {

        // holidays
        $numberOfDays = $this->params->numberOfDays;
        $this->validator->validate($request, [
            "type" => v::notEmpty(),
            "description" => v::notEmpty()
        ]);
        // v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate holiday
        $current_holiday = $this->holidays->where(["date" => $this->params->startDate])->first();
        if ($current_holiday) {
            $this->success = false;
            $this->responseMessage = "Holiday with the same Date & Year already exists!";
            return;
        }


        $startDate = new DateTime($this->params->startDate);

        $holiday = [];
        for ($i = 0; $i < $numberOfDays; $i++) {
            $holiday[] = [
                "type" => $this->params->type,
                "date" => $startDate->format('Y-m-d'),
                "description" => $this->params->description,
                "created_by" => $this->user->id,
                "status" => 1,
            ];

            // Increment the date for the next iteration
            $startDate->add(new DateInterval('P1D'));
        }

        DB::table('holidays')->insert($holiday);

        $this->responseMessage = "New holiday created successfully";
        $this->outputData = $holiday;
        $this->success = true;
    }

    public function getHolidays()
    {
        if (!$this->params->year) {
            $today = date("Y");
        } else {
            $today = $this->params->year;
        }

        // $holidays = $this->holidays->with(['creator','updator'])->where('year', $today)->get();
        // $holidays = $this->holidays->with(['creator', 'updator'])->where(["status" => 1])->get();
        $holidays = DB::table('holidays')
            ->select('holidays.*', 'holiday_type.name as type')
            ->join('holiday_type', 'holidays.type', '=', 'holiday_type.id')
            ->get();

        $this->responseMessage = "Holidays list fetched successfully";
        $this->outputData = $holidays;
        $this->success = true;
    }



    public function getHolidayList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('holidays')
            ->select('holidays.*', 'holiday_type.name as type')
            ->join('holiday_type', 'holidays.type', '=', 'holiday_type.id');
        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('holidays.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('holidays.status', '=', 0);
        }

        // if (isset($filter['yearMonth'])) {
        //     $query->whereYear('holidays.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('holidays.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('holiday_type.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('holidays.title', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('holidays.date', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('holidays.year', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_holidays =  $query->orderBy('holidays.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Holidays list fetched successfully";
        $this->outputData = [
            $pageNo => $all_holidays,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getHolidayInfo(Request $request, Response $response)
    {
        if (!isset($this->params->holiday_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $holiday = $this->holidays->find($this->params->holiday_id);

        if ($holiday->status == 0) {
            $this->success = false;
            $this->responseMessage = "Holiday missing!";
            return;
        }

        if (!$holiday) {
            $this->success = false;
            $this->responseMessage = "Holiday not found!";
            return;
        }

        $this->responseMessage = "Holiday info fetched successfully";
        $this->outputData = $holiday;
        $this->success = true;
    }

    public function editHoliday(Request $request, Response $response)
    {
        if (!isset($this->params->holiday_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $holiday = $this->holidays->find($this->params->holiday_id);

        if (!$holiday) {
            $this->success = false;
            $this->responseMessage = "Holiday not found!";
            return;
        }

        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "type" => v::notEmpty(),
            "date" => v::notEmpty(),
            "year" => v::notEmpty(),
            "description" => v::notEmpty(),
        ]);
        v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate holiday
        $current_holiday = $this->holidays->where(["year" => $this->params->year])->where(["date" => $this->params->date])->first();
        if ($current_holiday && $current_holiday->id != $this->params->holiday_id) {
            $this->success = false;
            $this->responseMessage = "Holiday with the same Date & Year already exists!";
            return;
        }

        $editedHoliday = $holiday->update([
            "title" => $this->params->title,
            "type" => $this->params->type,
            "date" => $this->params->date,
            "year" => $this->params->year,
            "description" => $this->params->description,
            "updated_by" => $this->user->id,
            "status" => $this->params->status,
        ]);

        $this->responseMessage = "Holiday Updated successfully";
        $this->outputData = $editedHoliday;
        $this->success = true;
    }





    public function deleteHoliday(Request $request, Response $response)
{
    if (!isset($this->params->holiday_id)) {
        $this->success = false;
        $this->responseMessage = "Parameter missing";
        return;
    }
    $holiday = $this->holidays->find($this->params->holiday_id);

    if (!$holiday) {
        $this->success = false;
        $this->responseMessage = "Holiday not found!";
        return;
    }

    $deleted = $holiday->delete();

    if (!$deleted) {
        $this->success = false;
        $this->responseMessage = "Failed to delete holiday";
        return;
    }

    $this->responseMessage = "Holiday deleted successfully";
    $this->outputData = $holiday;
    $this->success = true;
}




    public function createLeave(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
        ]);
        // v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate holiday
        $current_leave = DB::table('holiday_type')->where(["name" => $this->params->name])->first();
        if ($current_leave) {
            $this->success = false;
            $this->responseMessage = "Holiday with the same Date & Year already exists!";
            return;
        }



        $leave_type = DB::table('holiday_type')->insert([
            "name" => $this->params->name,
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $this->responseMessage = "New leave type created successfully";
        $this->outputData = $leave_type;
        $this->success = true;
    }


    public function getAllLeaveType()
    {

        $holidays = DB::table('holiday_type')->where(["status" => 1])->get();

        $this->responseMessage = "Leave type list fetched successfully";
        $this->outputData = $holidays;
        $this->success = true;
    }


    public function deleteLeaveType()
    {
        try {

            $attendance = DB::table('holiday_type')
                ->where("id", $this->params->leaveId)
                ->delete();
            if ($attendance) {
                $this->responseMessage = "Punch time deleted success";
                $this->success = true;
                return;
            } else {
                $this->responseMessage = "Please try again";
                $this->success = true;
                return;
            }
        } catch (Exception $error) {
            $this->responseMessage = "Something is worng";
            $this->outputData = [];
            $this->success = true;
            return;
        }
    }




    public function getTypeInfo(Request $request, Response $response)
    {
        if (!isset($this->params->leaveId)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $holiday = DB::table('holiday_type')->find($this->params->leaveId);

        if ($holiday->status == 0) {
            $this->success = false;
            $this->responseMessage = "Holiday type missing!";
            return;
        }

        if (!$holiday) {
            $this->success = false;
            $this->responseMessage = "Holiday type not found!";
            return;
        }

        $this->responseMessage = "Holiday type info fetched successfully";
        $this->outputData = $holiday;
        $this->success = true;
    }


    public function updateTypeInfo(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "uleaveId" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $driver = DB::table('holiday_type')->where(['id' => $this->params->uleaveId, 'status' => 1])
            ->update([
                'name' => $this->params->type,
                'updated_by' => $this->user->id
            ]);

        $this->responseMessage = "Type has been updated successfully !";
        $this->outputData = $driver;
        $this->success = true;
    }
}
