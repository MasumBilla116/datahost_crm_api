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

class DriverController
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


            case 'createDriver':
                $this->createDriver($request);
                break;

            case 'getAllDrivers':
                $this->getAllDrivers();
                break;

            case 'getAllDriverList':
                $this->getAllDriverList();
                break;
            case 'getDriverInfo':
                $this->getDriverInfo();
                break;

            case 'updateDriver':
                $this->updateDriver($request);
                break;
            case 'deleteDriver':
                $this->deleteDriver();
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


    public function createDriver($request)
    {
        $this->validator->validate($request, [
            "driverId" => v::notEmpty(),
            "vehicleId" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $driver = $this->driver
            ->create([

                "employee_id" => $this->params->driverId,
                "vehicle_id" => $this->params->vehicleId,
                "created_by" => $this->user->id,
                "status" => 1,
            ]);



        $this->responseMessage = "Vehicles has been created successfully!";
        $this->outputData = $driver;
        $this->success = true;
    }




    public function getAllDrivers()
    {
        $driver = DB::table("drivers")
            ->join('employees', 'employees.id', '=', 'drivers.employee_id')
            ->join('transport_vehicles', 'transport_vehicles.id', '=', 'drivers.vehicle_id')
            ->select(
                'drivers.*',
                'employees.name as employee_name',
                'transport_vehicles.model as model',
                'transport_vehicles.reg_no as reg_no',
                'transport_vehicles.seat_capacity as seat_capacity',
                'transport_vehicles.vehicle_type as vehicle_type',

            )
            ->where('drivers.status', 1)
            ->orderBy('drivers.id', 'desc')
            ->get();

        $this->responseMessage = "vehicles list fetched successfully";
        $this->outputData = $driver;
        $this->success = true;
    }


    public function getAllDriverList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = DB::table("drivers")
            ->join('employees', 'employees.id', '=', 'drivers.employee_id')
            ->join('transport_vehicles', 'transport_vehicles.id', '=', 'drivers.vehicle_id')
            ->select(
                'drivers.*',
                'employees.name as employee_name',
                'transport_vehicles.model as model',
                'transport_vehicles.reg_no as reg_no',
                'transport_vehicles.seat_capacity as seat_capacity',
                'transport_vehicles.vehicle_type as vehicle_type',

            );
        // ->where('drivers.status', 1)
        // ->orderBy('drivers.id', 'desc')
        // ->get();


        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('drivers.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('drivers.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('employees.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.model', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.reg_no', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('transport_vehicles.vehicle_type', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_drivers =  $query->orderBy('employees.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "vehicles list fetched successfully";
        $this->outputData = [
            $pageNo => $all_drivers,
            'total' => $totalRow,
        ];
        $this->success = true;
    }





    public function getDriverInfo()
    {
        if (!isset($this->params->driver_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $driver = $this->driver
            ->join('employees', 'employees.id', '=', 'drivers.employee_id')
            ->join('transport_vehicles', 'transport_vehicles.id', '=', 'drivers.vehicle_id')
            ->select(
                'drivers.*',
                'employees.name as employee_name',
                'employees.gender as gender',
                'employees.salary_type as salary_type',
                'employees.salary_amount as salary_amount',
                'employees.mobile as mobile',
                'employees.email as email',
                'transport_vehicles.model as model',
                'transport_vehicles.reg_no as reg_no',
                'transport_vehicles.seat_capacity as seat_capacity',
                'transport_vehicles.vehicle_type as vehicle_type',

            )
            ->find($this->params->driver_id);

        if ($driver->status == 0) {
            $this->success = false;
            $this->responseMessage = "driver missing!";
            return;
        }

        if (!$driver) {
            $this->success = false;
            $this->responseMessage = "driver not found!";
            return;
        }

        $this->responseMessage = "driver info fetched successfully";
        $this->outputData = $driver;
        $this->success = true;
    }




    public function updateDriver(Request $request)
    {


        //  check validation      
        $this->validator->validate($request, [
            "driverId" => v::notEmpty(),
            "vehicleId" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $driver = $this->driver->where(['id' => $this->params->driver_id, 'status' => 1])
            ->update([
                'employee_id' => $this->params->driverId,
                'vehicle_id' => $this->params->vehicleId,
                'updated_by' => $this->user->id
            ]);

        $this->responseMessage = "Booking note has been updated successfully !";
        $this->outputData = $driver;
        $this->success = true;
    }



    public function deleteDriver()
    {
        if (!isset($this->params->driver_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'driver_id' missing";
            return;
        }
    
        $driver = $this->driver->find($this->params->driver_id);
    
        if (!$driver) {
            $this->success = false;
            $this->responseMessage = "Driver not found!";
            return;
        }
    
        if ($driver->status == 0) {
            $deleted = $driver->delete();
    
            if ($deleted) {
                $this->responseMessage = "Driver deleted successfully";
                $this->outputData = $this->params->driver_id;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting driver";
                $this->success = false;
            }
        } else {
            $deletedDriver = $driver->update([
                "status" => 0,
            ]);
    
            $this->responseMessage = "Driver status updated to deleted";
            $this->outputData = $deletedDriver;
            $this->success = true;
        }
    }
    
}
