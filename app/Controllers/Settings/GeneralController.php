<?php

namespace  App\Controllers\Settings;


use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Settings\ConfigData;
use Illuminate\Pagination\Paginator;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Capsule\Manager as Capsule;
use PharIo\Manifest\Extension;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class GeneralController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $configData;
    protected $clientUsers;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->configData = new ConfigData();
        $this->clientUsers = new ClientUsers();
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

            case 'getSettingsData':
                $this->getSettingsData();
                break;
            case 'configDataInfo':
                $this->configDataInfo();
                break;

            case 'getConfigDataInfo':
                $this->getConfigDataInfo();
                break;
            case 'allTimeZone':
                $this->allTimeZone();
                break;
            case 'GeneralSettingsValue':
                $this->GeneralSettingsValue();
                break;
            case 'updateOrCreateConfigData':
                $this->updateOrCreateConfigData($request);
                break;
            case 'userVerifiaction':
                $this->userVerifiaction($request, $response);
                break;
            case 'hotelLogo':
                $this->hotelLogo($request);
                break;

            case 'darklLogo':
                $this->darklLogo($request);
                break;

            case 'invoiceTermsAndConditionsSave':
                $this->invoiceTermsAndConditionsSave($request, $response);
                break;

            case 'getInvoiceTermsAndCondition':
                $this->getInvoiceTermsAndCondition($request, $response);
                break;

            case 'saveBkashPeymentMethod':
                $this->saveBkashPeymentMethod($request, $response);
                break;

            case 'getAllPaymentMethods':
                $this->getAllPaymentMethods($request, $response);
                break;


            case 'lightLogo':
                $this->lightLogo($request);
                break;

                // lightLogo

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

    // @@ get all payment method
    public function getAllPaymentMethods(Request $request, Response $response)
    {
        $peymentMethods =  DB::table("payment_methods")->get();
        $accounts =  DB::table("accounts")->get();

        $data['payment_methods'] = $peymentMethods;
        $data['accounts'] = $accounts;

        $this->responseMessage = "All Payment methods";
        $this->outputData = $data;
        $this->success = true;
    }



    // @@ Save Bkash payment method
    public function saveBkashPeymentMethod(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $status_message = "";
        try {
            $status = !empty($this->params->status) ? 1 : 0;
            $check = DB::table("payment_methods")->select("method_name")->where("method_name", "Bkash")->first();
            if ($check) {
                $status_message = "Update";
                DB::table('payment_methods')->where("method_name", "Bkash")->update([
                    "app_key" => $this->params->app_key,
                    "app_secret_key" => $this->params->app_secret_key,
                    "app_user" => $this->params->user,
                    "app_password" => $this->params->password,
                    "status" => $status,
                ]);
            } else {
                $status_message = "Insert";
                DB::table('payment_methods')->insert([
                    "method_name" => "Bkash",
                    "app_key" => $this->params->app_key,
                    "app_secret_key" => $this->params->app_secret_key,
                    "app_user" => $this->params->user,
                    "app_password" => $this->params->password,
                    "status" => $status,
                ]);
            }
            $this->responseMessage = "$status_message is successfully";
            $this->outputData = [];
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Somethis is wrong try again ";
            $this->outputData = [];
            $this->success = true;
        }
    }

    // @@ save terms and conditions

    public function invoiceTermsAndConditionsSave(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        try {

            DB::table("config_data")->where([
                "group_name" => "inv_terms_and_conditions",
                "config_name" => "Terms & Conditions",
            ])->delete();


            for ($i = 1; $i <= $this->params->totalRow; $i++) {
                $termsKey = "terms_conditions_" . $i;
                $terms = $this->params->$termsKey;

                DB::table("config_data")->insert([
                    "group_name" => "inv_terms_and_conditions",
                    "config_name" => "Terms & Conditions",
                    "config_value" => $terms,
                    "status" => 1,
                ]);
            }

            $this->responseMessage = "Terms & Conditions save successfully";
            $this->outputData = [];
            $this->success = true;
        } catch (\Exception $th) {
            $this->responseMessage = "Terms & Conditions save failed";
            $this->outputData = [];
            $this->success = true;
        }
    }


    public function getInvoiceTermsAndCondition(Request $request, Response $response)
    {

        $invTermsAndConditions =  DB::table("config_data")->where([
            "group_name" => "inv_terms_and_conditions",
            "config_name" => "Terms & Conditions",
        ])->get();


        $this->responseMessage = "Terms & Conditions fetch successfully";
        $this->outputData = $invTermsAndConditions;
        $this->success = true;
    }



    public function userVerifiaction(Request $request, Response $response)
    {

        $user =  DB::table('org_users')->where('id', $this->user->id)->first();
        $hashedPassword = $user->password;
        $verify = password_verify($this->params->password, $hashedPassword);
        if ($verify == true) {
            $this->responseMessage = "Password Matched";
            $this->outputData = ['user' => 'verified'];
            $this->success = true;
        } else {
            $responseMessage = "Password Not Matched";
            return $this->customResponse->is400Response($response, $responseMessage);
        }
    }

    public function allTimeZone()
    {
        $data = DB::table('time_zones')->select('id', 'name')->where('status', 1)->get();
        $this->responseMessage = "Time zone fetched successfully";
        $this->outputData = $data;
        $this->success = true;
    }


    public function updateOrCreateConfigData(Request $request)
    {

        $this->validator->validate($request, [
            "config_value" => v::notEmpty(),
            "config_name" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        $matchThese = ['config_name' => $this->params->config_name];

        $configData = $this->configData->updateOrCreate($matchThese, [
            'config_value' => $this->params->config_value,
            'updated_by' => $this->user->id
        ]);

        // Write File 
        if ($this->params->config_name == "Time Zone") {
            $xml = simplexml_load_file("../config/xml/config.xml");
            $xml->timeZone = $this->params->config_value;
            $xml->asXML("../config/xml/config.xml");
        }

        $this->responseMessage = "Config data has been updated successfully";
        $this->outputData = $configData;
        $this->success = true;
    }

    // house rules
    public function houseRules(Request $request, Response $response)
    {
        $houseRules = DB::table('config_data')->where("group_name", 'hotel_rules')->whereIn('config_name', [
            "Weekend",
            "Check-In",
            "Check-Out",
            "Luggage Storage",
            "Cancellation/Payment",
            "Children & Extra Beds",
            "Pets",
            "Additional Info"
        ])->get();

        if (!$houseRules) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "General settings are fetched successfully !";
        $this->outputData = $houseRules;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function configDataInfo()
    {

        $configData = $this->configData->where('config_name', $this->params->name)->first();

        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        if ($this->params->name == "Weekend") {
            $configData->config_value = explode(',', $configData->config_value);
        }
        if (!empty($configData->config_value) && isset($configData->config_value)) {
            $photos = json_decode($configData->config_value);
        }
        $uploadsData = [];
        if ($this->params->name == "Dark Logo") {

            if (count($photos) > 0) {

                $ids = $photos;
                $uploadsData = array();

                for ($i = 0; $i < count($ids); $i++) {
                    $uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
                }
            }

            // $configData->config_value = explode(',', $configData->config_value);
        }


        $this->responseMessage = "Config data fetched successfully";
        $this->outputData = $configData;
        $this->outputData['photos'] = $photos;
        $this->outputData['uploadsData'] = $uploadsData;
        $this->success = true;
    }



    public function getHotelLogo()
    {
        $hotelLogoConfig = $this->configData
            ->where('group_name', 'hotel_rules')
            ->where('status', 1)
            ->where('config_name', 'Dark Logo')
            ->first(['config_name', 'config_value', 'id']);

        if ($hotelLogoConfig) {
            $uploadsData = DB::table('uploads')
                ->where('uploads.id', json_decode($hotelLogoConfig->config_value))
                ->value('file_path');

            $hotelLogoConfig->config_value = $uploadsData;
        }

        $this->responseMessage = "Config data fetched successfully";
        $this->outputData = $hotelLogoConfig ? $hotelLogoConfig : null;
        $this->success = $hotelLogoConfig ? true : false;
    }

    public function getConfigDataInfo()
    {
        $configData = $this->configData->where('group_name', '=', 'hotel_rules')->where('status', '=', 1)->get(['config_name', 'config_value', 'id']);

        $configResults = [];
        foreach ($configData as $cItem) {

            if ($cItem->config_name === 'Dark Logo') {

                $uploadsData  = DB::table('uploads')->where('uploads.id', '=', json_decode($cItem->config_value))->value('file_path');

                $cItem->config_value = $uploadsData;
            }

            if ($cItem->config_name === 'Light Logo') {

                $uploadsData  = DB::table('uploads')->where('uploads.id', '=', json_decode($cItem->config_value))->value('file_path');

                $cItem->config_value = $uploadsData;
            }

            $configResults[] = $cItem;
        }

        $this->responseMessage = "Config data fetched successfully";
        $this->outputData = $configResults;
        $this->success = true;
    }



    // {
    //     $configData = $this->configData->get(['config_name', 'config_value', 'id']);

    // if ($configData->isEmpty()) {
    //     $this->success = false;
    //     $this->responseMessage = "No data found!";
    //     return;
    // }

    // $uploadsData = [];
    // $photos = [];

    // foreach ($configData as $cItem) {
    //     if (!empty($cItem->config_value) && isset($cItem->config_value)) {
    //         $photos = json_decode($cItem->config_value);

    //         if ($cItem->config_name == "Dark Logo" && count($photos) > 0) {
    //             $ids = $photos;

    //             for ($i = 0; $i < count($ids); $i++) {
    //                 $uploadsData[] = DB::table('uploads')
    //                     ->where('uploads.user_id', '=', $this->user->id)
    //                     ->where('uploads.id', '=', $ids[$i])
    //                     ->first();
    //             }
    //         }
    //     }

    //     // Additional processing for 'Dark Logo' or other configurations if needed
    //     // $cItem->config_value = explode(',', $cItem->config_value);
    // }

    // $this->responseMessage = "Config data fetched successfully";
    // $this->outputData = $configData;
    // $this->outputData['photos'] = $photos;
    // $this->outputData['uploadsData'] = $uploadsData;
    // $this->success = true;
    // }


    public function getAllHotelConfigData(Request $request, Response $response)
    {
        $configData = $this->configData->get();
        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "No data found";
            return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
        }
        $this->responseMessage = "Config data fetched successfully";
        $this->outputData = $configData;
        $this->success = true;
        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }

    public function getSettingsData()
    {
        $getSettings = DB::table('config_data')->where('status', 1)->get();

        if (!$getSettings) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "General settings are fetched successfully !";
        $this->outputData = $getSettings;
        $this->success = true;
    }


    public function GeneralSettingsValue()
    {
        if (Capsule::schema()->hasTable($this->params->value)) {
            Capsule::schema()->dropIfExists($this->params->value);

            $this->responseMessage = "Fetched" . $this->params->value;
            $this->outputData = [];
            $this->success = true;
        } else {
            $this->responseMessage = 'not found' . $this->params->value;
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function hotelLogo(Request $request)
    {

        $configData = $this->configData->where('config_name', 'Dark Logo')->first();
        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "Section not found!";
            return;
        }

        $imageUrl = '';
        $photos = json_decode($configData->config_value);

        if (count($photos) > 0) {
            $imageUrl = DB::table('uploads')->where('uploads.id', '=', $photos[0])->value('file_path');
        }

        $this->responseMessage = "Logo fetched successfully";
        $this->outputData = $imageUrl;
        $this->success = true;
    }


    public function darklLogo(Request $request)
    {

        $configData = $this->configData->where('config_name', 'Dark Logo')->first();
        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "Section not found!";
            return;
        }

        $imageUrl = '';
        $photos = json_decode($configData->config_value);

        if (count($photos) > 0) {
            $imageUrl = DB::table('uploads')->where('uploads.id', '=', $photos[0])->value('file_path');
        }

        $this->responseMessage = "Logo fetched successfully";
        $this->outputData = $imageUrl;
        $this->success = true;
    }


    public function lightLogo(Request $request)
    {

        $configData = $this->configData->where('config_name', 'Light Logo')->first();
        if (!$configData) {
            $this->success = false;
            $this->responseMessage = "Section not found!";
            return;
        }

        $imageUrl = '';
        $photos = json_decode($configData->config_value);

        if (count($photos) > 0) {
            $imageUrl = DB::table('uploads')->where('uploads.id', '=', $photos[0])->value('file_path');
        }

        $this->responseMessage = "Logo fetched successfully";
        $this->outputData = $imageUrl;
        $this->success = true;
    }
}
