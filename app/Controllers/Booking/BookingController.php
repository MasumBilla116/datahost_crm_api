<?php

namespace  App\Controllers\Booking;

use DateTime;
use DateTimeZone;
use App\Auth\Auth;
use Carbon\Carbon;
use App\Helpers\Accounting;
use App\Helpers\Helper;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Customers\CustomerBooking;
use App\Models\Customers\CustomerBookingGrp;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class BookingController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $customerBookingGrp;
    protected $customerBooking;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->customerBookingGrp = new CustomerBookingGrp();
        $this->customerBooking = new CustomerBooking();

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

            case 'getAllBooking':
                $this->getAllBooking();
                break;
            case 'bookingInfo':
                $this->bookingInfo();
                break;

            case 'bookingGuestInfo':
                $this->bookingGuestInfo();
                break;
            case 'checkInDateTime':
                $this->checkInDateTime();
                break;
            case 'extraTimePayemnt':
                $this->extraTimePayemnt();
                break;

            case 'extraTimePayemntInfo':
                $this->extraTimePayemntInfo();
                break;

                // extraTimePayemntInfo
            case 'checkOutDateTime':
                $this->checkOutDateTime();
                break;
            case 'refund':
                $this->refund($request);
                break;
            case 'rejectRefund':
                $this->rejectRefund($request);
                break;
            case 'cancelBooking':
                $this->cancelBooking();
                break;
            case 'approveBooking':
                $this->approveBooking();
                break;
            case 'seeAllDuesCustomer':
                $this->seeAllDuesCustomer();
                break;
            case 'customerLedger':
                $this->customerLedger();
                break;
            case 'customerLedgerHistory':
                $this->customerLedgerHistory();
                break;
            case 'customerLedgerHistoryNew':
                $this->customerLedgerHistoryNew();
                break;

            case 'getAllActiveBooking':
                $this->getAllActiveBooking();
                break;
            case 'getAllRoomByCustomer':
                $this->getAllRoomByCustomer();
                break;

            case 'getAllActiveRoom':
                $this->getAllActiveRoom();
                break;
                // getAllActiveRoom
            case 'bookingInfoWithoutFrontDesk':
                $this->bookingInfoWithoutFrontDesk();
                break;

            case 'getAllRefund':
                $this->getAllRefund();
                break;

            case 'getAllRefundFrDashbord':
                $this->getAllRefundFrDashbord();
                break;

                // getAllRefundFrDashbord

            case 'withdrawRefund':
                $this->withdrawRefund($request);
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
    // All Booking
    public function getAllBooking_old()
    {
        // get all booking data by daily,weekly,monthly,yearly etc.
        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;

        if ($filter == 'all') {
            $bookings = DB::table('customer_booking_master')
                ->where('customer_booking_master.status', 1)
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'deleted') {
            $bookings = DB::table('customer_booking_master')
                ->where('customer_booking_master.status', 0)
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name', 'customers.mobile')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'daily') {
            $bookings = DB::table('customer_booking_master')
                ->whereDate('customer_booking_master.created_at', date('Y-m-d'))
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'weekly') {
            $bookings = DB::table('customer_booking_master')
                ->whereBetween('customer_booking_master.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'monthly') {
            $bookings = DB::table('customer_booking_master')
                ->whereYear('customer_booking_master.created_at', date('Y'))
                ->whereMonth('customer_booking_master.created_at', date('m'))
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'yearly') {
            $bookings = DB::table('customer_booking_master')
                ->whereYear('customer_booking_master.created_at', date('Y'))
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
                ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
                ->get();
        } else if ($filter == 'custom' && $start_date && $end_date) {
            $bookings = DB::table('customer_booking_master')
                ->whereBetween('customer_booking_master.created_at', [$start_date, $end_date])
                ->select('customer_booking_master.*', 'customers.first_name', 'customers.last_name')
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
    //get booking info
    public function bookingInfo()
    {
        //booking master max info
        $bookingInfo = DB::table('customer_booking_master')
            ->select(
                'customer_booking_master.*',
                'customer_booking_master.status as booking_status',
                'customer_booking_master.total_amount as sub_total',
                'customer_booking_master.created_at as createInvoiceTime',
                'customers.first_name',
                'customers.last_name',
                'customers.mobile',
                'customers.address',
                'customer_booking_days.adults',
                'customer_booking_days.childs',
                'customer_booking_days.room_type_id',
                'customer_booking_days.room_category_id',
                'customer_booking_days.room_id',
                'customer_booking_days.additional_total_adult_amount',
                'customer_booking_days.additional_total_child_amount',
                'customer_booking_days.booking_master_id',
                // 'customer_booking_days.*',
                'room_types.name as room_type_name',
                'room_categories.name as room_category_name',
                'room_types.adults as room_type_adults',
                'room_types.childrens as room_type_childs',
                'restaurant_promo_offers.name as promo_offer_name',
                'accounts.account_name as accounts_name',
                'rooms.room_no'
            )
            ->where(['customer_booking_master.id' => $this->params->booking_id])
            ->leftJoin('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->leftJoin('restaurant_promo_offers', 'customer_booking_master.promo_id', '=', 'restaurant_promo_offers.id')
            ->leftJoin('customer_booking_days', 'customer_booking_master.id', '=', 'customer_booking_days.booking_master_id')
            ->leftJoin('accounts', 'customer_booking_master.payment_accounts_id', '=', 'accounts.id')
            ->leftJoin('room_types', 'customer_booking_days.room_type_id', '=', 'room_types.id')
            ->leftJoin('room_categories', 'customer_booking_days.room_category_id', '=', 'room_categories.id')
            ->leftJoin('tower_floor_rooms as rooms', 'customer_booking_days.room_id', '=', 'rooms.id')
            ->first();

        // dd($bookingInfo);

        //If Hourly booking, How many hours through hourly_slot_id
        $slotInfo = DB::table('customer_booking_master')
            ->select('room_hourly_slots.hour')
            ->where(['customer_booking_master.status' => 1, 'customer_booking_master.id' => $this->params->booking_id])
            ->join('room_hourly_slots', 'room_hourly_slots.id', '=', 'customer_booking_master.hourly_slot_id')
            ->first();


        //Booking days table
        $bookingDays = DB::table('customer_booking_days')
            ->select('customer_booking_days.*', 'customer_booking_days.additional_total_adult_amount as adult_amount', 'customer_booking_days.additional_total_child_amount as child_amount')
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->get();

        //Base Total Tarrif 
        $baseTotalTarrif = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(tarrif_amount) as total_tarrif'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();

        //additional Total Adult Amount
        $daysTotalAdultPrice = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(additional_total_adult_amount) as total_adnl_adult_price'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();
        //additional Total Childs Amount
        $daysTotalChildPrice = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(additional_total_child_amount) as total_adnl_child_price'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();


        $total_additional_price = 0;
        if (!empty($daysTotalAdultPrice)) {
            $total_additional_price = $daysTotalAdultPrice->total_adnl_adult_price + $daysTotalChildPrice->total_adnl_child_price;
        }

        //booking days count
        $bookingDaysCount = DB::table('customer_booking_days')
            ->select('booking_master_id', DB::raw('COUNT(*) as count_days'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy(['booking_master_id'])
            ->first();

        //Additional adult count
        if ($bookingInfo->adults > $bookingInfo->room_type_adults) {
            $adnl_adults = ($bookingInfo->adults) - ($bookingInfo->room_type_adults);
        } else {
            $adnl_adults = 0;
        }
        //Additional Child count
        if ($bookingInfo->childs > $bookingInfo->room_type_childs) {
            $adnl_childs = ($bookingInfo->childs) - ($bookingInfo->room_type_childs);
        } else {
            $adnl_childs = 0;
        }

        if (!$bookingInfo) {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }

        //booking notes under this booking
        $booking_notes = DB::table('customer_booking_notes')
            ->where(['booking_master_id' => $this->params->booking_id, 'customer_booking_notes.status' => 1])
            ->select('customer_booking_notes.*', 'org_users.name as action_created')
            ->join('org_users', 'customer_booking_notes.created_by', '=', 'org_users.id')
            ->get();

        //Payment history under this booking
        $payment_slips = DB::table('payment_collection_slip')
            ->where(['invoice_id' => $this->params->booking_id, 'payment_collection_slip.status' => 1])
            ->select('payment_collection_slip.*', 'accounts.account_name as ac_name', 'accounts.type as ac_type')
            ->join('accounts', 'payment_collection_slip.account_id', '=', 'accounts.id')
            ->get();

        $customer_balance = Customer::where('id', $bookingInfo->customer_id)->value('balance');
        // dd($customer_balance);
        $other_dues = $customer_balance + $bookingInfo->total_due;
        // dd($customer_balance);

        $bookingInfo->other_dues = abs($other_dues); //make positive sign for calculations

        $customer_remaining_due = ($bookingInfo->net_amount + $bookingInfo->other_dues) - ($bookingInfo->total_paid + $bookingInfo->refund_amount);

        $bookingInfo->customer_remaining_due = $customer_remaining_due;


        $this->responseMessage = "Booking Info has been fetched successfully !";
        $this->outputData['bookingInfo'] = $bookingInfo;
        $this->outputData['bookingDays'] = $bookingDays;
        $this->outputData['baseTotalTarrif'] = $baseTotalTarrif;
        $this->outputData['total_additional_price'] = $total_additional_price;
        $this->outputData['bookingDaysCount'] = $bookingDaysCount;
        $this->outputData['adnl_adults'] = $adnl_adults;
        $this->outputData['adnl_childs'] = $adnl_childs;
        $this->outputData['slotInfo'] = $slotInfo;
        $this->outputData['booking_notes'] = $booking_notes;
        $this->outputData['payment_slips'] = $payment_slips;


        $this->success = true;
    }

    public function bookingGuestInfo()
    {

        $bookingGuest = DB::table('additional_guest')
            ->where('booking_id', '=', $this->params->booking_id)
            ->get();

        $this->responseMessage = "Booking Guest fetched Successfully!";
        $this->outputData = $bookingGuest;
        $this->success = true;
    }



    public function bookingInfoWithoutFrontDesk()
    {
        //booking master max info
        $bookingInfo = DB::table('customer_booking_master')
            ->select(
                'customer_booking_master.*',
                'customer_booking_master.status as booking_status',
                'customer_booking_master.total_amount as sub_total',
                'customer_booking_master.created_at as createInvoiceTime',
                'customers.first_name',
                'customers.last_name',
                'customers.mobile',
                'customers.address',
                'customer_booking_days.*',
                // 'room_types.name as room_type_name'
            )
            ->where(['customer_booking_master.id' => $this->params->booking_id])
            ->leftJoin('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->leftJoin('customer_booking_days', 'customer_booking_master.id', '=', 'customer_booking_days.booking_master_id')
            // ->join('room_types', 'customer_booking_days.room_type_id', '=', 'room_types.id')
            ->first();

        // dd($bookingInfo);

        //If Hourly booking, How many hours through hourly_slot_id
        $slotInfo = DB::table('customer_booking_master')
            ->select('room_hourly_slots.hour')
            ->where(['customer_booking_master.status' => 1, 'customer_booking_master.id' => $this->params->booking_id])
            ->join('room_hourly_slots', 'room_hourly_slots.id', '=', 'customer_booking_master.hourly_slot_id')
            ->first();


        //Booking days table
        $bookingDays = DB::table('customer_booking_days')
            ->select('customer_booking_days.*', 'customer_booking_days.additional_total_adult_amount as adult_amount', 'customer_booking_days.additional_total_child_amount as child_amount')
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->get();

        //Base Total Tarrif
        $baseTotalTarrif = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(tarrif_amount) as total_tarrif'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();

        //additional Total Adult Amount
        $daysTotalAdultPrice = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(additional_total_adult_amount) as total_adnl_adult_price'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();
        //additional Total Childs Amount
        $daysTotalChildPrice = DB::table('customer_booking_days')
            ->select(DB::raw('SUM(additional_total_child_amount) as total_adnl_child_price'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy('booking_master_id')
            ->first();

        // $total_additional_price = $daysTotalAdultPrice->total_adnl_adult_price + $daysTotalChildPrice->total_adnl_child_price;
        $total_additional_price = 0;
        if ($daysTotalAdultPrice && $daysTotalChildPrice) {
            $total_additional_price = $daysTotalAdultPrice->total_adnl_adult_price + $daysTotalChildPrice->total_adnl_child_price;
        }




        //booking days count
        $bookingDaysCount = DB::table('customer_booking_days')
            ->select('booking_master_id', DB::raw('COUNT(*) as count_days'))
            ->where(['booking_master_id' => $this->params->booking_id, 'status' => 1])
            ->groupBy(['booking_master_id'])
            ->first();

        if (!$bookingInfo) {
            $this->responseMessage = "No data found!";
            $this->outputData = [];
            $this->success = false;
        }

        //booking notes under this booking
        $booking_notes = DB::table('customer_booking_notes')
            ->where(['booking_master_id' => $this->params->booking_id, 'customer_booking_notes.status' => 1])
            ->select('customer_booking_notes.*', 'org_users.name as action_created')
            ->join('org_users', 'customer_booking_notes.created_by', '=', 'org_users.id')
            ->get();

        //Payment history under this booking
        $payment_slips = DB::table('payment_collection_slip')
            ->where(['invoice_id' => $this->params->booking_id, 'payment_collection_slip.status' => 1])
            ->select('payment_collection_slip.*', 'accounts.account_name as ac_name', 'accounts.type as ac_type')
            ->join('accounts', 'payment_collection_slip.account_id', '=', 'accounts.id')
            ->get();



        if ($bookingInfo) {
            // Fetch customer balance
            $customer_balance = Customer::where('id', $bookingInfo->customer_id)->pluck('balance')->first();

            // Calculate other dues
            $other_dues = $customer_balance + $bookingInfo->total_due;

            // Initialize bookingInfo properties if they don't exist
            if (!property_exists($bookingInfo, 'other_dues')) {
                $bookingInfo->other_dues = 0;
            }
            if (!property_exists($bookingInfo, 'net_amount')) {
                $bookingInfo->net_amount = 0;
            }
            if (!property_exists($bookingInfo, 'total_paid')) {
                $bookingInfo->total_paid = 0;
            }
            if (!property_exists($bookingInfo, 'refund_amount')) {
                $bookingInfo->refund_amount = 0;
            }

            // Calculate remaining due for the customer
            $customer_remaining_due = ($bookingInfo->net_amount + $bookingInfo->other_dues) - ($bookingInfo->total_paid + $bookingInfo->refund_amount);
            $bookingInfo->customer_remaining_due = $customer_remaining_due;

            // Update other_dues property with the absolute value
            $bookingInfo->other_dues = abs($other_dues); // Make positive sign for calculations
        }



        $this->responseMessage = "Booking Info has been fetched successfully !";
        $this->outputData['bookingInfo'] = $bookingInfo;
        $this->outputData['bookingDays'] = $bookingDays;
        $this->outputData['baseTotalTarrif'] = $baseTotalTarrif;
        $this->outputData['total_additional_price'] = $total_additional_price;
        $this->outputData['bookingDaysCount'] = $bookingDaysCount;
        $this->outputData['slotInfo'] = $slotInfo;
        $this->outputData['booking_notes'] = $booking_notes;
        $this->outputData['payment_slips'] = $payment_slips;


        $this->success = true;
    }


    public function checkInDateTime()
    {
        DB::table('customer_booking_master')->where('id', '=', $this->params->booking_id)->update(['checkin_at' => date("Y-m-d h:i:s"), "status" => 1]);

        $this->responseMessage = "Checked in successfully!";
        $this->outputData = [];
        $this->success = true;
    }


    // extraTimePayemnt

    public function extraTimePayemnt()
    {

        $booking_master = CustomerBookingGrp::where('id', '=', $this->params->booking_id)->first();

        $configData = DB::table('config_data')
            ->where('group_name', '=', 'hotel_rules')
            ->where('config_name', '=', 'Check-Out')
            ->where('status', '=', 1)
            ->get(['config_value']);

        $timeZone = DB::table('config_data')
            ->where('group_name', '=', 'hotel_rules')
            ->where('config_name', '=', 'Time Zone')
            ->where('status', '=', 1)
            ->get(['config_value']);

        if ($configData->isNotEmpty()) {
            $hotel_checkout_time = $configData->first()->config_value;
        } else {
            $hotel_checkout_time = "0:0";
        }

        $booking_master->update(['checkout_at' => date("Y-m-d H:i:s")]);
        $checkout_at_time = $booking_master->checkout_at;
        $checkout_at = date('Y-m-d', strtotime($booking_master->checkout_at));
        $lateRoomPrice = 0;
        $total_diff = "No extra time.";

        $end_date = date('Y-m-d', strtotime($booking_master->date_to));
        $checkout_at_datetime = new DateTime($checkout_at_time, new DateTimeZone($timeZone->first()->config_value));
        $hotel_checkout_datetime = new DateTime($end_date . ' ' . $hotel_checkout_time, new DateTimeZone($timeZone->first()->config_value));

        $max_time_slot = DB::table('room_hourly_slots')->max('hour');

        if ($end_date === $checkout_at_datetime->format('Y-m-d')) {

            if ($checkout_at_datetime > $hotel_checkout_datetime) {
                $interval = $hotel_checkout_datetime->diff($checkout_at_datetime);
                // dd($interval);
                // return;
                $hours = $interval->h;
                $minutes = $interval->i;
                $total_diff = "Extra time: {$hours} hours and {$minutes} minutes.";

                if ($hours <= $max_time_slot) {
                    $hourlyRoomPrice = DB::table('room_hourly_slots')
                        ->leftJoin('room_prices_hourly', 'room_prices_hourly.hour_slot_id', '=', 'room_hourly_slots.id')
                        ->where('room_prices_hourly.date', '=', $end_date)
                        ->where('room_hourly_slots.hour', '=', $hours)
                        ->first();
                    $lateRoomPrice = $hourlyRoomPrice ? intval($hourlyRoomPrice->price) : 0;
                } else {
                    $hourlyRoomPrice = DB::table('customer_booking_master')
                        ->leftJoin('customer_booking_days', 'customer_booking_days.booking_master_id', '=', 'customer_booking_master.id')
                        ->leftJoin('room_price', 'room_price.room_type_id', '=', 'customer_booking_days.room_type_id')
                        ->where('room_price.date', '=', $end_date)
                        ->where('customer_booking_master.id', '=', $this->params->booking_id)
                        ->select('room_price.*')
                        ->first();
                    $lateRoomPrice = $hourlyRoomPrice ? intval($hourlyRoomPrice->price) : 0;
                }
            }
        } else {
            $total_diff = "Checkout date does not match end date.";
        }

        // dd($end_date);
        // return;
        // if ($booking_master->checkout_type !== 'hourly') {

        // } else {
        //     $extra_time = 0;
        // }

        // dd($lateRoomPrice);
        // return;

        // Insert extra payment data

        $customer_hourly_due = DB::table('customer_hourly_due')->where(['booking_id' => $this->params->booking_id, 'customer_id' => $booking_master->customer_id])->first();
        if (empty($customer_hourly_due)) {

            $extraPayment = DB::table('customer_hourly_due')
                ->insert([
                    'booking_id' => $this->params->booking_id,
                    'customer_id' => $booking_master->customer_id,
                    'due' => $lateRoomPrice,
                    'paid' => 0,
                    'extra_time' => $total_diff,
                    'date' => $checkout_at,
                    'booking_invoice' => $booking_master->invoice_id,
                    'created_by' => $this->user->id,
                    'checkout_at' => $checkout_at_time,
                ]);
        } else {
            $extraPayment = DB::table('customer_hourly_due')->where(['booking_id' => $this->params->booking_id, 'customer_id' => $booking_master->customer_id])->update([
                'due' => $lateRoomPrice,
                'paid' => 0,
                'extra_time' => $total_diff,
                'date' => $checkout_at,
                'created_by' => $this->user->id,
                'checkout_at' => $checkout_at_time,
            ]);
        }


        // Update the checkout_at value to null
        $booking_master->update(['checkout_at' => null]);

        $this->responseMessage = "Successfully update done!";
        $this->outputData = $extraPayment;
        $this->success = true;
    }

    public function extraTimePayemntInfo()
    {
        $booking_master = DB::table('customer_hourly_due')->where('booking_id', '=', $this->params->booking_id)->first();

        if (!$booking_master) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Data are fetched successfully !";
        $this->outputData = $booking_master;
        $this->success = true;
    }

    public function checkOutDateTime()
    {
        $booking_master = CustomerBookingGrp::where('id', '=', $this->params->booking_id)->first();
        $booking_master->update(['checkout_at' => date("Y-m-d h:i:s"), "status" => 5]);

        // $configData = DB::table('config_data')
        //     ->where('group_name', '=', 'hotel_rules')
        //     ->where('config_name', '=', 'Check-Out')
        //     ->where('status', '=', 1)
        //     ->get(['config_value']);

        // $timeZone = DB::table('config_data')
        //     ->where('group_name', '=', 'hotel_rules')
        //     ->where('config_name', '=', 'Time Zone')
        //     ->where('status', '=', 1)
        //     ->get(['config_value']);

        // if ($configData->isNotEmpty()) {
        //     $hotel_checkout_time = $configData->first()->config_value;
        // } else {
        //     $hotel_checkout_time = "0:0";
        // }
        // $checkout_at_time = $booking_master->checkout_at;
        // $end_date = date('Y-m-d', strtotime($booking_master->date_to));
        // $checkout_at_datetime = new DateTime($checkout_at_time, new DateTimeZone($timeZone->first()->config_value));
        // $hotel_checkout_datetime = new DateTime($end_date . ' ' . $hotel_checkout_time, new DateTimeZone($timeZone->first()->config_value));
        // $interval = $hotel_checkout_datetime->diff($checkout_at_datetime);
        // $hours = $interval->h;
        // $minutes = $interval->i;
        // dd($hours);
        // return;


        $booking_master->update(['checkout_at' => date("Y-m-d h:i:s"), "status" => 5]);


        $checkout_at = date('Y-m-d', strtotime($booking_master->checkout_at));

        $count_start_date = date('Y-m-d', strtotime("+1 day", strtotime($booking_master->checkout_at)));
        $start_date = date('Y-m-d', strtotime($booking_master->date_from));
        $end_date = date('Y-m-d', strtotime($booking_master->date_to));
        if ($this->params->refund == 'yes') {

            $total_days = 0;
            for ($i = $start_date; $i <= $end_date; $i = date('Y-m-d', strtotime("+1 day", strtotime($i)))) {
                $total_days += 1;
            }

            if ($checkout_at < $end_date) {

                $unavail_days = 0;
                for ($i = $count_start_date; $i <= $end_date; $i = date('Y-m-d', strtotime("+1 day", strtotime($i)))) {
                    $unavail_days += 1;
                }



                if ($unavail_days > 0) {
                    $refundable = ($booking_master->net_amount / $total_days) * $unavail_days;
                    $old_refund = $booking_master->refund_amount;

                    $booking_master->update(['refund_amount' => $refundable]);

                    $new_refund = $booking_master->refund_amount;

                    $net_amount = $booking_master->net_amount;
                    $total_paid = $booking_master->total_paid;
                    $refund_amount = $booking_master->refund_amount;

                    $total_due = $net_amount - ($total_paid + $refund_amount);

                    $booking_master->update(['total_due' => $total_due]);

                    if ($old_refund < $new_refund) {
                        $credited_note = "amount refunded to customer credited";
                        $debited_note = "";
                        $credit = true;
                        $refund_amount = $new_refund - $old_refund;
                        Accounting::accountCustomer($credit, $booking_master->customer_id, $booking_master->id, $booking_master->invoice_id, $booking_master->invoice_type, $refund_amount, 0, $credited_note, $debited_note, $this->user->id, true);
                    } else {
                        $refund_amount = $old_refund - $new_refund;
                        $credited_note = "";
                        $debited_note = "amound refunded to customer debited";
                        $credit = false;
                        Accounting::accountCustomer($credit, $booking_master->customer_id, $booking_master->id, $booking_master->invoice_id, $booking_master->invoice_type, $refund_amount, 0, $credited_note, $debited_note, $this->user->id, false);
                    }

                    // Accounting::accountCustomer(true,$booking_master->customer_id,$booking_master->id,$booking_master->invoice_id,$booking_master->invoice_type,$refund_amount,0,$credited_note,$debited_note,$this->user->id,true);

                    // @TODO: UPDATE BANK ACCOUNT , HOW MUCH AMOUNT REFUND THROUGH WHICH ACCOUNT

                }
            }
        }


        $this->responseMessage = "Checkout done!";
        $this->outputData = $booking_master;
        $this->success = true;
    }

    public function refund(Request $request)
    {

        $this->validator->validate($request, [
            "refund_amount" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $booking_master = CustomerBookingGrp::where('id', '=', $this->params->booking_id)->first();


        // @TODO:: when refund amount, insert into account_customer and customer balance should be updated accordingly
        $old_refund = $booking_master->refund_amount;

        $booking_master->update(['refund_amount' => $this->params->refund_amount]);

        $new_refund = $booking_master->refund_amount;

        $net_amount = $booking_master->net_amount;
        $total_paid = $booking_master->total_paid;
        $refund_amount = $booking_master->refund_amount;

        $total_due = 0;
        // $total_due = $net_amount - ($total_paid + $refund_amount); //-51

        if ($booking_master->total_due > 0) {
            $total_due = $total_paid - $this->params->refund_amount;
        }
        $booking_master->update(['total_due' => $total_due]);


        //if refund amount success, update customer balance
        //Balance for account customer

        Helper::notification($booking_master->customer_id, "Refund", "Booking Refund", "Your booking amount is refund");

        if ($old_refund < $new_refund) {
            $credited_note = "amound refunded to customer credited";
            $debited_note = "";
            $credit = false;
            $refund_amount = $new_refund - $old_refund;
            Accounting::accountCustomer($credit, $booking_master->customer_id, $booking_master->id, $booking_master->invoice_id, $booking_master->invoice_type, $refund_amount, 0, $credited_note, $debited_note, $this->user->id, true);
        } else {
            $refund_amount = $old_refund - $new_refund;
            $credited_note = "";
            $debited_note = "amound refunded to customer debited";
            $credit = true;
            Accounting::accountCustomer($credit, $booking_master->customer_id, $booking_master->id, $booking_master->invoice_id, $booking_master->invoice_type, $refund_amount, 0, $credited_note, $debited_note, $this->user->id, false);
        }



        $this->responseMessage = "Amount has been successfully refunded!";
        $this->outputData = $booking_master;
        $this->success = true;
    }
    public function rejectRefund(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->validator->validate($request, [
                "refund_amount" => v::notEmpty(),
            ]);

            if ($this->validator->failed()) {
                $this->success = false;
                $this->responseMessage = $this->validator->errors;
                return;
            }
            // $config_data = DB::table("config_data")->select("config_name", "config_value")->whereIn("config_name", ["Rejected by Admin", "Cancel by Customer"])->get();
            $config_value = null;
            $booking_master = CustomerBookingGrp::where('id', '=', $this->params->booking_id)->first();

            $advance_paid = 0;
            $previous_booking_due = 0;
            $customer_balance = 0;
            $credited_note = "";
            $debited_note = "";

            $customer = DB::table("customers")->where("id", $booking_master->customer_id)->first();


            if ($booking_master) {





                $advance_paid = $booking_master->advance_payment;
                $previous_booking_due = $booking_master->total_due;
                $customer_balance = $customer->balance;

                $status = $booking_master->status;
                $config_data = DB::table("config_data")
                    ->select("config_name", "config_value")
                    ->whereIn("config_name", ["Rejected by Admin", "Cancel by Customer"])
                    ->get();



                if ($status == 4) {


                    foreach ($config_data as $config) {
                        if ($config->config_name === "Cancel by Customer") {
                            $config_value = $config->config_value;
                            $credited_note = "amount refunded to customer credited";
                            $debited_note = "Customer booking cancelation refund";
                            break;
                        }
                    }
                } else {
                    foreach ($config_data as $config) {
                        if ($config->config_name === "Rejected by Admin") {
                            $config_value = $config->config_value;
                            $credited_note = "amount refunded to customer credited";
                            $debited_note = "Refund by admin booking rejection";
                            break;
                        }
                    }
                }
            }

            // $this->outputData = $config_value;
            $refund_calculation = floatval($booking_master->total_paid) - floatval($booking_master->total_amount) * (floatval($config_value) / 100);
            // dd($refund_calculation);
            // return;
            $refund_amount = DB::table('booking_refundable')
                ->insert([
                    'booking_id' => $this->params->booking_id,
                    'customer_id' => $booking_master->customer_id,
                    'paid_amount' => $booking_master->total_paid,
                    'refund_amount' => $refund_calculation,
                    'status' => 1,

                ]);
            $booking_master->update([
                'status' => 0,
                'refund_amount' => $refund_calculation,
                'payment_status' => 4
            ]);

            $credit = true;
            $refund_amount = $refund_calculation;


            $new_balance = 0;
            // if ($customer_balance < 0) {
            //     $new_balance = $customer_balance + $previous_booking_due; //
            //     $new_balance =  $new_balance + $refund_calculation;
            //     $customer->balance = $new_balance;
            // } else {
            //     $new_balance = $customer_balance + $previous_booking_due;
            //     $new_balance =  $new_balance + $refund_calculation;
            //     $customer->balance = $new_balance;
            // }

            $new_balance = $customer_balance + $previous_booking_due;
            $new_balance =  $new_balance + $refund_calculation;
            $customer->balance = $new_balance;


            DB::table("customers")->where("id", $booking_master->customer_id)->update([
                "balance" => $new_balance
            ]);

            DB::table('account_customer')->insert([
                'customer_id' => $customer->id,
                'invoice_id' =>  $booking_master->id,
                'inv_type' => $booking_master->invoice_type,
                'reference' =>  $booking_master->invoice_id,
                'debit' => - ($new_balance),
                'credit' => 0,
                'balance' => $customer->balance,
                'note' => $debited_note,
                'created_by' =>  $this->user->id,
                'status' => 1
            ]);


            // Accounting::accountCustomer($credit, $booking_master->customer_id, $booking_master->id, $booking_master->invoice_id, $booking_master->invoice_type, $refund_amount, 0, $credited_note, $debited_note, $this->user->id, true);
            Helper::notification($booking_master->customer_id, "Refund", "Booking Reject & Refund", "Your booking is rejected and amount is refund");


            DB::commit();
            $this->responseMessage = "Successfully refunded done!";
            $this->outputData = $refund_amount;
            $this->success = true;
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "Refund is failed! $th";
            $this->outputData = [];
            $this->success = true;
        }
    }

    public function withdrawRefund(Request $request)
    {
        $this->validator->validate($request, [
            "withdraw_amount" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $refund_amount = DB::table('booking_refundable')
            ->where('booking_id', '=', $this->params->booking_id)
            ->update(['status' => 2]);

        $booking_master = CustomerBookingGrp::where('id', '=', $this->params->booking_id)->first();
        $credited_note = "";
        $debited_note = "Refund given customer";

        $existing_refund_amount = DB::table('booking_refundable')
            ->where('status', '!=', 2)
            ->first();
        if ($existing_refund_amount) {
            Accounting::Accounts($this->params->account_id, $booking_master->id, $booking_master->invoice_type, $this->params->withdraw_amount, $credited_note, $debited_note, $this->user->id, false);
        }




        $this->responseMessage = "Successfully refunded done!";
        $this->outputData = $refund_amount;
        $this->success = true;
    }

    // rejectRefund

    public function cancelBooking()
    {
        DB::beginTransaction();
        try {
            $cancelBooking = DB::table('customer_booking_master')->where('id', $this->params->booking_id)->update([
                'status' => 0,
                "canceled_by" => "Desk",
            ]);

            $customerInfo = DB::table("customer_booking_master")->where("id", $this->params->booking_id)->first();




            Helper::notification($customerInfo->customer_id, "Room Booking is Rejected", "Booking Rejected", "Your Booking Rejected");

            if (!$cancelBooking) {
                $this->success = false;
                $this->responseMessage = "Server error!";
                $this->outputData = [];
            }


            DB::commit();
            $this->success = true;
            $this->responseMessage = "Success!";
            $this->outputData = $cancelBooking;
        } catch (\Exception $th) {
            DB::rollback();
            $this->success = false;
            $this->responseMessage = "Server error!";
            $this->outputData = [];
        }
    }
    public function approveBooking()
    {
        DB::beginTransaction();
        try {


            $cancelBooking = DB::table('customer_booking_master')->where('id', $this->params->booking_id)->update([
                'status' => 2
            ]);

            $customerInfo = DB::table("customer_booking_master")->where("id", $this->params->booking_id)->first();

            // DB::table("notifications")->insert([
            //     "customer_id" => $customerInfo->customer_id,
            //     "message_type" => "Booking Approved",
            //     "is_read" => 0,
            // ]);

            Helper::notification($customerInfo->customer_id, "Room Booking Approved", "Booking Approved", "Your booking is Approved");


            if (!$cancelBooking) {
                $this->success = false;
                $this->responseMessage = "Server error!";
                $this->outputData = [];
            }


            DB::commit();
            $this->success = true;
            $this->responseMessage = "Success!";
            $this->outputData = $cancelBooking;
        } catch (\Exception $th) {
            DB::rollback();
            $this->success = false;
            $this->responseMessage = "Server error!";
            $this->outputData = [];
        }
    }

    public function seeAllDuesCustomer()
    {
        $customer = DB::table('customers')->where('id', $this->params->customer_id)->first();
        $duesArr = [];

        $vehicle_booking_Dues = DB::table('vehicle_booking_items')
            ->select(DB::raw('SUM(booking_charge) as total_due'))
            ->where('customer_id', $this->params->customer_id)
            ->where('status', 1)
            ->get();

        $restaurant_dues = DB::table('restaurant_invoices')
            ->select(DB::raw('SUM(due_amount) as total_due'))
            ->where('customer_id', $this->params->customer_id)
            ->where('status', 1)
            ->get();
        if ($vehicle_booking_Dues[0]->total_due !== null) {
            $post_data = json_encode(array('description' => 'Transportaion dues', 'due_amount' => $vehicle_booking_Dues[0]->total_due), JSON_FORCE_OBJECT);
            array_push($duesArr, json_decode($post_data));
        }
        if ($restaurant_dues[0]->total_due !== null) {
            $post_data = json_encode(array('description' => 'Restaurant dues', 'due_amount' => $restaurant_dues[0]->total_due), JSON_FORCE_OBJECT);
            array_push($duesArr, json_decode($post_data));
        }

        // dd($vehicle_booking_Dues[0]->total_due);
        // dd($duesArr);

        $this->success = true;
        $this->responseMessage = "Success!";
        $this->outputData['customer'] = $customer;
        $this->outputData['all_dues'] = $duesArr;
    }

    //Show customer Ledger Details from account_customer table
    public function customerLedger()
    {
        //Get customer information from customer table
        $customer = DB::table('customers')
            ->select('id', 'first_name', 'last_name', 'mobile')
            ->where('id', $this->params->customer_id)
            ->first();

        if (!$customer) {
            $this->success = false;
            $this->responseMessage = "Customer not found!";
            return;
        }

        // $new_start_date="2022-12-26";
        // $new_end_date = "2022-12-31";
        // $new_start_date = $this->params->fromDate;
        // $new_end_date = $this->params->toDate;

        $new_start_date = isset($this->params->fromDate) ? $this->params->fromDate : null;
        $new_end_date = isset($this->params->toDate) ? $this->params->toDate : null;

        // Now you can safely use $new_start_date and $new_end_date in your code


        //if having a new start date and end date show between these dates data otherwise show all

        if ($new_start_date && $new_end_date) {
            $account_ledger = DB::table('account_customer')
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->where(function ($query) use ($new_start_date, $new_end_date) {
                    $query->whereDate('created_at', '>=', $new_start_date)
                        ->whereDate('created_at', '<=', $new_end_date);
                })
                ->get();

            $total_debit_credit = DB::table('account_customer')
                ->select(DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->where(function ($query) use ($new_start_date, $new_end_date) {
                    $query->whereDate('created_at', '>=', $new_start_date)
                        ->whereDate('created_at', '<=', $new_end_date);
                })
                ->first();

            if (count($account_ledger) < 1) {
                $this->success = false;
                $this->responseMessage = "No Ledger Data Found!";
                $this->outputData['account_ledger'] = [];
                return;
            }

            $first_one = $account_ledger[0];

            $opening_balance = DB::table('account_customer')
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->where('account_customer.id', '<', $first_one->id)
                ->latest('account_customer.id')
                ->first();
        } else {
            $account_ledger = DB::table('account_customer')
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->get();

            $total_debit_credit = DB::table('account_customer')
                ->select(DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->first();

            if (count($account_ledger) < 1) {
                $this->success = false;
                $this->responseMessage = "No Ledger Data Found!";
                return;
            }

            $first_one = DB::table('account_customer')
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->first();

            $opening_balance = DB::table('account_customer')
                ->where('account_customer.customer_id', $this->params->customer_id)
                ->where('account_customer.id', '<', $first_one->id)
                ->latest('account_customer.id')
                ->first();
        }


        $this->success = true;
        $this->responseMessage = "Success!";
        $this->outputData['customer'] = $customer;
        $this->outputData['account_ledger'] = $account_ledger;
        $this->outputData['first_one'] = $first_one;
        if ($opening_balance !== null) {
            $this->outputData['opening_balance'] = $opening_balance;
        } else {
            $this->outputData['opening_balance'] = (object) ['balance' => 0];
        }

        $this->outputData['total_debit_credit'] = $total_debit_credit;
    }


    public function customerLedgerHistory()
    {
        // Get customer information from the customer table
        $customer = DB::table('customers')
            ->select('id', 'first_name', 'last_name', 'mobile')
            ->where('id', $this->params->customer_id)
            ->first();

        if (!$customer) {
            $this->success = false;
            $this->responseMessage = "Customer not found!";
            return;
        }

        // Retrieve all ledger entries for the given customer_id
        $account_ledger = DB::table('account_customer')
            ->where('customer_id', $this->params->customer_id)
            ->get();

        // Filter out ledger entries where both debit and credit are zero for the same invoice_id
        $filtered_ledger = collect($account_ledger)->groupBy('invoice_id')->filter(function ($entries) {
            $netBalance = $entries->sum(function ($entry) {
                return floatval($entry->debit) - floatval($entry->credit);
            });
            return $netBalance != 0;
        })->flatten();

        // Calculate total debit and credit
        $total_debit_credit = $filtered_ledger->reduce(function ($total, $ledger) {
            $total['total_debit'] += floatval($ledger->debit);
            $total['total_credit'] += floatval($ledger->credit);
            return $total;
        }, ['total_debit' => 0, 'total_credit' => 0]);

        // Check if there are no ledger entries after filtering
        if ($filtered_ledger->isEmpty()) {
            $this->success = false;
            $this->responseMessage = "No Ledger Data Found!";
            return;
        }

        // Get the first ledger entry after filtering
        $first_one = $filtered_ledger->first();

        // Find the opening balance based on the first ledger entry
        $opening_balance = DB::table('account_customer')
            ->where('customer_id', $this->params->customer_id)
            ->where('id', '<', $first_one->id)
            ->latest('id')
            ->first();

        // Set the success flag and output data
        $this->success = true;
        $this->responseMessage = "Success!";
        $this->outputData['customer'] = $customer;
        $this->outputData['account_ledger'] = $filtered_ledger->values(); // Reset array keys after filtering
        $this->outputData['first_one'] = $first_one;
        $this->outputData['opening_balance'] = $opening_balance ?? (object) ['balance' => 0];
        $this->outputData['total_debit_credit'] = $total_debit_credit;
    }



    public function customerLedgerHistoryNew()
    {

        try {

            $customer_last_order = DB::table('customer_booking_master')
                ->where('customer_id', $this->params->customer_id)
                ->orderBy('id', 'DESC')
                ->first();


            // Fetch data from customer_booking_master table
            $customerBookingMaster = DB::table('customer_booking_master')
                ->where([
                    'customer_id' => $this->params->customer_id,
                    "id" => $this->params->booking_id,
                ])
                ->whereNull('checkout_time')
                ->where('total_due', '>', 0)
                ->get();


            $restaurantInvoices = DB::table('restaurant_invoices')
                ->join(
                    DB::raw('(SELECT customer_id, MAX(id) AS max_id FROM customer_booking_master GROUP BY customer_id) AS latest_order'),
                    'restaurant_invoices.customer_id',
                    '=',
                    'latest_order.customer_id'
                )
                ->leftJoin('customer_booking_master', function ($join) {
                    $join->on('latest_order.max_id', '=', 'customer_booking_master.id');
                })
                ->where('restaurant_invoices.status', 1)
                ->where('restaurant_invoices.customer_id', $this->params->customer_id)
                ->whereNull('customer_booking_master.checkout_time')
                ->where('restaurant_invoices.is_paid', 0)
                ->where('restaurant_invoices.is_hold', 0)
                ->select('restaurant_invoices.*')
                ->orderBy('customer_booking_master.id', 'DESC')
                ->get();

            $vehicleInvoices = DB::table('vehicle_booking_items')
                ->join(
                    DB::raw('(SELECT customer_id, MAX(id) AS max_id FROM customer_booking_master GROUP BY customer_id) AS latest_order'),
                    'vehicle_booking_items.customer_id',
                    '=',
                    'latest_order.customer_id'
                )
                ->leftJoin('customer_booking_master', function ($join) {
                    $join->on('latest_order.max_id', '=', 'customer_booking_master.id');
                })
                ->where('vehicle_booking_items.status', 1)
                ->where('vehicle_booking_items.is_paid', 0)
                ->where('vehicle_booking_items.customer_id', $this->params->customer_id)
                ->whereNull('customer_booking_master.checkout_time')
                ->select('vehicle_booking_items.*')
                ->orderBy('customer_booking_master.id', 'DESC')
                ->get();



            $roomServiceInvoices = DB::table('cust_room_service_inv')
                ->join(
                    DB::raw('(SELECT customer_id, MAX(id) AS max_id FROM customer_booking_master GROUP BY customer_id) AS latest_order'),
                    'cust_room_service_inv.customer_id',
                    '=',
                    'latest_order.customer_id'
                )
                ->leftJoin('customer_booking_master', function ($join) {
                    $join->on('latest_order.max_id', '=', 'customer_booking_master.id');
                })
                ->where('cust_room_service_inv.status', 1)
                ->where('cust_room_service_inv.paid', 0)
                // ->where('cust_room_service_inv.paid', 0)
                ->where('cust_room_service_inv.customer_id', $this->params->customer_id)
                ->whereNull('customer_booking_master.checkout_time')
                ->select('cust_room_service_inv.*')
                ->orderBy('customer_booking_master.id', 'DESC')
                ->get();

            $hourlyRoomPriceDues = DB::table('customer_hourly_due')
                ->where('customer_id', $this->params->customer_id)
                ->where('booking_id', $this->params->booking_id)
                ->where('paid', '=', 0)
                ->get();

            $transformedData = [];

            foreach ($hourlyRoomPriceDues as $hourlyRoomPriceDue) {
                $transformedData[] = [
                    'customer_hourly_due' => [
                        'id' => $hourlyRoomPriceDue->id,
                        'due_amount' => $hourlyRoomPriceDue->due,
                        'created_at' => $hourlyRoomPriceDue->date,
                        'inv_id' => $hourlyRoomPriceDue->booking_invoice,
                        'inv_type' => 'customer_hourly_due'
                    ]
                ];
            }

            foreach ($customerBookingMaster as $bookingMaster) {
                $transformedData[] = [
                    'customer_booking_master' => [
                        'id' => $bookingMaster->id,
                        'total_paid' => $bookingMaster->total_paid,
                        'due_amount' => $bookingMaster->total_due,
                        'created_at' => $bookingMaster->created_at,
                        'inv_id' => $bookingMaster->invoice_id,
                        'inv_type' => 'hotel_booking'
                    ]
                ];
            }

            foreach ($restaurantInvoices as $restaurantInvoice) {
                $transformedData[] = [
                    'restaurant_invoices' => [
                        'id' => $restaurantInvoice->id,
                        'total_amount' => $restaurantInvoice->total_amount,
                        'due_amount' => $restaurantInvoice->due_amount,
                        'created_at' => $restaurantInvoice->created_at,
                        'inv_id' => $restaurantInvoice->invoice_number,
                        'inv_type' => 'restaurant'
                    ]
                ];
            }

            foreach ($vehicleInvoices as $vehicleInvoice) {
                $transformedData[] = [
                    'vehicle_booking' => [
                        'id' => $vehicleInvoice->id,
                        'due_amount' => $vehicleInvoice->booking_charge,
                        'created_at' => $vehicleInvoice->created_at,
                        'inv_id' => $vehicleInvoice->booking_voucher,
                        'inv_type' => 'vehicle_booking'
                    ]
                ];
            }

            foreach ($roomServiceInvoices as $cust_room_service_inv) {
                $transformedData[] = [
                    'cust_room_service' => [
                        'id' => $cust_room_service_inv->id,
                        'due_amount' => $cust_room_service_inv->due,
                        'total_amount' => $cust_room_service_inv->net_amount,
                        'created_at' => $cust_room_service_inv->created_at,
                        'inv_id' => $cust_room_service_inv->inv_number,
                        'inv_type' => 'cust_room_service'
                    ]
                ];
            }

            // Set response messages and data
            $this->responseMessage = "All customers fetched successfully!";
            $this->outputData['data'] = $transformedData;
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "All customers fetched failed!";
            $this->outputData['data'] = $th;
            $this->success = false;
        }
    }

    public function getAllBooking2()
    {
        $platform = !empty($this->params->platform) ? $this->params->platform : 'all';
        $statusString = !empty($this->params->status) ? $this->params->status : 'active';
        $status = $statusString === 'active' ? 1 : 0;
        $statusMsg = $this->params->status;
        $start_date = $this->params->startDate . " 00:00:00";
        $end_date = $this->params->endDate . " 23:59:59";


        $bQuery = DB::table('customer_booking_days');
        $bQuery->select(
            'customer_booking_master.*',
            'customer_booking_days.total_amount as net_amount',
            'customers.first_name',
            'customers.last_name',
            'customers.mobile',
            'customer_booking_days.room_id as room_id',
            'tower_floor_rooms.room_no as room_no'
            // 'customer_booking_master.invoice_id as invoice_id',
            // 'customer_booking_master.checkout_type as checkout_type',
            // 'customer_booking_master.date_from as date_from',
            // 'customer_booking_master.date_to as date_to',

        );
        $bQuery->join('customers', 'customer_booking_days.customer_id', '=', 'customers.id');
        $bQuery->join('customer_booking_master', 'customer_booking_days.customer_id', 'customer_booking_master.customer_id');
        $bQuery->leftJoin('tower_floor_rooms', 'customer_booking_days.room_id', 'tower_floor_rooms.id');
        // $bQuery->whereYear('customer_booking_master.created_at', '=', date('Y', strtotime($month_year)));
        // $bQuery->whereMonth('customer_booking_master.created_at', '=', date('m', strtotime($month_year)));
        $bQuery->whereBetween('customer_booking_master.created_at', [$start_date, $end_date]);
        $bQuery->selectRaw("DATEDIFF(date_to, date_from) AS num_of_days");

        if ($platform !== 'all') {
            $bQuery->where('customer_booking_master.platform', $platform);
        }
        // if ($platform === 'Web') {
        //     $bQuery->where('customer_booking_master.platform', 'Web');
        // }

        $bQuery->where('customer_booking_master.status', $status);
        $bQuery->orderBy('customer_booking_master.id', 'desc');
        $bQuery->groupBy('customer_booking_master.invoice_id');
        $bookings = $bQuery->get();
        // echo(count($bookings));

        // exit;
        if (count($bookings) < 1) {
            $this->responseMessage = "No $statusMsg data found!";
            $this->outputData = [];
            $this->success = true;
        } else {
            $this->responseMessage = "All bookings are fetched successfully !";
            $this->outputData = $bookings;
            $this->success = true;
        }
    }

    public function getAllBooking()
    {
        $platform = !empty($this->params->platform) ? $this->params->platform : 'all';
        $statusString = !empty($this->params->status) ? $this->params->status : 'pending';

        $status = $statusString === 'pending' ? 1 : 0;
        $statusMsg = $this->params->status;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;

        $bQuery = DB::table('customer_booking_master');
        $bQuery->select(
            'customer_booking_master.*',
            // 'customer_booking_days.total_amount as net_amount',
            'customers.first_name',
            'customers.last_name',
            'customers.mobile',
            'customer_booking_days.room_id as room_id',
            'tower_floor_rooms.room_no as room_no',
            DB::raw("DATEDIFF(customer_booking_master.date_to, customer_booking_master.date_from) AS num_of_days")
        );
        $bQuery->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id');

        $bQuery->leftJoin('customer_booking_days', 'customer_booking_days.booking_master_id', '=', 'customer_booking_master.id');

        $bQuery->leftJoin('tower_floor_rooms', 'customer_booking_days.room_id', '=', 'tower_floor_rooms.id');


        if (strtolower($platform) !== 'all') {
            $bQuery->where('customer_booking_master.platform', strtolower($platform));
        }

        if ((strtolower($statusMsg) === 'pending') && (strtotime($start_date) === strtotime($end_date))) {
            $bQuery->whereDate('customer_booking_master.date_from', "=", $start_date);
            $bQuery->where('customer_booking_master.status', 3);
        } else if ((strtolower($statusMsg) === 'pending') && (strtotime($end_date) > strtotime($start_date))) {

            $bQuery->where('customer_booking_master.status', 3);
        } else if ((strtolower($statusMsg) === 'booked')) {

            $bQuery->where('customer_booking_master.status', 2);
        } else if ((strtolower($statusMsg) === 'checked-in')) {

            $bQuery->whereDate('customer_booking_master.checkin_at', ">=", $start_date);
            $bQuery->whereDate('customer_booking_master.checkin_at', "<=", $end_date);
            $bQuery->where('customer_booking_master.status', 1);
        } else if ((strtolower($statusMsg) === 'cancelled')) {

            $bQuery->where('customer_booking_master.status', 4);
        } else if ((strtolower($statusMsg) === 'rejected')) {

            $bQuery->where('customer_booking_master.status', 0);
        }


        if ((strtolower($statusMsg) === 'cancelled') || (strtolower($statusMsg) === 'rejected') || (strtolower($statusMsg) === 'booked') || (strtolower($statusMsg) === 'pending') && (strtotime($end_date) > strtotime($start_date))) {
            $bQuery->whereDate('customer_booking_master.date_from', ">=", $start_date);
            $bQuery->whereDate('customer_booking_master.date_from', "<=", $end_date);
        }

        $bQuery->orderBy('customer_booking_master.id', 'desc');
        $bQuery->groupBy(
            'customer_booking_master.invoice_id',
            'customer_booking_master.id',
            'customer_booking_days.total_amount',
            'customers.first_name',
            'customers.last_name',
            'customers.mobile',
            'customer_booking_days.room_id',
            'tower_floor_rooms.room_no',
            'customer_booking_master.date_to',
            'customer_booking_master.date_from'
        );

        $bookings = $bQuery->get();

        if (count($bookings) == 0) {
            $this->responseMessage = "No $statusMsg data found!";
            $this->outputData = [];
            $this->success = true;
        } else {
            $this->responseMessage = "All bookings are fetched successfully!";
            $this->outputData = $bookings;
            $this->success = true;
        }
    }



    public function getAllActiveBooking()
    {
        $slotInfo = DB::table('customer_booking_master')
            ->join('customers', 'customers.id', '=', 'customer_booking_master.customer_id')
            ->where(['customer_booking_master.customer_id' => $this->params->customer_id, 'customer_booking_master.status' => 1])
            ->select('customer_booking_master.*', 'customers.first_name as first_name', 'customers.last_name as last_name', 'customers.mobile as mobile')
            ->whereNull('customer_booking_master.checkout_time')->get();

        $this->responseMessage = "Data fetch Successfully";
        $this->outputData = $slotInfo;
        $this->success = true;
    }

    public function getAllRoomByCustomer()
    {
        $slotInfo = DB::table('customer_booking_master')
            ->join('customer_booking_days', 'customer_booking_days.booking_master_id', '=', 'customer_booking_master.id')
            ->join('tower_floor_rooms', 'customer_booking_days.room_id', '=', 'tower_floor_rooms.id')
            ->where([
                'customer_booking_days.customer_id' => $this->params->customer_id,
                'customer_booking_master.status' => 1,
                'customer_booking_days.booking_master_id' => $this->params->booking_id
            ])
            ->select('customer_booking_days.*', 'tower_floor_rooms.room_no as room_no')
            // ->select('customer_booking_days.*')
            ->whereNull('customer_booking_master.checkout_time')
            ->get();

        $this->responseMessage = "Data fetch Successfully";
        $this->outputData = $slotInfo;
        $this->success = true;
    }


    public function getAllActiveRoom()
    {
        $slotInfo = DB::table('customer_booking_master')
            ->join('customer_booking_days', 'customer_booking_days.booking_master_id', '=', 'customer_booking_master.id')
            ->join('tower_floor_rooms', 'customer_booking_days.room_id', '=', 'tower_floor_rooms.id')
            ->where([
                'customer_booking_master.status' => 1,
            ])
            ->select('tower_floor_rooms.room_no as room_no', 'tower_floor_rooms.id as room_id')
            // ->select('tower_floor_rooms.room_no as room_no')
            // ->select('customer_booking_days.*')
            ->whereNull('customer_booking_master.checkout_time')
            ->groupBy("tower_floor_rooms.room_no", 'tower_floor_rooms.id')
            ->get();

        $this->responseMessage = "Data fetch Successfully";
        $this->outputData = $slotInfo;
        $this->success = true;
    }

    public function getAllRefund()
    {

        $booking_refundable = DB::table('booking_refundable')
            ->join('customers', 'booking_refundable.customer_id', '=', 'customers.id')
            ->orderBy('booking_refundable.id', 'desc')
            ->select('booking_refundable.*', 'customers.first_name as first_name', 'customers.last_name as last_name', 'customers.mobile as mobile')
            ->get();


        $this->responseMessage = "All refundable ammount fetched Successfully!";
        $this->outputData = $booking_refundable;
        $this->success = true;
    }

    public function getAllRefundFrDashbord()
    {
        $booking_refundable = DB::table('booking_refundable')
            ->join('customers', 'booking_refundable.customer_id', '=', 'customers.id')
            ->orderBy('booking_refundable.id', 'desc')
            ->select(
                'booking_refundable.*',
                'customers.first_name as first_name',
                'customers.last_name as last_name',
                'customers.mobile as mobile'
            )
            ->limit(10) // Limit the result to 10 records
            ->get();

        $this->responseMessage = "10 refundable amounts fetched successfully!";
        $this->outputData = $booking_refundable;
        $this->success = true;
    }
}
