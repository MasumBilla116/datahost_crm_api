<?php

namespace  App\Controllers\Customers;

use App\Auth\Auth;
use App\Helpers\Accounting;
use App\Validation\Validator;
use App\Models\Customers\Client;
use App\Response\CustomResponse;
use App\Models\Accounts\Accounts;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Models\RBM\TowerFloorRoom;
use App\Requests\CustomRequestHandler;
use Carbon\Carbon;

use Respect\Validation\Validator as v;
use App\Models\Accounts\AccountCustomer;
use App\Models\Customers\CustomerBooking;
use App\Models\Customers\CustomerBookingGrp;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use stdClass;

class CustomerController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $customer;
    protected $customerBookingGrp;
    protected $customerBooking;
    protected $towerFloorRoom;
    protected $accountCustomer;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->customer = new Customer();
        $this->customerBookingGrp = new CustomerBookingGrp();
        $this->customerBooking = new CustomerBooking();
        $this->towerFloorRoom = new TowerFloorRoom();
        $this->accountCustomer = new AccountCustomer();

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
            case 'createIndividualCustomer':
                $this->createIndividualCustomer($request, $response);
                break;
            case 'updateIndividualCustomer':
                $this->updateIndividualCustomer($request, $response);
                break;
            case 'individualCustomerInfo':
                $this->individualCustomerInfo();
                break;
            case 'customerInfo':
                $this->customerInfo();
                break;

            case 'customerInfoOld':
                $this->customerInfoOld();
                break;

                // customerInfoOld
            case 'getAllCustomer':
                $this->getAllCustomer();
                break;
            case 'getCustomerNumberList':
                $this->getCustomerNumberList();
                break;

            case 'getAllCustomerList':
                $this->getAllCustomerList();
                break;
            case 'getRoomInfoByCustomerId':
                $this->getRoomInfoByCustomerId();
                break;
            case 'customerInfoByID':
                $this->customerInfoByID();
                break;
            case 'deleteCustomerByID':
                $this->deleteCustomerByID();
                break;
            case 'createCorporateCustomer':
                $this->createCorporateCustomer();
                break;
            case 'getCorporateCustomerByID':
                $this->getCorporateCustomerByID();
                break;
            case 'updateCorporateCustomerByID':
                $this->updateCorporateCustomerByID();
                break;
            case 'deleteCorporateCustomerByID':
                $this->deleteCorporateCustomerByID();
                break;
            case 'customSearch':
                $this->customSearch();
                break;


            case 'getAllcheckinCustomer':
                $this->getAllcheckinCustomer();
                break;
            case 'getCheckInRoomCustomer':
                $this->getCheckInRoomCustomer();
                break;
                // getCheckInRoomCustomer

            case 'getAllCustomerNew':
                $this->getAllCustomerNew();
                break;



            case 'customSearchByRoomno':
                $this->customSearchByRoomno($request);
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

    //demo frontend function
    public function web(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);

        $customers = DB::table('customers')->where('id', $this->params->customer_id)->first();
        $this->user = Auth::user($request);

        if (!$customers) {
            $this->responseMessage = "No customer found !";
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        $this->responseMessage = "fetch all customers Successfully!";
        $this->outputData['customers'] = $customers;
        $this->outputData['user'] = $this->user;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function createIndividualCustomer(Request $request)
    {
        $this->validator->validate($request, [
            "mobile" => v::notEmpty(),
            "contact_type" => v::notEmpty(),
            "title" => v::notEmpty(),
            "fName" => v::notEmpty(),
            "lName" => v::notEmpty(),
            "gender" => v::notEmpty(),
            "birth_date" => v::notEmpty(),
            "nationality" => v::notEmpty(),
            "country_id" => v::notEmpty(),
            "state_id" => v::notEmpty(),
            "city_id" => v::notEmpty(),
            "pin_code" => v::notEmpty(),
            "arrival_from" => v::notEmpty(),
            "address" => v::notEmpty(),
            "status" => v::intVal()->notEmpty(),

        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $mobile = $this->params->mobile;

        function isDigits(string $s, int $minDigits = 9, int $maxDigits = 14): bool
        {
            return preg_match('/^[0-9]{' . $minDigits . ',' . $maxDigits . '}\z/', $s);
        }

        if (!isDigits($mobile)) {
            $this->responseMessage = "Invalid mobile number !";
            $this->success = false;
            return;
        }

        $existcustomer = $this->customer->where(["mobile" => $this->params->mobile, 'status' => 1, 'customer_type' => 0])->first();

        if ($existcustomer) {
            $this->responseMessage = "Customer has been already exist !";
            $this->success = false;
            return;
        }


        $customer = $this->customer;

        //customer info
        $customer->mobile = $this->params->mobile;
        $customer->contact_type = $this->params->contact_type;
        $customer->title = $this->params->title;
        $customer->first_name = $this->params->fName;
        $customer->last_name = $this->params->lName;
        $customer->gender = $this->params->gender;

        if ($this->params->birth_date) {
            $customer->dob = date('Y-m-d', strtotime($this->params->birth_date));
        }
        if ($this->params->anniversary_date !== null) {
            $customer->anniversary_date = date('Y-m-d', strtotime($this->params->anniversary_date));
        }

        $customer->nationality = $this->params->nationality;
        $customer->country_id = $this->params->country_id;
        $customer->state_id = $this->params->state_id;
        $customer->city_id = $this->params->city_id;
        $customer->pin_code = $this->params->pin_code;
        $customer->arrival_from = $this->params->arrival_from;
        $customer->address = $this->params->address;
        $customer->status = $this->params->status;
        $customer->created_by = $this->user->id;
        $customer->save();


        $this->responseMessage = "New Customer has been created successfully";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function updateIndividualCustomer(Request $request)
    {
        $this->validator->validate($request, [
            "mobile" => v::notEmpty(),
            "contact_type" => v::notEmpty(),
            "title" => v::notEmpty(),
            "fName" => v::notEmpty(),
            "lName" => v::notEmpty(),
            "gender" => v::notEmpty(),
            "birth_date" => v::notEmpty(),
            "nationality" => v::notEmpty(),
            "country_id" => v::notEmpty(),
            "state_id" => v::notEmpty(),
            "city_id" => v::notEmpty(),
            "pin_code" => v::notEmpty(),
            "arrival_from" => v::notEmpty(),
            "address" => v::notEmpty(),
            "status" => v::intVal()->notEmpty(),

        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $customer = $this->customer->find($this->params->customer_id);

        $existcustomer = $this->customer->where(["mobile" => $this->params->mobile, 'status' => 1, 'customer_type' => 0])->first();

        if ($existcustomer && $customer->id !== $existcustomer->id) {
            $this->responseMessage = "Customer has been already exist !";
            $this->success = false;
            return;
        }

        //customer info
        $customer->mobile = $this->params->mobile;
        $customer->contact_type = $this->params->contact_type;
        $customer->title = $this->params->title;
        $customer->first_name = $this->params->fName;
        $customer->last_name = $this->params->lName;
        $customer->gender = $this->params->gender;

        if ($this->params->birth_date) {
            $customer->dob = date('Y-m-d', strtotime($this->params->birth_date));
        }
        if ($this->params->anniversary_date !== null) {
            $customer->anniversary_date = date('Y-m-d', strtotime($this->params->anniversary_date));
        }

        $customer->nationality = $this->params->nationality;
        $customer->country_id = $this->params->country_id;
        $customer->state_id = $this->params->state_id;
        $customer->city_id = $this->params->city_id;
        $customer->pin_code = $this->params->pin_code;
        $customer->arrival_from = $this->params->arrival_from;
        $customer->address = $this->params->address;
        $customer->status = $this->params->status;
        $customer->created_by = $this->user->id;
        $customer->save();


        $this->responseMessage = "Customer has been updated successfully !";
        $this->outputData = $customer;
        $this->success = true;
    }

    //Fetching customer info
    public function customerInfo()
    {
        $customer = null;
        if (!empty($this->params->mobile)) {
            $query = Customer::query();
            $query->leftJoin('countries', 'countries.id', '=', 'customers.country_id');
            $query->leftJoin('states', 'states.id', '=', 'customers.state_id');
            $query->leftJoin('cities', 'cities.id', '=', 'customers.city_id');
            $query->where('customers.mobile', '=', $this->params->mobile);
            $query->where('customers.status', '=', 1);
            $customer = $query->first(['customers.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name']);
        } else if (!empty($this->params->customer_id)) {
            $query = Customer::query();
            $query->leftJoin('countries', 'countries.id', '=', 'customers.country_id');
            $query->leftJoin('states', 'states.id', '=', 'customers.state_id');
            $query->leftJoin('cities', 'cities.id', '=', 'customers.city_id');
            $query->where('customers.id', '=', $this->params->customer_id);
            $query->where('customers.status', '=', 1);
            $customer = $query->first(['customers.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name']);
        }

        if (!$customer) {
            $this->responseMessage = "No customer is available !";
            $this->outputData = [];
            $this->success = false;
            return;
        }

        // $bookings = DB::table('customer_booking_master')
        //     // ->select('customer_booking_days.booking_master_id')
        //     ->join('customer_booking_days','customer_booking_master.id','=','customer_booking_days.booking_master_id')
        //     ->join('tower_floor_rooms','tower_floor_rooms.id','=','customer_booking_days.room_id')
        //     ->where('customer_booking_master.customer_id','=',$customer->id)
        //     ->get()
        //     ->groupBy('customer_booking_days.booking_master_id');


        //when needed tree view , parent-child

        //Commented by tazim, do not enable this codes
        // $bookings = $this->customerBookingGrp->where('customer_id', $customer->id)->with('bookingDays', function ($query) {
        //     $query->with('room')->get();
        // })->get();

        // $this->responseMessage = "Customer Info has been fetched successfully";
        // $this->outputData = $customer ?? [];
        // $this->outputData['bookings'] = $bookings ?? [];

        // $this->outputData['country'] = $customer->country ?? [];
        // $this->outputData['state'] = $customer->state ?? [];
        // $this->outputData['city'] = $customer->city ?? [];
        //---------------------------------------------------
        $this->outputData = $customer;
        $this->success = true;
    }

    public function customerInfoOld()
    {
        $customer = Customer::where(['id' => $this->params->customer_id, 'status' => 1])->first();
        // $customer = Customer::where(['mobile' => $this->params->mobile, 'status' => 1])->orWhere(['id' => $this->params->customer_id])->first();

        if (!$customer) {
            $this->responseMessage = "No customer is available !";
            $this->outputData = [];
            $this->success = false;
        }


        //when needed tree view , parent-child
        $bookings = $this->customerBookingGrp->where('customer_id', $this->params->customer_id)->with('bookingDays', function ($query) {
            $query->with('room')->get();
        })->get();

        $this->responseMessage = "Customer Info has been fetched successfully";
        $this->outputData = $customer;
        $this->outputData['bookings'] = $bookings;

        $this->outputData['country'] = $customer->country;
        $this->outputData['state'] = $customer->state;
        $this->outputData['city'] = $customer->city;

        $this->success = true;
    }

    //Individual Customer Info
    public function individualCustomerInfo()
    {
        $customer = $this->customer->where(["mobile" => $this->params->mobile, 'customer_type' => 0, 'status' => 1])->first();

        $this->responseMessage = "Customer Info has been fetched successfully";
        $this->outputData = $customer;

        $this->outputData['country'] = $customer->country;
        $this->outputData['state'] = $customer->state;
        $this->outputData['city'] = $customer->city;

        $this->success = true;
    }

    // Done By MAMUN -------------------->
    public function getAllCustomer()
    {

        $customer = DB::table('customers')
            ->where('customers.status', 1)
            ->get();


        $this->responseMessage = "All customer fetched Successfully!";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function getCustomerNumberList()
    {
        $keyword = $this->params->keyword;

        $query = DB::table('customers');
        $query->where(function ($query) use ($keyword) {
            $query->orWhere('customers.first_name', 'LIKE', '%' . $keyword . '%', 'i')
                ->orWhere('customers.mobile', 'LIKE', '%' . $keyword . '%', 'i');
        });
        $customerList = $query->limit(20)->get(['mobile', 'first_name']);

        $this->responseMessage = "Fetched Successfully!";
        $this->outputData = $customerList;
        $this->success = true;
    }


    public function getAllcheckinCustomer()
    {

        $customer = DB::table('customers')
            ->where('customers.status', 1)
            ->get();

        $this->responseMessage = "All customers fetched successfully!";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function getAllCustomerList()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('customers');
        // ->where('customers.status', 1)
        // ->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('customers.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('customers.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('customers.first_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.last_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.mobile', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.personal_id', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_customers =  $query->orderBy('customers.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();

        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "All customer fetched Successfully!";
        $this->outputData = [
            $pageNo => $all_customers,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    //Room info by customer id
    public function getRoomInfoByCustomerId()
    {
        //get roominfo which customer avail from booking days
        $room = DB::table('customer_booking_days')
            ->select('customer_booking_days.*', 'tower_floor_rooms.room_no')
            ->where('customer_booking_days.customer_id', $this->params->cust_id)
            ->join('tower_floor_rooms', 'customer_booking_days.room_id', 'tower_floor_rooms.id')
            ->orderBy('customer_booking_days.id', 'desc')
            ->first();

        if (!$room) {
            $this->responseMessage = "Data not found!";
            $this->outputData = [];
            $this->success = false;
        }

        $this->responseMessage = "customer's booked room info fetched Successfully!";
        $this->outputData = $room;
        $this->success = true;
    }

    public function customSearch()
    {
        //state declaration
        $key = $this->params->key;

        if ($key) {

            $customer = $this->customer
                ->where('first_name', 'like', '%' . $key . '%')
                ->orWhere('mobile', 'like', '%' . $key . '%')
                ->where('status', 1)
                ->get();

            // $this->responseMessage = "All customer fetched Successfully!";
            // $this->outputData = $customer;
            // $this->success = true;
        } else {

            $customer = $this->customer->where('status', 1)->get();
        }

        if (count($customer) > 0) {
            $this->responseMessage = "All customer fetched Successfully!";
            $this->outputData = $customer;
            $this->success = true;
            return;
        }

        $this->responseMessage = "customer not found!";
        $this->outputData = [];
        $this->success = false;
    }

    public function customerInfoByID()
    {
        $customer = $this->customer->where(["id" => $this->params->id])->get();

        $this->responseMessage = "Customer Info has been fetched successfully";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function createCorporateCustomer()
    {

        $duplicateClient = $this->customer
            ->where(["customers.mobile" => $this->params->mobile])
            ->where(['customers.status' => 1])
            ->where(['customers.customer_type' => 1])
            ->where(['customers.corporate_client_id' => $this->params->corporate_client_id])
            ->get();

        if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
            $this->success = false;
            $this->responseMessage = "mobile " . $duplicateClient[0]->mobile . " already exits!";
            return;
        }

        $customer = $this->customer->create([
            "title" => $this->params->title,
            "first_name" => $this->params->first_name,
            "last_name" => $this->params->last_name,
            "mobile" => $this->params->mobile,
            "gender" => $this->params->gender === null ? 'male' : $this->params->gender,
            "dob" => date('Y-m-d', strtotime($this->params->birth_date)),
            "nationality" => $this->params->nationality,
            "arrival_from" => $this->params->arrival_from,
            "corporate_client_id" => $this->params->corporate_client_id,
            "created_by" => $this->user->id,
            "updated_by" => $this->user->id,
            "status" => 1,
            "customer_type" => 1,
        ]);

        $this->responseMessage = "new customer has been created successfully";
        $this->outputData = $duplicateClient;
        $this->success = true;
    }

    public function getCorporateCustomerByID()
    {

        //varaible declaration
        $id = $this->params->id;

        $customers = $this->customer
            ->select('customers.*')
            ->where(["customers.id" => $id])
            ->first();

        if (empty($customers)) {
            $this->success = false;
            $this->responseMessage = "customer not found!";
            return;
        }

        $this->responseMessage = "requested customer fetched Successfully!";
        $this->outputData = $customers;
        $this->success = true;
    }

    public function updateCorporateCustomerByID()
    {

        $duplicateClient = $this->customer
            ->where(["customers.mobile" => $this->params->mobile])
            ->where(['customers.status' => 1])
            ->where(['customers.customer_type' => 1])
            ->where(['customers.corporate_client_id' => $this->params->corporate_client_id])
            ->get();

        if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
            $this->success = false;
            $this->responseMessage = "mobile " . $duplicateClient[0]->mobile . " already exits!";
            return;
        }

        $customer = $this->customer
            ->where(["customers.id" => $this->params->id])
            ->update([
                "title" => $this->params->title,
                "first_name" => $this->params->first_name,
                "last_name" => $this->params->last_name,
                "mobile" => $this->params->mobile,
                "gender" => $this->params->gender === null ? 'male' : $this->params->gender,
                "dob" => date('Y-m-d', strtotime($this->params->birth_date)),
                "nationality" => $this->params->nationality,
                "arrival_from" => $this->params->arrival_from,
                "corporate_client_id" => $this->params->corporate_client_id,
                "created_by" => $this->user->id,
                "updated_by" => $this->user->id,
                "status" => 1,
                "customer_type" => 1,
            ]);

        $this->responseMessage = "requested customer has been updated successfully";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function deleteCorporateCustomerByID()
    {
        //varaible declaration
        $id = $this->params->id;

        $customer = $this->customer
            ->where('customers.id', '=', $id)
            ->update(['status' => 0]);

        $customers = $this->customer
            ->select('customers.*')
            ->where(['customers.status' => 1])
            ->where(['customers.customer_type' => 1])
            ->where(['customers.corporate_client_id' => $this->params->corporate_client_id])
            ->get();

        $this->responseMessage = "requested customer deleted Successfully!";
        $this->outputData = $customers;
        $this->success = true;
    }

    public function deleteCustomerByID()
    {
        //varaible declaration
        $id = $this->params->id;

        $customer = $this->customer
            ->where('customers.id', '=', $id)
            ->update(['status' => 0]);

        $customers = $this->customer
            ->where(['customers.status' => 1])
            ->select('customers.*')
            ->get();

        $this->responseMessage = "requested customer deleted Successfully!";
        $this->outputData = $customers;
        $this->success = true;
    }



    public function customSearchByRoomno(Request $request)
    {
        $now = Carbon::now();
        $date = $now->format('Y-m-d');



        $customers = DB::table('customers')
            ->join('customer_booking_days', 'customer_booking_days.customer_id', '=', 'customers.id')
            ->join('tower_floor_rooms', 'tower_floor_rooms.id', '=', 'customer_booking_days.room_id')
            ->where('tower_floor_rooms.room_no', '=', $this->params->room_no)
            ->where('customer_booking_days.date', '=', $date)
            ->first();




        if (!$customers) {
            $this->success = false;
            $this->responseMessage = "customer not found";
            return;
        }


        $this->responseMessage = "requested customer deleted Successfully!";
        $this->outputData =  $customers;
        $this->success = true;
    }


    public function getAllCustomerNew()
    {

        $customer = DB::table('customer_booking_master')
            ->select(
                'customer_booking_master.*',
                'tower_floor_rooms.room_no',
                'customers.id as cust_id',
                'customers.first_name as first_name',
                'customers.last_name as last_name',
                'customers.mobile as mobile',
            )
            ->join('customers', 'customer_booking_master.customer_id', 'customers.id')
            ->leftJoin('customer_booking_days', 'customers.id', 'customer_booking_days.customer_id')
            ->leftJoin('tower_floor_rooms', 'customer_booking_days.room_id', 'tower_floor_rooms.id')
            ->whereNull('customer_booking_master.checkout_at')
            ->orderBy('customer_booking_master.id', 'desc')
            ->distinct()
            ->get();


        $this->responseMessage = "All customer fetched Successfully!";
        $this->outputData = $customer;
        $this->success = true;
    }

    public function getCheckInRoomCustomer()
    {


        $customer = DB::table("customer_booking_days")->join("customers", "customers.id", "=", "customer_booking_days.customer_id")
            ->where("customer_booking_days.room_id", $this->params->room_id)
            ->select('customers.*', 'customer_booking_days.room_id as room_id', 'customer_booking_days.booking_master_id')

            ->groupBy("customers.id", "customer_booking_days.room_id", 'customer_booking_days.booking_master_id')
            ->get();
        $this->outputData = $customer;


        if (empty($customer)) {
            $this->outputData = [];
        }


        $this->responseMessage = "All customers fetched successfully!";
        $this->success = true;
    }
}
