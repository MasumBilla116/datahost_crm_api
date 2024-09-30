<?php

namespace  App\Controllers\Attendance;

use App\Auth\Auth;
use Carbon\Carbon;

/**
 * ?Model: Supplier Invoice 
 */

use App\Helpers\Helper;
use App\Validation\Validator;
use App\Models\Accounts\Payslip;
use App\Response\CustomResponse;

use App\Models\Accounts\Accounts;

/**
 * !Model: Supplier Invoice End
 */

/**
 * ?Model: Accounts AccountBank AccountCash Start
 */

use App\Models\Users\ClientUsers;
use PHPMailer\PHPMailer\PHPMailer;
use App\Models\Accounts\AccountBank;
use App\Models\Accounts\AccountCash;

/**
 * !Model: Accounts AccountBank AccountCash END
 */

use Respect\Validation\Rules\Number;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Accounts\FundTransferSlip;
use App\Models\Accounts\AccountAdjustment;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * !External Packages
 */

use Respect\Validation\Exceptions\NestedValidationException;
use App\Models\Purchase\Supplier;       //Table ======>  supplier
use App\Models\Purchase\Invoice;    //Table===========>  supplier_inv
use App\Models\Purchase\InvoiceItem;    //Table ======>  supplier_inv_item
use App\Models\Inventory\InventoryItem;       //Table ======>  Inventory Item


/***
 * ----------------------------------------------------------------
 * ----------- Attendance Model
 * ----------------------------------------------------------------
 * 
 */

use App\Models\Attendance\Attendance;
use Exception;

class AttendanceController
{
    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    private $helper;
    private $attendance;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->attendance = new Attendance();

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
            case "monthlyAttendanceDetails":
                $this->monthlyAttendanceDetails($request, $response);
                break;
            case "monthlyAttendanceReport":
                $this->monthlyAttendanceReport($request, $response);
                break;
            case "getAllEmployee":
                $this->getAllEmployee();
                break;
            case 'employeeAttendance':
                $this->employeeAttendance($request, $response);
                break;

            case 'attendance':
                $this->attendance($request, $response);
                break;

            case 'getAllReconcilation':
                $this->getAllReconcilation($request, $response);
                break;
                // getAllReconcilation

            case 'attendanceReconcilation':
                $this->attendanceReconcilation($request, $response);
                break;

                // attendanceReconcilation

                // employeeAttendance
            case "missionAttendance":
                $this->missionAttendance($request, $response);
                break;
            case "missionAttendanceUpdate":
                $this->missionAttendanceUpdate();
                break;
            case "lateAndEarlyMonthlyAttendanceDetails":
                $this->lateAndEarlyMonthlyAttendanceDetails($request, $response);
                break;
            case "findAttendanceLog":
                $this->findAttendanceLog($request, $response);
                break;
            case "attendanceFilterByDate":
                $this->attendanceFilterByDate($request, $response);
                break;
            case "logDetails":
                $this->logDetails($request, $response);
                break;

            case "activeUserAttendance":
                $this->activeUserAttendance($request, $response);
                break;

            case "activeUserTodayAttendanceInfo":
                $this->activeUserTodayAttendanceInfo($request, $response);
                break;
            case "update_attendance":
                $this->update_attendance($request, $response);
                break;
            case "delete_attendance":
                $this->delete_attendance($request, $response);
                break;
            case "createIpAddress":
                $this->createIpAddress($request, $response);
                break;
            case "getAllIpAddress":
                $this->getAllIpAddress($request, $response);
                break;
            case "deleteIpAddress":
                $this->deleteIpAddress($request, $response);
                break;
            case "updateIpAddress":
                $this->updateIpAddress($request, $response);
                break;


                // updateIpAddress
            case "updateReconciliation":
                $this->updateReconciliation($request, $response);
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


    // delete attendance
    public function delete_attendance()
    {
        try {

            $attendance = $this->attendance
                ->where("id", $this->params->at_id)
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

    // logDetails
    public function update_attendance(Request $request, Response $response)
    {
        try {

            if ($this->params->type === "in") {
                $attendance = $this->attendance
                    ->where("id", $this->params->at_id)
                    ->update([
                        "in_time" => $this->params->time
                    ]);
                $this->responseMessage = "Punch in time updated";
                $this->outputData = $attendance;
                $this->success = true;
                return;
            }

            if ($this->params->type === "out") {
                $attendance = $this->attendance
                    ->where("id", $this->params->at_id)
                    ->update([
                        "out_time" => $this->params->time
                    ]);
                $this->responseMessage = "Punch out time updated";
                $this->outputData = $attendance;
                $this->success = true;
                return;
            }
        } catch (Exception $err) {
            $this->responseMessage = "Something is worng";
            $this->outputData = [];
            $this->success = true;
            return;
        }
    }

    // attendance filter by date
    public function logDetails()
    {
        $result = $this->attendance
            ->join("employees", "employees.id", "=", "employee_attendance.employee_id")
            ->where("employee_attendance.employee_id", 89)
            ->whereDate("employee_attendance.date", "<=", date('Y-m-d')) 
            ->select("employees.name", "employees.id as emp_id", "employee_attendance.id as at_id", "employee_attendance.employee_id", "employee_attendance.status", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
            ->orderBy("employee_attendance.date", "DESC")
            ->get();


        if ($result !== "") {
            $this->outputData =  ["data" => $result, "name" => $result[0]->name];
            $this->success = true;
            return;
        }
        $this->responseMessage = "Not found any result";
        $this->outputData = [];
        $this->success = true;
    }
    // attendance filter by date
    public function attendanceFilterByDate()
    {
        $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
            ->whereDate("employee_attendance.date", ">=", $this->params->fromDate)
            ->whereDate("employee_attendance.date", "<=", $this->params->toDate)
            ->select("employees.name", "employees.id as emp_id", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
            ->get();
        if ($result->count() > 0) {
            $this->outputData = $result;
            $this->success = true;
            return;
        }
        $this->responseMessage = "Not found any result";
        $this->outputData = [];
        $this->success = true;
    }

    public function findAttendanceLog()
    {
        $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
            ->where("employee_attendance.employee_id", $this->params->emp_id)
            ->whereDate("employee_attendance.date", ">=", $this->params->fromDate)
            ->whereDate("employee_attendance.date", "<=", $this->params->toDate)
            ->select("employees.name", "employees.id as emp_id", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
            ->get();
        if ($result->count() > 0) {
            $this->outputData = $result;
            $this->success = true;
            return;
        }
        $this->responseMessage = "Not found any result";
        $this->outputData = [];
        $this->success = true;
    }

    // mission Attendance Update
    public function missionAttendanceUpdate()
    {
        $emp = $this->params->emp_id;
        $in_time = $this->params->in_time;
        $out_time = $this->params->out_time;

        for ($i = 0; $i < count($emp); $i++) {
            $this->attendance->where("employee_id", "=", $emp[$i])->update([
                'in_time' => $in_time[$i] === "" ? null : $in_time[$i],
                'out_time' => $out_time[$i] === "" ? null : $out_time[$i],
            ]);
        }
        $this->responseMessage = "All Missing Updated";
        $this->outputData = [];
        $this->success = true;
    }

    // mission attendance table data filtering
    public function missionAttendance()
    {
        $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
            ->join("designations", "designations.id", "employees.designation_id")
            ->orWhere("employee_attendance.in_time", null)
            ->orWhere("employee_attendance.out_time", null)
            ->whereDate("employee_attendance.date", $this->params->date)
            ->select("employees.name", "employees.id as emp_id", "designations.name as designation", "employee_attendance.status", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
            ->get();
        if ($result->count() > 0) {
            $this->outputData = $result;
            $this->success = true;
            return;
        }
        $this->responseMessage = "Not found any result";
        $this->outputData = [];
        $this->success = true;
    }

    // fetch all employee
    public function getAllEmployee()
    {
        $employee  = DB::table('employees')->where("status", "1")->get();
        if ($employee) {

            $this->responseMessage = "all employee";
            $this->outputData = $employee;
            $this->success = true;
        } else {
            $this->responseMessage = "Employee not found";
            $this->outputData = [];
            $this->success = false;
        }
    }
    // monthly attendance details
    public function monthlyAttendanceReport(Request $request, Response $response)
    {
        try {
            $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
                ->join("designations", "designations.id", "employees.designation_id")
                ->select("employees.name", "employees.id as emp_id", "designations.name as designation", "employee_attendance.status", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")->get();
            // filter by emp id
            if (!empty($this->params->emp_id)) {
                $result = $result->where("employees.emp_id", $this->params->emp_id);
            }
            $this->success = true;
            $this->outputData =  $result;
            return;
        } catch (Exception $error) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "No data found";
            return;
        }
    }


    // filtering monthly attendance
    public function monthlyAttendanceDetails(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "emp_id" => v::notEmpty()->setTemplate('All fields is required.'),
            "year" => v::notEmpty()->setTemplate('All fields is required.'),
            "month" => v::notEmpty()->setTemplate('All fields is required.'),
            "in_time" => v::notEmpty()->setTemplate('All fields is required.'),
            "out_time" => v::notEmpty()->setTemplate('All fields is required.'),
        ]);

        if ($this->validator->failed()) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "All Fields is required";
            return;
        }
        try {
            $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
                ->join("designations", "designations.id", "employees.designation_id")
                ->where("employee_attendance.employee_id", $this->params->emp_id)
                ->whereRaw("YEAR(employee_attendance.date) = ?", [$this->params->year])
                ->whereRaw("MONTH(employee_attendance.date) = ?", [$this->params->month])
                ->select("employees.name", "employees.id as emp_id", "designations.name as designation", "employee_attendance.status", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
                ->get();

            if ($result->count() > 0) {
                $this->success = true;
                $this->outputData =  $result;
                $this->responseMessage = "Filtering data";
                return;
            }
            $this->success = true;
            $this->outputData =  [];
            $this->responseMessage = "Filtering data";
        } catch (Exception $error) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "No data found";
            return;
        }
    }
    // late and early filtering monthly attendance
    public function lateAndEarlyMonthlyAttendanceDetails(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "emp_id" => v::notEmpty()->setTemplate('All fields is required.'),
            "year" => v::notEmpty()->setTemplate('All fields is required.'),
            "month" => v::notEmpty()->setTemplate('All fields is required.'),
        ]);

        if ($this->validator->failed()) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "All Fields is required";
            return;
        }
        try {
            $result = $this->attendance->join("employees", "employees.id", "employee_attendance.employee_id")
                ->join("designations", "designations.id", "employees.designation_id")
                ->where("employee_attendance.employee_id", $this->params->emp_id)
                ->whereRaw("YEAR(employee_attendance.date) = ?", [$this->params->year])
                ->whereRaw("MONTH(employee_attendance.date) = ?", [$this->params->month])
                ->select("employees.name", "employees.id as emp_id", "designations.name as designation", "employee_attendance.status", "employee_attendance.date", "employee_attendance.out_time", "employee_attendance.in_time")
                ->get();

            if ($result->count() > 0) {
                $this->success = true;
                $this->outputData =  $result;
                $this->responseMessage = "Filtering data";
                return;
            }
            $this->success = true;
            $this->outputData =  [];
            $this->responseMessage = "Filtering data";
        } catch (Exception $error) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "No data found";
            return;
        }
    }

    // take attendance check-in or check-out
    public function attendance(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "emp_id" => v::notEmpty()->setTemplate('Employee name is required.'),
            "date" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = true;
            $this->outputData = [];
            $this->responseMessage = "Employee name is required";
            return;
        }

        try {
            if ($this->params->check === "check-in") {
                $check_in_or_not = $this->attendance->where("employee_id", $this->params->emp_id)
                    ->whereDate("date", $this->params->date)->get();

                if (count($check_in_or_not) == 0) {
                    $attendance = $this->attendance->create([
                        "employee_id" => $this->params->emp_id,
                        "date" => $this->params->date,
                        "in_time" => $this->params->in_time,
                        "status" => "present",
                        "created_by" => $this->user->id
                    ]);
                    $this->responseMessage = "Attendance successfull";
                    $this->outputData = $attendance;
                    $this->success = true;
                    return;
                } else {
                    $this->responseMessage = "You are already check-in";
                    $this->outputData = [];
                    $this->success = true;
                    return;
                }
            }

            if ($this->params->check === "check-out") {

                $attendance = $this->attendance
                    ->where("employee_id", $this->params->emp_id)
                    ->whereDate("date", $this->params->date)
                    ->where("created_by", $this->user->id)
                    ->update([
                        "out_time" => $this->params->out_time
                    ]);
                if ($attendance) {
                    $this->responseMessage = "Checkout successfull";
                    $this->outputData = $attendance;
                    $this->success = true;
                    return;
                } else {
                    $this->responseMessage = "First check-in";
                    $this->outputData = [];
                    $this->success = true;
                    return;
                }
            }
        } catch (Exception $error) {
            $this->responseMessage = "Something is worng. Please try again";
            $this->outputData = [$error];
            $this->success = false;
            return;
        }
    }

    public function employeeAttendance(Request $request, Response $response)
    {
        $employee = DB::table('employees')->where('user_id', $this->user->id)->first();
        
        $rosterEmployee = DB::table('roster_employees as re')
            ->join('roster_assignments as ra', 're.roster_assignment_id', '=', 'ra.id')
            ->join('rosters as r', 'ra.roster_id', '=', 'r.id')
            ->join('duty_shifts as ds', 'r.duty_shift_id', '=', 'ds.id')
            ->where('re.employee_id', $employee->id)
            ->orderBy('re.id', 'desc')
            ->select('ds.start_time as roster_duty_start_time', 'ds.end_time as roster_duty_end_time')
            ->first();
        $attendanceSetting = DB::table('payroll_settings')->where('key_name', 'attendance_deduction_time')->orderBy('id', 'desc')->first();
        $bonusTime = $attendanceSetting->value;
    
        $rosterDutyStart = Carbon::createFromFormat('H:i:s', $rosterEmployee->roster_duty_start_time);
        $rosterDutyEnd = Carbon::createFromFormat('H:i:s', $rosterEmployee->roster_duty_end_time);
        $inTime = Carbon::createFromFormat('H:i:s', $this->params->in_time);
    
        // Add bonus time to in_time
        $adjustedInTime = $inTime->copy()->addMinutes($bonusTime);
    
        // Calculate the difference
        $lateInMinutes = $rosterDutyStart->diffInMinutes($adjustedInTime, false);
    
        if ($lateInMinutes > 0) {
            $lateInTime = $lateInMinutes;
        } else {
            $lateInTime = 0;
        }
    
        if ($this->params->check === "check-in") {
            $check_in_or_not = $this->attendance->where("employee_id", $this->user->id)
                ->whereDate("date", $this->params->date)->get();
    
            if (count($check_in_or_not) == 0) {
                $attendance = $this->attendance->create([
                    "employee_id" => $employee->id,
                    "date" => $this->params->date,
                    "in_time" => $this->params->in_time,
                    "late_in_time" => $lateInTime,
                    "status" => "present",
                    "created_by" => $this->user->id
                ]);
                $this->responseMessage = "Attendance successful";
                $this->outputData = $attendance;
                $this->success = true;
                return;
            } else {
                $this->responseMessage = "You are already checked-in";
                $this->outputData = [];
                $this->success = true;
                return;
            }
        }
    
        if ($this->params->check === "check-out") {
            $outTime = Carbon::createFromFormat('H:i:s', $this->params->out_time);
    
            // Calculate the late out time
            $lateOutMinutes = $rosterDutyEnd->diffInMinutes($outTime, false);
    
            if ($lateOutMinutes > 0) {
                $lateOutTime = $lateOutMinutes;
            } else {
                $lateOutTime = 0;
            }
    
            $attendance = $this->attendance
                ->where("employee_id", $employee->id)
                ->whereDate("date", $this->params->date)
                ->where("created_by", $this->user->id)
                ->update([
                    "out_time" => $this->params->out_time,
                    "late_out_time" => $lateOutTime
                ]);
            
            if ($attendance) {
                $this->responseMessage = "Checkout successful";
                $this->outputData = $attendance;
                $this->success = true;
                return;
            } else {
                $this->responseMessage = "First check-in";
                $this->outputData = [];
                $this->success = true;
                return;
            }
        }
    }
    





    public function activeUserAttendance()
    {

        if (isset($this->user->id)) {
            $userId = $this->user->id;

            $employee = DB::table('employees')->where('user_id', $this->user->id)->first();
            $result = $this->attendance->where("employee_id", $employee->id)->orderBy("date", "DESC")->get();

            if ($result->isEmpty()) {
                $this->responseMessage = "Not found any result";
            } else {
                $this->outputData = $result;
                $this->success = true;
            }
        } else {
            $this->responseMessage = "User ID is not provided";
        }
    }


    public function activeUserTodayAttendanceInfo()
    {
        $userId = $this->user->id;
        $currentDate = Carbon::now()->format('Y-m-d');
        $employee = DB::table('employees')->where('user_id', $userId)->first();
        // dd($employee);
        // return;
        $result = $this->attendance
            ->where("employee_id", $employee->id)
            ->whereDate("date", $currentDate)
            ->first();
            // dd($result);
            // return;
        if ($result) {
            $this->responseMessage = "Attendance details for today found.";
            $this->outputData = $result;
            $this->success = true;
        } else {
            $this->responseMessage = "Please Check In";
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function attendanceReconcilation(Request $request, Response $response)
    {

        // $this->validator->validate($request, [
        //     "time" => v::notEmpty(),
        //     "date" => v::notEmpty(),
        //     "reason" => v::notEmpty(),
        //     "type" => v::type(),
        // ]);

        // if ($this->validator->failed()) {
        //     $this->success = false;
        //     $this->responseMessage = $this->validator->errors;
        //     return;
        // }
        $reconcilation = DB::table('attendance_reconcilation')
            ->insert([
                "attendance_id" => $this->params->attendenceId,
                "employee_id" => $this->params->userId,
                "date" => $this->params->date,
                "time" => $this->params->time,
                "status" => "pending",
                "reason" => $this->params->reason,
                "type" => $this->params->type,
                "created_by" => $this->user->id
            ]);

        $this->responseMessage = "Reconcilation Create successfull";
        $this->outputData = $reconcilation;
        $this->success = true;
    }

    public function getAllReconcilation(Request $request, Response $response)
    {

        $result = DB::table('attendance_reconcilation')
            ->join('org_users', 'org_users.id', '=', 'attendance_reconcilation.employee_id')
            ->join('departments', 'departments.id', '=', 'org_users.role_id')
            ->select(
                'attendance_reconcilation.*',
                'org_users.name',
                'org_users.id as user_id',
                'org_users.role_id',
                'departments.id as department_id',
                'departments.name as department_name' // Assuming departments has a name field
            )
            ->where("attendance_reconcilation.status", "pending")
            ->get();

        $this->responseMessage = 'Reconciliation list fetch successful';
        $this->outputData = $result;
        $this->success = true;
    }






    public function updateReconciliation(Request $request, Response $response)
    {


        // $attendance = DB::table('attendance_reconcilation')->where("id", $this->params->id)->where("status", 0)->delete();
        $attendance = DB::table('attendance_reconcilation')
            ->where('id', '=', $this->params->id)
            ->update([
                'status' => $this->params->status,
                "updated_by" => $this->user->id,

            ]);

        $this->responseMessage = "laundry has been deleted successfully";
        $this->outputData =  $attendance;
        $this->success = true;
    }


    public function createIpAddress(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "ip_address" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $ipAddress = DB::table('attendance_ip')->insert([
            "ip_address" => $this->params->ip_address,
            "status" => $this->params->status,
            "created_by" => $this->user->id
        ]);
        $this->responseMessage = "Ip Create Successfully successfull";
        $this->outputData = $ipAddress;
        $this->success = true;
    }


    public function getAllIpAddress(Request $request, Response $response)
    {

        $result = DB::table('attendance_ip')
            ->get();

        $this->responseMessage = 'Ip list fetch successful';
        $this->outputData = $result;
        $this->success = true;
    }

    public function deleteIpAddress(Request $request, Response $response)
    {


        $ipAddress = DB::table('attendance_ip')
            ->where('id', '=', $this->params->address_id)
            ->delete();
        // ->update(['status' => 0]);


        $this->responseMessage = "Ip address has been deleted successfully";
        $this->outputData = $ipAddress;
        $this->success = true;
    }
    public function updateIpAddress(Request $request, Response $response)
    {
        $attendance = DB::table('attendance_ip')
            ->where('id', '=', $this->params->id)
            ->update([
                'ip_address' => $this->params->ip_address,
                'status' => $this->params->status,
                "updated_by" => $this->user->id
            ]);


        $this->responseMessage = "currencie has been updated successfully";
        $this->outputData = $attendance;
        $this->success = true;
    }
}
