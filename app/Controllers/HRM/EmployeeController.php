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

class EmployeeController
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

            case 'allEmployeeList':
                $this->allEmployeeList();
                break;
            case 'allUsers':
                $this->allUsers();
                break;
            case 'createEmployeeId':
                $this->createEmployeeId($request, $response);
                break;

            case 'addEmployee':
                // $this->createEmployee($request, $response);
                $this->createEmployeeNew($request, $response);
                break;
            case 'saveUserCredential':
                $this->saveUserCredential($request, $response);
                break;
            case 'getEmployeeInfo':
                $this->getEmployeeInfo();
                break;


            case 'updateEmployeeInfo':
                $this->updateEmployeeInfo($request, $response);
                break;
            case 'updateEmployeeSalaryInfo':
                $this->updateEmployeeSalaryInfo($request, $response);
                break;
            case 'deleteEmployee':
                $this->deleteEmployee($request, $response);
                break;

            case 'getEmployeeAdditionInfo':
                $this->getEmployeeAdditionInfo();
                break;

            case 'getEmployeeDeductionInfo':
                $this->getEmployeeDeductionInfo();
                break;

            case 'addAdditionInfo':
                $this->addAdditionInfo($request, $response);
                break;

            case 'deleteSalarySettingsItem':
                $this->deleteSalarySettingsItem($request, $response);
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




    public function allEmployeeList()
    {


        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('employees')
            // ->where('employees.status', '=', 1)
            ->select('employees.*', 'departments.name as department_name', 'designations.name as designation_name')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('designations', 'employees.designation_id', '=', 'designations.id');
        // ->get();
        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('employees.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('employees.status', '=', 0);
        }

        // if (isset($filter['yearMonth'])) {
        //     $query->whereYear('employees.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('employees.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('employees.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('employees.email', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('employees.mobile', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('departments.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('designations.name', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_employee =  $query->orderBy('employees.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "All Employee fetched successfully";
        // $this->outputData = $query;
        $this->outputData = [
            $pageNo => $all_employee,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    //All Users
    public function allUsers()
    {
        $employee = $this->employee->where('status', 1)->where('user_id', '!=', null)->get();
        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All Employee fetched successfully";
        $this->outputData = $employee;
        $this->success = true;
    }

    //Add employee
    public function createEmployeeId(Request $request, Response $response)
    {
        $employee_id = DB::table('employees')->insertGetId([]);
        $this->responseMessage = "New employee has been created successfully";
        $this->outputData = $employee_id;
        $this->success = true;
    }
    public function createEmployee(Request $request, Response $response)
    {
        try {

            DB::beginTransaction();
            $org_user_id = null;
            if (isset($this->params->userEmail) && !empty($this->params->userEmail)) {
                $check_exist = DB::table("org_users")->where("email", $this->params->userEmail)->first();
                if ($check_exist) {
                    $this->responseMessage = "Login Credential is exist";
                    $this->outputData = [];
                    $this->success = false;
                    return;
                }
                $org_user_id = DB::table('org_users')   //Bank Table
                    ->insertGetId([
                        "name" => $this->params->name,
                        "email" => $this->params->userEmail,
                        "phone" => $this->params->mobile,
                        // "password" => password_hash($this->params->password, PASSWORD_DEFAULT),
                        "password" => $this->params->password,
                        // "company" => $this->params->company,
                        "clientID" => 0,
                        "address" => $this->params->address,
                        "role_id" => $this->params->role_id,
                        "country_id" => $this->params->country_id,
                        "city_id" => $this->params->city_id,
                        "state_id" => $this->params->state_id,
                        "status" => 1,
                        "created_by" => $this->user->id,
                        "name" => $this->params->name,
                        "data_access_type" => $this->params->data_access_type
                    ]);
            }


            // check liability hrm is exist or not
            // if not exist then insert otherwise get hrm id
            $liability_hrm = DB::table("account_sectors")->select("*")->where("account_type", 'liability')->where("title", 'HRM')->where("status", 1)->first();
            $under_head = null;
            if ($liability_hrm) {
                $under_head = $liability_hrm->parent_id;
            } else {
                $check_sector = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Current Liabilities")->where("status", 0)->first();
                if ($check_sector) {
                    $check_sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                    if ($check_sundry_creditors) {
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'HRM',
                            'parent_id' => $check_sundry_creditors->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                    } else {
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'Sundry Creditors',
                            'parent_id' => $check_sector->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                        $check_sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'HRM',
                            'parent_id' => $check_sundry_creditors->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                    }
                } else {
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'Current Liabilities',
                        'parent_id' =>  0,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                    $sector_head = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Current Liabilities")->where("status", 1)->first();
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'Sundry Creditors',
                        'parent_id' => $sector_head->id,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                    $sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'HRM',
                        'parent_id' => $sundry_creditors->id,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                }
                $liability_hrm = DB::table("account_sectors")->select("*")->where("account_type", 'liability')->where("title", 'HRM')->where('status', 1)->first();
                $under_head = $liability_hrm->parent_id;
            }

            // start create account sector
            DB::table('account_sectors')->insert([
                'account_type' => 'liability',
                'title' => $this->params->name,
                'parent_id' => $under_head,
                "created_by" => $this->user->id,
                'status' => 1,
            ]);

            $last_sector_id = DB::table("account_sectors")->select("id")->orderBy('id', "desc")->limit(1)->first();
            // end account sector

            $employee_id = DB::table('employees')   //Bank Table
                ->insertGetId([
                    "name" => $this->params->name,
                    "designation_id" => $this->params->designation_id,
                    "department_id" => $this->params->department_id,
                    "sector_id" => $last_sector_id->id, // set account sector id
                    "company" => $this->user->company ?? null,
                    "clientID" => 0,
                    "address" => $this->params->address,
                    "gender" => $this->params->gender,
                    "salary_type" => $this->params->salaryType,
                    "salary_amount" => $this->params->basicAmmount ||  0,
                    "description" => $this->params->description ?? null,
                    "mobile" => $this->params->mobile,
                    "email" => $this->params->email,
                    "attendance_time" => $this->params->attendanceTime,
                    "employee_type" => $this->params->employeeType,
                    "acc_number" => $this->params->accNumber,
                    "bank_name" => $this->params->bankName,
                    "bban_number" => $this->params->bbanNumber,
                    "branch_address" => $this->params->branchAddress,
                    "basic_ammount" => $this->params->basicAmmount || 0,
                    "transport_allowance" => $this->params->transportAllowance || 0,
                    "gross_salary" => $this->params->grossSalary || 0,
                    "tin_no" => $this->params->tinNo,
                    "rate_type" => $this->params->rateType,
                    "rate" => $this->params->rate,
                    "hire_date" => $this->params->hire_date,
                    "join_date" => $this->params->join_date,
                    "birth_date" => $this->params->birth_date,
                    "marital_status" => $this->params->maritalStatus,
                    "city_residence" => $this->params->cityResidence,
                    "work_inCity" => $this->params->workInCity,
                    "work_permit" => $this->params->workPermit,
                    "photos" => json_encode($this->params->upload_ids),
                    "user_id" => $org_user_id,
                    "created_by" => $this->user->id,
                    "status" => 1,

                ]);


            $emargency =  DB::table("employee_emergency_contact")
                ->insert([

                    "employee_id" => $employee_id,
                    "contact_person" => $this->params->contactPerson,
                    "contact_number" => $this->params->contactNumber,
                    "home_phone" => $this->params->homePhone,
                    "work_phone" => $this->params->workPhone,
                    "contact_relationship" => $this->params->contactRelationship,
                    "alter_contact" => $this->params->alterContact,
                    "alt_home_phone" => $this->params->althomePhone,
                    "alt_work_Phone" => $this->params->altWorkPhone,
                ]);


            DB::commit();
            $this->responseMessage = "New employee has been created successfully";
            $this->outputData = $employee_id;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollBack();
            // $this->responseMessage = $e->getMessage();
            $this->responseMessage = "Something is worng";
            $this->outputData = [];
            $this->success = false;
        }
    }

    
    public function saveUserCredential(Request $request, Response $response)
    {
        $org_user = DB::table('org_users')
            ->where('email', '=', $this->params->email)
            ->update([
                "password" => $this->params->password,
                "data_access_type" => $this->params->data_access_type
            ]);

            $this->responseMessage = "New employee has been created successfully";
            $this->outputData = $org_user;
            $this->success = true;
    }

    public function createEmployeeNew(Request $request, Response $response)
    {
        try {

            DB::beginTransaction();
            $org_user_id = null;
            // if ($this->params->is_user ?? false) {

            if (isset($this->params->email) && !empty($this->params->email)) {
                $check_exist = DB::table("org_users")->where("email", $this->params->email)->first();
                if ($check_exist) {
                    $this->responseMessage = "Login Credential is exist";
                    $this->outputData = [];
                    $this->success = false;
                    return;
                }
                $org_user_id = DB::table('org_users')   //Bank Table
                    ->insertGetId([
                        "name" => $this->params->name,
                        "email" => $this->params->email,
                        "phone" => $this->params->mobile,
                        // "password" => password_hash($this->params->password, PASSWORD_DEFAULT),
                        "password" => $this->params->password,
                        // "company" => $this->params->company,
                        "clientID" => 0,
                        "address" => $this->params->address,
                        "role_id" => $this->params->role_id,
                        "country_id" => $this->params->country_id,
                        "city_id" => $this->params->city_id,
                        "state_id" => $this->params->state_id,
                        "status" => 1,
                        "created_by" => $this->user->id,
                        "name" => $this->params->name,
                        "data_access_type" => $this->params->data_access_type
                    ]);
            }
            // }


            // check liability hrm is exist or not
            // if not exist then insert otherwise get hrm id
            $liability_hrm = DB::table("account_sectors")->select("*")->where("account_type", 'liability')->where("title", 'HRM')->where("status", 1)->first();
            $under_head = null;
            if ($liability_hrm) {
                $under_head = $liability_hrm->parent_id;
            } else {
                $check_sector = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Current Liabilities")->where("status", 0)->first();
                if ($check_sector) {
                    $check_sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                    if ($check_sundry_creditors) {
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'HRM',
                            'parent_id' => $check_sundry_creditors->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                    } else {
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'Sundry Creditors',
                            'parent_id' => $check_sector->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                        $check_sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                        DB::table("account_sectors")->insert([
                            'account_type' => 'liability',
                            'title' => 'HRM',
                            'parent_id' => $check_sundry_creditors->id,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                    }
                } else {
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'Current Liabilities',
                        'parent_id' =>  0,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                    $sector_head = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Current Liabilities")->where("status", 1)->first();
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'Sundry Creditors',
                        'parent_id' => $sector_head->id,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                    $sundry_creditors = DB::table("account_sectors")->select("*")->where('account_type', "liability")->where('title', "Sundry Creditors")->where("status", 1)->first();
                    DB::table("account_sectors")->insert([
                        'account_type' => 'liability',
                        'title' => 'HRM',
                        'parent_id' => $sundry_creditors->id,
                        "created_by" => $this->user->id,
                        'status' => 1,
                    ]);
                }
                $liability_hrm = DB::table("account_sectors")->select("*")->where("account_type", 'liability')->where("title", 'HRM')->where('status', 1)->first();
                $under_head = $liability_hrm->parent_id;
            }

            // start create account sector
            DB::table('account_sectors')->insert([
                'account_type' => 'liability',
                'title' => $this->params->name ?? '',
                'parent_id' => $under_head,
                "created_by" => $this->user->id,
                'status' => 1,
            ]);

            $last_sector_id = DB::table("account_sectors")->select("id")->orderBy('id', "desc")->limit(1)->first();
            // end account sector

            $employee_id = DB::table('employees')
                ->where('id', '=', $this->params->employee_id)  //Bank Table
                ->update([
                    "name" => $this->params->name,
                    "designation_id" => $this->params->designation_id,
                    "department_id" => $this->params->department_id,
                    "roster_id" => $this->params->roster_id,
                    "sector_id" => $last_sector_id->id, // set account sector id
                    "company" => $this->user->company ?? null,
                    "clientID" => 0,
                    "address" => $this->params->address,
                    "gender" => $this->params->gender,
                    "salary_type" => $this->params->salaryType,
                    "salary_amount" => $this->params->basicAmmount ||  0,
                    "description" => $this->params->description ?? null,
                    "mobile" => $this->params->mobile,
                    "email" => $this->params->email,
                    "attendance_time" => $this->params->attendanceTime,
                    "employee_type" => $this->params->employeeType,
                    "acc_number" => $this->params->accNumber,
                    "bank_name" => $this->params->bankName,
                    "bban_number" => $this->params->bbanNumber,
                    "branch_address" => $this->params->branchAddress,
                    "basic_ammount" => $this->params->basicAmmount || 0,
                    "transport_allowance" => $this->params->transportAllowance || 0,
                    "gross_salary" => $this->params->grossSalary || 0,
                    "tin_no" => $this->params->tinNo,
                    "rate_type" => $this->params->rateType,
                    "rate" => $this->params->rate,
                    "hire_date" => $this->params->hire_date,
                    "join_date" => $this->params->join_date,
                    "regine_date" => $this->params->regine_date,
                    "birth_date" => $this->params->birth_date,
                    "marital_status" => $this->params->maritalStatus,
                    "city_residence" => $this->params->cityResidence,
                    "work_inCity" => $this->params->workInCity,
                    "work_permit" => $this->params->workPermit,
                    "photos" => json_encode($this->params->upload_ids),
                    "emp_status" => $this->params->emp_status,
                    "user_id" => $org_user_id,
                    "created_by" => $this->user->id,
                    "status" => 1,

                ]);


            $emargency =  DB::table("employee_emergency_contact")
                ->insert([

                    "employee_id" => $this->params->employee_id,
                    "contact_person" => $this->params->contactPerson,
                    "contact_number" => $this->params->contactNumber,
                    "home_phone" => $this->params->homePhone,
                    "work_phone" => $this->params->workPhone,
                    "contact_relationship" => $this->params->contactRelationship,
                    "alter_contact" => $this->params->alterContact,
                    "alt_home_phone" => $this->params->althomePhone,
                    "alt_work_Phone" => $this->params->altWorkPhone,
                ]);


            DB::commit();
            $this->responseMessage = "New employee has been created successfully";
            $this->outputData = $employee_id;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollBack();
            // $this->responseMessage = $e->getMessage();
            $this->responseMessage = "Something is worng";
            $this->outputData = [];
            $this->success = false;
        }
    }
    //Get employee details
    public function getEmployeeInfo()
    {
        if (!isset($this->params->employee_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $employee = $this->employee->findOrFail($this->params->employee_id);
        // $users = DB::table("org_users")->where("id", $employee->user_id)->first();
        // echo ($employee->user_id);
        // return;
        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "Employee not found!";
            return;
        }


        $account_sector = DB::table("account_sectors")->select("*")->where("id", $employee->sector_id)->first();
        // print_r($account_sector);
        // return;

        $this->success = true;
        $this->responseMessage = "Employee info fetched successfully!";
        $this->outputData = $employee;
        $this->outputData['creator'] = $employee->creator;
        $this->outputData['department'] = $employee->department;
        $this->outputData['designation'] = $employee->designation;
        $this->outputData['sector_head'] = $account_sector->account_type;
        $this->outputData['sector_id'] = $account_sector->id;
        $this->outputData['sector_parent_id'] = $account_sector->parent_id;
        if ($employee->user_id != null) {
            $this->outputData['user'] = $employee->user;
            $this->outputData['user_role'] = $employee->user->role;
            $this->outputData['user_country'] = $employee->user->country;
            $this->outputData['user_state'] = $employee->user->state;
            $this->outputData['user_city'] = $employee->user->city;
            // $this->outputData['data_access_type'] = $users->data_access_type;
            $userData = DB::table("org_users")->select("data_access_type")->where("id", $employee->user_id)->first();

            if ($userData !== null) {
                $this->outputData['data_access_type'] = $userData->data_access_type;
            } else {
                // Handle the case where $userData is null
                // For example, you can set a default value
                $this->outputData['data_access_type'] = null; // Or any default value you prefer
            }
        }
    }

    //update employee
    public function updateEmployeeInfo(Request $request, Response $response)
    {

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "designation_id" => v::notEmpty(),
            "department_id" => v::notEmpty(),
            "address" => v::notEmpty(),
            "gender" => v::notEmpty(),
            "mobile" => v::notEmpty(),
            "email" => v::notEmpty(),
        ]);
        //status validation
        v::intVal()->notEmpty()->validate($this->params->status);

        //if is_user checked then validation check
        if ($this->params->is_user == 1) {
            $this->validator->validate($request, [
                "role_id" => v::notEmpty(),
                "country_id" => v::notEmpty(),
                "city_id" => v::notEmpty(),
                "state_id" => v::notEmpty(),
                // "user_status"=>v::notEmpty()
            ]);
            //status validation
            v::intVal()->notEmpty()->validate($this->params->user_status);


            //password checking
            // if ($this->params->user_defined_password != true) {
            //     $this->validator->validate($request, [
            //         "password" => v::notEmpty()
            //     ]);

            //     if ($this->validator->failed()) {
            //         $this->success = false;
            //         $this->responseMessage = $this->validator->errors;
            //         return;
            //     }
            // }
        }

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate employee
        // $employee = $this->employee->where(["email"=>$this->params->email])->first();
        // if ($employee && $employee->id != $this->params->employee_id) {
        //     $this->success = false;
        //     $this->responseMessage = "Employee with the same email has already exists!";
        //     return;
        // }

        if (!isset($this->params->employee_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $employee = $this->employee->find($this->params->employee_id);

        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "Employee not found!";
            return;
        }

        $employee->name = $this->params->name;
        $employee->name = $this->params->name;
        $employee->designation_id = $this->params->designation_id;
        $employee->department_id = $this->params->department_id;
        // $employee->company = $this->user->company;
        $employee->clientID = 0;
        $employee->address = $this->params->address;
        $employee->gender = $this->params->gender;
        // $employee->salary_type = $this->params->salary_type;
        // $employee->salary_amount = $this->params->salary_amount;
        $employee->description = $this->params->description;
        $employee->mobile = $this->params->mobile;
        $employee->email = $this->params->email;
        $employee->created_by = $this->user->id;
        $employee->roster_id = $this->params->roster_id;
        $employee->regine_date = $this->params->regine_date;
        $employee->join_date = $this->params->join_date;
        $employee->emp_status = $this->params->emp_status;
        //if is_user checked then create an user

        $user = $this->newUser->find($employee->user->id);

        if ($this->params->is_user === true) {
            if (!$user) {
                $user = $this->newUser;
            }

            $user->name = $this->params->name;
            $user->email = $this->params->email;
            if (!$this->params->user_defined_password) {
                // $user->password = password_hash($this->params->password, PASSWORD_DEFAULT); //Pass valid
                // $user->password = $this->user->password;

                $user->password = $this->params->password;
            } else {

                $user->password = $this->user->password;
            }

            $user->phone = $this->params->mobile;
            $user->data_access_type = $this->params->data_access_type;
            // $user->company = $this->user->company;
            $user->clientID = 0;
            $user->address = $this->params->address;
            $user->role_id = $this->params->role_id;
            $user->country_id = $this->params->country_id;
            $user->city_id = $this->params->city_id;
            $user->state_id = $this->params->state_id;
            $user->status = 1;
            $user->created_by = $this->user->id;
            $user->save();
        }
        if ($this->params->is_user === false) {
            if ($user) {
                $user->status = 0;
                $user->save();
            }
        }

        $employee->user_id = $user->id;
        $employee->save();

        // update account sectors
        DB::table("account_sectors")
            ->where("id", $this->params->sector_id)
            ->update([
                "account_type" => $this->params->sector_head,
                "title" => $this->params->name,
                "parent_id" => $this->params->sector_parent_id,
                "status" => 1,
            ]);

        $this->responseMessage = "Employee has been updated successfully";
        $this->outputData = $employee;
        $this->success = true;
    }

    public function updateEmployeeSalaryInfo(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "salary_type" => v::notEmpty(),
            "salary_amount" => v::notEmpty(),
        ]);

        if (!isset($this->params->employee_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'employee_id' is missing.";
            return;
        }


        if (
            !isset($this->params->additions) || !is_array($this->params->additions) ||
            !isset($this->params->deductions) || !is_array($this->params->deductions)
        ) {
            $this->success = false;
            $this->responseMessage = "'additions' and 'deductions' must be non-empty arrays.";
            return;
        }

        $employee = $this->employee->find($this->params->employee_id);

        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "Employee not found!";
            return;
        }


        $employeeSalary = DB::table("employees")
            ->where("id", $this->params->employee_id)
            ->update([
                "salary_type" => $this->params->salary_type,
                "salary_amount" => $this->params->salary_amount,
            ]);


        foreach ($this->params->additions as $addition) {
            $existingRecord = DB::table("employee_salary_settings")
                ->where('emp_id', $this->params->employee_id)
                ->where('id', $addition['addition_id'])
                ->first();

            if ($existingRecord) {
                DB::table("employee_salary_settings")
                    ->where('emp_id', $this->params->employee_id)
                    ->where('id', $addition['addition_id'])
                    ->update([
                        'amount' => $addition['addition_amount'],
                        'type_id' => $addition['addition_typeId'],
                        "updated_by" => $this->user->id,
                    ]);
            } else {
                DB::table("employee_salary_settings")->insert([
                    'emp_id' => $this->params->employee_id,
                    'type_id' => $addition['addition_typeId'],
                    'amount' => $addition['addition_amount'],
                    "created_by" => $this->user->id,
                ]);
            }
        }


        foreach ($this->params->deductions as $deduction) {
            $existingRecord = DB::table("employee_salary_settings")
                ->where('emp_id', $this->params->employee_id)
                ->where('id', $deduction['deduction_id'])
                ->first();

            if ($existingRecord) {
                DB::table("employee_salary_settings")
                    ->where('emp_id', $this->params->employee_id)
                    ->where('id', $deduction['deduction_id'])
                    ->update([
                        // 'type_id' => $addition['deduction_typeId'],
                        'amount' => $deduction['deduction_amount'],
                        "updated_by" => $this->user->id,
                        'type_id' => $deduction['deduction_typeId'],
                    ]);
            } else {
                DB::table("employee_salary_settings")->insert([
                    'emp_id' => $this->params->employee_id,
                    'type_id' => $deduction['deduction_typeId'],
                    'amount' => $deduction['deduction_amount'],
                    "created_by" => $this->user->id,
                ]);
            }
        }

        $this->responseMessage = "Employee Salary has been updated successfully";
        $this->outputData = $employeeSalary;
        $this->success = true;
    }


    //Delete Employee
    public function deleteEmployee(Request $request, Response $response)
    {
        $employee = $this->employee->find($this->params->employee_id);
        if (!$employee) {
            $this->success = false;
            $this->responseMessage = "Employee not found !";
            return;
        }

        $employee->status = 0;
        $employee->save();

        $user = $this->newUser->find($employee->user->id);
        if ($user) {
            $user->status = 0;
            $user->save();
        }

        $this->responseMessage = "Employee has been deleted successfully";
        $this->success = true;
    }


    public function getEmployeeAdditionInfo()
    {
        $payslip = DB::table('employee_salary_settings')
            ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
            // ->join('employees', 'employees.id', '=', 'employee_salary_settings.emp_id')
            ->select(
                'employee_salary_settings.id as addition_id',
                'employee_salary_settings.type_id as addition_typeId',
                'employee_salary_settings.amount as addition_amount',
                'addition_deduction_type.name as addition_typeName'
            )
            ->where(['employee_salary_settings.emp_id' => $this->params->employee_id])
            // ->where(['addition_deduction_type.type' => 'additton'])
            ->where(['employee_salary_settings.add_ded_type' => 'additton'])
            ->get();

        // addition_typeId: '', addition_typeName: '', addition_amount: ''
        $this->responseMessage = "Data Fetched successfully!";
        $this->outputData = $payslip;
        $this->success = true;
    }

    public function getEmployeeDeductionInfo()
    {
        $payslip = DB::table('employee_salary_settings')
            ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
            // ->join('employees', 'employees.id', '=', 'employee_salary_settings.emp_id')
            ->select(
                'employee_salary_settings.id as deduction_id',
                'employee_salary_settings.type_id as deduction_typeId',
                'employee_salary_settings.amount as deduction_amount',
                'addition_deduction_type.name as deduction_typeName',

            )
            ->where(['employee_salary_settings.emp_id' => $this->params->employee_id])
            // ->where(['addition_deduction_type.type' => 'deduction'])
            ->where(['employee_salary_settings.add_ded_type' => 'deduction'])
            ->get();

        // addition_typeId: '', addition_typeName: '', addition_amount: ''
        $this->responseMessage = "Data Fetched successfully!";
        $this->outputData = $payslip;
        $this->success = true;
    }


    public function addAdditionInfo(Request $request, Response $response)
    {
        $type_id = DB::table('employee_salary_settings')
            ->insertGetId([
                "created_by" => $this->user->id,
                'emp_id' => $this->params->employee_id,
                'add_ded_type' => $this->params->add_ded_type,
            ]);

        $this->responseMessage = "New employee has been created successfully";
        $this->outputData = $type_id;
        $this->success = true;
    }

    public function deleteSalarySettingsItem(Request $request, Response $response)
    {


        $settingsItem = DB::table('employee_salary_settings')
            ->where('id', '=', $this->params->id)
            ->delete();
        // ->update(['status' => 0]);


        $this->responseMessage = "Deleted successfully";
        $this->outputData = $settingsItem;
        $this->success = true;
    }
}
