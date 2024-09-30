<?php

namespace  App\Controllers\Booking;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Helpers\Accounting;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;

use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Accounts\AccountCustomer;
use App\Models\RoomServices\RoomService;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RoomServiceController
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
    protected $accountCustomer;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
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

            case 'createRoomService':
                $this->createRoomService();
                break;
            case 'updateRoomService':
                $this->updateRoomService();
                break;
            case 'allInvLists':
                $this->allInvLists();
                break;
            case 'getSingleRoomService':
                $this->getSingleRoomService();
                break;

            case 'deleteRoomService':
                $this->deleteRoomService();
                break;
            case 'getSingleRoomServiceUpdateInfo':
                $this->getSingleRoomServiceUpdateInfo();
                break;


            case 'updateRoomServiceNew':
                $this->updateRoomServiceNew();
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

    //all functions here
    public function createRoomService()
    {
        $itemsArr = json_encode($this->params->foodItemsArr);
        $customerInfo = (object) $this->params->customerInfo;

        // dd(json_decode($itemsArr));
        // dd($customerInfo);

        if (count(json_decode($itemsArr)) < 1) {
            $this->success = false;
            $this->responseMessage = 'Items are not available !';
            return;
        }

        $roomServiceInv = RoomService::create([
            'inv_number' => 'INV-' . strtotime('now'),
            'inv_type' => 'room service',
            'customer_id' => $customerInfo->cust_id,
            'room_id' => $customerInfo->room_id,
            'net_amount' => $this->params->sumPrice,
            'paid' => 0,
            'due' => $this->params->sumPrice,
            'order_status' => 'processing',
            'created_by' => $this->user->id,
            'status' => 1
        ]);

        //later insert room service invoice items table also
        $items = [];
        foreach (json_decode($itemsArr) as $key => $item) {

            $items[] = array(
                'inv_id' => $roomServiceInv->id,
                'category_id' => $item->restaurant_category_id,
                'item_id' => $item->id,
                'unit_price' => $item->price,
                'qty' => $item->quantity,
                'price' => $item->totalPrice
            );
        }

        //now insert into cust_room_service_inv_items
        $service_inv_items = DB::table('cust_room_service_inv_items')->insert($items);
        if ($service_inv_items === false) {
            $this->success = false;
            $this->responseMessage = 'Something went wrong !';
            return;
        }

        //account customer
        $credited_note = "";
        $debited_note = "Bill generated for room service";
        //    dd($roomServiceInv);

        Accounting::accountCustomer(false, $roomServiceInv->customer_id, $roomServiceInv->id, $roomServiceInv->inv_number, $roomServiceInv->inv_type, $roomServiceInv->net_amount, 0, $credited_note, $debited_note, $this->user->id, false);



        $this->success = true;
        $this->responseMessage = 'Room service created';
        $this->outputData = $roomServiceInv;
    }

    //update room service
    public function updateRoomService()
    {
        $itemsArr = json_encode($this->params->foodItemsArr);
        $customerInfo = (object) $this->params->customerInfo;

        // dd(json_decode($itemsArr));
        // dd($customerInfo);

        if (count(json_decode($itemsArr)) < 1) {
            $this->success = false;
            $this->responseMessage = 'Items are not available !';
            return;
        }

        //find room service by id
        $roomServiceInv = RoomService::find($this->params->inv_id);

        $old_net_amount = $roomServiceInv->net_amount;
        //customer
        $customer = Customer::where('id', $roomServiceInv->customer_id)->where('status', 1)->first();

        $roomServiceInv->net_amount = $this->params->sumPrice;
        $roomServiceInv->due = ($this->params->sumPrice) - ($roomServiceInv->paid);
        $roomServiceInv->updated_by = $this->user->id;
        $roomServiceInv->save();



        // dd($roomServiceInv);

        //remove old items from room service items
        DB::table('cust_room_service_inv_items')->where('inv_id', $roomServiceInv->id)->delete();

        //later insert room service invoice items table also
        $items = [];
        foreach (json_decode($itemsArr) as $key => $item) {

            $items[] = array(
                'inv_id' => $roomServiceInv->id,
                'category_id' => $item->category_id,
                'item_id' => $item->item_id,
                'unit_price' => $item->unit_price,
                'qty' => $item->qty,
                'price' => $item->price
            );
        }

        //now insert into cust_room_service_inv_items
        $service_inv_items = DB::table('cust_room_service_inv_items')->insert($items);
        if ($service_inv_items === false) {
            $this->success = false;
            $this->responseMessage = 'Something went wrong !';
            return;
        }


        //customer account balance update
        $new_net_amount = $roomServiceInv->net_amount;
        if ($new_net_amount > $old_net_amount) {
            $differenceOfNetAmount = $new_net_amount - $old_net_amount;
        } else {
            $differenceOfNetAmount = $old_net_amount - $new_net_amount;
        }
        $balance = $customer->balance;

        if ($new_net_amount > $old_net_amount) {
            $balance -= $differenceOfNetAmount;
        } else {
            $balance += $differenceOfNetAmount;
        }

        $customer->balance = $balance;
        $customer->save();

        $accountCustomer = $this->accountCustomer;
        $accountCustomer->customer_id = $customer->id;
        $accountCustomer->invoice_id = $roomServiceInv->id;
        $accountCustomer->inv_type = $roomServiceInv->inv_type;
        $accountCustomer->reference = $roomServiceInv->inv_number;
        if ($new_net_amount > $old_net_amount) {
            $accountCustomer->debit = - ($differenceOfNetAmount);
            $accountCustomer->credit = 0;
            $accountCustomer->note = 'Amount has debited for customer room service';
        } else {
            $accountCustomer->debit = 0;
            $accountCustomer->credit = $differenceOfNetAmount;
            $accountCustomer->note = 'Amount has credited for customer room service';
        }
        $accountCustomer->balance = $customer->balance;
        $accountCustomer->created_by = $this->user->id;
        $accountCustomer->status = 1;
        $accountCustomer->save();


        $this->success = true;
        $this->responseMessage = 'Room service updated successfully';
        $this->outputData = $roomServiceInv;
    }

    //invoice lists
    public function allInvLists()
    {
        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;



        $query = DB::table('cust_room_service_inv')
            ->select('customers.first_name', 'tower_floor_rooms.room_no', 'customers.last_name', 'cust_room_service_inv.*')
            ->join('customers', 'cust_room_service_inv.customer_id', '=', 'customers.id')
            ->join('tower_floor_rooms', 'cust_room_service_inv.room_id', '=', 'tower_floor_rooms.id');

        if ($filter['status'] == 'all') {
            $query->where('cust_room_service_inv.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('cust_room_service_inv.status', '=', 0);
        }

        if ($filter['status'] == 'confirm' || $filter['status'] == 'processing' || $filter['status'] == 'pending') {
            $query->where('cust_room_service_inv.order_status', '=',  $filter['status']);
        }

        if (isset($filter['yearMonth'])) {
            $query->whereYear('cust_room_service_inv.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
                ->whereMonth('cust_room_service_inv.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('customers.first_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('customers.last_name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('tower_floor_rooms.room_no', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('cust_room_service_inv.inv_number', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        // if ($pageNo == 1 && $filter['paginate'] == true) {
        if ($pageNo == 1 && $filter['paginate'] == true) {
            $totalRow = $query->count();
        }


        $room_service_inv =  $query->orderBy('cust_room_service_inv.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();



        foreach ($room_service_inv as $service) {
            $service->selected_category = (object) ['value' => 1, 'label' => 'category name'];
        }

        $this->success = true;
        $this->responseMessage = 'Room service invoices are fetched !';
        $this->outputData = [
            $pageNo => $room_service_inv,
            'total' => $totalRow,
        ];
    }

    //get single room service info for Edit section
    public function getSingleRoomService()
    {
        $invoice_id = $this->params->inv_id;

        $inv_info = DB::table('cust_room_service_inv')
            ->join('customers', 'customers.id', '=', 'cust_room_service_inv.customer_id')
            ->join('tower_floor_rooms', 'cust_room_service_inv.room_id', '=', 'tower_floor_rooms.id')
            ->where('cust_room_service_inv.id', '=', $invoice_id)
            //    ->select('cust_room_service_inv.inv_number','cust_room_service_inv.inv_type','cust_room_service_inv.customer_id','customers.first_name','customers.last_name','customers.address','customers.mobile','tower_floor_rooms.room_no')
            ->select('cust_room_service_inv.*', 'customers.first_name', 'customers.last_name', 'customers.address', 'customers.mobile', 'tower_floor_rooms.room_no')
            ->first();

        $inv_items_arr = DB::table('cust_room_service_inv_items')
            ->join('restaurant_categories', 'restaurant_categories.id', '=', 'cust_room_service_inv_items.category_id')
            ->join('restaurant_foods', 'restaurant_foods.id', '=', 'cust_room_service_inv_items.item_id')
            ->where('cust_room_service_inv_items.inv_id', '=', $invoice_id)
            ->select('cust_room_service_inv_items.*', 'restaurant_categories.name as category_name', 'restaurant_foods.name as item_name')
            ->get();

        foreach ($inv_items_arr as $item) {
            $item->selectedCategory = (object) ['value' => $item->category_id, 'label' => $item->category_name];
            $item->selectedItem = (object) ['value' => $item->item_id, 'label' => $item->item_name];
        }

        $this->success = true;
        $this->responseMessage = 'Single Room Service Info has been fetched !';
        $this->outputData['inv_items_arr'] = $inv_items_arr;
        $this->outputData['inv_info'] = $inv_info;
    }





    public function getSingleRoomServiceUpdateInfo()
    {
        $invoice_id = $this->params->inv_id;

        $inv_info = DB::table('cust_room_service_inv')
            ->join('customers', 'customers.id', '=', 'cust_room_service_inv.customer_id')
            ->join('tower_floor_rooms', 'cust_room_service_inv.room_id', '=', 'tower_floor_rooms.id')
            ->where('cust_room_service_inv.id', '=', $invoice_id)
            //    ->select('cust_room_service_inv.inv_number','cust_room_service_inv.inv_type','cust_room_service_inv.customer_id','customers.first_name','customers.last_name','customers.address','customers.mobile','tower_floor_rooms.room_no')
            ->select('cust_room_service_inv.*', 'customers.first_name', 'customers.last_name', 'customers.address', 'customers.mobile', 'tower_floor_rooms.room_no')
            ->first();

        $inv_items_arr = DB::table('cust_room_service_inv_items')
            ->join('restaurant_foods', 'restaurant_foods.id', '=', 'cust_room_service_inv_items.item_id')
            ->where('cust_room_service_inv_items.inv_id', '=', $invoice_id)
            ->select(
                'restaurant_foods.id as id',
                'cust_room_service_inv_items.unit_price as price',
                'cust_room_service_inv_items.qty as quantity',
                'cust_room_service_inv_items.price as totalPrice',
                'restaurant_foods.name as name'
            )
            ->get();

        foreach ($inv_items_arr as $item) {
            $item->selectedItem = (object)['value' => $item->id, 'label' => $item->name];
        }

        $this->success = true;
        $this->responseMessage = 'Single Room Service Info has been fetched !';
        $this->outputData['inv_items_arr'] = $inv_items_arr;
        $this->outputData['inv_info'] = $inv_info;
    }



    public function deleteRoomService()
    {
        if (!isset($this->params->customerInvoiceId)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
        }
        $room_service = DB::table('cust_room_service_inv')
            ->where('id', $this->params->customerInvoiceId)
            ->update(['status' => 0]);
        if (!$room_service) {
            $this->success = false;
            $this->responseMessage = 'Voucher not found!';
            return;
        }

        $this->responseMessage = 'Delete successfully';
        $this->outputData = $room_service;
        $this->success = true;
    }



    //update room service
    public function updateRoomServiceNew()
    {
        $itemsArr = json_encode($this->params->foodItemsArr);
        $customerInfo = (object) $this->params->customerInfo;

        // dd(json_decode($itemsArr));
        // dd($customerInfo);

        if (count(json_decode($itemsArr)) < 1) {
            $this->success = false;
            $this->responseMessage = 'Items are not available !';
            return;
        }

        //find room service by id
        $roomServiceInv = RoomService::find($this->params->inv_id);

        $old_net_amount = $roomServiceInv->net_amount;
        //customer
        $customer = Customer::where('id', $roomServiceInv->customer_id)->where('status', 1)->first();

        $roomServiceInv->net_amount = $this->params->grandTotal;
        $roomServiceInv->due = ($this->params->grandTotal) - ($roomServiceInv->paid);
        $roomServiceInv->updated_by = $this->user->id;
        $roomServiceInv->save();



        // dd($roomServiceInv);

        //remove old items from room service items
        DB::table('cust_room_service_inv_items')->where('inv_id', $roomServiceInv->id)->delete();

        //later insert room service invoice items table also
        $items = [];
        foreach (json_decode($itemsArr) as $key => $item) {

            $items[] = array(
                'inv_id' => $roomServiceInv->id,
                'category_id' => $item->restaurant_category_id,
                'item_id' => intval($item->id),
                'unit_price' => intval($item->price),
                'qty' => intval($item->quantity),
                'price' => intval($item->totalPrice)


                // 'inv_id' => $roomServiceInv->id,
                //     'category_id' => $item->category_id,
                //     'item_id' => $item->item_id,
                //     'unit_price' => $item->unit_price,
                //     'qty' => $item->qty,
                //     'price' => $item->price
            );
        }

        //now insert into cust_room_service_inv_items
        $service_inv_items = DB::table('cust_room_service_inv_items')->insert($items);
        if ($service_inv_items === false) {
            $this->success = false;
            $this->responseMessage = 'Something went wrong !';
            return;
        }



        //customer account balance update
        $new_net_amount = $roomServiceInv->net_amount;

        if ($new_net_amount > $old_net_amount) {
            $differenceOfNetAmount = $new_net_amount - $old_net_amount;
        } else {
            $differenceOfNetAmount = $old_net_amount - $new_net_amount;
        }
        $balance = $customer->balance;

        if ($new_net_amount > $old_net_amount) {
            $balance -= $differenceOfNetAmount;
        } else {
            $balance += $differenceOfNetAmount;
        }

        $customer->balance = $balance;
        $customer->save();

        $accountCustomer = $this->accountCustomer;
        $accountCustomer->customer_id = $customer->id;
        $accountCustomer->invoice_id = $roomServiceInv->id;
        $accountCustomer->inv_type = $roomServiceInv->inv_type;
        $accountCustomer->reference = $roomServiceInv->inv_number;

        if ($new_net_amount > $old_net_amount) {
            $accountCustomer->debit = - ($differenceOfNetAmount);
            $accountCustomer->credit = 0;
            $accountCustomer->note = 'Amount has debited for customer room service';
        } else {
            $accountCustomer->debit = 0;
            $accountCustomer->credit = $differenceOfNetAmount;
            $accountCustomer->note = 'Amount has credited for customer room service';
        }

        $accountCustomer->balance = $customer->balance;
        $accountCustomer->created_by = $this->user->id;
        $accountCustomer->status = 1;
        $accountCustomer->save();


        $this->success = true;
        $this->responseMessage = 'Room service updated successfully';
        $this->outputData = $roomServiceInv;
    }
}
