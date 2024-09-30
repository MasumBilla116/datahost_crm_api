<?php

namespace  App\Controllers\Transport;

use DateTime;
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

class VehicleController
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


            case 'createVehicle':
                $this->createVehicle($request);
                break;
            case 'getAllVehicle':
                $this->getAllVehicle($request);
                break;
            case 'getAllVehicleBydate':
                $this->getAllVehicleBydate($request);
                break;

                // getAllVehicleBydate
            case 'getAllVehicleList':
                $this->getAllVehicleList($request);
                break;
            case 'getVehicleInfo':
                $this->getVehicleInfo();
                break;
            case 'deleteVehicle':
                $this->deletevehicle();
                break;
            case 'updateVehicle':
                $this->updateVehicle($request);
                break;
            case 'getAllDrivers':
                $this->getAllDrivers($request);
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


    public function createVehicle($request)
    {
        $this->validator->validate($request, [
            "model" => v::notEmpty(),
            "reg_no" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $vehicle = $this->vehicle
            ->create([
                "model" => $this->params->model,
                "brand" =>  $this->params->brand,
                "reg_no" =>  $this->params->reg_no,
                "vehicle_type" => $this->params->vehicle_type,
                "seat_capacity" =>  $this->params->seat_capacity,
                "fuel_type" => $this->params->fuel_type,
                // "employee_id" => $this->params->driverId,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);

        $this->responseMessage = "Vehicles has been created successfully!";
        $this->outputData = $vehicle;
        $this->success = true;
    }

    public function getAllVehicle()
    {
        $vehicles = $this->vehicle->where('status', 1)->orderBy('id', 'desc')->get();

        $this->responseMessage = "vehicles list fetched successfully";
        $this->outputData = $vehicles;
        $this->success = true;
    }

    public function getAllVehicleBydate2()
    {
        // Check if date, time, and end time are provided
        if (isset($this->params->date) && isset($this->params->time) && isset($this->params->endtime)) {
            $dateTime = new DateTime($this->params->date);
            $formattedDate = $dateTime->format('Y-m-d');

            $dateTime = new DateTime($this->params->time);
            $formattedTime = $dateTime->format('h:i:s A');

            $endTime = new DateTime($this->params->endtime);
            $formattedEndTime = $endTime->format('h:i:s A');

            $query = DB::table('transport_vehicles')
                ->leftJoin('vehicle_booking', 'transport_vehicles.id', '=', 'vehicle_booking.vehicle_id')
                ->select('transport_vehicles.*', 'vehicle_booking.*')
                ->whereDate('vehicle_booking.booking_date', '=', $formattedDate)
                ->where(function ($query) use ($formattedTime, $formattedEndTime) {
                    $query->whereBetween('vehicle_booking.booking_time', [$formattedTime, $formattedEndTime])
                        ->orWhereBetween('vehicle_booking.booking_end_time', [$formattedTime, $formattedEndTime])
                        ->orWhere(function ($query) use ($formattedTime, $formattedEndTime) {
                            $query->where('vehicle_booking.booking_time', '<', $formattedTime)
                                ->where('vehicle_booking.booking_end_time', '>', $formattedEndTime);
                        });
                });

            $all_transport_vehicle = $query->orderBy('transport_vehicles.id', 'desc')->get();

            if ($all_transport_vehicle->isEmpty()) {
                $this->success = false;
                $this->responseMessage = "No data found!";
                return;
            }

            $this->responseMessage = "Vehicles list fetched successfully";
            $this->outputData = $all_transport_vehicle;
            $this->success = true;
        } else {
            // If date, time, and end time are not provided, retrieve all transport vehicles where status is 1
            $all_transport_vehicle = DB::table('transport_vehicles')
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();

            $this->responseMessage = "All vehicles list fetched successfully";
            $this->outputData = $all_transport_vehicle;
            $this->success = true;
        }
    }



    public function getAllVehicleBydate()
    {
        try {

            // Check if date, time, and end time are provided
            if (isset($this->params->date) && isset($this->params->time) && isset($this->params->endtime)) {
                $dateTime = new DateTime($this->params->date);
                $formattedDate = $dateTime->format('Y-m-d');

                $dateTime = new DateTime($this->params->time);
                $formattedTime = $dateTime->format('h:i:s A');

                $endTime = new DateTime($this->params->endtime);
                $formattedEndTime = $endTime->format('h:i:s A');

                $assignedVehicleIds = DB::table('vehicle_booking')
                    ->whereDate('booking_date', '=', $formattedDate)
                    ->where(function ($query) use ($formattedTime, $formattedEndTime) {
                        $query->whereBetween('booking_time', [$formattedTime, $formattedEndTime])
                            ->orWhereBetween('booking_end_time', [$formattedTime, $formattedEndTime])
                            ->orWhere(function ($query) use ($formattedTime, $formattedEndTime) {
                                $query->where('booking_time', '<', $formattedTime)
                                    ->where('booking_end_time', '>', $formattedEndTime);
                            });
                    })
                    ->pluck('vehicle_id')
                    ->toArray();

                $query = DB::table('transport_vehicles')
                    ->whereNotIn('id', $assignedVehicleIds)
                    ->where('status', 1)
                    ->orderBy('id', 'desc');

                $all_transport_vehicle = $query->get();

                if ($all_transport_vehicle->isEmpty()) {
                    $this->success = false;
                    $this->responseMessage = "No available vehicles found!";
                    return;
                }

                $this->responseMessage = "Available vehicles list fetched successfully";
                $this->outputData = $all_transport_vehicle;
                $this->success = true;
            } else {
                // If any required parameter is not provided, return an error message
                $this->success = false;
                $this->responseMessage = "Missing required parameters!";
                $this->outputData = [];
                $this->success = false;
            }
        } catch (\Exception $th) {
            $this->responseMessage = "Available vehicles list fetched failed";
            $this->outputData = [];
            $this->success = false;
        }
    }






    public function getAllVehicleList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;


        $query = DB::table('transport_vehicles');

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('transport_vehicles.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('transport_vehicles.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('transport_vehicles.model', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.brand', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.reg_no', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.vehicle_type', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_transport_vehicle =  $query->orderBy('transport_vehicles.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "vehicles list fetched successfully";
        $this->outputData = [
            $pageNo => $all_transport_vehicle,
            'total' => $totalRow,
        ];
        $this->success = true;
    }


    public function getVehicleInfo()
    {
        if (!isset($this->params->vehicle_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $vehicle = $this->vehicle->find($this->params->vehicle_id);

        if ($vehicle->status == 0) {
            $this->success = false;
            $this->responseMessage = "Vehicle missing!";
            return;
        }

        if (!$vehicle) {
            $this->success = false;
            $this->responseMessage = "Vehicle not found!";
            return;
        }

        $this->responseMessage = "vehicle info fetched successfully";
        $this->outputData = $vehicle;
        $this->success = true;
    }

    public function deleteVehicle()
    {
        if (!isset($this->params->vehicle_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'vehicle_id' missing";
            return;
        }

        $vehicle = $this->vehicle->find($this->params->vehicle_id);

        if (!$vehicle) {
            $this->success = false;
            $this->responseMessage = "Vehicle not found!";
            return;
        }

        if ($vehicle->status == 0) {
            // If status is already 0, delete the vehicle entirely
            $deleted = $vehicle->delete();

            if ($deleted) {
                $this->responseMessage = "Vehicle deleted successfully";
                $this->outputData = $this->params->vehicle_id;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting vehicle";
                $this->success = false;
            }
        } else {
            // If status is not 0, update status to 0
            $deletedVehicle = $vehicle->update([
                "status" => 0,
            ]);

            $this->responseMessage = "Vehicle status updated to deleted";
            $this->outputData = $deletedVehicle;
            $this->success = true;
        }
    }


    public function updateVehicle(Request $request)
    {

        $vehicle = $this->vehicle->find($this->params->vehicle_id);

        //  check validation      
        $this->validator->validate($request, [
            "model" => v::notEmpty(),
            "reg_no" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $vehicle->model = $this->params->model;
        $vehicle->brand = $this->params->brand;
        $vehicle->reg_no = $this->params->reg_no;
        $vehicle->vehicle_type = $this->params->vehicle_type;
        $vehicle->seat_capacity = $this->params->seat_capacity;
        $vehicle->fuel_type = $this->params->fuel_type;
        $vehicle->updated_by = $this->user->id;
        $vehicle->save();

        $this->responseMessage = "vehicle updated Successfully!";
        $this->outputData = $vehicle;
        $this->success = true;
    }



    public function getAllDrivers()
    {
        $drivers = DB::table("drivers")
            ->join('employees', 'employees.id', '=', 'drivers.employee_id')
            ->join('designations', 'designations.id', '=', 'employees.designation_id')
            ->join('departments', 'departments.id', '=', 'designations.department_id')
            ->select(
                'drivers.employee_id as id',
                'employees.name as name',
                'designations.name as designation_name',
                'departments.name as department_name'

            )
            ->where('employees.status', 1)
            ->orderBy('drivers.id', 'desc')
            ->get();

        $this->responseMessage = "drivers list fetched successfully";
        $this->outputData = $drivers;
        $this->success = true;
    }
}
