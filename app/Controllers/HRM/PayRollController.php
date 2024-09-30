<?php

namespace  App\Controllers\HRM;

use DateTime;

use App\Auth\Auth;
use Carbon\Carbon;
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

class PayRollController
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


            case 'createAddDeddType':
                $this->createAddDeddType($request);
                break;

            case 'getAllAddDeddType':
                $this->getAllAddDeddType($request, $response);
                break;

            case 'getAddDeddTypeInfo':
                $this->getAddDeddTypeInfo();
                break;

            case 'updateAddDeddType':
                $this->updateAddDeddType($request);
                break;

            case 'deleteAddDeddType':
                $this->deleteAddDeddType();
                break;

            case 'crtAndUptAtnStngs':
                $this->crtAndUptAtnStngs($request);
                break;

            case 'crtAndUptBonusStngs':
                $this->crtAndUptBonusStngs($request);
                break;

            case 'crtAndUptTypeStngs':
                $this->crtAndUptTypeStngs($request);
                break;
            case 'getLatAttendanceInfo':
                $this->getLatAttendanceInfo();
                break;
            case 'getGnrlStngInfo':
                $this->getGnrlStngInfo();
                break;

            case 'generateMonthlySalary':
                $this->generateMonthlySalary($request);
                break;
            case 'getAllMonthlySalary':
                $this->getAllMonthlySalary();
                break;

            case 'getPayslipInfo':
                $this->getPayslipInfo();
                break;
                // getPayslipInfo
            case 'updateStatusOfSalaryMonth':
                $this->updateStatusOfSalaryMonth($request, $response);
                break;
            case 'deleteMonthlySalary':
                $this->deleteMonthlySalary($request, $response);
                break;

            case 'getAllBonusList':
                $this->getAllBonusList($request, $response);
                break;
            case 'getSpecificMonthlySalary':
                $this->getSpecificMonthlySalary();
                break;

            case 'generateBonus':
                $this->generateBonus($request);
                break;

            case 'singleGenerateBonus':
                $this->singleGenerateBonus($request);
                break;

                // singleGenerateBonus

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





    public function createAddDeddType($request)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "type" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $addDeddType = DB::table('addition_deduction_type')->insert([
            "name" => $this->params->name,
            "type" => $this->params->type,
            'created_by' => $this->user->id,
            "status" => $this->params->status,
        ]);



        $this->responseMessage = "Created successfully!";
        $this->outputData = $addDeddType;
        $this->success = true;
    }



    public function getAllAddDeddType(Request $request, Response $response)
    {
        $addDeddTypeList = DB::table('addition_deduction_type')->get();
        $this->responseMessage = "All data fetched successfully";
        $this->outputData = $addDeddTypeList;
        $this->success = true;
    }


    public function getAddDeddTypeInfo()
    {
        if (!isset($this->params->type_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $addDeddTypeInfo = DB::table('addition_deduction_type')->find($this->params->type_id);

        if (!$addDeddTypeInfo) {
            $this->success = false;
            $this->responseMessage = "salary not found!";
            return;
        }

        $this->responseMessage = "salary info fetched successfully";
        $this->outputData = $addDeddTypeInfo;
        $this->success = true;
    }

    public function updateAddDeddType(Request $request)
    {
        $this->validator->validate($request, [
            "type_id" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $addDeddType = DB::table('addition_deduction_type')->where(['id' => $this->params->type_id])
            ->update([
                "name" => $this->params->name,
                "type" => $this->params->type,
                'updated_by' => $this->user->id,
                "status" => $this->params->status,
            ]);

        $this->responseMessage = "Advance Salary been updated successfully !";
        $this->outputData = $addDeddType;
        $this->success = true;
    }
    public function deleteAddDeddType()
    {
        // Find the record
        $addDeddType = DB::table('addition_deduction_type')->find($this->params->type_id);


        // Check if the record exists
        if ($addDeddType) {
            // Delete the record
            $delete = DB::table('addition_deduction_type')
                ->where('id', '=', $this->params->type_id)
                ->delete();
            $this->responseMessage = "Record deleted successfully!";
            $this->outputData =  $addDeddType;
            $this->success = true;
        } else {
            $this->responseMessage = "Record not found!";
            $this->outputData =  $addDeddType;
            $this->success = true;
        }
    }


    public function crtAndUptAtnStngs($request)
    {
        // Validate the request
        $this->validator->validate($request, [
            "time" => v::notEmpty(),
            "days" => v::notEmpty(),
            "percentageDeduction" => v::notEmpty(),
            // "weekend" => v::notEmpty()->arrayType(),
        ]);

        // Check if validation failed
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // Define settings
        $settings = [
            [
                'key_name' => 'attendance_deduction_time',
                'name' => 'time',
                'value' => $this->params->time,
            ],
            [
                'key_name' => 'attendance_deduction_days',
                'name' => 'days',
                'value' => $this->params->days,
            ],
            [
                'key_name' => 'attendance_deduction_percentage',
                'name' => 'percentageDeduction',
                'value' => $this->params->percentageDeduction,
            ],
            [
                'key_name' => 'salaryType',
                'name' => 'salaryType',
                'value' => $this->params->salaryType,
            ],
            [
                'key_name' => 'weekend',
                'name' => 'weekend',
                'value' => json_encode($this->params->weekend),
            ],
        ];

        // Process each setting
        foreach ($settings as $setting) {
            // Check if the setting exists
            $existingRecord = DB::table('payroll_settings')
                ->where('key_name', $setting['key_name'])
                ->first();

            if ($existingRecord) {
                // Update existing record
                DB::table('payroll_settings')
                    ->where('key_name', $setting['key_name'])
                    ->update([
                        'value' => $setting['value'],
                        'updated_by' => $this->user->id,
                    ]);
            } else {
                // Insert new record
                DB::table('payroll_settings')->insert([
                    'key_name' => $setting['key_name'],
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                ]);
            }
        }

        // Set response message and data
        $this->responseMessage = "Created/Updated successfully!";
        $this->success = true;
    }



    public function crtAndUptBonusStngs($request)
    {
        // Validate the request
        $this->validator->validate($request, [
            "bonusAmount" => v::notEmpty(),
            "bonusType" => v::notEmpty(),
        ]);

        // Check if validation failed
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // Define settings
        $settings = [
            [
                'key_name' => 'bonus_amount',
                'name' => 'bonusAmount',
                'value' => $this->params->bonusAmount,
            ],
            [
                'key_name' => 'bonus_type',
                'name' => 'bonusType',
                'value' => $this->params->bonusType,
            ],
        ];

        // Process each setting
        foreach ($settings as $setting) {
            // Check if the setting exists
            $existingRecord = DB::table('payroll_settings')
                ->where('key_name', $setting['key_name'])
                ->first();

            if ($existingRecord) {
                // Update existing record
                DB::table('payroll_settings')
                    ->where('key_name', $setting['key_name'])
                    ->update([
                        'value' => $setting['value'],
                        'updated_by' => $this->user->id,
                    ]);
            } else {
                // Insert new record
                DB::table('payroll_settings')->insert([
                    'key_name' => $setting['key_name'],
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                ]);
            }
        }

        // Set response message and data
        $this->responseMessage = "Created/Updated successfully!";
        $this->success = true;
    }



    public function crtAndUptTypeStngs($request)
    {
        // Validate the request
        $this->validator->validate($request, [
            "unpaidType" => v::notEmpty(),
            "absentType" => v::notEmpty(),
        ]);

        // Check if validation failed
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // Define settings
        $settings = [
            [
                'key_name' => 'unpaidType',
                'name' => 'unpaidType',
                'value' => $this->params->unpaidType,
            ],
            [
                'key_name' => 'absentType',
                'name' => 'absentType',
                'value' => $this->params->absentType,
            ],
        ];

        // Process each setting
        foreach ($settings as $setting) {
            // Check if the setting exists
            $existingRecord = DB::table('general_settings')
                ->where('key_name', $setting['key_name'])
                ->first();

            if ($existingRecord) {
                // Update existing record
                DB::table('general_settings')
                    ->where('key_name', $setting['key_name'])
                    ->update([
                        'value' => $setting['value'],
                        'updated_by' => $this->user->id,
                    ]);
            } else {
                // Insert new record
                DB::table('general_settings')->insert([
                    'key_name' => $setting['key_name'],
                    'name' => $setting['name'],
                    'value' => $setting['value'],
                    'created_by' => $this->user->id,
                    'updated_by' => $this->user->id,
                ]);
            }
        }

        // Set response message and data
        $this->responseMessage = "Created/Updated successfully!";
        $this->success = true;
    }



    public function getLatAttendanceInfo()
    {
        // Fetch the latest values for time, days, and percentageDeduction
        $attendanceSettings = DB::table('payroll_settings')
            ->whereIn('key_name', [
                'attendance_deduction_time',
                'attendance_deduction_days',
                'attendance_deduction_percentage',
                'weekend',
                'bonus_amount',
                'bonus_type',
                'salaryType'

            ])
            ->orderBy('id', 'desc')
            ->get();

        if ($attendanceSettings->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "Attendance settings not found!";
            return;
        }

        // Prepare the response data
        $attendance = [];
        foreach ($attendanceSettings as $setting) {
            switch ($setting->key_name) {
                case 'attendance_deduction_time':
                    $attendance['time'] = $setting->value;
                    break;
                case 'attendance_deduction_days':
                    $attendance['days'] = $setting->value;
                    break;
                case 'attendance_deduction_percentage':
                    $attendance['percentageDeduction'] = $setting->value;
                    break;
                case 'weekend':
                    $attendance['weekend'] = $setting->value;
                    break;
                case 'bonus_amount':
                    $attendance['bonusAmount'] = $setting->value;
                    break;
                case 'bonus_type':
                    $attendance['bonusType'] = $setting->value;
                    break;
                case 'salaryType':
                    $attendance['salaryType'] = $setting->value;
                    break;
            }
        }

        $this->responseMessage = "Attendance info fetched successfully";
        $this->outputData = $attendance;
        $this->success = true;
    }



    public function getGnrlStngInfo()
    {
        // Fetch the latest values for time, days, and percentageDeduction
        $attendanceSettings = DB::table('general_settings')
            ->whereIn('key_name', [
                'absentType',
                'unpaidType',

            ])
            ->orderBy('id', 'desc')
            ->get();

        if ($attendanceSettings->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "Attendance settings not found!";
            return;
        }

        // Prepare the response data
        $attendance = [];
        foreach ($attendanceSettings as $setting) {
            switch ($setting->key_name) {
                case 'absentType':
                    $attendance['absentType'] = $setting->value;
                    break;
                case 'unpaidType':
                    $attendance['unpaidType'] = $setting->value;
                    break;
            }
        }

        $this->responseMessage = "Attendance info fetched successfully";
        $this->outputData = $attendance;
        $this->success = true;
    }



    public function getLatAttendanceInfo1()
    {



        $attendance = DB::table('attendance_settings')->orderBy('id', 'desc')->first();


        if (!$attendance) {
            $this->success = false;
            $this->responseMessage = "Attendance not found!";
            return;
        }

        $this->responseMessage = "Attendance info fetched successfully";
        $this->outputData = $attendance;
        $this->success = true;
    }



    public function crtAndUptAtnStngs1($request)
    {

        $this->validator->validate($request, [
            "time" => v::notEmpty(),
            "days" => v::notEmpty(),
            "percentageDeduction" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        $existingRecord = DB::table('attendance_settings')
            ->where('time', $this->params->time)
            ->where('days', $this->params->days)
            ->where('percentageDeduction', $this->params->percentageDeduction)
            ->first();

        if ($existingRecord) {
            $this->success = false;
            $this->responseMessage = "A record with the same values already exists.";
            return;
        }


        $addDeddType = DB::table('attendance_settings')->insert([
            "time" => $this->params->time,
            "days" => $this->params->days,
            // 'created_by' => $this->user->id,
            'percentageDeduction' => $this->params->percentageDeduction,
            "status" => 1,
        ]);


        $this->responseMessage = "Created successfully!";
        $this->outputData = $addDeddType;
        $this->success = true;
    }




    public function generateMonthlySalary($request)
    {
        DB::beginTransaction();
        try {
            //code...

            // Validate the request
            $this->validator->validate($request, [
                "generated_date" => v::notEmpty(),
            ]);

            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }


            $generatedDate =  $this->params->generated_date;

            $date = new DateTime($generatedDate);
            $year = $date->format('Y');
            $month = $date->format('m');

            /**Calculate the number of days in the current month start*/
            $firstDayOfMonth = new DateTime($date->format('Y-m-01'));
            $lastDayOfMonth = (clone $firstDayOfMonth)->modify('first day of next month')->modify('-1 day');
            $daysInMonth = $firstDayOfMonth->diff($lastDayOfMonth)->days + 1;
            /**Calculate the number of days in the current month end*/

            /**Fetch the list of holidays start */

            // Map weekend days to PHP's weekday numbers (0=Sun, 1=Mon, ..., 6=Sat)
            $dayMapping = [
                'Sun' => 0,
                'Mon' => 1,
                'Tue' => 2,
                'Wed' => 3,
                'Thu' => 4,
                'Fri' => 5,
                'Sat' => 6
            ];
            $holidaysList = DB::table('holidays')->get();


            // Filter holidays to include only those in the specified month and year
            $monthlyHolidayDates = DB::table('holidays')->whereYear('date', $year)->whereMonth('date', $month)->pluck('date');


            $payrollSettings = DB::table('payroll_settings')
                ->whereIn('key_name', ['weekend', 'attendance_deduction_days', 'attendance_deduction_percentage'])
                ->orderBy('id', 'desc')
                ->get()
                ->keyBy('key_name');
            $weekendSetting = $payrollSettings->get('weekend');
            $deductionDaysValue = isset($payrollSettings['attendance_deduction_days']) ? intval($payrollSettings['attendance_deduction_days']->value) : 0;
            $dedctnSalaryPcentValue = isset($payrollSettings['attendance_deduction_percentage']) ? intval($payrollSettings['attendance_deduction_percentage']->value) : 0;


            $generalSettings = DB::table('general_settings')
                ->whereIn('key_name', ['unpaidType', 'absentType'])
                ->orderBy('id', 'desc')
                ->get()
                ->keyBy('key_name');

            $unpaidTypeValue = isset($generalSettings['unpaidType']) ? $generalSettings['unpaidType']->value : '';
            $absentTypeValue = isset($generalSettings['absentType']) ? $generalSettings['absentType']->value : '';



            if (!$weekendSetting) {
                $this->success = false;
                $this->responseMessage = "Weekend setting not found!";
                return;
            }

            // Prepare the response data
            $weekendDays = json_decode($weekendSetting->value, true);



            // Find the weekend days in the current month start
            $weekendDates = [];
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $currentDate = (clone $firstDayOfMonth)->modify("+$i days");
                $currentDayOfWeek = $currentDate->format('w');

                if (in_array($currentDayOfWeek, array_map(function ($day) use ($dayMapping) {
                    return $dayMapping[$day];
                }, $weekendDays))) {
                    $weekendDates[] = $currentDate->format('Y-m-d');
                }
            }
            $totalWeekendDays = count($weekendDates);


            // Find the weekend days in the current month end


            $leaves = DB::table('leave_applications')
                ->join('leave_categories', 'leave_categories.id', '=', 'leave_applications.leave_category_id')
                ->where('leave_applications.leave_status', 'Approved')
                ->whereYear('leave_applications.date', $year)
                ->whereMonth('leave_applications.date', $month)
                ->select('leave_applications.date', 'leave_categories.title', 'leave_applications.employee_id')
                ->get();
            $employes = DB::table('employees')->where('status', 1)->get();


            $basicSalaryByEmployee = [];

            foreach ($employes as $employee) {
                // Initialize the arrays if the emp_id is not already in the array
                if (!isset($basicSalaryByEmployee[$employee->id])) {
                    $basicSalaryByEmployee[$employee->id] = [];
                }

                // Store each deduction amount for the respective id
                $basicSalaryByEmployee[$employee->id][] = $employee->salary_amount;
            }


            $dailySalaryByEmployee = [];

            foreach ($employes as $employee) {
                $dailySalaryByEmployee[$employee->id] = $employee->salary_amount / $daysInMonth;
            }



            // Initialize arrays to store leave dates for each employee
            $paidLeavesByEmployee = [];
            $unpaidLeavesByEmployee = [];
            $unpaidLeavesCountByEmployee = [];
            // Loop through the leave records
            foreach ($leaves as $leave) {
                // Initialize the arrays if the employee_id is not already in the array
                if (!isset($paidLeavesByEmployee[$leave->employee_id])) {
                    $paidLeavesByEmployee[$leave->employee_id] = [];
                    $unpaidLeavesByEmployee[$leave->employee_id] = [];
                    $unpaidLeavesCountByEmployee[$leave->employee_id] = 0;
                }

                // Separate paid and unpaid leaves
                if ($leave->title === 'Unpaid Leave') {
                    $unpaidLeavesByEmployee[$leave->employee_id][] = $leave->date;
                    $unpaidLeavesCountByEmployee[$leave->employee_id]++;
                } else {
                    $paidLeavesByEmployee[$leave->employee_id][] = $leave->date;
                }
            }

            $unpaidLeaveDeductionByEmployee = [];
            $unpaidLeaveByEmployee = [];
            foreach ($unpaidLeavesCountByEmployee as $empId => $unpaidLeaveCount) {
                $unpaidLeaveDeductionByEmployee[$empId] = $unpaidLeaveCount * $dailySalaryByEmployee[$empId];
                $unpaidLeaveByEmployee[$empId] = $unpaidLeaveCount;
            }

            /**Fetch the list of holidays start */


            /** Loan value of employee start */
            $loans = DB::table('loan_applications')
                ->where('loan_status', 'Approved')
                ->select('id', 'employee_id', 'payment_amount', 'loan_status')
                ->get();

            $approvedLoanByEmployee = [];

            foreach ($loans as $loan) {
                // Initialize the arrays if the employee_id is not already in the array
                if (!isset($approvedLoanByEmployee[$loan->employee_id])) {
                    $approvedLoanByEmployee[$loan->employee_id] = [];
                }

                // Separate paid and unpaid leaves
                if ($loan->loan_status === 'Approved') {
                    $approvedLoanByEmployee[$loan->employee_id][] = $loan->payment_amount;
                }
            }
            /** Loan value of employee start */


            /** Advance Salary of this employee  */
            $empSalaryAdvance = DB::table('emp_salary_advance')
                ->where('status', 1)
                ->whereYear('salary_month', $year)
                ->whereMonth('salary_month', $month)
                ->select('id', 'emp_id', 'salary_amount', 'salary_month')
                ->get();


            $salaryAdvanceByEmployee = [];

            foreach ($empSalaryAdvance as $advanceSalary) {
                // Initialize the arrays if the emp_id is not already in the array
                if (!isset($salaryAdvanceByEmployee[$advanceSalary->emp_id])) {
                    $salaryAdvanceByEmployee[$advanceSalary->emp_id] = [];
                }

                // Store each deduction amount for the respective emp_id
                $salaryAdvanceByEmployee[$advanceSalary->emp_id][] = $advanceSalary->salary_amount;
            }



            $attendance = DB::table('employee_attendance')
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get();

            $attendanceDates = $attendance->pluck('date');

            // Initialize an array to store the count of days present for each employee
            $presentDaysByEmployee = [];
            $presentDaysWithoutDeduction = [];
            $lateDaysByEmployee = [];

            // Loop through the attendance records
            foreach ($attendance as $record) {
                // Initialize the count if the employee_id is not already in the array
                if (!isset($presentDaysByEmployee[$record->employee_id])) {
                    $presentDaysWithoutDeduction[$record->employee_id] = 0;
                    $presentDaysByEmployee[$record->employee_id] = 0;
                    $lateDaysByEmployee[$record->employee_id] = 0;
                }

                // Increment the present count if the status is "present"
                if ($record->status === 'present') {
                    $presentDaysWithoutDeduction[$record->employee_id]++;
                    $presentDaysByEmployee[$record->employee_id]++;
                }

                // Count the late days
                if ($record->late_in_time > 0) {
                    $lateDaysByEmployee[$record->employee_id]++;
                }
            }


            $lateDaysDivisionByEmployee = [];
            foreach ($lateDaysByEmployee as $empId => $lateDays) {
                $lateDaysDivisionByEmployee[$empId] = $lateDays / $deductionDaysValue;
            }


            $deductionDaysSalaryResults = [];

            foreach ($basicSalaryByEmployee as $empId => $salaryArray) {
                $basicSalary = $salaryArray[0];
                if (isset($lateDaysDivisionByEmployee[$empId])) {
                    $lateDaysDivision = $lateDaysDivisionByEmployee[$empId];
                    // $deductionAmount = ($basicSalary * $dedctnSalaryPcentValue / 100) * $lateDaysDivision;
                    $deductionAmount = ($dailySalaryByEmployee[$empId] * $dedctnSalaryPcentValue / 100) * $lateDaysDivision;
                    $deductionDaysSalaryResults[$empId] = $deductionAmount;
                } else {
                    $deductionDaysSalaryResults[$empId] = 0;
                }
            }


            $monthlyHolidayDatesArray = is_array($monthlyHolidayDates) ? $monthlyHolidayDates : $monthlyHolidayDates->toArray();
            $weekendDatesArray = is_array($weekendDates) ? $weekendDates : $weekendDates->toArray();

            // Calculate the difference
            $nonMatchingDates = array_diff($monthlyHolidayDatesArray, $weekendDatesArray);
            $overlappingDates = array_intersect($monthlyHolidayDatesArray, $weekendDatesArray);

            $monthlyHolidayCount = count($monthlyHolidayDatesArray);
            $weeklyHolidayCount = count($weekendDatesArray);
            $overlappingCount = count($overlappingDates);

            $totalOfficeDays = $daysInMonth - ($monthlyHolidayCount + $weeklyHolidayCount - $overlappingCount);

            $additions = DB::table('employee_salary_settings')
                ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
                ->where('employee_salary_settings.add_ded_type', 'additton')
                ->select('employee_salary_settings.emp_id', 'employee_salary_settings.amount', 'addition_deduction_type.name as type_name')
                ->get();

            $additionsByEmployee = [];

            foreach ($additions as $addition) {
                if (!isset($additionsByEmployee[$addition->emp_id])) {
                    $additionsByEmployee[$addition->emp_id] = [];
                }
                $additionsByEmployee[$addition->emp_id][] = $addition->amount;
            }
            $totalAdditionsByEmployee = [];
            foreach ($additionsByEmployee as $empId => $additions) {
                $totalAdditionsByEmployee[$empId] = array_sum($additions);
            }

            $deductions = DB::table('employee_salary_settings')
                ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
                ->where('employee_salary_settings.add_ded_type', 'deduction')
                ->select('employee_salary_settings.emp_id', 'employee_salary_settings.amount', 'addition_deduction_type.name as type_name')
                ->get();

            $deductionsByEmployee = [];

            foreach ($deductions as $deduction) {
                // Initialize the arrays if the emp_id is not already in the array
                if (!isset($deductionsByEmployee[$deduction->emp_id])) {
                    $deductionsByEmployee[$deduction->emp_id] = [];
                }

                // Store each deduction amount for the respective emp_id
                $deductionsByEmployee[$deduction->emp_id][] = $deduction->amount;
            }


            $totalDeductionsByEmployee = [];
            foreach ($deductionsByEmployee as $empId => $deductions) {
                $totalDeductionsByEmployee[$empId] = array_sum($deductions);
            }

            $bonues = DB::table('employee_bonus')->get();

            $bonusByEmployee = [];

            foreach ($bonues as $bonus) {
                // Initialize the arrays if the emp_id is not already in the array
                if (!isset($bonusByEmployee[$bonus->employee_id])) {
                    $bonusByEmployee[$bonus->employee_id] = [];
                }

                // Store each deduction amount for the respective employee_id
                $bonusByEmployee[$bonus->employee_id][] = $bonus->amount;
            }


            // Combine salary, bonus, additions, and deductions for each employee
            $employeeSums = [];

            // Combine salary, bonus, additions, and deductions for each employee
            foreach ($basicSalaryByEmployee as $empId => $salaries) {
                $employeeSums[$empId]['salary'] = array_sum($salaries);
                $employeeSums[$empId]['bonus'] = isset($bonusByEmployee[$empId]) ? (float) array_sum($bonusByEmployee[$empId]) : 0.0;
                $employeeSums[$empId]['loan'] = isset($approvedLoanByEmployee[$empId]) ? (float) array_sum($approvedLoanByEmployee[$empId]) : 0.0;
                $employeeSums[$empId]['advanceSalary'] = isset($salaryAdvanceByEmployee[$empId]) ? (float) array_sum($salaryAdvanceByEmployee[$empId]) : 0.0;
                $employeeSums[$empId]['addition'] = $totalAdditionsByEmployee[$empId] ?? 0.0;
                $employeeSums[$empId]['deduction'] = $totalDeductionsByEmployee[$empId] ?? 0.0;
                $employeeSums[$empId]['unpaidLeave'] = intval($unpaidLeaveDeductionByEmployee[$empId] ?? 0.0);
                $employeeSums[$empId]['lateAtnd'] = intval($deductionDaysSalaryResults[$empId] ?? 0.0);
                // $employeeSums[$empId]['absent'] = intval($deductionAbsentDaysSalaryResults[$empId] ?? 0.0);


                // $deductionAbsentDaysSalaryResults
            }


            $employeeTotals = [];

            foreach ($employeeSums as $empId => $data) {
                $employeeTotals[$empId] = [
                    'total_addition' => $data['bonus'] + $data['addition'],
                    // 'total_deduction' => $data['deduction'] + $data['loan'] + $data['advanceSalary'] + $data['unpaidLeave'] + $data['lateAtnd'] + $data['absent']
                    'total_deduction' => $data['deduction'] + $data['loan'] + $data['advanceSalary'] + $data['lateAtnd']
                    // 'total_deduction' => $data['deduction'] + $data['loan'] + $data['advanceSalary'] + $data['unpaidLeave'] + $data['lateAtnd'] 
                ];
            }

            $monthName = $date->format('F Y');
            $month = $date->format('m');

            $existingRecord = DB::table('monthly_salary')
                ->where(DB::raw("MONTH(generated_date)"), $month)
                ->first();

            if ($existingRecord) {
                $this->success = false;
                $this->responseMessage = "A record for the specified month already exists.";
                return;
            }

            $generatedDateFormatted = $date->format('Y-m-d');

            $generatedId = DB::table('monthly_salary')->insertGetId([
                'month_name' => $monthName,
                'generated_date' => $generatedDateFormatted,
                'status' => 'pending',
                'generated_by' => $this->user->id,
                'created_by' => $this->user->id,
            ]);

            $employees = DB::table('employees')->where('status', 1)->orderBy('id', 'desc')->get();
            foreach ($employees as $key => $employee) {



                $additions = DB::table('employee_salary_settings')
                    ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
                    ->where('employee_salary_settings.emp_id', $employee->id)
                    ->where('employee_salary_settings.add_ded_type', 'additton')
                    ->select('employee_salary_settings.*', 'addition_deduction_type.name as type_name')
                    ->get();
                $deductions = DB::table('employee_salary_settings')
                    ->leftJoin('addition_deduction_type', 'addition_deduction_type.id', '=', 'employee_salary_settings.type_id')
                    ->where('employee_salary_settings.emp_id', $employee->id)
                    ->where('employee_salary_settings.add_ded_type', 'deduction')
                    ->select('employee_salary_settings.*', 'addition_deduction_type.name as type_name')
                    ->get();



                $additionData = [];
                $deductionData = [];

                $grossSalary = ($employee->salary_amount + $employeeTotals[$employee->id]['total_addition']);


                $absentDaysDivision = $totalOfficeDays -  $presentDaysWithoutDeduction[$employee->id];
                $deductionAbsentAmount = 0;
                $unpaidDaysDivision = 0;

                if ($absentTypeValue == 'gross') {

                    $deductionAbsentAmount = ($grossSalary / $daysInMonth)  * $absentDaysDivision;
                } else {
                    $deductionAbsentAmount = ($employee->salary_amount / $daysInMonth)  * $absentDaysDivision;
                }

                if ($unpaidTypeValue  == 'gross') {
                    $unpaidDaysDivision = ($grossSalary / $daysInMonth)  * $unpaidLeaveByEmployee[$employee->id];
                } else {
                    $unpaidDaysDivision =  ($employee->salary_amount / $daysInMonth)  * $unpaidLeaveByEmployee[$employee->id];
                }

                $additionData = $additions->map(function ($addition) {
                    return [
                        'amount' => $addition->amount,
                        'name' => $addition->type_name,
                    ];
                })->toArray();

                $deductionData = $deductions->map(function ($deduction) {
                    return [
                        'amount' => $deduction->amount,
                        'name' => $deduction->type_name,
                    ];
                })->toArray();

                // 

                $additionData[] = [
                    'amount' => isset($bonusByEmployee[$employee->id]) ? (float) array_sum($bonusByEmployee[$employee->id]) : 0.0,
                    'name' => 'Bonus',
                ];
                $deductionData[] = [
                    'amount' => $deductionAbsentAmount,
                    'name' => $absentDaysDivision . ' Days Deduction Absent Amount',
                ];

                $deductionData[] = [
                    'amount' => $unpaidDaysDivision,
                    'name' =>  $unpaidLeaveByEmployee[$employee->id] . ' Unpaid Days',
                ];

                $deductionData[] = [
                    'amount' => isset($salaryAdvanceByEmployee[$employee->id]) ? (float) array_sum($salaryAdvanceByEmployee[$employee->id]) : 0.0,
                    'name' =>  'Advance Salary',
                ];

                $deductionData[] = [
                    'amount' => isset($approvedLoanByEmployee[$employee->id]) ? (float) array_sum($approvedLoanByEmployee[$employee->id]) : 0.0,
                    'name' =>  'Loan Deduction',
                ];
                $deductionData[] = [
                    'amount' => intval($deductionDaysSalaryResults[$employee->id] ?? 0.0),
                    'name' =>  'Late Attendance',
                ];


                // intval($deductionDaysSalaryResults[$empId] ?? 0.0);

                $netSalary = ($grossSalary -  ($employeeTotals[$employee->id]['total_deduction'] + $deductionAbsentAmount + $unpaidDaysDivision));
                if ($presentDaysByEmployee[$employee->id] > 0) {
                    
                    $generate = DB::table('employee_salary_details')->insert([
                        'monthly_salary_id' => $generatedId,
                        'employee_id' => $employee->id,
                        'dept_id' => $employee->department_id,
                        'basic_salary' => $employee->salary_amount,
                        'addition' => $employeeTotals[$employee->id]['total_addition'],
                        'gross_salary' => $grossSalary,
                        'deduction' => $employeeTotals[$employee->id]['total_deduction'] + ($deductionAbsentAmount + $unpaidDaysDivision),
                        'net_salary' => $netSalary,
                        'addition_data' => json_encode($additionData),
                        'deduction_data' => json_encode($deductionData),
                    ]);
                }
            }
            $this->responseMessage = "Generated Successfully!!";
            $this->outputData = $generate;
            $this->success = true;

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollback();
            $this->responseMessage = "Generated failed";
            $this->outputData = [];
            $this->success = false;
        }
    }


    public function getAllMonthlySalary()
    {
        $allMonthlySalary = DB::table('monthly_salary')
            ->leftJoin('org_users as generator', 'generator.id', '=', 'monthly_salary.generated_by')
            ->leftJoin('org_users as approver', 'approver.id', '=', 'monthly_salary.approved_by')
            ->select(
                'monthly_salary.*',
                'generator.name as generated_by_name',
                'approver.name as approved_by_name'
            )
            ->orderBy('monthly_salary.id', 'desc')
            ->get();

        $this->responseMessage = "All Data fetched Successfully";
        $this->outputData = $allMonthlySalary;
        $this->success = true;
    }


    public function updateStatusOfSalaryMonth(Request $request)
    {

        // $todayDate = date('d-m-Y');
        $todayDate = Carbon::now();

        $currentStatus = DB::table('monthly_salary')
            ->where(['id' => $this->params->id])
            ->value('status');
        if ($currentStatus == 'approved') {
            $this->responseMessage = "Salary status is already approved. No further changes are possible.";
            $this->success = false;
            $this->outputData = null;
            return;
        }

        $updateStatus = DB::table('monthly_salary')
            ->where(['id' => $this->params->id])
            ->update([
                'approved_by' => $this->user->id,
                'updated_by' => $this->user->id,
                'approved_date' => $todayDate,
                'status' => $this->params->status,
            ]);

        $this->responseMessage = "Advance Salary has been updated successfully!";
        $this->outputData = $updateStatus;
        $this->success = true;
    }


    public function deleteMonthlySalary(Request $request, Response $response)
    {


        $deleteMonthlySalary = DB::table('monthly_salary')
            ->where('id', '=', $this->params->id)
            ->delete();


        $this->responseMessage = "Deleted successfully";
        $this->outputData = $deleteMonthlySalary;
        $this->success = true;
    }


    public function getSpecificMonthlySalary()
    {
        $allMonthlySalary = DB::table('employee_salary_details')
            ->leftJoin('employees', 'employees.id', '=', 'employee_salary_details.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employee_salary_details.dept_id')
            ->leftJoin('designations', 'designations.id', '=', 'employees.designation_id')
            ->leftJoin('monthly_salary', 'monthly_salary.id', '=', 'employee_salary_details.monthly_salary_id')

            ->select(
                'employee_salary_details.*',
                'departments.name as department_name',
                'employees.name as employee_name',
                'employees.salary_type as salary_type',
                'employees.email as email',
                'employees.bank_name as bank_name',
                'employees.branch_address as bank_branch_address',
                'employees.acc_number as bank_acc_number',
                'employees.mobile as mobile',
                'employees.address as address',
                'monthly_salary.generated_date as generated_date',
                'monthly_salary.month_name as month_name',
                'designations.name as designations'
            )

            ->where('employee_salary_details.monthly_salary_id', '=', $this->params->id)
            ->get();

        $this->responseMessage = "All Data fetched Successfully";
        $this->outputData = $allMonthlySalary;
        $this->success = true;
    }

    public function generateBonus($request)
    {
        $bonusValues = DB::table('payroll_settings')
            ->whereIn('key_name', ['bonus_amount', 'bonus_type'])
            ->orderBy('id', 'desc')
            ->get();

        if ($bonusValues->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "Attendance settings not found!";
            return;
        }

        // Prepare the response data
        $bonuses = [];
        foreach ($bonusValues as $bonus) {
            if ($bonus->key_name === 'bonus_amount') {
                $bonuses['bonusAmount'] = $bonus->value;
            } elseif ($bonus->key_name === 'bonus_type') {
                $bonuses['bonusType'] = $bonus->value;
            }
        }
        $bonusPercentage =  floatval($bonuses['bonusAmount']);
        $bonusType = $bonuses['bonusType'];
        // dd($bonusPercentage );


        $employees = DB::table('employees')->where('status', 1)->orderBy('id', 'desc')->get();
        foreach ($employees as $key => $employee) {
            $bonusAmount = (floatval($bonusPercentage)  / 100) * floatval($employee->salary_amount);
            $generate = DB::table('employee_bonus')->insert([

                'employee_id' => $employee->id,
                'amount' => $bonusAmount,
                'bonus_type' => $bonusType,
                'created_by' => $this->user->id,
                'date' => $this->params->generated_date
            ]);
        }

        $this->responseMessage = "Created successfully!";
        $this->outputData = $bonusType;
        $this->success = true;
    }



    public function singleGenerateBonus($request)
    {
        // $this->params->generated_date
        $employeeId = intval($this->params->employee) ?? null;
        $employee = DB::table('employee_bonus')->where(["employee_id" => $employeeId])->where(["date" => $this->params->salary_month])->first();
        if ($employee) {
            $this->success = false;
            $this->responseMessage = "Employee with the same name bonus already exist";
            return;
        }

        $bonusValues = DB::table('payroll_settings')
            ->whereIn('key_name', ['bonus_amount', 'bonus_type'])
            ->orderBy('id', 'desc')
            ->get();

        if ($bonusValues->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "Attendance settings not found!";
            return;
        }

        // Prepare the response data
        $bonuses = [];
        foreach ($bonusValues as $bonus) {
            if ($bonus->key_name === 'bonus_amount') {
                $bonuses['bonusAmount'] = $bonus->value;
            } elseif ($bonus->key_name === 'bonus_type') {
                $bonuses['bonusType'] = $bonus->value;
            }
        }
        $bonusPercentage =  floatval($bonuses['bonusAmount']);
        $bonusType = $bonuses['bonusType'];

        // Check if employee ID is provided


        if ($employeeId) {
            // Retrieve the specific employee's data
            $employee = DB::table('employees')->where('id', $employeeId)->where('status', 1)->first();

            if (!$employee) {
                $this->success = false;
                $this->responseMessage = "Employee not found or inactive!";
                return;
            }

            // Calculate and insert the bonus for the specific employee
            $bonusAmount = ($bonusPercentage / 100) * floatval($employee->salary_amount);
            $generate = DB::table('employee_bonus')->insert([
                'employee_id' => $employee->id,
                'amount' => $this->params->bonusAmount ?? $bonusAmount,
                'bonus_type' => $bonusType,
                'created_by' => $this->user->id,
                'date' => $this->params->salary_month
            ]);
        } else {
            // Retrieve all active employees
            $employees = DB::table('employees')->where('status', 1)->orderBy('id', 'desc')->get();
            foreach ($employees as $key => $employee) {
                $bonusAmount = ($bonusPercentage / 100) * floatval($employee->salary_amount);
                $generate = DB::table('employee_bonus')->insert([
                    'employee_id' => $employee->id,
                    'amount' => $this->params->bonusAmount ?? $bonusAmount,
                    'bonus_type' => $bonusType,
                    'created_by' => $this->user->id,
                    'date' => $this->params->salary_month
                ]);
            }
        }

        $this->responseMessage = "Created successfully!";
        $this->outputData = $bonusType;
        $this->success = true;
    }


    public function getAllBonusList(Request $request, Response $response)
    {
        $bonusTypeList = DB::table('employee_bonus')
            ->leftJoin('employees', 'employees.id', '=', 'employee_bonus.employee_id')
            ->select('employee_bonus.*', 'employees.name')

            ->get();
        $this->responseMessage = "All data fetched successfully";
        $this->outputData = $bonusTypeList;
        $this->success = true;
    }


    public function getPayslipInfo()
    {
        if (!isset($this->params->emp_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $addDeddTypeInfo = DB::table('employee_salary_details')
        ->leftJoin('employees', 'employees.id', '=', 'employee_salary_details.employee_id')
        ->leftJoin('monthly_salary', 'monthly_salary.id', '=', 'employee_salary_details.monthly_salary_id')
        ->leftJoin('designations', 'designations.id', '=', 'employees.designation_id')
        ->select('employee_salary_details.*', 
        'employees.name as employee_name',
        'employees.salary_type as salary_type',
        'employees.email as email',
        'employees.bank_name as bank_name',
        'employees.branch_address as bank_branch_address',
        'employees.acc_number as bank_acc_number',
        'employees.mobile as mobile',
        'employees.address as address',
        'monthly_salary.generated_date as generated_date',
        'monthly_salary.month_name as month_name',
        'designations.name as designations'
        
        )

            ->where('employee_id', $this->params->emp_id)
            ->where('monthly_salary_id', $this->params->date)
            ->get();

        if (!$addDeddTypeInfo) {
            $this->success = false;
            $this->responseMessage = "salary not found!";
            return;
        }

        $this->responseMessage = "salary info fetched successfully";
        $this->outputData = $addDeddTypeInfo;
        $this->success = true;
    }
}
