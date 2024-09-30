<?php

namespace  App\Controllers\Accounts;

use App\Auth\Auth;
use App\Models\Accounts\AccountSector;
use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;

class ACCOUNT_DashboardController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $account_sectors;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->validator = new Validator();
        $this->account_sectors = new AccountSector();

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
            case 'accountsDashbord':
                $this->accountsDashbord();
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


    public function accountsDashbord()
    {
        $totalAccounts = DB::table('accounts')->where('status', 1)->count('id');
    
        if (!$totalAccounts) {
            $this->success = false;
            $this->responseMessage = "No Accounts found!";
            return;
        }
    
        $totalBalance = DB::table('accounts')->where('status', 1)->sum('balance');
    
        $totalAccount_sectors = DB::table('account_sectors')->where('status', 1)->count('id');
    
        if (!$totalAccount_sectors) {
            $this->success = false;
            $this->responseMessage = "No Account Sectors found!";
            return;
        }
    
        $payment_vouchers = DB::table('payment_vouchers')->sum('amount');
    
        $this->responseMessage = "General account settings are fetched successfully!";
        $this->outputData['totalAccounts'] = $totalAccounts;
        $this->outputData['totalAccount_sectors'] = $totalAccount_sectors;
        $this->outputData['totalBalance'] = $totalBalance;
        $this->outputData['payment_vouchers'] = $payment_vouchers;
        $this->success = true;
    }
    



}
