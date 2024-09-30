<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Exceptions\NestedValidationException;

class CustomerController
{
    protected $user;
    protected $customerRegister;
    protected $customResponse;
    protected $validator;
    protected $params;
    protected $responseMessage;
    protected $success;
    protected $outputData;

    public function __construct()
    {
        $this->user = new ClientUsers();
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->customerRegister = new Customer();
        $this->user = new ClientUsers();

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }







    public function customerInfo($id, Response $response)
    {
        try {

            $customerId = DB::table("customers")->select("id")->where("uid", $id)->first();
            $id = $customerId->id;
            if (!isset($id)) {
                $this->success = false;
                $this->responseMessage = "Parameter missing";
                return;
            }

            $customer = $this->customerRegister->find($id);

            $this->responseMessage = "Rooms fetched successfully";
            $this->outputData =  $customer;
            $this->success = true;


            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        } catch (\Exception $th) {
            $this->responseMessage = "Rooms fetched successfully";
            $this->outputData =  $customer;
            $this->success = true;


            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
    }



    public function updateCustomer($id, Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);
        DB::beginTransaction();

        try {
            $customer = $this->customerRegister->where(['uid' => $id, 'status' => 1])
                ->update([
                    'first_name' => $this->params->full_name,
                    'mobile' => $this->params->mobile_number,
                    "id_type" => $this->params->id_type,
                    "personal_id" => $this->params->nid,
                    "address" => $this->params->address,
                    "country_id" => $this->params->country,
                    "city_id" => $this->params->city,
                    "state_id" => $this->params->state,
                    "pin_code" => $this->params->zip_code,
                ]);



            $user = $this->user->where(['id' => $id, 'status' => 1])
                ->update([
                    "name" => $this->params->full_name,
                    'phone' => $this->params->mobile_number,
                    "id_type" => $this->params->id_type,
                    "clientID" => $this->params->nid,
                    "address" => $this->params->address,
                    "country_id" => $this->params->country,
                    "city_id" => $this->params->city,
                    "state_id" => $this->params->state,
                    "zip_code" => $this->params->zip_code,

                ]);

            $this->responseMessage = "User updated successfully !";
            $this->outputData = ["success" => true];
            $this->success = true;

            DB::commit();
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        } catch (\Exception $th) {
            DB::rollback();
            $this->responseMessage = "User updated failed !" . $th;
            $this->success = false;

            $this->outputData = ["failed" => true];

            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
    }
}
