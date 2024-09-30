<?php

namespace  App\Controllers\Locker;

use App\Auth\Auth;
use App\Models\Locker\Locker;
use App\Models\Locker\LockerEntries;
use App\Models\Locker\LockerLuggageItems;
use App\Models\Locker\LockerEntriesInfo;
use App\Models\Locker\LockerLuggageInfo;
use Illuminate\Database\Capsule\Manager as DB;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class LockerEntriesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->lockers = new LOCKER();
        $this->lockerEntries = new lockerEntries();
        $this->LockerEntriesInfo = new LockerEntriesInfo();
        $this->LockerLuggageInfo = new LockerLuggageInfo();
        $this->LockerLuggageItems = new LockerLuggageItems();
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
            case 'lockerEntry':
                $this->lockerEntry($request, $response);
                break;
            case 'lockerEntryInfo':
                $this->lockerEntryInfo();
                break;
            case 'getAllLockerEntry':
                $this->getAllLockerEntry($request, $response);
                break;

            case 'getAllLockerEntryList':
                $this->getAllLockerEntryList($request, $response);
                break;
            case 'lockerEntryInfoByID':
                $this->lockerEntryInfoByID();
                break;
            case 'lockerUpdateByID':
                $this->lockerUpdateByID();
                break;
            case 'lockerDeleteByID':
                $this->lockerDeleteByID();
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

    public function lockerEntry(Request $request, Response $response)
    {

        //varaible declaration
        $guest_id = $this->params->guest_id;
        $lockers_id = $this->params->locker_id;
        $pickup_date = $this->params->pickup_date;
        $pickup_time = $this->params->pickup_time;
        $token = $this->params->token;
        $remarks = $this->params->remarks === null ? "remarks"  : $this->params->remarks;

        $luggage = $this->params->luggage;

        if (count($luggage)) {

            $allLuggages = [];

            foreach ($luggage as $key => $value) {

                //Luggage items
                $LockerLuggageItems = $this->LockerLuggageItems
                    ->create([
                        "item_name" => $value[item_name],
                        "size" =>  $value[size],
                        "type" =>  $value[type],
                        "description" =>  $value[description],
                        "created_by" => $this->user->id,
                        "updated_by" =>  $this->user->id,
                    ]);

                $allLuggages[] = $LockerLuggageItems->id;
            }
        } else {

            $this->success = false;
            $this->responseMessage = 'Luggage items not found!';
            return;
        }

        // Locker entry
        $lockerEntries = $this->lockerEntries;
        $lockerEntries->guest_id = $guest_id;
        $lockerEntries->pickup_date = date('Y-m-d', strtotime($pickup_date . ' +1 day'));
        // $lockerEntries->time =  date("h.i A",strtotime($pickup_time.'+6 hour'));
        $lockerEntries->time =  date('Y-m-d h:i:s', strtotime($pickup_time . '+6 hour'));
        // $lockerEntries->time =  $pickup_time;
        $lockerEntries->token = $token;
        $lockerEntries->remarks = $remarks;
        $lockerEntries->created_by =  $this->user->id;
        $lockerEntries->updated_by = $this->user->id;
        $lockerEntries->save();

        if (COUNT($lockers_id)) {

            foreach ($lockers_id as $key => $value) {

                //Locker availability
                $locker = $this->lockers
                    ->where(["lockers.id" => $value])
                    ->update([
                        "availability" => 'unavailable',
                    ]);
            }
        }

        //Pivot table
        $lockerEntries->Lockerss()->attach($lockers_id);
        $lockerEntries->LockerLuggageItemsss()->attach($allLuggages);

        $this->responseMessage = "locker entry successfully!";
        $this->outputData = $lockerEntries;
        $this->success = true;
    }

    public function getAllLockerEntry()
    {

        $getLockerEntries = DB::select(DB::raw(
            "SELECT le.id, le.status,le.pickup_date,le.time, le.remarks, le.token, le.created_at,le.updated_at, 
                (SELECT c.first_name FROM customers c WHERE le.guest_id = c.id) as first_name, 
                (SELECT c.last_name FROM customers c WHERE le.guest_id = c.id) as last_name,
                (SELECT c.title FROM customers c WHERE le.guest_id = c.id) as title,
                (SELECT c.mobile FROM customers c WHERE le.guest_id = c.id) as mobile,
                (SELECT COUNT(`lei`.locker_entries_id) FROM managebeds.locker_luggage_info lei WHERE lei.locker_entries_id = le.id AND lei.status = 1) as total_item
                from locker_entries le WHERE le.status = 1 ORDER BY id DESC"
        ));

        if (!COUNT($getLockerEntries)) {

            $this->success = false;
            $this->responseMessage = 'locker not found!';
            return;
        }

        $this->responseMessage = "All lockers fetched Successfully!";
        $this->outputData = $getLockerEntries;
        $this->success = true;
    }


    public function getAllLockerEntryList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;



        $query = DB::table('locker_entries')
            ->select('locker_entries.id', 'locker_entries.status', 'locker_entries.pickup_date', 'locker_entries.time', 'locker_entries.remarks', 'locker_entries.token', 'locker_entries.created_at', 'locker_entries.updated_at', 'customers.first_name', 'customers.last_name', 'customers.title', 'customers.mobile')
            ->selectRaw('(SELECT COUNT(`locker_luggage_info`.`locker_entries_id`) FROM `locker_luggage_info` WHERE `locker_luggage_info`.`locker_entries_id` = `locker_entries`.`id` AND `locker_luggage_info`.`status` = 1) as total_item')
            ->join('customers', 'locker_entries.guest_id', '=', 'customers.id');
        // ->where('locker_entries.status', 1)
        // ->orderBy('locker_entries.id', 'DESC')
        // ->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('locker_entries.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('locker_entries.status', '=', 0);
        }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('locker_entries.token', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.first_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.mobile', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_locker =  $query->orderBy('locker_entries.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "All lockers fetched Successfully!";
        $this->outputData = [
            $pageNo => $all_locker,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function lockerEntryInfoByID()
    {
        //varaible declaration
        $id = $this->params->id;

        // $lockerEntryInfo = $this->lockerEntries->with('customers')->find($id);
        $lockerEntryInfo = $this->lockerEntries->find($id);

        // if (!COUNT($lockerEntryInfo)) {

        //     $this->success = false;
        //     $this->responseMessage = 'locker not found!';
        //     return;
        // }

        $LockerLuggageInfo = $this->LockerLuggageInfo
            ->select('locker_luggage_info.locker_luggage_items_id')
            ->where(["locker_luggage_info.locker_entries_id" => $lockerEntryInfo->id])
            ->get();

        $luggages = [];
        if (COUNT($LockerLuggageInfo)) {

            foreach ($LockerLuggageInfo as $key => $value) {
                $LockerLuggageItems = $this->LockerLuggageItems
                    ->select('locker_luggage_items.*')
                    ->where(["locker_luggage_items.id" => $value->locker_luggage_items_id])
                    ->get();

                $luggages[] = $LockerLuggageItems[0];
            }
        }

        $LockerEntriesInfo = $this->LockerEntriesInfo
            ->select('locker_entries_info.locker_id')
            ->where(["locker_entries_info.locker_entries_id" => $lockerEntryInfo->id])
            ->where(["status" => 1])
            ->get();

        $lockers = [];
        if (COUNT($LockerEntriesInfo)) {

            foreach ($LockerEntriesInfo as $key => $value) {
                $locker = $this->lockers
                    ->select('lockers.*')
                    ->where(["lockers.id" => $value->locker_id])
                    ->get();

                $lockers[] = $locker[0];
            }
        }

        $this->responseMessage = "Locker entry info fetched Successfully!";
        $this->outputData = $lockerEntryInfo;
        $this->outputData['lockers'] = $lockers;
        $this->outputData['luggages'] = $luggages;
        $this->success = true;
    }

    public function lockerUpdateByID()
    {

        //varaible declaration
        // $token = $this->params->token;
        $guest_id = $this->params->guest_id;
        $lockers_id = $this->params->locker_id;
        $pickup_date = $this->params->pickup_date;
        $pickup_time = $this->params->pickup_time;
        $remarks = $this->params->remarks === null ? "remarks"  : $this->params->remarks;

        $locker_id = $this->params->locker_id;

        $luggage = $this->params->luggage;
        $newLuggage = $this->params->newLuggage;
        $deletedLuggage = $this->params->deletedLuggage;

        // Locker entry
        $lockerEntries = $this->lockerEntries
            ->where(["locker_entries.id" => $this->params->id])
            ->update([
                "guest_id" => $guest_id,
                "pickup_date" => date('Y-m-d', strtotime($pickup_date . ' +1 day')),
                "time" => date('Y-m-d h:i:s', strtotime($pickup_time . '+6 hour')),
                "remarks" => $remarks,
                "created_by" => $this->user->id,
                "updated_by" => $this->user->id
            ]);

        //update luggage
        if (COUNT($luggage)) {

            foreach ($luggage as $key => $value) {

                if ($value[id] !== null) {

                    //locker_luggage_items
                    $LockerLuggageItems = $this->LockerLuggageItems
                        ->where(["locker_luggage_items.id" => $value[id]])
                        ->update([
                            "item_name" => $value[item_name],
                            "size" =>  $value[size],
                            "type" =>  $value[type],
                            "description" =>  $value[description],
                            "created_by" => $this->user->id,
                            "updated_by" =>  $this->user->id,
                        ]);
                }
            }
        }

        //new luggage
        if (COUNT($newLuggage)) {

            $allLuggages = [];

            foreach ($newLuggage as $key => $value) {

                //Luggage items
                $LockerLuggageItems = $this->LockerLuggageItems
                    ->create([
                        "item_name" => $value[item_name],
                        "size" =>  $value[size],
                        "type" =>  $value[type],
                        "description" =>  $value[description],
                        "created_by" => $this->user->id,
                        "updated_by" =>  $this->user->id,
                    ]);

                $allLuggages[] = $LockerLuggageItems->id;
            }

            if (COUNT($allLuggages)) {

                foreach ($allLuggages as $value) {
                    $LockerLuggageInfo = $this->LockerLuggageInfo
                        ->create([
                            "locker_entries_id" => $this->params->id,
                            "locker_luggage_items_id" =>  $value,
                        ]);
                }
            }
        }

        //delete luggage
        if (COUNT($deletedLuggage)) {

            foreach ($deletedLuggage as $key => $value) {

                //locker_luggage_items
                $LockerLuggageItems = $this->LockerLuggageItems
                    ->where(["locker_luggage_items.id" => $value[id]])
                    ->update([
                        "status" => 0,
                        "updated_by" => $this->user->id
                    ]);

                //locker_luggage_info
                $LockerLuggageInfo = $this->LockerLuggageInfo
                    ->where(["locker_luggage_info.locker_luggage_items_id" => $value[id]])
                    ->update([
                        "status" => 0,
                    ]);
            }
        }


        //Pivot table:: locker entries info
        $LockerEntriesInfo = $this->LockerEntriesInfo
            ->where(["locker_entries_id" => $this->params->id])
            ->update([
                "status" => 0,
            ]);

        if (COUNT($locker_id)) {

            foreach ($locker_id as $value) {
                $test = $this->LockerEntriesInfo
                    ->create([
                        "locker_entries_id" => $this->params->id,
                        "locker_id" =>  $value,
                    ]);
            }
        }

        $this->responseMessage = "requested locker updated successfully!";
        $this->outputData = $lockerEntries;
        $this->success = true;
    }

    public function lockerDeleteByID()
    {
        // Check if id parameter is set
        if (!isset($this->params->id)) {
            $this->success = false;
            $this->responseMessage = "Parameter 'id' missing";
            return;
        }
        $lockerEntry = $this->lockerEntries->find($this->params->id);
        if (!$lockerEntry) {
            $this->success = false;
            $this->responseMessage = "Locker entry not found!";
            return;
        }
        if ($lockerEntry->status == 0) {
            $deleted = $lockerEntry->delete();
    
            if ($deleted) {
                $this->responseMessage = "Locker entry deleted successfully";
                $this->outputData = null;
                $this->success = true;
            } else {
                $this->responseMessage = "Error deleting locker entry";
                $this->success = false;
            }
        } else {
            $deletedLockerEntry = $lockerEntry->update([
                "status" => 0,
            ]);
    
            $this->responseMessage = "Locker entry status updated to deleted";
            $this->outputData = $deletedLockerEntry;
            $this->success = true;
        }
    }
    
}
