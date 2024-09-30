<?php

namespace  App\Controllers\Transport;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Transport\Drivers;


use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use App\Models\Transport\TransportVehicles;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;

class VehicleDashboardController
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



    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();

        $this->vehicle = new TransportVehicles();
        $this->driver = new Drivers();
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


            case 'fetchTotalCount':
                $this->fetchTotalCount($request);
                break;
            case 'fetchAllVehicleBookingList':
                $this->fetchAllVehicleBookingList($request);
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


    public function fetchAllVehicleBookingList($request)
    {
  
        $query = DB::table('vehicle_booking')
            ->join('transport_vehicles', 'transport_vehicles.id', 'vehicle_booking.vehicle_id')
            ->whereDate('vehicle_booking.created_at', '<=', date('Y-m-d'))
            ->where('vehicle_booking.status', 1);

        if ($this->user->data_access_type === 'own') {
            $empId = DB::table('employees')->select('id')->where('user_id', '=', $this->user->id)->first();
            if ($empId) {

                $query->where('transport_vehicles.employee_id', '=', $empId->id);
            }
        }

        $allVehicleBooking = $query->orderBy('vehicle_booking.id', 'desc')->limit(10)->get();
        $this->responseMessage = "Vehicles Fetch successfully!";
        $this->outputData = $allVehicleBooking  ?? [];
        $this->success = true;
    }


    public function fetchTotalCount($request)
    {
        $totalVehicle = DB::table('transport_vehicles')->where("status", 1)->count("id");
        $totalDrivers = DB::table('drivers')->where("status", 1)->count("id");
        $totalBookings = DB::table('vehicle_booking')->whereDate("booking_date", date('Y-m-d'))->count("id");
        $totalMonthlyIncome = DB::table('vehicle_booking_items')->join("vehicle_booking", "vehicle_booking_items.vehicle_booking_id", 'vehicle_booking.id')->whereDate('vehicle_booking.booking_date', ">=", date('Y-m-' . '01'))->sum("vehicle_booking_items.booking_charge");

        $this->responseMessage = "Vehicles Fetch successfully!";
        $this->outputData = [
            'totalVehicle' => $totalVehicle ?? [],
            'totalDrivers' => $totalDrivers ?? [],
            "totalBookings" => $totalBookings ?? [],
            "totalMonthlyIncome" => $totalMonthlyIncome ?? [],
        ];
        $this->success = true;
    }
}
