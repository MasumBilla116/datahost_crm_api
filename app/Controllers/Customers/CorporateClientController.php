<?php

namespace  App\Controllers\Customers;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Models\Customers\Client;
use Illuminate\Database\Capsule\Manager as DB;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;

use App\Models\Customers\CustomerBooking;
use App\Models\Customers\CustomerBookingGrp;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CorporateClientController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $customer;
    protected $client;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->customer = new Customer();
        $this->client = new Client();
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

            case 'createClient':
                $this->createClient($request, $response);
                break;
            case 'getAllClients':
                $this->getAllClients($request, $response);
                break;

            case 'getAllClientList':
                $this->getAllClientList($request, $response);
                break;
            case 'getAllCorporateClientsByID':
                $this->getAllCorporateClientsByID($request, $response);
                break;
            case 'getClientByID':
                $this->getClientByID($request, $response);
                break;
            case 'updateClientByID':
                $this->updateClientByID($request, $response);
                break;
            case 'deleteClient':
                $this->deleteClient();
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

    public function createClient(Request $request)
    {

        $duplicateClient = $this->client
            ->where(["corporate_clients.name" => $this->params->name])
            ->get();

        if (isset($this->params->id) && COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1) {
            if ($duplicateClient[0]->id !== $this->params->id) {
                $this->success = false;
                $this->responseMessage = "client " . $duplicateClient[0]->name . " already exits!";
                return;
            }
        }

        $duplicateClient = $this->client
            ->where(["corporate_clients.email" => $this->params->email])
            ->get();

        if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
            $this->success = false;
            $this->responseMessage = "email " . $duplicateClient[0]->email . " already exits!";
            return;
        }

        $duplicateClient = $this->client
            ->where(["corporate_clients.contact_number" => $this->params->contact_number])
            ->get();

        if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
            $this->success = false;
            $this->responseMessage = "phone " . $duplicateClient[0]->contact_number . " already exits!";
            return;
        }

        $client = $this->client->insertGetId([
            "name" => $this->params->name,
            "balance" => $this->params->opening_balance,
            "opening_balance" =>  $this->params->opening_balance,
            "credit_limit" => $this->params->credit_limit,
            "country" => $this->params->country_id,
            "type" => $this->params->type,
            "bank_acc_number" => $this->params->bank_acc_number,
            "bank_name" => $this->params->bank_name,
            "address" => $this->params->address,
            "contact_number" => $this->params->contact_number,
            "email" => $this->params->email,
            "description" => $this->params->description,
            "created_by" => $this->user->id,
            "updated_by" => $this->user->id,
            "status" => 1,
        ]);



        $customer = $this->customer->insertGetId([
            "first_name" => $this->params->name,
            "mobile" => $this->params->contact_number,
            "balance" => $this->params->opening_balance,
            "country_id" => $this->params->country_id,
            "address" => $this->params->address,
            "email" => $this->params->email,
            "corporate_client_id" => $client,
            "created_by" => $this->user->id,
            "status" => 1,
        ]);









        $this->responseMessage = "new client has been created successfully";
        $this->outputData = $client;
        $this->success = true;
    }

    public function getAllClients()
    {

        $clients = $this->client
            ->select('corporate_clients.*')
            ->get();

        $this->responseMessage = "all clients has been fetched successfully!";
        $this->outputData = $clients;
        $this->success = true;
    }


    public function getAllClientList()
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;

        $query = $this->client
            ->select('corporate_clients.*');
        // ->get();

        if (!$query) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($filter['status'] == 'all') {
            $query->where('corporate_clients.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('corporate_clients.status', '=', 0);
        }


        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('corporate_clients.name', 'LIKE', '%' . $search . '%', 'i')
                    ->orWhere('corporate_clients.email', 'LIKE', '%' . $search . '%', 'i');
            });
        }

        $all_clients =  $query->orderBy('corporate_clients.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }

        $this->responseMessage = "all clients has been fetched successfully!";
        $this->outputData = [
            $pageNo => $all_clients,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getAllCorporateClientsByID()
    {

        $corporates = $this->customer
            ->select('customers.*')
            ->where(['customers.status' => 1])
            ->where(['customers.customer_type' => 1])
            ->where(['customers.corporate_client_id' => $this->params->id])
            ->get();

        $this->responseMessage = "all corporate clients has been fetched successfully!";
        $this->outputData = $corporates;
        $this->success = true;
    }

    public function getClientByID()
    {
        //varaible declaration
        $id = $this->params->id;

        $client = $this->client
            ->select('corporate_clients.*')
            ->where(["corporate_clients.id" => $id])
            ->get();

        if (!COUNT($client)) {
            $this->success = false;
            $this->responseMessage = "client not found!";
            return;
        }

        $this->responseMessage = "requested client fetched Successfully!";
        $this->outputData = $client;
        $this->success = true;
    }

    public function updateClientByID(Request $request)
    {

        $targetClient = $this->client
            ->where(["corporate_clients.id" => $this->params->id])
            ->get();

        if (COUNT($targetClient)) {

            $duplicateClient = $this->client
                ->where(["corporate_clients.name" => $this->params->name])
                ->get();

            if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
                $this->success = false;
                $this->responseMessage = "client " . $duplicateClient[0]->name . " already exits!";
                return;
            }

            $duplicateClient = $this->client
                ->where(["corporate_clients.email" => $this->params->email])
                ->get();

            if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
                $this->success = false;
                $this->responseMessage = "email " . $duplicateClient[0]->email . " already exits!";
                return;
            }

            $duplicateClient = $this->client
                ->where(["corporate_clients.contact_number" => $this->params->contact_number])
                ->get();

            if (COUNT($duplicateClient) > 1 || COUNT($duplicateClient) === 1 && $duplicateClient[0]->id !== $this->params->id) {
                $this->success = false;
                $this->responseMessage = "phone " . $duplicateClient[0]->contact_number . " already exits!";
                return;
            }

            $client = $this->client
                ->where(["corporate_clients.id" => $this->params->id])
                ->update([
                    "name" => $this->params->name,
                    "balance" => $this->params->opening_balance,
                    "opening_balance" =>  $this->params->opening_balance,
                    "credit_limit" => $this->params->credit_limit,
                    "country" => $this->params->country,
                    "type" => $this->params->type,
                    "bank_acc_number" => $this->params->bank_acc_number,
                    "bank_name" => $this->params->bank_name,
                    "address" => $this->params->address,
                    "contact_number" => $this->params->contact_number,
                    "email" => $this->params->email,
                    "description" => $this->params->description,
                    "created_by" => $this->user->id,
                    "updated_by" => $this->user->id,
                    "status" => 1,
                ]);


                // $customer = DB::table('customer_booking_master')
                // ->where(["id" => $this->params->id])
                // ->update([
                //     "name" => $this->params->name,
                //     "balance" => $this->params->opening_balance,
                //     "opening_balance" =>  $this->params->opening_balance,
                //     "credit_limit" => $this->params->credit_limit,
                //     "country" => $this->params->country,
                //     "type" => $this->params->type,
                //     "bank_acc_number" => $this->params->bank_acc_number,
                //     "bank_name" => $this->params->bank_name,
                //     "address" => $this->params->address,
                //     "contact_number" => $this->params->contact_number,
                //     "email" => $this->params->email,
                //     "description" => $this->params->description,
                //     "created_by" => $this->user->id,
                //     "updated_by" => $this->user->id,
                //     "status" => 1,
                // ]);


                // customer_booking_master



        } else {

            $this->success = false;
            $this->responseMessage = "client not found!";
            return;
        }

        $this->responseMessage = "requested client has been updated successfully";
        $this->outputData = $client;
        $this->success = true;
    }

    public function deleteClient()
    {
        $id = $this->params->id;
        $isDelete = $this->params->clientStatus;

        if ($isDelete == 0) {
            $this->client->where('corporate_clients.id', '=', $id)->delete();
        } else {
            $this->client
                ->where('corporate_clients.id', '=', $id)
                ->update(['status' => 0]);
        }

        $clients = $this->client
            ->select('corporate_clients.*')
            ->get();

        $this->responseMessage = "requested client removed successfully!";
        $this->outputData = $clients;
        $this->success = true;
    }
}
