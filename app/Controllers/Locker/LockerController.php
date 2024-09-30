<?php

namespace  App\Controllers\Locker;

use App\Auth\Auth;
use App\Models\Locker\Locker;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class LockerController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $lockers;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->lockers = new LOCKER();
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
            case 'createLocker':
                $this->createLocker($request, $response);
                break;
            case 'getAllLocker':
                $this->getAllLocker($request, $response);
                break;

            case 'getAllLockerList':
                $this->getAllLockerList($request, $response);
                break;

                // getAllLockerList
            case 'getLockerByID':
                $this->getLockerByID($request, $response);
                break;
            case 'getLockerByIDs':
                $this->getLockerByIDs($request, $response);
                break;
            case 'updateLockerByID':
                $this->updateLockerByID($request, $response);
                break;
            case 'deleteLocker':
                $this->deleteLocker($request, $response);
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


    public function createLocker(Request $request, Response $response)
    {
        //varaible declaration
        $lockers = $this->params->lockers;
        $type = $this->params->type;
        $size = $this->params->size;
        $description = $this->params->description ? $this->params->description : 'sample locker';
        $status = $this->params->status === null ? 1 : $this->params->status;

        //validation
        $this->validator->validate($request, [
            "type" => v::notEmpty(),
            "size" => v::notEmpty(),
        ]);
        // v::intVal()->notEmpty()->validate($this->params->status);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if (COUNT($lockers)) {

            foreach ($lockers as $locker) {

                $lockers = $this->lockers->create([
                    "prefix" => $locker[0],
                    "type" => $type,
                    "serial" =>  $locker,
                    "availability" => 'available',
                    "size" => $size,
                    "description" => $description,
                    "created_by" => $this->user->id,
                    "status" => $status,
                ]);
            }

            $this->responseMessage = "lockers created Successfully!";
            $this->outputData = $lockers;
            $this->success = true;
        } else {

            $this->success = false;
            $this->responseMessage = 'no locker found to create';
            return;
        }
    }

    public function getAllLocker()
    {

        $lockers = DB::table('lockers')
            ->select('lockers.*')
            ->orderBy('id', 'desc')
            ->get();

        $this->responseMessage = "All lockers fetched Successfully!";
        $this->outputData = $lockers;
        $this->success = true;
    }


    public function getAllLockerList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = DB::table('lockers');
        // ->select('lockers.*')
        // ->orderBy('id','desc')
        // ->get();


        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('lockers.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('lockers.status', '=', 0);
        }

        if ($filter['status'] == 'unavailable' || $filter['status'] == 'available') {
            $query->where('lockers.availability', '=',  $filter['status']);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('lockers.type', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('lockers.serial', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        if ($pageNo == 1 && $filter['paginate'] == true) {
            $totalRow = $query->count();
        }


        $locker =  $query->orderBy('lockers.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();

        $this->responseMessage = "All lockers fetched Successfully!";
        $this->outputData = [
            $pageNo => $locker,
            'total' => $totalRow,
        ];
        $this->success = true;
    }




    public function getLockerByID()
    {
        //varaible declaration
        $id = $this->params->id;

        $lockers = $this->lockers
            ->select('lockers.*')
            ->where(["lockers.id" => $id])
            ->get();

        if (!COUNT($lockers)) {
            $this->success = false;
            $this->responseMessage = "Locker not found!";
            return;
        }

        $this->responseMessage = "requested locker fetched Successfully!";
        $this->outputData = $lockers;
        $this->success = true;
    }

    public function getLockerByIDs()
    {
        //varaible declaration
        $ids = $this->params->id;

        if (COUNT($ids)) {

            $allLockers = [];

            foreach ($ids as $id) {

                $lockers = $this->lockers
                    ->select('lockers.*')
                    ->where(["lockers.id" => $id])
                    ->get();

                $allLockers[] = $lockers;
            }

            if (!COUNT($allLockers)) {
                $this->success = false;
                $this->responseMessage = "Locker not found!";
                return;
            }

            $this->responseMessage = "requested locker fetched Successfully!";
            $this->outputData = $allLockers;
            $this->success = true;
        } else {
            $this->success = false;
            $this->responseMessage = "id not found!";
            return;
        }
    }


    public function updateLockerByID(Request $request, Response $response)
    {
        //varaible declaration
        $id = $this->params->id;

        $prefix = $this->params->prefix;
        $serial = $this->params->serial;
        $length = $this->params->length;
        $type = $this->params->type;
        $size = $this->params->size;
        $availibility = $this->params->availibility;
        $description = $this->params->description ? $this->params->description : 'sample locker';
        $status = $this->params->status === null ? 1 : $this->params->status;

        // validation
        $this->validator->validate($request, [
            "type" => v::notEmpty(),
            "prefix" => v::notEmpty(),
            "length" => v::notEmpty(),
            "size" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $TargetLocker = $this->lockers
            ->select('lockers.*')
            ->where(["lockers.id" => $id])
            ->get();

        if (COUNT($TargetLocker)) {

            //All serial
            $locker = $this->lockers
                ->select('lockers.serial', 'lockers.id')
                ->get();

            if (COUNT($locker)) {

                foreach ($locker as $lock) {
                    if ($lock->serial === $serial && $lock->id != $id) {

                        $this->success = false;
                        $this->responseMessage = "Serial already exists!";
                        return;
                    }
                }
            }

            //Update locker
            $locker = $this->lockers
                ->where(["lockers.id" => $id])
                ->update([
                    "serial" => $serial,
                    "type" => $type,
                    "size" => $size,
                    "availability" => $availibility,
                    "description" => $description,
                    "status" => $status,
                ]);


            $this->responseMessage = "requested locker updated successfully!";
            $this->outputData = $locker;
            $this->success = true;
        } else {

            $this->success = false;
            $this->responseMessage = "Locker not found!";
            return;
        }
    }

    public function deleteLocker()
    {
        if (!isset($this->params->id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'id' missing";
            return;
        }
        $locker = $this->lockers->find($this->params->id);
        if (!$locker) {
            $this->success = false;
            $this->responseMessage = "Locker not found!";
            return;
        }
        if ($locker->status == 0) {
            $deleted = $locker->delete();
    
            if ($deleted) {
                $lockers = $this->lockers->select('lockers.*')->get();
    
                $this->responseMessage = "Locker deleted successfully";
                $this->outputData = $lockers;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting locker";
                $this->success = false;
            }
        } else {
            $deletedLocker = $locker->update([
                "status" => 0,
            ]);
    
            $this->responseMessage = "Locker status updated to deleted";
            $this->outputData = $deletedLocker;
            $this->success = true;
        }
    }
    
}
