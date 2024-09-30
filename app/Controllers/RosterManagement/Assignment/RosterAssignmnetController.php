<?php

namespace  App\Controllers\RosterManagement\Assignment;

use App\Auth\Auth;
use App\Models\HRM\Employee;
use App\Models\RosterManagement\Roster;
use App\Models\RosterManagement\RosterAssignment;
use App\Models\RosterManagement\RosterEmployee;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RosterAssignmnetController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $rosterAssignment;
    protected $rosterEmployee;
    protected $employee;
    protected $roster;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->rosterAssignment = new RosterAssignment();  //table  -  roster_assignments
        $this->rosterEmployee = new RosterEmployee(); // table  - roster_employees
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->employee = new Employee();
        $this->roster = new Roster();
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

            case 'allRosterAssignments':
                $this->allRosterAssignments();
                break;
            case 'createRosterAssignment':
                $this->createRosterAssignment($request);
                break;
            case 'rosterAssignmentInfo':
                $this->rosterAssignmentInfo();
                break;
            case 'updateRosterAssignment':
                $this->updateRosterAssignment($request);
                break;
            case 'deleteRosterAssignment':
                $this->deleteRosterAssignment();
                break;
            case 'rosterEmployeeInfo':
                $this->rosterEmployeeInfo();
                break;
            case 'rosterDeptInfo':
                $this->rosterDeptInfo();
                break;  //allEmployee
            case 'rosterEmployeeBydate':
                $this->rosterEmployeeBydate();
                break;
            case 'allEmployee':
                $this->allEmployee();
                break;
            case 'rosterInfoEmployee':
                $this->rosterInfoEmployee();
                break;
            case 'allDeptInfoFromRoster':
                $this->allDeptInfoFromRoster();
                break;

            case 'allRosters':
                $this->allRosters();
                break;
            case 'allEmpInfoFromRoster':
                $this->allEmpInfoFromRoster();
                break;

            case 'empInfoFromRoster':
                $this->empInfoFromRoster();
                break;

                //allEmpInfoFromRoster empInfoFromRoster
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

    // public function createRosterAssignment(Request $request)
    // {
    //     $this->validator->validate($request, [
    //         "roster_id" => v::notEmpty(),
    //     ]);

    //     if ($this->validator->failed()) {
    //         $this->success = false;
    //         $this->responseMessage = $this->validator->errors;
    //         return;
    //     }

    //     unset($this->params->action);
    //     $rosterEmployee = $this->params->roster_employee;
    //     unset($this->params->roster_employee);
    //     $data = (array) $this->params;
    //     $data["created_by"] = $this->user->id;
    //     $rosterAssignment =  $this->rosterAssignment->create($data);
    //     $rosterAssignment->rosterEmployee()->createMany($rosterEmployee);

    //     $this->responseMessage = "RosterAssignment has been created successfully";
    //     $this->outputData = $rosterAssignment;
    //     $this->success = true;
    // }

    public function createRosterAssignment(Request $request)
    {
        $this->validator->validate($request, [
            "roster_id" => v::notEmpty(),
        ]);
    
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
    
        unset($this->params->action);
        $rosterEmployee = $this->params->roster_employee;
        unset($this->params->roster_employee);
        $data = (array) $this->params;
        $data["created_by"] = $this->user->id;
    
        // Find the existing roster assignment if it exists
        $rosterAssignment = $this->rosterAssignment->firstOrNew(['roster_id' => $data['roster_id']]);
        
        if ($rosterAssignment->exists) {
            // Update scenario: Check and add new employees only if they don't exist
            foreach ($rosterEmployee as $employee) {
                $exists = $rosterAssignment->rosterEmployee()
                    ->where('employee_id', $employee['employee_id'])
                    ->exists();
    
                if (!$exists) {
                    $rosterAssignment->rosterEmployee()->create($employee);
                }
            }
        } else {
            // Create scenario: Create the roster assignment and add employees
            $rosterAssignment->fill($data);
            $rosterAssignment->save();
            $rosterAssignment->rosterEmployee()->createMany($rosterEmployee);
        }
    
        $this->responseMessage = $rosterAssignment->wasRecentlyCreated
            ? "RosterAssignment has been created successfully"
            : "RosterAssignment has been updated successfully";
        $this->outputData = $rosterAssignment;
        $this->success = true;
    }
    


    public function rosterAssignmentInfo()
    {

        $rosterAssignment = $this->rosterAssignment->with(['roster', 'rosterEmployee', 'rosterEmployee.employee'])->where('status', 1)->find($this->params->id);

        if (!$rosterAssignment) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Roster Assignment fetched successfully";
        $this->outputData = $rosterAssignment;
        $this->success = true;
    }

    public function updateRosterAssignment(Request $request)
    {

        $this->validator->validate($request, [
            "roster_id" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        unset($this->params->action);
        $rosterEmployee = collect($this->params->roster_employee);
        unset($this->params->roster_employee);
        $data = (array) $this->params;
        $data["updated_by"] = $this->user->id;
        $prev_employees = $this->rosterAssignment->with('rosterEmployee')->where('status', 1)->find($this->params->id);

        // update and delete data 


        foreach ($prev_employees->rosterEmployee as $obj) {
            $check =  $rosterEmployee->where('employee_id', $obj->employee_id)->first();
            if ($check) {
                $obj->update([
                    "employee_id" => $check['employee_id']
                ]);
            } else {
                $obj->delete();
            }
        }

        // create new data 

        $rosters = collect($prev_employees->rosterEmployee);

        foreach ($rosterEmployee as $obj) {

            $check = $rosters->where('employee_id', $obj['employee_id'])->first();
            if (!$check) {
                $obj['roster_assignment_id'] = $data['id'];
                $obj["created_by"] = $this->user->id;
                $this->rosterEmployee->create($obj);
            }
        }

        $rosterAssignment = $this->rosterAssignment->where('status', 1)->find($this->params->id)->update($data);
        $this->responseMessage = "Roster Assignment has been updated successfully";
        $this->outputData = $rosterAssignment;
        $this->success = true;
    }


    public function allRosterAssignments()
    {

        $room_categories = $this->rosterAssignment->with(['roster', 'rosterEmployee', 'rosterEmployee.employee', 'rosterEmployee.employee.designation'])
            ->where('status', 1)
            // ->order_by('roster.id', 'desc')
            ->get();

        if (!$room_categories) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $room_categories;
        $this->success = true;
    }


    public function deleteRosterAssignment()
    {
        $rosterAssignment = $this->rosterAssignment->find($this->params->id);
        $rosterAssignment->rosterEmployee()->delete();
        $rosterAssignment->delete();
        if (!$rosterAssignment) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "Roster Assignment has been successfully deleted !";
        $this->success = true;
    }


    public function rosterDeptInfo()
    {


        $roster_dept_emp = DB::table('roster_employees')
            ->join('employees', 'employees.id', '=', 'roster_employees.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            // ->join('roster_employees','roster_employees.roster_assignment_id','=','roster_assignments.id')
            ->select(
                'roster_employees.id as roster_table_id',
                'roster_employees.roster_assignment_id as roster_assignment_id',
                'employees.id as employeeID',
                'employees.name as employeeName',
                'employees.department_id as department_id',
                'departments.name as department_name'
            )
            ->where('roster_employees.roster_assignment_id', $this->params->id)
            ->get();




        if (!$roster_dept_emp) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $roster_dept_emp;
        $this->success = true;
    }


    public function rosterEmployeeInfo()
    {


        $employee_info = DB::table('roster_employees')
            ->join('employees', 'employees.id', '=', 'roster_employees.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            // ->join('designations', 'designations.department_id', '=', 'departments.id')
            // ->join('roster_employees','roster_employees.roster_assignment_id','=','roster_assignments.id')
            ->select(
                'roster_employees.id as roster_table_id',
                'roster_employees.roster_assignment_id as roster_assignment_id',
                'employees.id as employeeID',
                'employees.name as employeeName',
                'employees.department_id as department_id',
                'departments.name as department_name'
            )
            ->where('roster_employees.roster_assignment_id', $this->params->id)
            ->get();


        if (!$employee_info) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $employee_info;
        $this->success = true;
    }

    public function rosterEmployeeBydate()
    {



        $roster_select_date = DB::table('rosters')
            ->where('start_date', $this->params->selectedDate)
            ->get();


        if (!$roster_select_date) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $roster_select_date;
        $this->success = true;
    }



    //All Employee
    public function allEmployee()
    {
        $employee =  DB::table('employees')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->join('designations', 'designations.id', '=', 'employees.designation_id')
            ->select(

                'employees.*',
                'departments.name as department_name',
                'designations.name as designations_name',

            )
            ->where('employees.status', 1)

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

    public function rosterInfoEmployee()
    {




        $employee_info = DB::table('roster_employees')
            ->join('roster_assignments', 'roster_assignments.id', '=', 'roster_employees.roster_assignment_id')
            ->join('rosters', 'rosters.id', '=', 'roster_assignments.roster_id')
            ->join('employees', 'employees.id', '=', 'roster_employees.employee_id')
            ->select(

                // 'employees.name as label',
                // 'rosters.id as roster_id',
                // 'rosters.name as roster_name',
                // 'roster_assignments.id as roster_assignments_id',
                'roster_employees.employee_id as value',
                'employees.name as label',

            )
            ->where('rosters.id', $this->params->id)
            ->get();
        $this->responseMessage = "All Employee fetched successfully";
        $this->outputData = $employee_info;
        $this->success = true;
    }




    public function allDeptInfoFromRoster()
    {


        $roster_dept_emp = DB::table('roster_employees')
        ->leftJoin('roster_assignments', 'roster_assignments.id', '=', 'roster_employees.roster_assignment_id')
        ->leftJoin('employees', 'employees.id', '=', 'roster_employees.employee_id')
        ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
        ->leftJoin('designations', 'designations.id', '=', 'employees.designation_id')
        ->leftJoin(
            DB::raw('(SELECT roster_assignments.roster_id, employees.department_id, COUNT(*) as assigned_employees 
                      FROM roster_employees
                      JOIN roster_assignments ON roster_assignments.id = roster_employees.roster_assignment_id
                      JOIN employees ON employees.id = roster_employees.employee_id
                      GROUP BY roster_assignments.roster_id, employees.department_id) as assigned_employee_count'),
            function($join) {
                $join->on('assigned_employee_count.roster_id', '=', 'roster_assignments.roster_id')
                     ->on('assigned_employee_count.department_id', '=', 'departments.id');
            }
        )
        ->select(
            'roster_assignments.id as id',
            'roster_assignments.roster_id as roster_id',
            'roster_employees.employee_id as employee_id',
            'employees.department_id as department_id',
            'employees.name as employeeName',
            'employees.designation_id as designation_id',
            'departments.name as department_name',
            'designations.name as designations_name',
            'assigned_employee_count.assigned_employees as assigned_employees',
            DB::raw('(SELECT COUNT(*) FROM employees WHERE department_id = departments.id) as total_employees')
        )
        ->where('roster_assignments.roster_id', $this->params->id)
        ->get();
    
        // ->orderBy('departments.id', 'desc')->first();
        // ->get();
                
        if (!$roster_dept_emp) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $roster_dept_emp;
        $this->success = true;
    }



    public function allRosters()
    {

        $all_rosters = DB::table('rosters')
            ->join('roster_assignments', 'roster_assignments.roster_id', '=', 'rosters.id')
            ->select(
                'rosters.*'
            )
            ->where('rosters.status', 1)
            ->get();

        if (!$all_rosters) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster fetched successfully";
        $this->outputData = $all_rosters;
        $this->success = true;
    }




    public function allEmpInfoFromRoster()
    {

        $roster_id_employee = $this->rosterAssignment->with(['roster', 'rosterEmployee.employee'])
            ->where('status', 1)
            ->where('roster_assignments.roster_id', $this->params->id)
            ->get();

        // $roster_id_employee = $this->rosterAssignment->with(['roster', 'rosterEmployee'])
        //     ->where('status', 1)
        //     ->where('roster_assignments.roster_id', $this->params->id)
        //     ->get();

        if (!$roster_id_employee) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $roster_id_employee;
        $this->success = true;
    }




    public function empInfoFromRoster()
    {

        $roster_assignments = isset($this->params->id) ? $this->params->id : null;
        $roster_id = isset($this->params->roster_id) ? $this->params->roster_id : null;
        $emp_id = isset($this->params->emp_id) ? $this->params->emp_id : null;

        if ($roster_assignments) {
            $roster_dept_emp = DB::table('roster_employees')
                ->leftJoin('roster_assignments', 'roster_assignments.id', '=', 'roster_employees.roster_assignment_id')
                ->leftJoin('employees', 'employees.id', '=', 'roster_employees.employee_id')
                ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                ->leftJoin('designations', 'designations.id', '=', 'employees.designation_id')
                ->select(
                    'roster_assignments.id as id',
                    'roster_assignments.roster_id as roster_id',
                    'roster_employees.employee_id as employee_id',
                    'employees.department_id as department_id',
                    'employees.name as employeeName',
                    'employees.designation_id as designation_id',
                    'departments.name as department_name',
                    'designations.name as designations_name'
                )
                ->where('employees.department_id', $this->params->department_id)
                ->where('roster_assignments.id', $this->params->id)
                // ->where('roster_assignments.roster_id', $this->params->roster_id)
                // ->where('roster_employees.employee_id', $this->params->emp_id)
                ->get();
        } else {
            $roster_dept_emp = DB::table('roster_employees')
                ->leftJoin('roster_assignments', 'roster_assignments.id', '=', 'roster_employees.roster_assignment_id')
                ->leftJoin('employees', 'employees.id', '=', 'roster_employees.employee_id')
                ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                ->leftJoin('designations', 'designations.id', '=', 'employees.designation_id')
                ->select(
                    'roster_assignments.id as id',
                    'roster_assignments.roster_id as roster_id',
                    'roster_employees.employee_id as employee_id',
                    'employees.department_id as department_id',
                    'employees.name as employeeName',
                    'employees.designation_id as designation_id',
                    'departments.name as department_name',
                    'designations.name as designations_name'
                )
                // ->where('roster_assignments.id', $this->params->id)
                ->get();
        }



        if (!$roster_dept_emp) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "All Roster Assignment fetched successfully";
        $this->outputData = $roster_dept_emp;
        $this->success = true;
    }
}
