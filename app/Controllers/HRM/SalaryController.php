<?php

namespace  App\Controllers\HRM;

use App\Auth\Auth;
use App\Models\HRM\Salary;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\HRM\AdvanceSalary;
use App\Models\Users\ClientUsers;

use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class SalaryController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $salary;
    protected $advance_salary;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->salary = new Salary();
        $this->advance_salary = new AdvanceSalary();

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
            case 'allSalaries':
                $this->allSalaries();
                break;

            case 'allAdvanceSalaries':
                $this->allAdvanceSalaries();
                break;

            case 'allSalarieList':
                $this->allSalarieList();
                break;

            case 'getAdvanceSalaryInfo':
                $this->getAdvanceSalaryInfo();
                break;

            case 'allAdvanceSalarieList':
                $this->allAdvanceSalarieList();
                break;

            case 'createSalary':
                $this->createSalary($request);
                break;



            case 'createAadvanceSalary':
                $this->createAadvanceSalary($request);
                break;

            case 'getSalaryInfo':
                $this->getSalaryInfo();
                break;

            case 'updateSalary':
                $this->updateSalary($request);
                break;

            case 'updateAdvanceSalary':
                $this->updateAdvanceSalary($request);
                break;
            case 'deleteSalary':
                $this->deleteSalary();
                break;

            case 'deleteAdvanceSalary':
                $this->deleteAdvanceSalary();
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

    public function allSalaries()
    {
        $salary = DB::table("salary")
            ->join('employees', 'salary.employee_id', '=', 'employees.id')
            ->where('salary.status', 1)
            ->select('salary.*', 'employees.name as employees_name')
            ->orderBy('salary.id', 'desc')
            ->get();

        $this->responseMessage = "Salary list fetched successfully";
        $this->outputData = $salary;
        $this->success = true;
    }

    public function allAdvanceSalaries()
    {
        $salary = DB::table("emp_salary_advance")
            ->join('employees', 'emp_salary_advance.emp_id', '=', 'employees.id')
            ->where('emp_salary_advance.status', 1)
            ->select('emp_salary_advance.*', 'employees.name as employees_name')
            ->orderBy('emp_salary_advance.id', 'desc')
            ->get();

        $this->responseMessage = "Salary list fetched successfully";
        $this->outputData = $salary;
        $this->success = true;
    }


    public function allSalarieList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table("salary")
            ->join('employees', 'salary.employee_id', '=', 'employees.id')
            ->where('salary.status', 1)
            ->select('salary.*', 'employees.name as employees_name');


        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('salary.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('salary.status', '=', 0);
        }


        // if (isset($filter['yearMonth'])) {
        //     $query->whereYear('salary.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('salary.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('employees.name', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_sallary =  $query->orderBy('salary.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();

        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Salary list fetched successfully";
        $this->outputData = [
            $pageNo => $all_sallary,
            'total' => $totalRow,
        ];
        $this->success = true;
    }


    public function allAdvanceSalarieList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table("emp_salary_advance")
            ->join('employees', 'emp_salary_advance.emp_id', '=', 'employees.id')
            ->where('emp_salary_advance.status', 1)
            ->select('emp_salary_advance.*', 'employees.name as employees_name');


        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('emp_salary_advance.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('emp_salary_advance.status', '=', 0);
        }


        // if (isset($filter['yearMonth'])) {
        //     $query->whereYear('emp_salary_advance.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('emp_salary_advance.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('employees.name', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_sallary =  $query->orderBy('emp_salary_advance.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();

        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "emp_salary_advance list fetched successfully";
        $this->outputData = [
            $pageNo => $all_sallary,
            'total' => $totalRow,
        ];
        $this->success = true;
    }


    public function createSalary($request)
    {
        $this->validator->validate($request, [
            "employeeId" => v::notEmpty(),
            "amount" => v::notEmpty(),
            "salary_month" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $salary = $this->salary
            ->create([

                "employee_id" => $this->params->employeeId,
                "amount" => $this->params->amount,
                "salary_month" => $this->params->salary_month,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);



        $this->responseMessage = "Salary has been created successfully!";
        $this->outputData = $salary;
        $this->success = true;
    }

    public function createAadvanceSalary($request)
    {
        $this->validator->validate($request, [
            "employeeId" => v::notEmpty(),
            "amount" => v::notEmpty(),
            "salary_month" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $salary = $this->advance_salary
            ->create([

                "emp_id" => $this->params->employeeId,
                "salary_amount" => $this->params->amount,
                "salary_month" => $this->params->salary_month,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);



        $this->responseMessage = "Salary has been created successfully!";
        $this->outputData = $salary;
        $this->success = true;
    }


    public function getSalaryInfo()
    {
        if (!isset($this->params->salary_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $salary = $this->salary
            ->join('employees', 'employees.id', '=', 'salary.employee_id')
            ->select(
                'salary.*',
                'employees.name as employee_name',

            )
            ->find($this->params->salary_id);

        if ($salary->status == 0) {
            $this->success = false;
            $this->responseMessage = "salary missing!";
            return;
        }

        if (!$salary) {
            $this->success = false;
            $this->responseMessage = "salary not found!";
            return;
        }

        $this->responseMessage = "salary info fetched successfully";
        $this->outputData = $salary;
        $this->success = true;
    }


    public function getAdvanceSalaryInfo()
    {
        if (!isset($this->params->salary_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $salary = $this->advance_salary
            ->join('employees', 'employees.id', '=', 'emp_salary_advance.emp_id')
            ->select(
                'emp_salary_advance.*',
                'employees.name as employee_name',

            )
            ->find($this->params->salary_id);

        if ($salary->status == 0) {
            $this->success = false;
            $this->responseMessage = "salary missing!";
            return;
        }

        if (!$salary) {
            $this->success = false;
            $this->responseMessage = "salary not found!";
            return;
        }

        $this->responseMessage = "salary info fetched successfully";
        $this->outputData = $salary;
        $this->success = true;
    }



    public function updateSalary(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "salary_id" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $salary = $this->salary->where(['id' => $this->params->salary_id, 'status' => 1])
            ->update([


                "employee_id" => $this->params->employeeId,
                "amount" => $this->params->amount,
                "salary_month" => $this->params->salary_month,
                'updated_by' => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Booking note has been updated successfully !";
        $this->outputData = $salary;
        $this->success = true;
    }


    public function updateAdvanceSalary(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "salary_id" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $salary = $this->advance_salary->where(['id' => $this->params->salary_id, 'status' => 1])
            ->update([


                "emp_id" => $this->params->emp_id,
                "salary_amount" => $this->params->salary_amount,
                "salary_month" => $this->params->salary_month,
                'updated_by' => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Advance Salary been updated successfully !";
        $this->outputData = $salary;
        $this->success = true;
    }



    public function deleteSalary()
    {
        if (!isset($this->params->salary_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $salary = $this->salary->find($this->params->salary_id);

        if (!$salary) {
            $this->success = false;
            $this->responseMessage = "Salary not found!";
            return;
        }

        $deletedSalary = $salary->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Salary Deleted successfully";
        $this->outputData = $deletedSalary;
        $this->success = true;
    }


    public function deleteAdvanceSalary()
    {
        if (!isset($this->params->salary_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $salary = $this->advance_salary->find($this->params->salary_id);

        if (!$salary) {
            $this->success = false;
            $this->responseMessage = "Salary not found!";
            return;
        }

        $deletedSalary = $salary->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Salary Deleted successfully";
        $this->outputData = $deletedSalary;
        $this->success = true;
    }
}
