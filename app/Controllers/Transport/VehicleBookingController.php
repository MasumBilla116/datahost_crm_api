<?php

namespace  App\Controllers\Transport;

use DateTime;
use App\Auth\Auth;
use Carbon\Carbon;
use App\Helpers\Accounting;


use App\Validation\Validator;

use App\Response\CustomResponse;
use App\Models\Accounts\Accounts;
use App\Models\Transport\Drivers;
use App\Models\Customers\Customer;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Transport\VehicleBooking;
use App\Models\Transport\TransportVehicles;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class VehicleBookingController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $vehicle;
    protected $driver;
    protected $vehicle_booking;
    protected $helper;
    protected $customer;
    protected $accounts;



    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->customer = new Customer();
        $this->vehicle = new TransportVehicles();
        $this->driver = new Drivers();
        $this->vehicle_booking = new VehicleBooking();
        $this->accounts = new Accounts();
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


            case 'createVehicleBooking':
                $this->createVehicleBooking($request);
                break;

            case 'getAllVehicleBooking':
                $this->getAllVehicleBooking();
                break;

            case 'getAllVehicleBookingList':
                $this->getAllVehicleBookingList();
                break;




            case 'deleteVehicleBooking':
                $this->deleteVehicleBooking();
                break;

            case 'getVehicleBookingInfo':
                $this->getVehicleBookingInfo();
                break;

            case 'updateVehicleBooking':
                $this->updateVehicleBooking($request, $response);
                break;


            case 'makeVehicleBookingPayments':
                $this->makeVehicleBookingPayments($request, $response);
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


    public function createVehicleBooking($request)
    {

        $now = Carbon::now();
        $date = $now->format('ym');
        $last_voucher = DB::table('vehicle_booking')
            ->select('id')
            ->orderBy('id', 'DESC')
            ->first();
        $voucher_id = $last_voucher->id + 1;
        if ($voucher_id == null) {
            $voucher_id = 1;
        }

        $voucher_number = sprintf('VBV-%s000%d', $date, $voucher_id);
        $customerInfo = $this->params->customerInfo;
        $count = count($customerInfo);
        $vehicle_booking_items = [];


        $dateTime = new DateTime($this->params->date);
        $formattedDate = $dateTime->format('Y-m-d');


        $dateTime = new DateTime($this->params->time);
        $formattedTime = $dateTime->format('h:i:s A');


        $endTime = new DateTime($this->params->endtime);
        $formattedEndTime = $endTime->format('h:i:s A');


        // Check if there's already a booking for the same vehicle within the specified time range and date
        $existingBooking = DB::table("vehicle_booking")
            ->where("vehicle_id", $this->params->vehicleId)
            ->where("booking_date", $formattedDate)
            ->where(function ($query) use ($formattedTime, $formattedEndTime) {
                $query->whereBetween("booking_time", [$formattedTime, $formattedEndTime])
                    ->orWhereBetween("booking_end_time", [$formattedTime, $formattedEndTime])
                    ->orWhere(function ($query) use ($formattedTime, $formattedEndTime) {
                        $query->where("booking_time", "<", $formattedTime)
                            ->where("booking_end_time", ">", $formattedEndTime);
                    });
            })
            ->first();

        if ($existingBooking) {
            $this->success = false;
            $this->responseMessage = "This vehicle is already assigned";
            return;
        }

        $vehicle_bookingId = $this->vehicle_booking
            ->insertGetId([
                "booking_type" =>  $this->params->bookingType,
                "customer_type" =>  $this->params->customer_type,
                "total_customer" => $count,
                "vehicle_id" =>  $this->params->vehicleId,
                "booking_date" => $formattedDate,
                "booking_time" => $formattedTime,
                "booking_end_time" => $formattedEndTime,
                "total_amount" => $this->params->totalCharge,
                "local_voucer" => $voucher_number,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $invoice_number = sprintf("VI-%s000%d", $date, $vehicle_bookingId);

        DB::table('vehicle_booking_items')
            ->where([
                'vehicle_booking_items.vehicle_booking_id' => $this->params->vehicleBookingId,
                'vehicle_booking_items.status' => 1
            ])
            ->update([
                'status' => 0
            ]);



        for ($j = 0; $j < $count; $j++) {


            $vehicle_booking_items[] = [
                "vehicle_booking_id" => $vehicle_bookingId,
                "booking_voucher" => $voucher_number,
                "customer_id" => $customerInfo[$j]["customer_id"],
                "customer_name" => $customerInfo[$j]["customerName"],
                "booking_charge" => intval($customerInfo[$j]["booking_charge"]),
                "is_paid" => 0,
                'created_by' => $this->user->id,
                'status' => 1,
            ];



            // ------------customer table-------------------
            $singleCustomerInfo = DB::table('customers')
                ->where('id', '=', $customerInfo[$j]["customer_id"])
                ->where('status', '=', 1)
                ->first();

            // ------------customer  account table end-------------------
            $credited_note = "";
            $debited_note = "Vehicle booking bill generated";
            Accounting::accountCustomer(false, $customerInfo[$j]["customer_id"], $vehicle_bookingId, $invoice_number, "vehicle_booking", intval($customerInfo[$j]["booking_charge"]), null, $credited_note, $debited_note, $this->user->id, false);
        }

        if (count($vehicle_booking_items) > 0) {
            DB::table('vehicle_booking_items')->insert($vehicle_booking_items);
        }

        $this->responseMessage = "Vehicles has been created successfully!";
        $this->outputData = $vehicle_bookingId;
        $this->success = true;
    }


    public function getAllVehicleBooking()
    {
        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;

        if ($filter == 'all') {
            $allVehicleBooking = $this->vehicle_booking
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'deleted') {
            $allVehicleBooking = $this->vehicle_booking
                ->where('status', 0)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'daily') {
            $allVehicleBooking = $this->vehicle_booking
                ->whereDate('created_at', date('Y-m-d'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'weekly') {

            $allVehicleBooking = $this->vehicle_booking
                ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'monthly') {
            $allVehicleBooking = $this->vehicle_booking
                ->whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'yearly') {
            $allVehicleBooking = $this->vehicle_booking
                ->whereYear('created_at', date('Y'))
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else if ($filter == 'custom' && $start_date && $end_date) {
            $allVehicleBooking = $this->vehicle_booking
                ->whereBetween('created_at', [$start_date, $end_date])
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $allVehicleBooking = $this->vehicle_booking
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        }



        $this->responseMessage = 'Vehicle Booking list fetched successfully';
        $this->outputData = $allVehicleBooking;
        $this->success = true;
    }


    public function getAllVehicleBookingList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('vehicle_booking')
            ->join('transport_vehicles', 'transport_vehicles.id', 'vehicle_booking.vehicle_id');
        // ->get();


        if ($filter['status'] == 'all') {
            $query->where('vehicle_booking.status', '=', 1);
        }
        if ($filter['status'] == 'deleted') {
            $query->where('vehicle_booking.status', '=', 0);
        }

        if ($filter['status'] == 'daily') {
            $query->whereDate('vehicle_booking.created_at', date('Y-m-d'));
        }

        if ($filter['status'] == 'weekly') {
            $query->whereBetween('vehicle_booking.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->whereDate('vehicle_booking.created_at', date('Y-m-d'));
        }


        if ($filter['status'] == 'monthly') {
            $query->whereYear('vehicle_booking.created_at', date('Y'))
                ->whereMonth('vehicle_booking.created_at', date('m'));
        }

        if ($filter['status'] == 'yearly') {
            $query->whereYear('vehicle_booking.created_at', date('Y'));
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('vehicle_booking.local_voucer', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        // if ($this->user->data_access_type === 'own') {
        //     $query->where('transport_vehicles.employee_id', '=', $this->user->id);
        // }


        if ($this->user->data_access_type === 'own') {
            $empId = DB::table('employees')->select('id')->where('user_id', '=', $this->user->id)->first();
            if ($empId) {

                $query->where('transport_vehicles.employee_id', '=', $empId->id);
            }
        }

        $all_list =  $query->orderBy('vehicle_booking.id', 'desc')
            ->select('vehicle_booking.*')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = 'Vehicle Booking list fetched successfully';
        $this->outputData = [
            $pageNo => $all_list,
            'total' => $totalRow,
        ];
        $this->success = true;
    }



    public function deleteVehicleBooking()
    {
        if (!isset($this->params->vehicleBookingId)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
        }
        $vehicleBooking = $this->vehicle_booking
            ->where('id', $this->params->vehicleBookingId)
            ->update(['status' => 0]);
        if (!$vehicleBooking) {
            $this->success = false;
            $this->responseMessage = 'vehicle Booking not found!';
            return;
        }

        $this->responseMessage = 'Vehicle Booking has been Delete successfully';
        $this->outputData = $vehicleBooking;
        $this->success = true;
    }


    public function getVehicleBookingInfo()
    {


        $vehicleBookingInfo = $this->vehicle_booking->with('creator')->findOrFail($this->params->vehicleBookingId);

        if (!isset($this->params->vehicleBookingId)) {
            $this->success = false;
            $this->responseMessage = 'Parameter missing';
            return;
        }

        if ($vehicleBookingInfo->status == 0) {
            $this->success = false;
            $this->responseMessage = 'vehicleBookingInfo missing!';
            return;
        }

        if (!$vehicleBookingInfo) {
            $this->success = false;
            $this->responseMessage = 'vehicleBookingInfo not found!';
            return;
        }

        $vehicleBook = DB::table('vehicle_booking')
            // ->join('vehicle_booking_items', 'vehicle_booking_items.vehicle_booking_id', '=', 'vehicle_booking.id')
            ->join('vehicle_booking_items', 'vehicle_booking_items.vehicle_booking_id', '=', 'vehicle_booking.id')
            ->join('transport_vehicles', 'transport_vehicles.id', '=', 'vehicle_booking.vehicle_id')
            ->join('customers', 'customers.id', '=', 'vehicle_booking_items.customer_id')
            ->select(
                'transport_vehicles.model as vehicle_name',
                'vehicle_booking_items.customer_name as customerName',
                'vehicle_booking_items.booking_charge',
                'vehicle_booking_items.customer_id as customer_id',
                'vehicle_booking_items.is_paid as is_paid'
            )
            ->where('vehicle_booking.status', '=', 1)
            ->where('vehicle_booking_items.status', '=', 1)
            ->where('vehicle_booking.id', '=', $this->params->vehicleBookingId)
            ->get();
        $this->responseMessage = 'rcv_bck info fetched successfully';
        $this->outputData = $vehicleBookingInfo;
        $this->outputData['vehicleBookDetails'] = $vehicleBook;
        $this->success = true;
    }


    public function updateVehicleBooking(Request $request, Response $response)
    {


        $vehicleBooking = $this->vehicle_booking->where('status', 1)->find($this->params->vehicleBookingId);

        // $this->outputData = $prevBalance ;
        // return;
        if (!$vehicleBooking) {
            $this->success = false;
            $this->responseMessage = 'Item not found!';
            return;
        }
        $customerInfo = $this->params->customerInfo;
        $now = Carbon::now();
        $date = $now->format('ym');
        $count = count($customerInfo);
        $voucher_number = sprintf('VBV-%s000%d', $date, $this->params->vehicleBookingId);
        $vehicle_booking_items = [];


        $this->vehicle_booking
            ->where('id', '=', $this->params->vehicleBookingId)
            ->update([
                'total_customer' => $count,
                'total_amount' => $this->params->totalCharge,
            ]);



        DB::table('vehicle_booking_items')
            ->where([
                'vehicle_booking_items.vehicle_booking_id' => $this->params->vehicleBookingId,
                'vehicle_booking_items.status' => 1
            ])
            ->update([
                'status' => 0
            ]);

        $tempAmount = 0;
        $prevAmount = intval($vehicleBooking->total_amount);
        $prnstAmount = intval($this->params->totalCharge);

        $balance = 0;



        for ($j = 0; $j < $count; $j++) {


            $vehicle_booking_items[] = [
                "vehicle_booking_id" => $this->params->vehicleBookingId,
                "booking_voucher" => $voucher_number,
                "customer_id" => $customerInfo[$j]["customer_id"],
                "customer_name" => $customerInfo[$j]["customerName"],
                "booking_charge" => intval($customerInfo[$j]["booking_charge"]),
                'created_by' => $this->user->id,
                'status' => 1,
            ];

            $singleCustomerInfo = DB::table('customers')
                ->where('id', '=', $customerInfo[$j]["customer_id"])
                ->where('status', '=', 1)
                ->first();


            $customerItemTbl = DB::table('vehicle_booking_items')
                ->where('vehicle_booking_id', '=', $this->params->vehicleBookingId)
                ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                // ->where('status', '=', 1)
                // ->select('booking_charge')
                ->orderBy('id', 'DESC')
                ->first();


            $customerAccountTbl =  DB::table('account_customer')
                ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                ->where('invoice_id', '=', $this->params->vehicleBookingId)
                ->where('status', '=', 1)
                ->orderBy('id', 'DESC')
                ->first();




            if ($prevAmount > $prnstAmount) {
                $tempAmount = $prevAmount - $prnstAmount;
                DB::table('customers')
                    ->where('id', '=', $customerInfo[$j]["customer_id"])
                    ->where('status', '=', 1)
                    ->update(['balance' => intval($singleCustomerInfo->balance) + $tempAmount]);

                if (intval($customerItemTbl->booking_charge) > intval($customerInfo[$j]["booking_charge"])) {

                    $balance = intval($customerItemTbl->booking_charge) - intval($customerInfo[$j]["booking_charge"]);
                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->where('status', '=', 1)
                        ->update([
                            'debit' => intval($customerAccountTbl->debit) < 0 ? intval($customerAccountTbl->debit) - (-$balance) : intval($customerAccountTbl->debit) - $balance,
                            'balance' => intval($customerAccountTbl->balance) + $balance

                        ]);
                } elseif (intval($customerItemTbl->booking_charge) < intval($customerInfo[$j]["booking_charge"])) {

                    $balance =  intval($customerInfo[$j]["booking_charge"]) -  intval($customerItemTbl->booking_charge);

                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('status', '=', 1)
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->update([
                            'debit' => intval($customerAccountTbl->debit) + $balance,
                            'debit' => intval($customerAccountTbl->debit) < 0 ? - (- (intval($customerAccountTbl->debit)) + $balance)  : intval($customerAccountTbl->debit) + $balance,
                        ]);
                } else {
                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->where('status', '=', 1)
                        ->update([
                            'debit' => intval($customerAccountTbl->debit),
                            // 'credit' => intval($customerAccountTbl->credit),
                            'balance' => intval($customerAccountTbl->balance)

                        ]);
                }
            } elseif ($prevAmount < $prnstAmount) {
                $tempAmount = $prnstAmount - $prevAmount;
                DB::table('customers')
                    ->where('id', '=', $customerInfo[$j]["customer_id"])
                    ->where('status', '=', 1)
                    ->update(['balance' => intval($singleCustomerInfo->balance) - $tempAmount]);



                if (intval($customerItemTbl->booking_charge) > intval($customerInfo[$j]["booking_charge"])) {
                    $balance = intval($customerItemTbl->booking_charge) - intval($customerInfo[$j]["booking_charge"]);
                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('status', '=', 1)
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->update([
                            // 'debit' => intval($customerAccountTbl->debit) - $balance,
                            'debit' => intval($customerAccountTbl->debit) < 0 ? intval($customerAccountTbl->debit) - (-$balance) : intval($customerAccountTbl->debit) - $balance,

                            // 'credit' => intval($customerAccountTbl->credit) + $balance,
                            'balance' => intval($customerAccountTbl->balance) + $balance

                        ]);
                } elseif (intval($customerItemTbl->booking_charge) < intval($customerInfo[$j]["booking_charge"])) {
                    $balance =  intval($customerInfo[$j]["booking_charge"]) -  intval($customerItemTbl->booking_charge);
                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->where('status', '=', 1)
                        ->update([
                            'debit' => intval($customerAccountTbl->debit) < 0 ? - (- (intval($customerAccountTbl->debit)) + $balance)  : intval($customerAccountTbl->debit) + $balance,
                            'balance' => intval($customerAccountTbl->balance) - $balance
                        ]);
                } else {
                    DB::table('account_customer')
                        ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                        ->where('invoice_id', '=', $this->params->vehicleBookingId)
                        ->where('status', '=', 1)
                        ->update([
                            'debit' => intval($customerAccountTbl->debit),
                            // 'credit' => intval($customerAccountTbl->credit),
                            'balance' => intval($customerAccountTbl->balance)

                        ]);
                }
            } else {
                DB::table('customers')
                    ->where('id', '=', $customerInfo[$j]["customer_id"])
                    ->where('status', '=', 1)
                    ->update(['balance' => intval($singleCustomerInfo->balance)]);

                DB::table('account_customer')
                    ->where('customer_id', '=', $customerInfo[$j]["customer_id"])
                    ->where('invoice_id', '=', $this->params->vehicleBookingId)
                    ->where('status', '=', 1)
                    ->update([
                        'debit' => intval($customerAccountTbl->debit),
                        // 'credit' => intval($customerAccountTbl->credit),
                        'balance' => intval($customerAccountTbl->balance)

                    ]);
            }
        }

        if (count($vehicle_booking_items) > 0) {
            DB::table('vehicle_booking_items')->insert($vehicle_booking_items);
        }


        $this->responseMessage = "Supplier Invoice Data fetched Successfully!";
        $this->outputData = $vehicleBooking;
        $this->success = true;
    }


    public function makeVehicleBookingPayments(Request $request, Response $response)
    {


        $singleCustomerInfo = DB::table('vehicle_booking_items')
            ->where('customer_id', '=', $this->params->customerId)
            ->where('status', '=', 1)
            ->orderBy('id', 'desc')
            ->first();


        if ($singleCustomerInfo->is_paid === 1) {
            $this->success = false;
            $this->responseMessage = "Already paid";
            return;
        }


        if (!$this->params->payment) {
            $this->success = false;
            $this->responseMessage = "Please select the account";
            return;
        }



        $bookingCharge = intval($singleCustomerInfo->booking_charge);

        if ($this->params->booking_charge > $bookingCharge || $this->params->booking_charge < $bookingCharge) {
            $this->success = false;
            $this->responseMessage = "please paid total ammount";
            return;
        }




        DB::table('vehicle_booking_items')
            ->where('customer_id', '=', $this->params->customerId)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->update(['is_paid' => 1]);


        $reference = 'PAYSLIP-' . $singleCustomerInfo->vehicle_booking_id . '-' . strtotime('now');


        $credited_note = "Vehicle booking bill collected";
        $debited_note = "Vehicle booking bill generated";

        Accounting::accountCustomer(false, $this->params->customerId, $singleCustomerInfo->vehicle_booking_id, $reference, "vehicle_booking", $this->params->booking_charge, $this->params->booking_charge, $credited_note, $debited_note, $this->user->id, true);




        if ($this->params->booking_charge > 0) {
            $credited_note = "payment taken from customer";
            $debited_note = "";

            Accounting::Accounts($this->params->payment, $singleCustomerInfo->vehicle_booking_id, "vehicle_booking", $this->params->booking_charge, $credited_note, $debited_note, $this->user->id,true);
        }




        $this->responseMessage = "Customer Data fetched Successfully!";
        $this->outputData = $bookingCharge;
        $this->success = true;
    }
}
