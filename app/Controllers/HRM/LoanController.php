<?php

namespace  App\Controllers\HRM;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Models\HRM\Employee;
use App\Validation\Validator;

use App\Response\CustomResponse;
use App\Models\HRM\LoanApplication;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class LoanController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $loanApplications;
    protected $employees;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->employees = new Employee();
        $this->validator = new Validator();
        $this->loanApplications = new LoanApplication();
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

            case 'createLoanApplication':
                $this->createLoanApplication($request, $response);
                break;

            case 'getAllLoanApplications':
                $this->getAllLoanApplications($request, $response);
                break;

            case 'getAllLoanApplicationList':
                $this->getAllLoanApplicationList($request, $response);
                break;

            case 'deleteLoanApplication':
                $this->deleteLoanApplication($request, $response);
                break;

            case 'getLoanDetails':
                $this->getLoanDetails($request, $response);
                break;

            case 'loanApplicationApproval':
                $this->loanApplicationApproval($request, $response);
                break;

            case 'updateLoanApplication':
                $this->updateLoanApplication($request, $response);
                break;

            case 'getLoanHistory':
                $this->getLoanHistory($request, $response);
                break;

            case 'payLoanInstallment':
                $this->payLoanInstallment($request, $response);
                break;


            case 'getLoanHistoryByEmployee':
                $this->getLoanHistoryByEmployee($request, $response);
                break;

            case 'getLoanInstallmentHistory':
                $this->getLoanInstallmentHistory($request, $response);
                break;

                case 'createLoanCategory':
                    $this->createLoanCategory($request, $response);
                    break;

                    case 'getAllLoanCategory':
                        $this->getAllLoanCategory($request, $response);
                        break;

                        case 'deleteLoanCategoryType':
                            $this->deleteLoanCategoryType();
                            break;
                // deleteLoanCategoryType

                // getLoanInstallmentHistory

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


    public function createLoanApplication(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "amount" => v::notEmpty(),
        ]);
        v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }



        $loan = $this->loanApplications->create([
            "subject" => $this->params->subject,
            "employee_id" => $this->params->employee_id,
            "amount" => $this->params->amount,
            "payment_amount" => $this->params->paymentAmount,
            "loan_category" => $this->params->loan_category,
            "loan_payment" => $this->params->loan_payment,
            "description" => $this->params->description,
            "paid_amount" => 0,
            "due_amount" => $this->params->amount,
            "date" => $this->params->date,
            "created_by" => $this->user->id,
            "loan_status" => "Pending",
            "status" => 1,
        ]);

        $this->responseMessage = "New Leave Category created successfully";
        $this->outputData = $loan;
        $this->success = true;
    }


    public function getAllLoanApplications()
    {

        $loan_applications = DB::table('loan_applications')
            ->join('employees', 'employees.id', '=', 'loan_applications.employee_id')
            ->select(
                'loan_applications.*',
                'employees.name as name'
            )
            ->orderBy('id', 'desc')
            ->where('loan_applications.status', 1)
            ->get();

        $this->responseMessage = "Leave Categories list fetched successfully";
        $this->outputData = $loan_applications;
        $this->success = true;
    }

    public function getAllLoanApplicationList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = DB::table('loan_applications')
            ->join('employees', 'employees.id', '=', 'loan_applications.employee_id')
            ->select(
                'loan_applications.*',
                'employees.name as name'
            );
        // ->orderBy('id', 'desc')
        // ->where('loan_applications.status', 1)
        // ->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }


        if ($filter['status'] == 'all') {
            $query->where('loan_applications.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('loan_applications.status', '=', 0);
        }

        if ($filter['status'] == 'Pending' || $filter['status'] == 'Approved' ) {
            $query->where('loan_applications.loan_status', '=',  $filter['status']);
        }
        //             if (isset($filter['yearMonth'])) {
        //     $query->whereYear('loan_applications.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('loan_applications.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('employees.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('loan_applications.subject', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('loan_applications.loan_category', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $loan_application =  $query->orderBy('loan_applications.id', 'desc')
        ->offset(($pageNo - 1) * $perPageShow)
        ->limit($perPageShow)
        ->get();


        if ($pageNo == 1 && $filter['paginate'] == true) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "Leave Categories list fetched successfully";
        $this->outputData = [
            $pageNo => $loan_application,
            'total' => $totalRow,
        ];
        $this->success = true;
    }


    public function deleteLoanApplication(Request $request, Response $response)
    {
        $loanApplications = $this->loanApplications->find($this->params->loan_id);

        $loanApplications->status = 0;
        $loanApplications->save();


        $this->responseMessage = "loan Applications has been deleted successfully";
        $this->outputData = $loanApplications;
        $this->success = true;
    }

    // getLoanDetails

    public function getLoanDetails()
    {

        if (!isset($this->params->loan_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $laundry = DB::table('loan_applications')
            ->join('employees', 'employees.id', '=', 'loan_applications.employee_id')
            ->join('loan_category', 'loan_category.id', '=', 'loan_applications.loan_category')
            ->select(
                'loan_applications.*',
                'employees.name as name',
                'loan_category.name as loan_category_name',
            )
            ->where('loan_applications.status', 1)
            ->where('loan_applications.id', $this->params->loan_id)
            ->first();

        if (!$laundry) {
            $this->success = false;
            $this->responseMessage = "Laundry operator not found!";
            return;
        }


        $this->responseMessage = "Laundry operator fetched Successfully!";
        $this->outputData = $laundry;
        $this->success = true;
    }


    public function loanApplicationApproval(Request $request, Response $response)
    {
        if (!isset($this->params->loan_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $application = $this->loanApplications->find($this->params->loan_id);

        if (!$application) {
            $this->success = false;
            $this->responseMessage = "Leave Application not found!";
            return;
        }

        if ($application->loan_status == 'Pending') {
            $approvalApplication = $application->update([
                "loan_status" => $this->params->loan_status,
                "admin_note" => $this->params->admin_note,
            ]);
        } else {
            $this->success = false;
            $this->responseMessage = "Can not change leave status Approve or Reject!";
            return;
        }

        $this->responseMessage = "Leave Application Updated with approval successfully";
        $this->outputData = $approvalApplication;
        $this->success = true;
    }


    //update loan Application
    public function updateLoanApplication(Request $request, Response $response)
    {

        $application = $this->loanApplications->find($this->params->loan_id);

        $application_updated = DB::table('loan_applications')
            ->where('id', $application->id)
            ->update([
                "subject" => $this->params->subject,
                "employee_id" => $this->params->employee_id,
                "amount" => $this->params->amount,
                "payment_amount" => $this->params->payment_amount,
                "loan_payment" => $this->params->loan_payment,
                "loan_category" => $this->params->loan_category,
                "description" => $this->params->description,
                "date" => $this->params->date,
                "updated_by" => $this->user->id
            ]);


        $this->responseMessage = "Loan application updated  successfully";
        $this->outputData = $application_updated;
        $this->success = true;
    }


    public function getLoanHistory(Request $request, Response $response)
    {

        $count = DB::table('loan_applications')->where('id', $this->params->loan_id)->count();
        $application = DB::table('loan_applications')
            ->where('employee_id', $this->params->employee_id)
            ->orderBy('id', 'desc')
            ->get();


        $this->responseMessage = "Loan application fetch history successfully";
        $this->outputData = $application;
        $this->success = true;
    }



    public function getLoanHistoryByEmployee(Request $request, Response $response)
    {

        $application = DB::table('loan_applications')
            ->join('employees', 'employees.id', '=', 'loan_applications.employee_id')
            // ->join('loan_applications', 'loan_applications.employee_id', '=', 'employees.id')
            ->select(
                'loan_applications.*',
                'employees.name as name'
            )
            ->where('loan_applications.id', $this->params->loan_id)
            ->first();
        $this->responseMessage = "Loan application fetch history successfully";
        $this->outputData = $application;
        $this->success = true;
    }



    public function payLoanInstallment(Request $request)
    {
        $application = $this->loanApplications->find($this->params->loan_id);

        // $this->outputData = $application->amount;
        // return;

        $this->validator->validate($request, [
            "loan_id" => v::notEmpty(),
            "employee_id" => v::notEmpty(),
            "account_id" => v::notEmpty(),
            "amount" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $pay_ammount = intval($this->params->amount);

        $installment = DB::table('loan_installment')->insert([
            'loan_id' => $this->params->loan_id,
            'employee_id' => $this->params->employee_id,
            'reference' => 'PAYSLIP-' . $this->params->loan_id . '-' . strtotime('now'),
            'amount' => $pay_ammount,
            'account_id' => $this->params->account_id,
            'remark' => $this->params->remark,
            'created_by' => $this->user->id,
            'status' => 1
        ]);

        $due_amount = $application->due_amount - $pay_ammount;
        $paid_amount = $application->paid_amount + $pay_ammount;


        //tbl
        $approvalApplication = $application->update([
            "due_amount" => $due_amount,
            "paid_amount" => $paid_amount,
        ]);



        if (!$installment) {
            $this->responseMessage = "Payment collection failed. Please try again !";
            $this->outputData = [];
            $this->success = false;
        }

        $this->responseMessage = "Payment has been collected successfully";
        $this->outputData = $installment;
        $this->success = true;
    }



    public function getLoanInstallmentHistory(Request $request, Response $response)
    {

        $application = DB::table('loan_installment')
            ->join('loan_applications', 'loan_applications.id', '=', 'loan_installment.loan_id')
            ->join('accounts', 'accounts.id', '=', 'loan_installment.account_id')
            // ->join('loan_applications', 'loan_applications.employee_id', '=', 'employees.id')
            ->select(
                'loan_installment.*',
                'loan_applications.loan_category as loan_category',
                'accounts.account_name as account_name',

            )
            // ->where('loan_installment.id', $this->params->installment_id)
            ->where('loan_installment.loan_id', $this->params->loan_id)
            ->get();
        $this->responseMessage = "Loan application fetch history successfully";
        $this->outputData = $application;
        $this->success = true;
    }


    public function createLoanCategory(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
        ]);
        v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // loan_category

        $loan = DB::table('loan_category')->insert([
            "name" => $this->params->name,
            'created_by' => $this->user->id,
        ]);

        $this->responseMessage = "Loan category created successfully";
        $this->outputData = $loan;
        $this->success = true;
    }

    public function getAllLoanCategory(Request $request, Response $response)
    {
        $allLoanCategory = DB::table('loan_category')->get();
        $this->responseMessage = "All data fetched successfully";
        $this->outputData = $allLoanCategory;
        $this->success = true;
    }


    public function deleteLoanCategoryType()
    {
        // Find the record
        $loanCategory = DB::table('loan_category')->find($this->params->type_id);


        // Check if the record exists
        if ($loanCategory) {
            // Delete the record
            $delete = DB::table('loan_category')
                ->where('id', '=', $this->params->type_id)
                ->delete();
            $this->responseMessage = "Record deleted successfully!";
            $this->outputData =  $loanCategory;
            $this->success = true;
        } else {
            $this->responseMessage = "Record not found!";
            $this->outputData =  $loanCategory;
            $this->success = true;
        }
    }
}
