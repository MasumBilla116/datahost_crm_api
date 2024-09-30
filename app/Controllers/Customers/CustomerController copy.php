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

            case 'createCustomer':
                $this->createCustomer($request, $response);
                break;
            case 'cloneCustomerBooking':
                $this->cloneCustomerBooking($request, $response);
                break;
            case 'updateBooking':
                $this->updateBooking($request, $response);
                break;
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

            case 'getAllCustomerNew':
                $this->getAllCustomerNew();
                break;

            case 'customSearchByRoomno':
                $this->customSearchByRoomno($request);
                break;

            case 'getCheckInRoomCustomer':
                $this->getCheckInRoomCustomer();
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


    public function cloneCustomerBooking1(Request $request)
    {


        // get from existing booking customer id
        $bookingId = $this->params->bookingId;
        $bookingData = DB::table('customer_booking_master')->where('id', $bookingId)->first();


        DB::beginTransaction();
        try {

            $cloneBooking = $bookingData->replicate();
            $cloneBooking->invoice_id = 'RB-R-' . strtotime('now');
            $cloneBooking->promo_id = null;
            $cloneBooking->promo_discount = 0;
            $cloneBooking->additional_discount = 0;
            $cloneBooking->total_tax = 0;
            $cloneBooking->net_amount = 0;
            $cloneBooking->total_paid = 0;
            $cloneBooking->total_due = 0;
            $cloneBooking->status = 0;
            $cloneBooking->save();



            DB::commit();
            $this->responseMessage = "Booking cloned successfully";
            $this->outputData = [];
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->responseMessage = "Booking clone failed";
            $this->outputData = [];
            $this->success = true;
        }
    }


    public function cloneCustomerBooking(Request $request)
    {
        // get from existing booking customer id
        $bookingId = $this->params->bookingId;
        $bookingData = DB::table('customer_booking_master')->where('id', $bookingId)->first();

        DB::beginTransaction();
        try {
            // Create a new stdClass instance to store the cloned booking data
            $cloneBooking = new stdClass();
            $cloneBooking->invoice_id = 'RB-R-' . strtotime('now'); //95015
            $cloneBooking->promo_id = null;
            $cloneBooking->promo_discount = 0;
            $cloneBooking->additional_discount = 0;
            $cloneBooking->total_tax = 0;
            $cloneBooking->net_amount = 0;
            $cloneBooking->total_paid = 0;
            $cloneBooking->total_due = 0;
            $cloneBooking->platform = "FrontDesk";
            $cloneBooking->status = 3;
            $cloneBooking->payment_status = 0;

            $cloneBooking->customer_id = $bookingData->customer_id;
            $cloneBooking->date_from = $bookingData->date_from;
            $cloneBooking->date_to = $bookingData->date_to;
            $cloneBooking->created_by = $this->user->id;
            $cloneBooking->checkout_type = $bookingData->checkout_type;
            $cloneBookingId = DB::table('customer_booking_master')->insertGetId((array) $cloneBooking);

            if ($cloneBookingId) {
                DB::commit();
                $this->responseMessage = "Booking cloned successfully";
                $this->outputData = [];
                $this->success = true;
            } else {
                DB::rollBack();
                $this->responseMessage = "Failed to save cloned booking data";
                $this->outputData = [];
                $this->success = false;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->responseMessage = "Booking clone failed: " . $e->getMessage();
            $this->outputData = [];
            $this->success = false;
        }
    }



    public function createCustomer(Request $request)
    {


        $this->validator->validate($request, [
            "mobile" => v::notEmpty(),
            "contact_type" => v::notEmpty(),
            // "title" => v::notEmpty(),
            "fName" => v::notEmpty(),
            // "lName" => v::notEmpty(),
            "gender" => v::notEmpty(),
            "id_type" => v::notEmpty(),
            "personal_id" => v::notEmpty(),
            "birth_date" => v::notEmpty(),
            "nationality" => v::notEmpty(),
            "country_id" => v::notEmpty(),
            "state_id" => v::notEmpty(),
            "city_id" => v::notEmpty(),
            "pin_code" => v::notEmpty(),
            "arrival_from" => v::notEmpty(),
            "address" => v::notEmpty(),
            // "status" => v::notEmpty(),
            "room_type_id" => v::notEmpty(),
            "room_category_id" => v::notEmpty(),
            "room_id" => v::notEmpty(),
            "checkout_type" => v::notEmpty(),
            "date_from" => v::notEmpty(),
            // "account_id" => v::notEmpty(),
        ]);

        $payment_status = (int)$this->params->payment_status;
        $room_booking_status = (int)$this->params->room_booking_status;

        if ($this->params->corporatePay === false && $payment_status === 1) {
            $this->validator->validate($request, [
                "account_id" => v::notEmpty()
            ]);
        }

        if ($payment_status === 1 || $payment_status === 2) {
            $this->validator->validate($request, [
                "account_id" => v::notEmpty()
            ]);
        }

        if ($this->params->checkout_type === 'hourly') {
            $this->validator->validate($request, [
                "hourly_slot_id" => v::notEmpty()
            ]);
        }

        if ($this->params->adults < 0) {
            $this->success = false;
            $this->responseMessage = 'Enter no.of adults !';
            return;
        }
        if ($this->params->childs < 0) {
            $this->success = false;
            $this->responseMessage = 'Enter no.of childs !';
            return;
        }


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $mobile = $this->params->mobile;

        // function isMobile(string $s, int $minDigits = 8, int $maxDigits = 14): bool
        // {
        //     return preg_match('/^[0-9]{' . $minDigits . ',' . $maxDigits . '}\z/', $s);
        // }

        // if (!isMobile($mobile)) {
        //     $this->responseMessage = "Invalid mobile number !";
        //     $this->success = false;
        //     return;
        // }

        DB::beginTransaction();
        try {



            $duplicateCustomer = $this->customer->where(['id_type' => $this->params->id_type, 'personal_id' => $this->params->personal_id])->first();

            $existcustomer = $this->customer->where(["mobile" => $this->params->mobile])->first();

            if ($existcustomer && $this->params->newCustomer === false) {
                $customer = $existcustomer;
                if ($duplicateCustomer && $customer->id != $duplicateCustomer->id) {
                    $this->responseMessage = "Customer has Already Exist with this ID!";
                    $this->success = false;
                    return;
                }
            } else {
                $customer = $this->customer;

                if ($duplicateCustomer) {
                    $this->responseMessage = "Customer has Already Exist with this ID!";
                    $this->success = false;
                    return;
                }
            }

            //customer info
            $customer->mobile = $this->params->mobile;
            $customer->contact_type = $this->params->contact_type;
            $customer->title = $this->params->title ?? "";
            $customer->first_name = $this->params->fName;
            $customer->last_name = $this->params->lName ?? "";
            $customer->gender = $this->params->gender;
            $customer->id_type = $this->params->id_type;
            $customer->personal_id = $this->params->personal_id;

            $customer->balance = 0;

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

            //re-call updated customer
            $customer = DB::table('customers')->where('id', $customer->id)->first();
            //if customer created, then create customer booking group
            if ($customer) {

                $customer_last_order = DB::table('customer_booking_master')
                    ->where('customer_id', $customer->id)
                    ->orderBy('id', 'DESC')
                    ->first();


                $customer_booking_grp = $this->customerBookingGrp;
                $customer_booking_grp->platform = "FrontDesk";
                $customer_booking_grp->invoice_type = "booking";
                $customer_booking_grp->customer_id = $customer->id;
                $customer_booking_grp->checkout_type = $this->params->checkout_type;

                if ($payment_status === 1) {
                    $customer_booking_grp->payment_accounts_id = $this->params->account_id;
                }


                if ($this->params->checkout_type === 'hourly') {
                    $customer_booking_grp->hourly_slot_id = $this->params->hourly_slot_id;
                }

                $customer_booking_grp->date_from = date('Y-m-d', strtotime($this->params->date_from));


                // @@ from and to date calculation @@
                $begin = $customer_booking_grp->date_from;
                if ($this->params->date_to !== null) {
                    $customer_booking_grp->date_to = date('Y-m-d', strtotime($this->params->date_to));
                    $end = $customer_booking_grp->date_to;
                } else {
                    $end = $begin;
                }

                //Tarrif information for date range price info, tarrif id, tarrif amount
                $room_type_info = DB::table('room_types')->where('id', $this->params->room_type_id)->first();

                if ($this->params->checkout_type === 'hourly') {
                    $tarrif = DB::table('room_prices_hourly')
                        ->where(['room_type_id' => $this->params->room_type_id, 'hour_slot_id' => $this->params->hourly_slot_id, 'date' => $begin])
                        ->first();
                } else {

                    $tarrif = DB::table('room_price')
                        ->where('room_type_id', $this->params->room_type_id)
                        ->whereBetween('date', [$begin, $end])
                        ->get();
                }

                // $tarrif = DB::table('room_price')
                // ->where('room_type_id',$this->params->room_type_id)
                // ->whereBetween('date',[$begin,$end])
                // ->get();

                $additional_adult_tarrif = $this->params->totalAdultAmount;
                $additional_child_tarrif = $this->params->totalChildAmount;



                $arr = array();
                //Tower Id
                $room = DB::table('tower_floor_rooms')->find($this->params->room_id);

                // for ($x = 0, $i = date('Y-m-d', strtotime("+1 day", strtotime($begin))); $i <= $end; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {
                //     $arr[] = array(
                //         'customer_id' => $customer->id,
                //         'room_id' => $this->params->room_id,
                //         'tower_id' => $room->tower_id,
                //         'room_type_id' => $this->params->room_type_id,
                //         'room_category_id' => $this->params->room_category_id,
                //         'date' => date('Y-m-d', strtotime($i)),
                //         'adults' => $this->params->adults,
                //         'childs' => $this->params->childs,

                //     );
                // }
                //end customer booking
                $customer_booking_grp->invoice_id = 'RB-' . $room->room_no . '-' . strtotime('now');
                // $customer_booking_grp->checkin_at = date("Y-m-d h:i:s",time() + 6 * 60 * 60);

                $corporate_pay = $this->params->corporatePay;
                $total_amount = $this->params->total_amount;
                $promo_discount = $this->params->promo_discount;
                $additional_discount = $this->params->additional_discount;
                $total_tax = $this->params->total_tax;
                $total_paid = 0;

                if ($payment_status === 2) {

                    $total_paid = 0;
                } else {
                    $total_paid = (float)$this->params->total_paid;
                }
                // $total_paid_int = $this->params->total_paid;
                // $total_paid = intval($total_paid_int);

                // $total_paid = $this->params->total_paid;
                if ($corporate_pay === false) {
                    $net_amount = ($total_amount + $total_tax) - ($promo_discount + $additional_discount);
                    $total_due = $net_amount - $total_paid;
                    $revenue = $net_amount - $total_tax;

                    $customer_booking_grp->promo_id = $this->params->promo_id;
                    $customer_booking_grp->promo_discount = $promo_discount;
                    $customer_booking_grp->additional_discount = $additional_discount;
                    $customer_booking_grp->total_paid = $total_paid;
                }

                if ($corporate_pay === true) {
                    $customer_booking_grp->total_paid = 0;
                    $net_amount = $total_amount + $total_tax;
                    $total_due = $net_amount;
                }

                $customer_booking_grp->total_amount = $total_amount;

                $customer_booking_grp->net_amount = $net_amount;
                $customer_booking_grp->total_tax = $total_tax;
                // $customer_booking_grp->total_due = $total_due; //booking invoice due only

                if ($payment_status === 2) {
                    $customer_booking_grp->total_paid = 0;
                    // $customer_booking_grp->total_due = $this->params->total_amount;
                    $customer_booking_grp->total_due = $customer_booking_grp->net_amount;
                }

                $customer_booking_grp->status = $room_booking_status; ///$this->params->booking_grp_status;
                if ($room_booking_status === 1) {  // 2024-08-28 04:16:50
                    $customer_booking_grp->checkin_at = date("Y-m-d h:m:s");
                }
                $customer_booking_grp->created_by = $this->user->id;

                // if (count($arr) > 0) {
                //     $customer_booking_grp->save();
                // }

                $customer_booking_grp->save();
            }

            if ($customer_booking_grp) {


                $customerGuestInfo = $this->params->customerGuestInfo;
                // foreach ($customerGuestInfo as $guest) {
                //     $dob = isset($guest['birth_date']) ? date('Y-m-d H:i:s', strtotime($guest['birth_date'])) : null;

                //     DB::table('additional_guest')->insert([
                //         'booking_id' => $customer_booking_grp->id,
                //         'title' => empty($guest['title']) ? 'Mr.' : $guest['title'],
                //         'first_name' => $guest['fName'],
                //         'last_name' => $guest['lName'],
                //         'gender' => $guest['gender'],
                //         'id_type' => isset($guest['id_type']),
                //         'dob' => $dob,
                //         "created_by" => $this->user->id,
                //         'status' => 1,
                //     ]);
                // }


                if (empty($customerGuestInfo)) {
                    DB::table('additional_guest')->insert([
                        'booking_id' => $customer_booking_grp->id,
                        'created_by' => $this->user->id,
                        'status' => 1,
                    ]);
                } else {
                    foreach ($customerGuestInfo as $guest) {
                        $dob = isset($guest['birth_date']) ? date('Y-m-d H:i:s', strtotime($guest['birth_date'])) : null;

                        DB::table('additional_guest')->insert([
                            'booking_id' => $customer_booking_grp->id,
                            'title' => empty($guest['title']) ? 'Mr.' : $guest['title'],
                            'first_name' => $guest['fName'],
                            'last_name' => $guest['lName'],
                            'gender' => $guest['gender'],
                            'id_type' => isset($guest['id_type']),
                            'personal_id' => $guest['personal_id'],
                            'dob' => $dob,
                            "created_by" => $this->user->id,
                            'status' => 1,
                        ]);
                    }
                }
                $from_date_ = date('Y-m-d', strtotime($this->params->date_from)); // 01/09/2024
                $to_date_ = date('Y-m-d', strtotime($this->params->date_to)); // 01/09/2024

                if ($this->params->checkout_type === 'hourly') {  // true
                    $to_date_ = date('Y-m-d', strtotime("+1 day", strtotime($this->params->date_from))); // 02/09/2024
                }


                $booking_days_id = null;
                // 01/09/2024       01/09/2024 < 02/09/2024           
                for ($x = 0, $i =  $from_date_; $i < $to_date_; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {



                    if ($this->params->checkout_type === 'hourly') {
                        $tarrif_id = $tarrif->id;
                        $tarrif_amount = $tarrif->price;
                    } else {
                        $tarrif_id = $tarrif[$x]->id;
                        $tarrif_amount = $tarrif[$x]->price;
                    }
                    // dd($customer_booking_grp);
                    $booking_days_id =  DB::table('customer_booking_days')->insertGetId(
                        array(
                            'booking_master_id' => $customer_booking_grp->id,
                            'customer_id' => $customer->id,
                            'room_id' => $this->params->room_id,
                            'tower_id' => $room->tower_id,
                            'room_type_id' => $this->params->room_type_id,
                            'room_category_id' => $this->params->room_category_id,
                            'date' => date('Y-m-d', strtotime($i)),
                            'adults' => $this->params->adults,
                            'childs' => $this->params->childs,
                            'tarrif_id' => $tarrif_id,
                            'tarrif_amount' => $tarrif_amount,
                            'additional_total_adult_amount' => $additional_adult_tarrif[$i] ?? 0,
                            'additional_total_child_amount' => $additional_child_tarrif[$i] ?? 0,
                            'total_amount' => $tarrif_amount + ($additional_adult_tarrif[$i] ?? 0) + ($additional_child_tarrif[$i] ?? 0)
                        )
                    );

                    //customer_booking_adln_adults table insert againts this booking_days_id
                    if ($this->params->checkout_type !== 'hourly') {

                        $additional_adult_price = [];
                        for ($j = ($room_type_info->adults) + 1; $j <= $this->params->adults; $j++) {

                            $additional_adult_price[] = DB::table('room_price_additional')
                                ->where('room_price_additional.room_type_id', '=', $this->params->room_type_id)
                                ->where('date', $i)
                                ->join('room_occupancies', 'room_price_additional.room_occupancy_id', '=', 'room_occupancies.id')
                                ->where('room_occupancies.guest_num', '=', $j)
                                ->where('room_occupancies.guest_type', '=', 1)
                                ->select('room_price_additional.id', 'room_occupancies.guest_num', 'price')
                                ->first();
                        }

                        if (count($additional_adult_price) > 0) {
                            foreach ($additional_adult_price as $adult_price) {
                                DB::table('customer_booking_adnl_adults')->insert(array(
                                    'customer_booking_days_id' => $booking_days_id,
                                    'additional_tarrif_id' => $adult_price->id,
                                    'guest_num' => $adult_price->guest_num,
                                    'additional_price' => $adult_price->price
                                ));
                            }
                        }

                        //customer_booking_adln_childs table insert againts this booking_days_id
                        $additional_child_price = [];
                        for ($j = ($room_type_info->childrens) + 1; $j <= $this->params->childs; $j++) {

                            $additional_child_price[] = DB::table('room_price_additional')
                                ->where('room_price_additional.room_type_id', '=', $this->params->room_type_id)
                                ->where('date', $i)
                                ->join('room_occupancies', 'room_price_additional.room_occupancy_id', '=', 'room_occupancies.id')
                                ->where('room_occupancies.guest_num', '=', $j)
                                ->where('room_occupancies.guest_type', '=', 0)
                                ->select('room_price_additional.id', 'room_occupancies.guest_num', 'price')
                                ->first();
                        }

                        if (count($additional_child_price) > 0) {
                            foreach ($additional_child_price as $child_price) {
                                DB::table('customer_booking_adnl_childs')->insert(array(
                                    'customer_booking_days_id' => $booking_days_id,
                                    'additional_tarrif_id' => $child_price->id,
                                    'guest_num' => $child_price->guest_num,
                                    'additional_price' => $child_price->price
                                ));
                            }
                        }
                    }
                }





                if ($customer->customer_type === 0 or ($customer->customer_type === 1 and $corporate_pay === false)) {

                    //Insert balance into account_customer table through accountCustomer function
                    $credited_note = "Payment taken from customer";
                    $debited_note = "Bill generated for booking";
                    $credit = false;
                    if ($payment_status === 1) {

                        Accounting::accountCustomer($credit, $customer->id, $customer_booking_grp->id, $customer_booking_grp->invoice_id, $customer_booking_grp->invoice_type, $net_amount, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, false);
                    }


                    if (!empty($this->params->account_id) && ($customer_booking_grp->total_paid > 0) && ($payment_status === 1)) {
                        $credited_note = "payment taken from customer";
                        $debited_note = "";
                        Accounting::Accounts($this->params->account_id, $customer_booking_grp->id, $customer_booking_grp->invoice_type, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, true);
                    } else {
                        if ($payment_status === 1) {
                            # code...
                            Accounting::Accounts($this->params->account_id, $customer_booking_grp->id, $customer_booking_grp->invoice_type, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, false);
                        }
                    }

                    //generate payment slip
                    if ($customer_booking_grp->total_paid > 0 && $payment_status === 1) {
                        //customer pay within creating invoice
                        //Account balance
                        DB::table('payment_collection_slip')->insert([
                            'invoice_id' => $customer_booking_grp->id,
                            'invoice_type' => $customer_booking_grp->invoice_type,
                            'record_id' => $this->params->record_id ?? 0,
                            'slip_number' => 'PAYSLIP-' . $customer_booking_grp->id . '-' . strtotime('now'),
                            'account_id' => $this->params->account_id,
                            'payee' => $customer->id,
                            'amount' => $customer_booking_grp->total_paid,
                            'reference' => $this->params->reference,
                            'remark' => $this->params->remark,
                            'payment_date' => date('Y-m-d', strtotime($customer_booking_grp->created_at)),
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    }

                    if ($payment_status === 1) {

                        DB::table('account_revenue')->insert([
                            'invoice' => $customer_booking_grp->id,
                            'inv_type' => $customer_booking_grp->invoice_type,
                            'sector' => 12,
                            'debit' => $revenue, //Total_amount - tax
                            'credit' => 0,
                            'note' => 'payment taken from customer',
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    }
                }

                //Account corporate client
                if ($customer->customer_type === 1 and $corporate_pay === true) {
                    //corporate client balance
                    $corp_client = Client::where('status', 1)->find($customer->corporate_client_id);

                    if ($corp_client) {

                        $balance = $corp_client->balance;
                        $balance -= $customer_booking_grp->total_due;
                        $corp_client->balance = $balance;
                        $corp_client->save();

                        DB::table('account_corp_customer')->insert([
                            'client_id' => $customer->corporate_client_id,
                            'invoice_id' => $customer_booking_grp->id,
                            'inv_type' => $customer_booking_grp->invoice_type,
                            'debit' => - ($customer_booking_grp->total_due),
                            'credit' => 0,
                            'balance' => $corp_client->balance,
                            'note' => 'Bill generated for booking against corporate client',
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    }
                }

                //Account Tax
                if ($this->params->tax_id && $payment_status === 1) {

                    DB::table('account_tax')->insert([
                        'invoice_id' => $customer_booking_grp->id,
                        'invoice_type' => $customer_booking_grp->invoice_type,
                        'tax_id' => $this->params->tax_id,
                        'debit' => $customer_booking_grp->total_tax,
                        'credit' => 0,
                        'note' => 'tax has been debited against room booking',
                        'created_by' => $this->user->id,
                        'status' => 1
                    ]);
                }
            }
            DB::commit();
            $this->responseMessage = "Booking created successfully";
            $this->outputData = $customer;
            $this->success = true;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->responseMessage = "Booking creation failed";
            $this->outputData = $e;
            $this->success = true;
        }
    }

    public function updateBooking(Request $request)
    {

        $room_booking_status = (int)$this->params->room_booking_status;
        $payment_status = (int)$this->params->payment_status;

        if ($this->params->checkout_type === 'hourly') {
            $this->validator->validate($request, [
                "hourly_slot_id" => v::notEmpty()
            ]);
        }

        if ($this->params->adults < 0) {
            $this->success = false;
            $this->responseMessage = 'Enter no.of adults !';
            return;
        }
        if ($this->params->childs < 0) {
            $this->success = false;
            $this->responseMessage = 'Enter no.of childs !';
            return;
        }


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        DB::beginTransaction();

        try {



            //update customer booking master/group information
            $customer_booking_grp = $this->customerBookingGrp->find($this->params->booking_master_id);
            //old booking payable amount
            $old_net_amount = $customer_booking_grp->net_amount;
            //customer
            $customer = $this->customer->where('id', $customer_booking_grp->customer_id)->where('status', 1)->first();

            // dd($customer);

            $customer_booking_grp->checkout_type = $this->params->checkout_type;
            // $customer_booking_grp->status = 2; 
            $customer_booking_grp->status = $room_booking_status;
            if ($room_booking_status === 1) {  // 2024-08-28 04:16:50
                $customer_booking_grp->checkin_at = date("Y-m-d h:m:s");
            }

            if ($this->params->checkout_type === 'hourly') {

                $customer_booking_grp->hourly_slot_id = $this->params->hourly_slot_id;
            }

            $customer_booking_grp->date_from = date('Y-m-d', strtotime($this->params->date_from));
            // $customer_booking_grp->date_from = date('Y-m-d', strtotime("+1 day", strtotime($this->params->date_from)));

            // Loop between timestamps, 24 hours at a time. Inserting data into customer_bookings
            $begin = $customer_booking_grp->date_from;

            if ($this->params->date_to !== null) {
                // $customer_booking_grp->date_to = date('Y-m-d',strtotime("+1 day", strtotime($this->params->date_to)));
                $customer_booking_grp->date_to = date('Y-m-d', strtotime($this->params->date_to));
                $end = $customer_booking_grp->date_to;
            } else {
                $end = $begin;
            }

            //Tarrif information for date range price info, tarrif id, tarrif amount

            $room_type_info = DB::table('room_types')->where('id', $this->params->room_type_id)->first();

            if ($this->params->checkout_type === 'hourly') {
                $tarrif = DB::table('room_prices_hourly')
                    ->where(['room_type_id' => $this->params->room_type_id, 'hour_slot_id' => $this->params->hourly_slot_id, 'date' => $begin])
                    ->first();
            } else {

                $tarrif = DB::table('room_price')
                    ->where('room_type_id', $this->params->room_type_id)
                    ->whereBetween('date', [$begin, $end])
                    ->get();
            }

            // $additional_adult_tarrif = $this->params->totalAdultAmount;
            // $additional_child_tarrif = $this->params->totalChildAmount;

            $additional_adult_tarrif = !empty($this->params->totalAdultAmount) && is_array($this->params->totalAdultAmount) ? $this->params->totalAdultAmount[0] : 0;
            $additional_child_tarrif = !empty($this->params->totalChildAmount) && is_array($this->params->totalChildAmount) ? $this->params->totalChildAmount[0] : 0;

            $arr = array();
            //Tower Id
            $room = DB::table('tower_floor_rooms')->find($this->params->room_id);

            for ($x = 0, $i =  date('Y-m-d', strtotime($begin)); $i < $end; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {
                $arr[] = array(
                    'customer_id' => $customer_booking_grp->customer_id,
                    'room_id' => $this->params->room_id,
                    'tower_id' => $room->tower_id,
                    'room_type_id' => $this->params->room_type_id,
                    'room_category_id' => $this->params->room_category_id,
                    'date' => date('Y-m-d', strtotime($i)),
                    'adults' => $this->params->adults,
                    'childs' => $this->params->childs,

                );
            }
            //end customer booking
            // $customer_booking_grp->checkin_at = date("Y-m-d h:i:s",time() + 6 * 60 * 60);
            $corporate_pay = $this->params->corporatePay;
            $total_amount = $this->params->total_amount;
            $promo_discount = $this->params->promo_discount;
            $additional_discount = $this->params->additional_discount;
            $total_tax = $this->params->total_tax;

            // $total_paid = $customer_booking_grp->total_paid;
            if ($payment_status === 2) {

                $total_paid = 0;
            } else {
                $total_paid = (float)$this->params->total_paid;
            }

            if ($corporate_pay === false) {
                $net_amount = ($total_amount + $total_tax) - ($promo_discount + $additional_discount);
                $total_due = $net_amount - $total_paid;
                $revenue = $net_amount - $total_tax;

                $customer_booking_grp->promo_id = $this->params->promo_id;
                $customer_booking_grp->promo_discount = $promo_discount;
                $customer_booking_grp->additional_discount = $additional_discount;
                $customer_booking_grp->total_paid = $total_paid; // @@ paid amount is later update
            }

            //@TODO: check if this is needed corporate pay
            if ($corporate_pay === true) {
                $customer_booking_grp->total_paid = 0;
                $net_amount = $total_amount + $total_tax;
                $total_due = $net_amount;
            }


            if ($payment_status === 1) {
                $customer_booking_grp->payment_accounts_id = $this->params->account_id;
            }


            $customer_booking_grp->total_amount = $total_amount;

            $customer_booking_grp->net_amount = $net_amount;
            $customer_booking_grp->total_tax = $total_tax;
            $customer_booking_grp->total_due = $total_due;

            // if($total_paid > $net_amount){
            //     $customer_booking_grp->refund_amount = $total_paid - $net_amount;
            // }else{
            //     $customer_booking_grp->refund_amount = null;
            // }


            $customer->mobile = $this->params->mobile;
            $customer->contact_type = $this->params->contact_type;
            $customer->title = $this->params->title ?? "";
            $customer->first_name = $this->params->fName;
            $customer->last_name = $this->params->lName ?? "";
            $customer->gender = $this->params->gender;
            $customer->id_type = $this->params->id_type;
            $customer->personal_id = $this->params->personal_id;
            $customer->nationality = $this->params->nationality;
            $customer->country_id = $this->params->country_id;
            $customer->state_id = $this->params->state_id;
            $customer->city_id = $this->params->city_id;
            $customer->pin_code = $this->params->pin_code;
            $customer->arrival_from = $this->params->arrival_from;
            $customer->address = $this->params->address;
            $customer->save();

            $customer_booking_grp->updated_by = $this->user->id;

            if (count($arr) > 0) {

                $customer_booking_grp->save();
            }

            //End customer bookings group

            if ($customer_booking_grp) {


                // Check if $customerGuestInfo is set and is an array


                /**additional guest start */
                if (isset($this->params->customerGuestInfo) && is_array($this->params->customerGuestInfo)) {
                    $customerGuestInfo = $this->params->customerGuestInfo;

                    foreach ($customerGuestInfo as $guest) {
                        $dob = isset($guest['birth_date']) ? date('Y-m-d H:i:s', strtotime($guest['birth_date'])) : null;

                        // Check if the guest already exists by some identifier, like an ID
                        $existingGuest = DB::table('additional_guest')
                            ->where('booking_id', $this->params->booking_master_id)
                            ->first();

                        if ($existingGuest) {
                            // Update the existing guest
                            DB::table('additional_guest')
                                ->where('id', $existingGuest->id) // Use the primary key to identify the guest to update
                                ->update([
                                    'title' => empty($guest['title']) ? 'Mr.' : $guest['title'],
                                    'first_name' => $guest['fName'],
                                    'last_name' => $guest['lName'],
                                    'gender' => $guest['gender'],
                                    'id_type' => isset($guest['id_type']),
                                    'dob' => $dob,
                                    'updated_by' => $this->user->id, // Assuming you want to track who last updated the record
                                    'status' => 1,
                                ]);
                        } else {
                            // Insert a new guest if it doesn't exist
                            DB::table('additional_guest')->insert([
                                'booking_id' => $this->params->booking_master_id,
                                'title' => empty($guest['title']) ? 'Mr.' : $guest['title'],
                                'first_name' => $guest['fName'],
                                'last_name' => $guest['lName'],
                                'gender' => $guest['gender'],
                                'id_type' => isset($guest['id_type']),
                                'dob' => $dob,
                                'created_by' => $this->user->id,
                                'status' => 1,
                            ]);
                        }
                    }
                } else {
                    $this->responseMessage = "No addtional guest in here";
                    $this->outputData = [];
                    $this->success = true;
                }

                /**additional guest start */


                //First delete old booking days from customer booking days table and adnl adult + adnl child removed
                $bookingDays = DB::table('customer_booking_days')->where(['booking_master_id' => $customer_booking_grp->id, 'status' => 1])->get();
                foreach ($bookingDays as $day) {
                    DB::table('customer_booking_adnl_adults')->where('customer_booking_days_id', $day->id)->delete();
                    DB::table('customer_booking_adnl_childs')->where('customer_booking_days_id', $day->id)->delete();
                }

                DB::table('customer_booking_days')->where(['booking_master_id' => $customer_booking_grp->id, 'status' => 1])->delete();

                for ($x = 0, $i = date('Y-m-d', strtotime("+1 day", strtotime($begin))); $i <= $end; $i = date('Y-m-d', strtotime("+1 day", strtotime($i))), $x++) {

                    if ($this->params->checkout_type === 'hourly') {
                        $tarrif_id = $tarrif->id;
                        $tarrif_amount = $tarrif->price;
                    } else {
                        $tarrif_id = $tarrif[$x]->id;
                        $tarrif_amount = $tarrif[$x]->price;
                    }

                    $booking_days_id =  DB::table('customer_booking_days')->insertGetId(
                        array(
                            'booking_master_id' => $customer_booking_grp->id,
                            'customer_id' => $customer_booking_grp->customer_id,
                            'room_id' => $this->params->room_id,
                            'tower_id' => $room->tower_id,
                            'room_type_id' => $this->params->room_type_id,
                            'room_category_id' => $this->params->room_category_id,
                            'date' => date('Y-m-d', strtotime($i)),
                            'adults' => $this->params->adults,
                            'childs' => $this->params->childs,
                            'tarrif_id' => $tarrif_id,
                            'tarrif_amount' => $tarrif_amount,
                            // 'additional_total_adult_amount' => $additional_adult_tarrif[$x],
                            // 'additional_total_child_amount' => $additional_child_tarrif[$x],
                            // 'total_amount' => $tarrif_amount + $additional_adult_tarrif[$x] + $additional_child_tarrif[$x]
                            'additional_total_adult_amount' => $additional_adult_tarrif,
                            'additional_total_child_amount' => $additional_child_tarrif,
                            'total_amount' => $tarrif_amount + $additional_adult_tarrif + $additional_child_tarrif
                        )
                    );

                    //customer_booking_adln_adults table insert againts this booking_days_id
                    if ($this->params->checkout_type !== 'hourly') {

                        $additional_adult_price = [];
                        for ($j = ($room_type_info->adults) + 1; $j <= $this->params->adults; $j++) {

                            $additional_adult_price[] = DB::table('room_price_additional')
                                ->where('room_price_additional.room_type_id', '=', $this->params->room_type_id)
                                ->where('date', $i)
                                ->join('room_occupancies', 'room_price_additional.room_occupancy_id', '=', 'room_occupancies.id')
                                ->where('room_occupancies.guest_num', '=', $j)
                                ->where('room_occupancies.guest_type', '=', 1)
                                ->select('room_price_additional.id', 'room_occupancies.guest_num', 'price')
                                ->first();
                        }

                        if (count($additional_adult_price) > 0) {
                            foreach ($additional_adult_price as $adult_price) {
                                DB::table('customer_booking_adnl_adults')->insert(array(
                                    'customer_booking_days_id' => $booking_days_id,
                                    'additional_tarrif_id' => $adult_price->id,
                                    'guest_num' => $adult_price->guest_num,
                                    'additional_price' => $adult_price->price
                                ));
                            }
                        }


                        //customer_booking_adln_childs table insert againts this booking_days_id
                        $additional_child_price = [];
                        for ($j = ($room_type_info->childrens) + 1; $j <= $this->params->childs; $j++) {

                            $additional_child_price[] = DB::table('room_price_additional')
                                ->where('room_price_additional.room_type_id', '=', $this->params->room_type_id)
                                ->where('date', $i)
                                ->join('room_occupancies', 'room_price_additional.room_occupancy_id', '=', 'room_occupancies.id')
                                ->where('room_occupancies.guest_num', '=', $j)
                                ->where('room_occupancies.guest_type', '=', 0)
                                ->select('room_price_additional.id', 'room_occupancies.guest_num', 'price')
                                ->first();
                        }

                        if (count($additional_child_price) > 0) {
                            foreach ($additional_child_price as $child_price) {
                                DB::table('customer_booking_adnl_childs')->insert(array(
                                    'customer_booking_days_id' => $booking_days_id,
                                    'additional_tarrif_id' => $child_price->id,
                                    'guest_num' => $child_price->guest_num,
                                    'additional_price' => $child_price->price
                                ));
                            }
                        }
                    }
                }






                if ($customer->customer_type === 0 or ($customer->customer_type === 1 and $corporate_pay === false)) {

                    //Insert balance into account_customer table through accountCustomer function
                    $credited_note = "Payment taken from customer";
                    $debited_note = "Bill generated for booking";
                    $credit = false;


                    if (!empty($this->params->account_id)  && ($payment_status === 1)) {
                        $credited_note = "payment taken from customer";
                        $debited_note = "Bill generated for booking";
                        Accounting::Accounts($this->params->account_id, $customer_booking_grp->id, $customer_booking_grp->invoice_type, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, true);
                        Accounting::accountCustomer($credit, $customer->id, $customer_booking_grp->id, $customer_booking_grp->invoice_id, $customer_booking_grp->invoice_type, $net_amount, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, false);
                    } else {
                        if ($payment_status === 1) {
                            # code...
                            Accounting::Accounts($this->params->account_id, $customer_booking_grp->id, $customer_booking_grp->invoice_type, $customer_booking_grp->total_paid, $credited_note, $debited_note, $this->user->id, false);
                        }
                    }

                    //generate payment slip
                    $online_payment_info = DB::table("online_payments")->where("room_booking_invoice", $customer_booking_grp->invoice_id)->first();
                    if ($customer_booking_grp->total_paid > 0 && $payment_status === 1 && (empty($online_payment_info))) {

                        //customer pay within creating invoice
                        //Account balance
                        DB::table('payment_collection_slip')->insert([
                            'invoice_id' => $customer_booking_grp->id,
                            'invoice_type' => $customer_booking_grp->invoice_type,
                            'record_id' => $this->params->record_id ?? 0,
                            'slip_number' => 'PAYSLIP-' . $customer_booking_grp->id . '-' . strtotime('now'),
                            'account_id' => $this->params->account_id,
                            'payee' => $customer->id,
                            'amount' => $customer_booking_grp->total_paid,
                            'reference' => $this->params->reference ?? "Online Booking",
                            'remark' => $this->params->remark,
                            'payment_date' => date('Y-m-d', strtotime($customer_booking_grp->created_at)),
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    } else if ($customer_booking_grp->total_paid > 0 && $payment_status === 1 && (!empty($online_payment_info) && $online_payment_info->payment_status == "unpaid")) {
                        DB::table('payment_collection_slip')->insert([
                            'invoice_id' => $customer_booking_grp->id,
                            'invoice_type' => $customer_booking_grp->invoice_type,
                            'record_id' => $this->params->record_id ?? 0,
                            'slip_number' => 'PAYSLIP-' . $customer_booking_grp->id . '-' . strtotime('now'),
                            'account_id' => $this->params->account_id,
                            'payee' => $customer->id,
                            'amount' => $customer_booking_grp->total_paid,
                            'reference' => $this->params->reference ?? "Online Booking",
                            'remark' => $this->params->remark,
                            'payment_date' => date('Y-m-d', strtotime($customer_booking_grp->created_at)),
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    }


                    if ($payment_status === 1) {

                        DB::table('account_revenue')->insert([
                            'invoice' => $customer_booking_grp->id,
                            'inv_type' => $customer_booking_grp->invoice_type,
                            'sector' => 12,
                            'debit' => $revenue, //Total_amount - tax
                            'credit' => 0,
                            'note' => 'payment taken from customer',
                            'created_by' => $this->user->id,
                            'status' => 1
                        ]);
                    }
                }

                if ($this->params->tax_id) {

                    DB::table('account_tax')->where(['invoice_id' => $customer_booking_grp->id, 'invoice_type' => $customer_booking_grp->invoice_type])
                        ->update([
                            'invoice_id' => $customer_booking_grp->id,
                            'invoice_type' => $customer_booking_grp->invoice_type,
                            'tax_id' => $this->params->tax_id,
                            'debit' => $customer_booking_grp->total_tax,
                            'credit' => 0,
                            'note' => 'tax has been updated and debited against room booking',
                            'updated_by' => $this->user->id,
                            'status' => 1
                        ]);
                }
            }

            DB::commit();
            $this->responseMessage = "New Customer has been created successfully";
            $this->outputData = $customer_booking_grp;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Booking updating failed";
            $this->success = false;
        }
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

        // $bookings = DB::table('customer_booking_master')
        //     // ->select('customer_booking_days.booking_master_id')
        //     ->join('customer_booking_days','customer_booking_master.id','=','customer_booking_days.booking_master_id')
        //     ->join('tower_floor_rooms','tower_floor_rooms.id','=','customer_booking_days.room_id')
        //     ->where('customer_booking_master.customer_id','=',$customer->id)
        //     ->get()
        //     ->groupBy('customer_booking_days.booking_master_id');


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
            ->leftJoin('customer_booking_master', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->where('customers.status', 1)
            ->whereNull('customer_booking_master.checkout_at')
            ->whereNotNull('customer_booking_master.checkin_at') // Add this condition
            ->select('customers.*', 'customer_booking_master.checkout_at as checkout_at', 'customer_booking_master.checkin_at as checkin_at')
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



    // public function getAllCustomerNew()
    // {

    //     $customer = DB::table('customer_booking_days')
    //         ->select(
    //             'customer_booking_days.*',
    //             'tower_floor_rooms.room_no',
    //             'customers.id as cust_id',
    //             'customers.first_name as first_name',
    //             'customers.last_name as last_name',
    //             'customers.mobile as mobile',
    //         )
    //         ->join('customers', 'customer_booking_days.customer_id', 'customers.id')
    //         ->join('customer_booking_master', 'customer_booking_days.customer_id', 'customer_booking_master.customer_id')
    //         ->join('tower_floor_rooms', 'customer_booking_days.room_id', 'tower_floor_rooms.id')
    //         // ->whereNotNull('customer_booking_master.checkout_at')
    //         ->orderBy('customer_booking_days.id', 'desc')
    //         ->get();


    //     $this->responseMessage = "All customer fetched Successfully!";
    //     $this->outputData = $customer;
    //     $this->success = true;
    // }

    public function getAllCustomerNew()
    {

        // $customer = DB::table('customer_booking_master')
        //     ->select(
        //         'customer_booking_master.*',
        //         // 'tower_floor_rooms.room_no',
        //         'customers.id as cust_id',
        //         'customers.first_name as first_name',
        //         'customers.last_name as last_name',
        //         'customers.mobile as mobile',
        //     )
        //     // ->join('customers', 'customer_booking_days.customer_id', 'customers.id')
        //     ->join('customers', 'customer_booking_master.customer_id', 'customers.id')
        //     // ->join('customer_booking_master', 'customer_booking_days.customer_id', 'customer_booking_master.customer_id')
        //     // ->join('tower_floor_rooms', 'customer_booking_master.room_id', 'tower_floor_rooms.id')
        //     ->whereNull('customer_booking_master.checkout_at')
        //     ->orderBy('customer_booking_master.id', 'desc')
        //     ->get();

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
}
