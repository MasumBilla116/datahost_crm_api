<?php

namespace  App\Controllers\HRM;

use App\Auth\Auth;
use App\Models\HRM\Employee;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class HRM_DashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $employee;
    protected $newUser;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->employee = new Employee();
        $this->newUser = new ClientUsers();
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
            case 'allEmployee':
                $this->allEmployee();
                break;
            case 'todayEmpAttandance':
                $this->todayEmpAttandance();
                break;
            case 'monthlyLoanApplication':
                $this->monthlyLoanApplication();
                break;
            case 'monthlyLeaveApplication':
                $this->monthlyLeaveApplication();
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



    public function monthlyLeaveApplication()
    {
        $date = date('Y-m');
        $count = DB::table("leave_applications")->where("leave_status", 'Approved')->whereDate("date", '>=', $date . '-01')->count('id');
        $this->responseMessage = "success!";
        $this->outputData = $count;
        $this->success = true;
    }


    public function monthlyLoanApplication()
    {
        $date = date('Y-m');
        $count = DB::table("loan_applications")->where("status", 1)->whereDate("created_at", ">=", $date . '-01')->count('id');
        $this->responseMessage = "success!";
        $this->outputData = $count;
        $this->success = true;
    }



    public function todayEmpAttandance()
    {
        $date = date('Y-m-d');
        $today_attendance = DB::table("employee_attendance")->where("status", 'present')->whereDate("created_at", $date)->count('id');
        $this->responseMessage = "success!";
        $this->outputData = $today_attendance;
        $this->success = true;
    }



    //All Employee
    public function allEmployee()
    {
        $employee = DB::table('employees')
            ->where('employees.status', '=', 1)
            ->select('employees.*', 'departments.name as department_name', 'designations.name as designation_name')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('designations', 'employees.designation_id', '=', 'designations.id')
            ->get();
        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All Employee fetched successfully";
        $this->outputData = $employee;
        $this->success = true;
    }
}
