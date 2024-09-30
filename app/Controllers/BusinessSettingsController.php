<?php

namespace  App\Controllers;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Models\Currency;
use App\Validation\Validator;

use App\Models\BusinessSetting;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class BusinessSettingsController
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
        $this->user = new ClientUsers();
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
         
            case 'update':
                $this->update();
                break;                                
            case 'settingsInfo':
                $this->settingsInfo();
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

    public function settingsInfo($default = null, $lang = false){
        $key = $this->params->key;

        if ($lang == false) {
            $setting = BusinessSetting::where('type', $key)->first();
        } else {
            $setting = BusinessSetting::where('type', $key)->where('lang', $lang)->first();
            $setting = !$setting ? BusinessSetting::where('type', $key)->first() : $setting;
        }

        // $value = $setting == null ? $default : $setting->value;
        
        $currency = Currency::where('id', $setting->value)->first();
        $currency_info = (object)['value'=>$currency->id, 'label'=>$currency->name, 'symbol'=>$currency->symbol, 'exchange_rate'=>$currency->exchange_rate];

        $this->responseMessage = "Settings updated successfully!";
        $this->outputData['currency_info'] = $currency_info;
        $this->success = true;
    }

    public function update()
    {
        
        foreach ($this->params->types as $key => $type) {
         
                $lang = null;
                if(gettype($type) == 'array'){
                    $lang = array_key_first($type);
  
                    $type = $type[$lang];
                    $business_settings = BusinessSetting::where('type', $type)->where('lang',$lang)->first();
                }else{
                    $business_settings = BusinessSetting::where('type', $type)->first();
                }

                if($business_settings!=null){
                    if(gettype($this->params->$type) == 'array'){
                        $business_settings->value = json_encode($this->params->$type);
                    }
                    else {
                        $business_settings->value = $this->params->$type;
                    }
                    $business_settings->lang = $lang;
                    $business_settings->save();
                }
                else{
                    $business_settings = new BusinessSetting;
                    $business_settings->type = $type;
                    if(gettype($this->params->$type) == 'array'){
                        $business_settings->value = json_encode($this->params->$type);
                    }
                    else {
                        $business_settings->value = $this->params->$type;
                    }
                    $business_settings->lang = $lang;
                    $business_settings->save();
                }
            
        }

        $this->responseMessage = "Settings updated successfully!";
        $this->outputData = [];
        $this->success = true;

      
    }
}