<?php

namespace  App\Controllers;

use App\Auth\Auth;
use Carbon\Carbon;
use App\Models\Currency;
use App\Validation\Validator;

use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CurrencyController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $currency;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->user = new ClientUsers();
        $this->validator = new Validator();
        $this->currency = new Currency();
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

            case 'createCurrency':
                $this->createCurrency($request, $response);
                break;
                
            case 'getCurrencyInfo':
                $this->getCurrencyInfo($request, $response);
                break;
            case 'updateCurrency':
                $this->updateCurrency($request, $response);
                break;
            case 'removeCurrencyData':
                $this->removeCurrencyData();
                break;
            case 'changeCurencyStatus':
                $this->changeCurencyStatus($request, $response);
                break;
            case 'getAllActiveCurrencies':
                $this->getAllActiveCurrencies();
                break;
            case 'getAllCurrency':
                $this->getAllCurrency();
                break;

                case 'activeCurrency':
                    $this->activeCurrency($request, $response);
                    break;
                // activeCurrency

            case 'deleteCurrency':
                $this->deleteCurrency($request, $response);
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

    public function createCurrency(Request $request, Response $response)
    {


        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "symbol" => v::notEmpty(),
            "exchange_rate" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }



        //check duplicate laundry
        $currency_name = $this->currency->where(["name" => $this->params->name])->first();
        if ($currency_name) {
            $this->success = false;
            $this->responseMessage = "Currency with the same name already exist";
            return;
        }


        $currency = $this->currency
            ->create([
                "name" => $this->params->name,
                "symbol" => $this->params->symbol,
                "code" => $this->params->code,
                "exchange_rate" => $this->params->exchange_rate,
                "status" => 1,
            ]);
        $this->responseMessage = "Laundry Operator has been created successfully!";
        $this->outputData = $currency;
        $this->success = true;
    }

    public function getAllActiveCurrencies()
    {
        $active_currencies = Currency::where('status', 1)->get();

        $this->responseMessage = "success!";
        $this->outputData = $active_currencies;
        $this->success = true;
    }


    public function getAllCurrency()
    {
        $all_currency = $this->currency
            // ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        $this->responseMessage = "All Data fetch Successfully";
        $this->outputData = $all_currency;
        $this->success = true;
    }



    public function getCurrencyInfo()
    {

        if (!isset($this->params->currency_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $currency = $this->currency->findOrFail($this->params->currency_id);

        if (!$currency) {
            $this->success = false;
            $this->responseMessage = "currency operator not found!";
            return;
        }


        $this->responseMessage = "currency operator fetched Successfully!";
        $this->outputData = $currency;
        $this->success = true;
    }


    public function deleteCurrency(Request $request, Response $response)
    {


        $currency_id = DB::table('currencies')
            ->where('id', '=', $this->params->currency_id)
            ->delete();
        // ->update(['status' => 0]);


        $this->responseMessage = "currencie has been deleted successfully";
        $this->outputData = $currency_id;
        $this->success = true;
    }

    public function removeCurrencyData()
    {
        if (Capsule::schema()->hasTable($this->params->currencyName)) {
            Capsule::schema()->dropIfExists($this->params->currencyName);

            $this->responseMessage ="success". $this->params->currencyName;
            $this->outputData = [];
            $this->success = true;
        } else {
            $this->responseMessage ='failed'. $this->params->currencyName;
            $this->outputData = [];
            $this->success = false;
        }
    }

    public function changeCurencyStatus(Request $request, Response $response)
    {


        $currency = DB::table('currencies')->find($this->params->id);
        $status = $currency->status;
        DB::table('currencies')->update(['status' => 0]);

        if ($status == 0) {
            $currency_update = DB::table('currencies')
                ->where('id', '=', $this->params->id)
                ->update(['status' => 1]);
        } else {
            $currency_update = DB::table('currencies')
                ->where('id', '=', $this->params->id)
                ->update(['status' => 0]);
        }

        $this->responseMessage = "currencie has been deleted successfully";
        $this->outputData =  $currency_update;
        $this->success = true;
    }

    public function updateCurrency(Request $request, Response $response)
    {

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "symbol" => v::notEmpty(),
            "exchange_rate" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }


        $currency = DB::table('currencies')
            ->where('id', '=', $this->params->currency_id)
            ->update([
                'name' => $this->params->name,
                'symbol' => $this->params->symbol,
                'code' => $this->params->code,
                'exchange_rate' => $this->params->exchange_rate
            ]);


        $this->responseMessage = "currencie has been updated successfully";
        $this->outputData = $currency;
        $this->success = true;
    }


    public function activeCurrency(Request $request, Response $response)
    {


        $currency = DB::table('currencies')
            ->where('status', '=', 1)
            ->get();

        $this->responseMessage = "All active currency fetch successfull";
        $this->outputData = $currency;
        $this->success = true;
    }


    // updateCurrency
}
