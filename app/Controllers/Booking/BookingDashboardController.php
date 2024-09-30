<?php

namespace  App\Controllers\Booking;

use App\Auth\Auth;
use App\Models\Customers\CustomerBooking;
use App\Models\Customers\CustomerBookingGrp;
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

class BookingDashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $bookings;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->bookings = new CustomerBookingGrp();
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
            case 'getBookingCounts':
                $this->getBookingCounts();
                break;
            case 'getLatestBookingList':
                $this->getLatestBookingList();
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




    public function getBookingCounts()
    {
        $date = date('Y-m-d');

        $platformCounts = CustomerBookingGrp::selectRaw('platform, COUNT(*) as count')
            ->whereYear('created_at', '=', date('Y', strtotime($date)))
            ->whereMonth('created_at', '=', date('m', strtotime($date)))
            ->groupBy('platform')
            ->get();

        $countList = ["total"=>0, "hotel"=>0, "web"=>0, "channel"=>0];
        foreach ($platformCounts as $platformCount){
            if(!empty($platformCount->platform)){
                $countList[strtolower($platformCount->platform)] = $platformCount->count;
            }
            $countList['total'] += $platformCount->count;
        }

        $this->responseMessage = "success!";
        $this->outputData = $countList;
        $this->success = true;
    }


    public function getLatestBookingList()
    {
        $bookings = DB::table('customer_booking_master')
            ->select(
                'customer_booking_master.id','customer_booking_master.platform','customer_booking_master.invoice_id', 'customer_booking_master.invoice_id','customer_booking_master.checkin_at','customer_booking_master.checkout_at', 'customers.first_name', 'customers.last_name',
                'room_types.name as room_type_name'
            )
            ->join('customers', 'customer_booking_master.customer_id', '=', 'customers.id')
            ->join('customer_booking_days', 'customer_booking_master.id', '=', 'customer_booking_days.booking_master_id')
            ->join('room_types', 'customer_booking_days.room_type_id', '=', 'room_types.id')
            ->where('customer_booking_master.status', 1)
            ->take(10)
            ->get();

        $this->responseMessage = "success!";
        $this->outputData = $bookings;
        $this->success = true;
    }

}
