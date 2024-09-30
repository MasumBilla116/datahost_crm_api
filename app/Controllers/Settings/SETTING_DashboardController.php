<?php

namespace  App\Controllers\Settings;


use App\Auth\Auth;
use App\Validation\Validator;
use PharIo\Manifest\Extension;
use App\Models\Settings\TaxHead;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Models\Settings\ConfigData;
use Illuminate\Pagination\Paginator;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;

class SETTING_DashboardController
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
    protected $taxes;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->configData = new ConfigData();
        $this->clientUsers = new ClientUsers();
        $this->validator = new Validator();
        $this->taxes = new TaxHead();
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

            case 'settingsDashbord':
                $this->settingsDashbord();
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


    // house rules
    public function settingsDashbord()
{
    $totalRoles = DB::table('roles')->where('status', 1)->count('id');

    if (!$totalRoles) {
        $this->success = false;
        $this->responseMessage = "No Roles found!";
        return;
    }

    $totalCurrencies = DB::table('currencies')->where('status', 1)->count('id');

    if (!$totalCurrencies) {
        $this->success = false;
        $this->responseMessage = "No totalCurrencies found!";
        return;
    }

    $totalpromo_offers = DB::table('restaurant_promo_offers')->where('status', 1)->count('id');

    if (!$totalpromo_offers) {
        $this->success = false;
        $this->responseMessage = "No totalpromo_offers found!";
        return;
    }

    // Removed the condition for taxes to always execute the code block

    $taxes = $this->taxes->with('TaxSubtaxes')->where('status', 1)->where('is_group', '=', 1)->count('id');

    // Removed the condition to check $taxes before setting success and responseMessage
    $this->responseMessage = "General settings are fetched successfully !";
    $this->outputData['totalRoles'] = $totalRoles;
    $this->outputData['totalCurrencies'] = $totalCurrencies;
    $this->outputData['totalpromo_offers'] = $totalpromo_offers;
    $this->outputData['taxes'] = $taxes;
    $this->success = true;
}


 
    





}
