<?php

namespace  App\Controllers\GeneralLedger;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class GeneralLedgerController
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

            case 'CreateLedger':
                $this->CreateLedger($request);
                break;

            case 'getAllLedgers':
                $this->getAllLedgers($request, $response);
                break;
            case 'getAllLedgersList':
                $this->getAllLedgersList($request, $response);
                break;
            case 'getLedgerInfo':
                $this->getLedgerInfo($request, $response);
                break;

            case 'deleteLedger':
                $this->deleteLedger($request, $response);
                break;

            case 'updateLedger':
                $this->updateLedger($request, $response);
                break;
            case 'getAllLedgerUsers':
                $this->getAllLedgerUsers($request, $response);
                break;


            case 'getUserLedgersHistory':
                $this->getUserLedgersHistory($request, $response);
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

    public function CreateLedger(Request $request)
    {

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "sector_head" => v::notEmpty(),
            "sector_id" => v::notEmpty(),
            "opening_balance" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        // Create general ledger
        $general_ledger = DB::table('general_ledger')->insert([
            'name' => $this->params->name,
            'sector_head' => $this->params->sector_head,
            'sector_id' => $this->params->sector_id,
            'opening_balance' => $this->params->opening_balance,
            'balance' => $this->params->opening_balance,
            'description' => $this->params->description,
            "created_by" => $this->user->id,
        ]);

        if (!$general_ledger) {
            $this->success = false;
            $this->responseMessage = 'Invalid Request ! Try again.';
            return;
        }

        //insert also account sector // this ledger under which sector
        $sector = DB::table('account_sectors')->insert([
            'account_type' => $this->params->sector_head,
            'title' => $this->params->name,
            'parent_id' => $this->params->sector_id,
            'description' => $this->params->description,
            "created_by" => $this->user->id,
            'status' => 1,
        ]);

        $this->responseMessage = "New Ledger has been created successfully";
        $this->outputData = $general_ledger;
        $this->success = true;
    }


    public function getAllLedgers(Request $request, Response $response)
    {

        $filter = $this->params->filterValue;
        $start_date = $this->params->startDate;
        $end_date = $this->params->endDate;
        $sector = $this->params->sector;


        if ($filter == 'all') {


            if ($sector) {
                $general_ledgers = DB::table('general_ledger')
                    ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
                    ->select(
                        'general_ledger.*',
                        'account_sectors.account_type as account_type',
                        'account_sectors.title as title',
                    )
                    ->where('general_ledger.sector_head', $sector)
                    ->orderBy('general_ledger.id', 'desc')
                    ->get();
            } else {
                $general_ledgers = DB::table('general_ledger')
                    ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
                    ->select(
                        'general_ledger.*',
                        'account_sectors.account_type as account_type',
                        'account_sectors.title as title',
                    )
                    ->orderBy('general_ledger.id', 'desc')
                    ->get();
            }
        } else if ($filter == 'custom' && $start_date && $end_date) {

            if ($sector) {


                $general_ledgers = DB::table('general_ledger')
                    ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
                    ->whereBetween('general_ledger.created_at', [$start_date, $end_date])
                    ->select(
                        'general_ledger.*',
                        'account_sectors.account_type as account_type',
                        'account_sectors.title as title',
                    )
                    ->where('general_ledger.sector_head', $sector)
                    ->orderBy('general_ledger.id', 'desc')
                    ->get();
            } else {
                $general_ledgers = DB::table('general_ledger')
                    ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
                    ->whereBetween('general_ledger.created_at', [$start_date, $end_date])
                    ->select(
                        'general_ledger.*',
                        'account_sectors.account_type as account_type',
                        'account_sectors.title as title',
                    )
                    ->orderBy('general_ledger.id', 'desc')
                    ->get();
            }
        } else {
            $general_ledgers = DB::table('general_ledger')
                ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
                ->select(
                    'general_ledger.*',
                    'account_sectors.account_type as account_type',
                    'account_sectors.title as title',
                )
                ->orderBy('general_ledger.id', 'desc')
                ->get();
        }


        $this->responseMessage = "General Ledger list fetched successfully";
        $this->outputData = $general_ledgers;
        $this->success = true;
    }



    public function getAllLedgersList(Request $request, Response $response)
    {

        $pageNo = $_GET['page'];
        $perPageShow = $_GET['perPageShow'];
        $totalRow = 0;
        $filter = $this->params->filterValue;
        $sector = $this->params->sector;



        $query = DB::table('general_ledger')
            ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
            ->select(
                'general_ledger.*',
                'account_sectors.account_type as account_type',
                'account_sectors.title as title',
            )
            // ->where('general_ledger.sector_head', $sector)
            ->orderBy('general_ledger.id', 'desc');

        if ($filter['status'] == 'all') {
            $query->where('general_ledger.status', '=', 1);
        }

        if ($filter['status'] == 'deleted') {
            $query->where('general_ledger.status', '=', 0);
        }

        // if ($filter['status'] ==  $filter) {
        //     $query->where('general_ledger.sector_head',  $filter);
        // }
        // if (isset($filter['yearMonth'])) {
        //     $query->whereYear('general_ledger.created_at', '=', date("Y", strtotime($filter['yearMonth'])))
        //         ->whereMonth('general_ledger.created_at', '=', date("m", strtotime($filter['yearMonth'])));
        // }

        if (isset($filter['search'])) {
            $search = $filter['search'];

            $query->where(function ($query) use ($search) {
                $query->orWhere('general_ledger.name', 'LIKE', '%' . $search . '%', 'i');
            });
        }


        $all_ledger =  $query->orderBy('general_ledger.id', 'desc')
            ->offset(($pageNo - 1) * $perPageShow)
            ->limit($perPageShow)
            ->get();


        if ($pageNo == 1) {
            $totalRow = $query->count();
        }


        $this->responseMessage = "General Ledger list fetched successfully";
        $this->outputData = [
            $pageNo => $all_ledger,
            'total' => $totalRow,
        ];
        $this->success = true;
    }

    public function getLedgerInfo(Request $request, Response $response)
    {
        $general_ledgers = DB::table('general_ledger')
            ->join('account_sectors', 'account_sectors.id', '=', 'general_ledger.sector_id')
            ->select(
                'general_ledger.*',
                'account_sectors.account_type as account_type',
                'account_sectors.title as title',
            )
            ->where('general_ledger.id', $this->params->id)
            ->orderBy('general_ledger.id', 'desc')
            ->first();

        $this->responseMessage = "General Ledger list fetched successfully";
        $this->outputData = $general_ledgers;
        $this->success = true;
    }


    public function updateLedger(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
            "sector_head" => v::notEmpty(),
            "sector_id" => v::notEmpty(),
            "opening_balance" => v::notEmpty(),
        ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }



        $general_ledger = DB::table('general_ledger')
            ->where('id', '=', $this->params->id)
            ->update([
                'name' => $this->params->name,
                'sector_head' => $this->params->sector_head,
                'sector_id' => $this->params->sector_id,
                'opening_balance' => $this->params->opening_balance,
                'balance' => $this->params->opening_balance,
                'description' => $this->params->description,
                "updated_by" => $this->user->id,

            ]);


        DB::table('account_sectors')
            ->where('parent_id', '=', $this->params->sector_id)
            ->update([
                'account_type' => $this->params->sector_head,
                'title' => $this->params->name,
                'description' => $this->params->description,
                "updated_by" => $this->user->id,
                'status' => 1,

            ]);


        $this->responseMessage = "Ledger has been updated successfully";
        $this->outputData = $general_ledger;
        $this->success = true;
    }


    public function deleteLedger(Request $request, Response $response)
    {
        if (!isset($this->params->id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
        }
        $ledger = DB::table('general_ledger')
            ->where('id', $this->params->id)->delete();
        if (!$ledger) {
            $this->success = false;
            $this->responseMessage = 'Voucher not found!';
            return;
        }

        $this->responseMessage = 'ledger has been Delete Laundry Voucher successfully';
        $this->outputData = $ledger;
        $this->success = true;
    }



    public function getAllLedgerUsers(Request $request, Response $response)
    {
        $ledgers = [];

        if ($this->params->sector_name == 'Laundry') {
            $ledgers = DB::table('laundry_operators')
                ->select(
                    'laundry_operators.id as id',
                    'laundry_operators.operator_name as name',
                )
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        } elseif ($this->params->sector_name == 'Suppliers') {
            $ledgers = DB::table('supplier')
                ->select(
                    'supplier.id as id',
                    'supplier.name as name',
                )
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        }

        $this->responseMessage = "Users list fetched successfully";
        $this->outputData =  $ledgers;
        $this->success = true;
    }


    public function getUserLedgersHistory(Request $request, Response $response)
    {
        $total_debit = 0;
        $total_credit = 0;

        if ($this->params->sector_name == 'Laundry') {
            if ($this->params->start_date && $this->params->end_date) {

                $ledgers = DB::table('account_laundry')
                    ->select(
                        'account_laundry.id as id',
                        'account_laundry.laundry_id as user_id',
                        'account_laundry.invoice_id as invoice_id',
                        'account_laundry.debit as debit',
                        'account_laundry.credit as credit',
                        'account_laundry.balance as balance',
                        'account_laundry.note as note',
                        'account_laundry.created_at as created_at',
                        'account_laundry.created_by as created_by',
                    )
                    ->where('laundry_id', $this->params->sector_id)
                    ->where('account_laundry.created_at', '>=', $this->params->start_date)
                    ->where('account_laundry.created_at', '<=', $this->params->end_date)
                    ->where('status', 1)
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                $ledgers = DB::table('account_laundry')
                    ->select(
                        'account_laundry.id as id',
                        'account_laundry.laundry_id as user_id',
                        'account_laundry.invoice_id as invoice_id',
                        'account_laundry.debit as debit',
                        'account_laundry.credit as credit',
                        'account_laundry.balance as balance',
                        'account_laundry.note as note',
                        'account_laundry.created_at as created_at',
                        'account_laundry.created_by as created_by',
                    )
                    ->where('laundry_id', $this->params->sector_id)
                    ->where('status', 1)
                    ->orderBy('id', 'desc')
                    ->get();
            }
        } elseif ($this->params->sector_name == 'Suppliers') {
            if ($this->params->start_date && $this->params->end_date) {
                $ledgers = DB::table('account_supplier')
                    ->select(
                        'account_supplier.id as id',
                        'account_supplier.supplier_id as user_id',
                        'account_supplier.invoice_id as invoice_id',
                        'account_supplier.debit as debit',
                        'account_supplier.credit as credit',
                        'account_supplier.balance as balance',
                        'account_supplier.note as note',
                        'account_supplier.created_at as created_at',
                        'account_supplier.created_by as created_by',
                    )
                    ->where('account_supplier.created_at', '>=', $this->params->start_date)
                    ->where('account_supplier.created_at', '<=', $this->params->end_date)
                    ->where('supplier_id', $this->params->sector_id)
                    ->where('status', 1)
                    ->orderBy('id', 'desc')
                    ->get();
            } else {

                $ledgers = DB::table('account_supplier')
                    ->select(
                        'account_supplier.id as id',
                        'account_supplier.supplier_id as user_id',
                        'account_supplier.invoice_id as invoice_id',
                        'account_supplier.debit as debit',
                        'account_supplier.credit as credit',
                        'account_supplier.balance as balance',
                        'account_supplier.note as note',
                        'account_supplier.created_at as created_at',
                        'account_supplier.created_by as created_by',
                    )
                    ->where('supplier_id', $this->params->sector_id)
                    ->where('status', 1)
                    ->orderBy('id', 'desc')
                    ->get();
            }
        }


        foreach ($ledgers as $key => $ledger) {
            $total_debit += $ledger->debit;
            $total_credit += $ledger->credit;
            $data['sl'] = $key + 1;
            $data['date'] = $ledger->created_at;
            $data['credit'] = $ledger->credit;
            $data['debit'] = $ledger->debit;

            array_push($ledgerArr, $data);
        }





        $this->responseMessage = "Users list fetched successfully";
        $this->outputData['ledgers'] =  $ledgers;
        $this->outputData['total_debit'] = $total_debit;
        $this->outputData['total_credit'] = $total_credit;
        $this->success = true;
    }
}
