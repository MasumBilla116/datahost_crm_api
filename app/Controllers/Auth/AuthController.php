<?php

namespace  App\Controllers\Auth;

use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Customers\Customer;
use App\Requests\CustomRequestHandler;
use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Controllers\Permissions\PermissionController;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;

class AuthController
{

    protected $user;  // this user is org_users
    protected $customResponse;
    protected $validator;
    protected $customerRegister;
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

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }

    public function register(Request $request, Response $response)
    {
        try {
            $this->params = CustomRequestHandler::getAllParams($request);
            $passwordHash = $this->hashPassword(CustomRequestHandler::getParam($request, 'password'));
            DB::beginTransaction();

            $customerRegister = $this->customerRegister->create([
                "first_name" => $this->params->fName,
                "last_name" => $this->params->lName,
                "email" => $this->params->email,
                "mobile" => $this->params->phone,
                "gender" => $this->params->gender,
                "id_type" => $this->params->id_type,
                "personal_id" => $this->params->personal_id,
                "nationality" => $this->params->nationality,
                "country_id" => $this->params->country_id,
                "city_id" => $this->params->city_id,
                "state_id" => $this->params->state_id,
                "pin_code" => $this->params->zip_code,
                "address" => $this->params->address,
                "status" => 1,

            ]);


            $name = $this->params->fName . " " . $this->params->lName;

            $clientUsers = $this->user->create([
                "name" => $name,
                "email" => $this->params->email,
                "phone" => $this->params->phone,
                "password" => $passwordHash,
                "gender" => $this->params->gender,
                "id_type" => $this->params->id_type,
                "clientID" => $this->params->personal_id,
                "nationality" => $this->params->nationality,
                "country_id" => $this->params->country_id,
                "city_id" => $this->params->city_id,
                "state_id" => $this->params->state_id,
                "zip_code" => $this->params->zip_code,
                "address" => $this->params->address,
                "status" => 1,

            ]);


            $this->responseMessage = "Successfully Register";
            $this->outputData = $clientUsers;
            $this->outputData = $customerRegister;
            $this->success = true;
            DB::commit();
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        } catch (Exception $th) {
            DB::rollBack();
        }
    }



    public function login(Request $request, Response $response)
    {

        $this->validator->validate($request, [
            "email" => v::notEmpty()->email(),
            "password" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $responseMessage = $this->validator->errors;
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $verifyAccount = $this->verifyAccount(
            CustomRequestHandler::getParam($request, "password"),
            CustomRequestHandler::getParam($request, "email")
        );


        if ($verifyAccount == false) {
            $responseMessage = "invalid username or password";
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        if ($verifyAccount->status != 1) {
            $responseMessage = "Inactive User";
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        $permissionController = new PermissionController;

        $data = $verifyAccount;
        // $data["permissions"] = $permissionController->getPermissionByRoleIdReturn($data->role_id);
        $token = GenerateTokenController::generateToken($data);
        $responseMessage = $token;
        $data['access_codes'] = DB::table('role_permission')->where("role_id", $data->role_id)->pluck("access_code")->toArray();

        DB::table("access_tokens")->insertGetId(['uid' => $data->id, 'token' => $token]);

        // $responseMessage["permissions"] = PermissionController::getPermissionByRoleIdReturn($data->role_id);
        return $this->customResponse->is200Response($response, $responseMessage, $data);
    }

    public function webLogin(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);

        $this->validator->validate($request, [
            "email" => v::notEmpty()->email(),
            "password" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $responseMessage = $this->validator->errors;
            return $this->customResponse->is400Response($response, $responseMessage);
        }

        // $verifyAccount = $this->verifyAccount(
        //     CustomRequestHandler::getParam($request, "password"),
        //     CustomRequestHandler::getParam($request, "email")
        // );


        // if ($verifyAccount == false) {
        //     $responseMessage = "invalid username or password";
        //     return $this->customResponse->is400Response($response, $responseMessage);
        // }

        // if ($verifyAccount->status != 1) {
        //     $responseMessage = "Inactive User";
        //     return $this->customResponse->is400Response($response, $responseMessage);
        // }

        $loginUser =  DB::table("org_users")->where([
            "email" => $this->params->email,
            "password" => $this->params->password,
        ])->first();


        if (empty($loginUser)) {
            $responseMessage = "invalid username or password";
            return $this->customResponse->is400Response($response, $responseMessage);
        }


        if ($loginUser->status != 1) {
            $responseMessage = "Inactive User";
            return $this->customResponse->is400Response($response, $responseMessage);
        }


        $countryInfo = DB::table("countries")->select("name")->where("id", $loginUser->country_id)->first();
        $cityInfo = DB::table("cities")->select("name")->where("id", $loginUser->city_id)->first();
        $stateInfo = DB::table("states")->select("name")->where("id", $loginUser->state_id)->first();

        $loginUser->country_name = $countryInfo->name ?? null;
        $loginUser->city_name = $cityInfo->name ?? null;
        $loginUser->state_name = $stateInfo->name ?? null;


        $token = GenerateTokenController::generateTokenForWeb($loginUser);
        $data['user'] = $loginUser;
        $data['access_token'] = $token;
        $responseMessage = "Login is successfull";
        return $this->customResponse->is200Response($response, $responseMessage, $data);


        // $data["permissions"] = $permissionController->getPermissionByRoleIdReturn($data->role_id);
        // $token = GenerateTokenController::generateToken($data);
        // $responseMessage = $token;
        // $responseMessage = "Login is successfull";
        // $data['access_codes'] = DB::table('role_permission')->where("role_id", $data->role_id)->pluck("access_code")->toArray();

        // DB::table("access_tokens")->insertGetId(['id' => $data->id, 'token' => $token]);

        // $responseMessage["permissions"] = PermissionController::getPermissionByRoleIdReturn($data->role_id);
        // return $this->customResponse->is200Response($response, $responseMessage, $data);
    }

    public function webRegister(Request $request, Response $response)
    {
        try {
            $this->params = CustomRequestHandler::getAllParams($request);
            // $passwordHash = $this->hashPassword(CustomRequestHandler::getParam($request, 'password'));
            $passwordHash = $this->params->password;
            DB::beginTransaction();

            $emailExist = DB::table("org_users")->where("email", $this->params->email)->first();
            if ($emailExist) {
                DB::rollBack();
                $data["emailExist"] = true;
                $this->responseMessage = "Your email already exist";
                $this->outputData = $data;
                $this->success = false;
                return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
            }

            $emailExist = DB::table("org_users")->where("phone", $this->params->phone)->first();
            if ($emailExist) {
                DB::rollBack();
                $data["phoneNumberExist"] = true;
                $this->responseMessage = "Your phone number already exist";
                $this->outputData = $data;
                $this->success = false;
                return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
            }


            $clientUsers = $this->user->create([
                "name" => $this->params->full_name,
                "email" => $this->params->email,
                "phone" => $this->params->phone,
                "password" => $passwordHash,
                "gender" => $this->params->gender,
                "id_type" => $this->params->id_type,
                "clientID" => $this->params->personal_id,
                "nationality" => $this->params->nationality,
                "country_id" => $this->params->country_id,
                "city_id" => $this->params->city_id,
                "state_id" => $this->params->state_id,
                "zip_code" => $this->params->zip_code,
                "address" => $this->params->address,
                "status" => 1,

            ]);

            $customerRegister = $this->customerRegister->create([
                "first_name" => $this->params->full_name,
                "uid" => $clientUsers->id,
                "email" => $this->params->email,
                "mobile" => $this->params->phone,
                "gender" => $this->params->gender,
                "id_type" => $this->params->id_type,
                "personal_id" => $this->params->personal_id,
                "nationality" => $this->params->nationality,
                "country_id" => $this->params->country_id,
                "city_id" => $this->params->city_id,
                "state_id" => $this->params->state_id,
                "pin_code" => $this->params->zip_code,
                "address" => $this->params->address,
                "status" => 1,

            ]);


            $this->responseMessage = "Successfully Register";
            $this->outputData = $clientUsers;
            $this->outputData = $customerRegister;
            $this->success = true;
            DB::commit();
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        } catch (\Exception $th) {
            DB::rollBack();
            $this->responseMessage = "Failed  Register";
            $this->outputData = [];
            $this->success = false;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
    }



    public function webResetPassword(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        try {

            $password = $this->params->new_password;

            $customer = DB::table("org_users")->select("*")->where(["id" => $this->params->userId, "password" => $this->params->current_password])->first();
            if (!empty($customer)) {
                DB::table("org_users")->where("id", $this->params->userId)
                    ->update([
                        "password" => $password,
                    ]);

                $this->responseMessage = "Password reset successfully";
                $this->success = true;
                return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
            } else {
                $this->responseMessage = "Old Password not matching";
                $this->outputData['error_type'] = "wrong_credentials";
                $this->success = false;
                return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
            }
        } catch (\Exception $th) {
            $this->responseMessage = "Password reset failed";
            $this->success = true;
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
    }


    public function logout(Request $request, Response $response)
    {
        $authorizationHeader = $request->getHeaderLine('Authorization');
        $bearerToken = trim(str_replace('Bearer', '', $authorizationHeader));
        $responseMessage = DB::table("access_tokens")->where('uid', '=', $request->get('uid'))->where('token', '=', $bearerToken)->delete();
        return $this->customResponse->is200Response($response, $responseMessage, []);
    }


    public function verifyAccount($password, $email)
    {
        $count = $this->user->where(["email" => $email])->count();
        if ($count == 0) {
            return false;
        }
        $user = $this->user->where(["email" => $email])->where(["password" => $password])->first();
        if (empty($user)) {
            return false;
        }

        return $user;
    }

    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function emailExist($email)
    {
        $count = $this->user->where(['email' => $email])->count();
        if ($count == 0) {
            return false;
        }
        return true;
    }


    public function getCity($stateId, Response $response)
    {
        $city = DB::table("cities")->where("state_id", $stateId)->get();
        // print_r($city);
        // exit;
        if (!$city) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All cities fetched successfully";
        $this->outputData = $city;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function getState($countryId, Response $response)
    {
        $state = DB::table("states")->where("country_id", $countryId)->get();
        if (!$state) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All state fetched successfully";
        $this->outputData = $state;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function allCountries(Request $request, Response $response)
    {
        $countries = DB::table("countries")->get();
        if (!$countries) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }
        $this->responseMessage = "All countries fetched successfully";
        $this->outputData = $countries;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function invoiceTermsAndConditions(Request $request, Response $response)
    {
        $invTermsAndConditions =  DB::table("config_data")->where([
            "group_name" => "inv_terms_and_conditions",
            "config_name" => "Terms & Conditions",
        ])->get();

        $this->responseMessage = "All items and conditions fetched successfully";
        $this->outputData = $invTermsAndConditions;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function getActiveCurrency(Request $request, Response $response)
    {
        $businessSettings = DB::table("business_settings")->select("value")->where("type", 'system_default_currency')->first();
        $currency = DB::table("currencies")->where("id", $businessSettings->value)->first();
        $this->responseMessage = "All items and conditions fetched successfully";
        $this->outputData = $currency;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
