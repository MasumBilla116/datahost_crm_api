<?php

namespace  App\Controllers;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Validation\Validator;

use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class DashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $currency;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
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

            case 'index':
                $this->index();
                break;

            case 'totalIncome':
                $this->totalIncome();
                break;

            case 'totalBooking':
                $this->totalBooking();
                break;

            case 'totalExpense':
                $this->totalExpense();
                break;
            case 'checkInDateTime':
                $this->checkInDateTime();
                break;
            case 'sells':
                $this->sells();
                break;

            case 'inhouseGuests':
                $this->inhouseGuests();
                break;

            case 'cancellation':
                $this->cancellation();
                break;

            case 'checkOutDateTime':
                $this->checkOutDateTime();
                break;

            case 'sellsOverview':
                $this->sellsOverview();
                break;

            case 'restrudentSellsOverview':
                $this->restrudentSellsOverview();
                break;
            case 'staffingManage':
                $this->staffingManage();
                break;
            case 'roomInfo':
                $this->roomInfo();
                break;
                // Staffing:


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

    public function index()
    {
        $totalEmployees = DB::table('employees')
            ->select(DB::raw('COUNT(*) AS total'))
            ->where('status', 1)
            ->first();

        $this->responseMessage = "success!";
        $this->outputData['totalEmployees'] = $totalEmployees;
        $this->success = true;
    }

    // total income start

    public function totalIncome()
    {

        // $bank_credit = DB::table('account_bank')->where('status', 1)->sum('credit');
        // $cash_credit = DB::table('account_cash')->where('status', 1)->sum('credit');
        // $total_income =  $bank_credit + $cash_credit;

        $bank_credit = DB::table('accounts')->where('status', 1)->sum('balance');
        // $cash_credit = DB::table('account_cash')->where('status', 1)->sum('credit');

        $total_income =  $bank_credit;


        $this->responseMessage = "success!";
        $this->outputData = number_format($total_income, 2);
        $this->success = true;
    }



    // total income start

    public function totalBooking()
    {

        $total = DB::table('customer_booking_master')->where("status", 1)->count('id');

        $this->responseMessage = "success!";
        $this->outputData = number_format($total, 2);
        $this->success = true;
    }


    // total income end








    // total totalExpense start

    public function totalExpense()
    {


        $bank_debit = DB::table('account_bank')->where('status', 1)->sum('debit');
        $cash_debit = DB::table('account_cash')->where('status', 1)->sum('debit');

        $total_expense =  $bank_debit + $cash_debit;


        $this->responseMessage = "success!";
        $this->outputData = number_format($total_expense, 2);
        $this->success = true;
    }

    // total totalExpense end




    public function checkInDateTime()
    {
        // get all booking data by daily,weekly,monthly,yearly etc.
        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;

        if ($filter == 'all') {
            $bookings = DB::table('customer_booking_master')
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'daily') {
            $bookings = DB::table('customer_booking_master')
                ->where('customer_booking_master.date_from', date('Y-m-d'))
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'tomorrow') {
            $bookings = DB::table('customer_booking_master')
                ->where('customer_booking_master.date_from', date('Y-m-d', strtotime('tomorrow')))
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else {
            $bookings = DB::table('customer_booking_master')
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        }


        if (count($bookings) < 1) {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }

        $this->responseMessage = "All bookings are fetched successfully !";
        $this->outputData = $bookings;
        $this->success = true;
    }

    public function sells()
    {



        $bookings = DB::table('customer_booking_master')
            ->where('customer_booking_master.checkout_at', '!=', false)
            ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
            ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->get();

        $this->responseMessage = "All checkout_at  are fetched successfully !";
        $this->outputData = $bookings;
        $this->success = true;
    }

    public function inhouseGuests()
    {
        $inhouseGuests = DB::table('customer_booking_master')
            ->whereNotNull('customer_booking_master.checkin_at')
            ->whereNull('customer_booking_master.checkout_at')
            ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
            ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->get();

        $this->responseMessage = "All Inhouse Guests!";
        $this->outputData = $inhouseGuests;
        $this->success = true;
    }


    public function cancellation()
    {



        $bookings = DB::table('customer_booking_master')
            ->where('customer_booking_master.status', '==', 0)
            ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
            ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->get();

        $this->responseMessage = "All checkout_at  are fetched successfully !";
        $this->outputData = $bookings;
        $this->success = true;
    }


    public function checkOutDateTime()
    {



        // $bookings = DB::table('customer_booking_master')
        // ->where('customer_booking_master.checkout_at', '!=', false)
        // ->select('customer_booking_master.*','customers.first_name','customers.last_name','customers.mobile')
        // ->join('customers','customer_booking_master.customer_id','=','customers.id')
        // ->get();



        $checkOutFilter = $this->params->checkOutFilter;


        if ($checkOutFilter == 'all') {
            $bookings = DB::table('customer_booking_master')
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->where('customer_booking_master.checkout_at', '!=', false)

                ->get();
        } else if ($checkOutFilter == 'daily') {
            $bookings = DB::table('customer_booking_master')

                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                // ->where('customer_booking_master.checkout_at', '!=', false)
                ->where('customer_booking_master.date_to', date('Y-m-d'))
                ->get();
        } else if ($checkOutFilter == 'tomorrow') {
            $bookings = DB::table('customer_booking_master')

                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                // ->where('customer_booking_master.checkout_at', '!=', false)
                ->where('customer_booking_master.date_to', date('Y-m-d', strtotime('tomorrow')))
                ->get();
        } else {
            $bookings = DB::table('customer_booking_master')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->where('customer_booking_master.checkout_at', '!=', false)
                ->get();
        }


        if (count($bookings) < 1) {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }



        $this->responseMessage = "All checkout_at  are fetched successfully !";
        $this->outputData = $bookings;
        $this->success = true;
    }



    public function sellsOverview()
    {



        // date wise
        // $day = Carbon::now()->subDays(30);
        // $monthlyRecords = DB::table('customer_booking_master')
        // ->where('created_at','>=',$day)
        // ->select('customer_booking_master.total_amount as amount')
        // ->get();


        $monthlyRecords = DB::table('customer_booking_master')
            ->select(DB::raw("MONTHNAME(created_at) as monthname"), DB::raw("SUM(total_amount) as total"))
            // ->select(DB::raw("(COUNT(*)) as count"),DB::raw("MONTHNAME(created_at) as monthname"),DB::raw("SUM(total_amount) as total"))
            ->whereYear('created_at', date('Y'))
            ->groupBy('monthname')
            ->get();
        $this->responseMessage = "All  fetched successfully !";
        $this->outputData = $monthlyRecords;
        $this->success = true;
    }


    public function restrudentSellsOverview()
    {



        // date wise
        // $day = Carbon::now()->subDays(30);
        // $monthlyRecords = DB::table('customer_booking_master')
        // ->where('created_at','>=',$day)
        // ->select('customer_booking_master.total_amount as amount')
        // ->get();


        $monthlyRecords = DB::table('restaurant_invoices')
            ->select(DB::raw("MONTHNAME(created_at) as monthname"), DB::raw("SUM(total_amount) as total"))
            // ->select(DB::raw("(COUNT(*)) as count"),DB::raw("MONTHNAME(created_at) as monthname"),DB::raw("SUM(total_amount) as total"))
            ->whereYear('created_at', date('Y'))
            ->groupBy('monthname')
            ->get();
        $this->responseMessage = "All data fetched successfully !";
        $this->outputData = $monthlyRecords;
        $this->success = true;
    }


    public function staffingManage()
    {
        $staffingDatas = DB::table('employees')
            ->join('designations', 'designations.id', '=', 'employees.designation_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            // ->join('roster_employees','roster_employees.employee_id','=','employees.id')
            // ->join('roster_assignments','roster_employees.roster_id','=','rosters.id')
            ->select(
                'employees.*',
                'designations.name as designation_name',
                'departments.name as department_name',
                'departments.description as department_description',
                // 'rosters.name as department_rosters',
            )
            ->where(["employees.status" => 1])
            ->get();

        $this->responseMessage = "All employees data fetched successfully !";
        $this->outputData =  $staffingDatas;
        $this->success = true;
    }



    public function roomInfo()
    {
        $roomDatas = DB::table('tower_floor_rooms')
            ->join('room_types', 'room_types.id', '=', 'tower_floor_rooms.room_type_id')
            ->select('tower_floor_rooms.*', 'room_types.name as room_type')
            ->get();

        $this->responseMessage = "All rooms data fetched successfully !";
        $this->outputData =  $roomDatas;
        $this->success = true;
    }
}
