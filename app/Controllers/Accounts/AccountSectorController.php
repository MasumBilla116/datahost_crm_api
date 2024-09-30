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

class AccountSectorController
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
            case 'createSector':
                $this->createSector($request, $response);
                break;
            case 'getAllSectors':
                $this->getAllSectors($request, $response);
                break;

            case 'getAllSectorsTryToFixed':
                $this->getAllSectorsTryToFixed($request, $response);
                break;

                // getAllSectorsTryToFixed
            case 'getSubSectors':
                $this->getSubSectors($request, $response);
                break;

            case 'getSubSectorsByparents':
                $this->getSubSectorsByparents($request, $response);
                break;

            case 'getSectorInfo':
                $this->getSectorInfo($request, $response);
                break;
            case 'editSector':
                $this->editSector($request, $response);
                break;
            case 'deleteSector':
                $this->deleteSector();
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


    public function createSector(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "account_type" => v::notEmpty(),
            "title" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate category
        // $current_category = $this->categories->where(["name"=>$this->params->name])->where('status',1)->first();
        // if ($current_category) {
        //     $this->success = false;
        //     $this->responseMessage = "Category with the same name already exists!";
        //     return;
        // }

        $sector = $this->account_sectors->create([
            "account_type" => $this->params->account_type,
            "title" => $this->params->title,
            "parent_id" => $this->params->parent_id,
            "description" => $this->params->description,
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $this->responseMessage = "New Account Sector created successfully";
        $this->outputData = $sector;
        $this->success = true;
    }

    public function getAllSectors()
    {
        $sectors = $this->account_sectors->with(['childrenRecursive'])->where('status', 1)->where('parent_id', '=', 0)->get()->groupBy("account_type");
        $this->responseMessage = "sectors list fetched successfully";
        $this->outputData = $sectors;
        $this->success = true;
    }


    public function getSubSectorsByparents()
    {
        $sectors = $this->account_sectors
            ->with([
                'childrenRecursive' => function ($query) {
                    $query->where('parent_id', $this->params->parent_id);
                },
            ])
            ->where('account_type', $this->params->account_type)
            // ->where('id', $this->params->sector_id)
            ->where('status', 1)
            ->where('parent_id', '=', 0)
            ->get();

        $formattedSectors = $sectors->map(function ($sector) {
            return [
                "id" => $sector->id,
                "account_type" => $sector->account_type,
                "title" => $sector->title,
                "parent_id" => $sector->parent_id,
                "description" => $sector->description,
                "status" => $sector->status,
                "created_by" => $sector->created_by,
                "created_at" => $sector->created_at,
                "updated_by" => $sector->updated_by,
                "updated_at" => $sector->updated_at,
                "children_recursive" => $sector->childrenRecursive
                    ->map(function ($child) {
                        return [
                            "id" => $child->id,
                            "account_type" => $child->account_type,
                            "title" => $child->title,
                            "parent_id" => $child->parent_id,
                            "description" => $child->description,
                            "status" => $child->status,
                            "created_by" => $child->created_by,
                            "created_at" => $child->created_at,
                            "updated_by" => $child->updated_by,
                            "updated_at" => $child->updated_at,
                        ];
                    })
                    ->all(),
            ];
        });

        $this->responseMessage = "Sector list fetched successfully";
        $this->outputData = $formattedSectors;
        $this->success = true;
    }



    public function getSubSectors()
    {
        $sectors = $this->account_sectors->with(['childrenRecursive'])
            ->where('account_type', $this->params->account_type)
            ->where('status', 1)
            ->where('parent_id', '=', 0)
            ->get();

        $this->responseMessage = "sector list fetched successfully";
        $this->outputData = $sectors;
        $this->success = true;
    }


    //     public function getSubSectors()
    // {
    //     $sectors = $this->account_sectors
    //         ->with(['childrenRecursive'])
    //         ->where('account_type', $this->params->account_type)
    //         ->where('status', 1)
    //         ->where('parent_id', '=', 0)
    //         ->get();

    //     // Extract only the children and exclude the root sectors
    //     $childrenOnly = collect($sectors)->flatMap(function ($sector) {
    //         return $sector->childrenRecursive;
    //     })->all();

    //     $this->responseMessage = "Sector list fetched successfully";
    //     $this->outputData = $childrenOnly;
    //     $this->success = true;
    // }


    // public function getSubSectors()
    // {
    //     $sectors = $this->account_sectors
    //         ->with(['childrenRecursive'])
    //         ->where('account_type', $this->params->account_type)
    //         ->where('status', 1)
    //         ->where('parent_id', '=', 0)
    //         ->get();

    //     $formattedSectors = $sectors->map(function ($sector) {
    //         return [
    //             "id" => $sector->id,
    //             "account_type" => $sector->account_type,
    //             "title" => $sector->title,
    //             "parent_id" => $sector->parent_id,
    //             "description" => $sector->description,
    //             "status" => $sector->status,
    //             "created_by" => $sector->created_by,
    //             "created_at" => $sector->created_at,
    //             "updated_by" => $sector->updated_by,
    //             "updated_at" => $sector->updated_at,
    //             "children_recursive" => $sector->childrenRecursive
    //                 ->map(function ($child) {
    //                     return [
    //                         "id" => $child->id,
    //                         "account_type" => $child->account_type,
    //                         "title" => $child->title,
    //                         "parent_id" => $child->parent_id,
    //                         "description" => $child->description,
    //                         "status" => $child->status,
    //                         "created_by" => $child->created_by,
    //                         "created_at" => $child->created_at,
    //                         "updated_by" => $child->updated_by,
    //                         "updated_at" => $child->updated_at,
    //                     ];
    //                 })
    //                 ->all(),
    //         ];
    //     });

    //     $this->responseMessage = "Sector list fetched successfully";
    //     $this->outputData = $formattedSectors;
    //     $this->success = true;
    // }



    public function getSectorInfo(Request $request, Response $response)
    {
        if (!isset($this->params->sector_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $sector = $this->account_sectors->find($this->params->sector_id);

        if ($sector->status == 0) {
            $this->success = false;
            $this->responseMessage = "sector missing!";
            return;
        }

        if (!$sector) {
            $this->success = false;
            $this->responseMessage = "sector not found!";
            return;
        }

        $this->responseMessage = "sector info fetched successfully";
        $this->outputData = $sector;
        $this->success = true;
    }

    public function editSector(Request $request, Response $response)
    {
        if (!isset($this->params->sector_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $sector = $this->account_sectors->where('status', 1)->find($this->params->sector_id);

        $countExistingRows = $this->account_sectors->where(['module' => 'supplier', 'parent_id' => $sector->id])->get();



        if (!$sector) {
            $this->success = false;
            $this->responseMessage = "sector not found!";
            return;
        }

        // $this->validator->validate($request, [
        //     "title"=>v::notEmpty(),
        // ]);

        //  if ($this->validator->failed()) {
        //      $this->success = false;
        //      $this->responseMessage = $this->validator->errors;
        //      return;
        //  }

        //check duplicate category
        //  $current_category = $this->categories->where(["name"=>$this->params->name])->where('status',1)->first();
        //  if ($current_category && $current_category->id != $this->params->category_id) {
        //      $this->success = false;
        //      $this->responseMessage = "Category with the same name has already exists!";
        //      return;
        //  }


        if ($this->params->module == "supplier") {

            $users = DB::table('supplier')->orderBy('id', 'desc')->get()->take(5);
            $sectorData = [];
            $editedSector = $sector->update([
                "title" => $this->params->title,
                "description" => $this->params->description,
                "updated_by" => $this->user->id,
                "status" => 1,
            ]);

            if (count($users) > 0 && count($countExistingRows) < 1) {
                foreach ($users as $user) {
                    $this->account_sectors->create([
                        "account_type" => $sector->account_type,
                        "title" => $user->name,
                        "parent_id" => $sector->id,
                        "module" => $this->params->module,
                        "description" => $sector->description,
                        "created_by" => $this->user->id,
                        "status" => 1,
                    ]);
                }
            }
        } elseif ($this->params->module == "laundry") {
            $users = DB::table('laundry_operators')->orderBy('id', 'desc')->get()->take(5);
            $sectorData = [];
            $editedSector = $sector->update([
                "title" => $this->params->title,
                "description" => $this->params->description,
                "updated_by" => $this->user->id,
                "status" => 1,
            ]);

            if (count($users) > 0 && count($countExistingRows) < 1) {
                foreach ($users as $user) {
                    $this->account_sectors->create([
                        "account_type" => $sector->account_type,
                        "title" => $user->operator_name,
                        "parent_id" => $sector->id,
                        "module" => $this->params->module,
                        "description" => $sector->description,
                        "created_by" => $this->user->id,
                        "status" => 1,
                    ]);
                }
            }
        }




        $this->responseMessage = "Sector Updated successfully";
        $this->outputData = $users;
        $this->success = true;
    }

    public function deleteSector()
    {
        if (!isset($this->params->sector_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $sector = $this->account_sectors->find($this->params->sector_id);

        if (!$sector) {
            $this->success = false;
            $this->responseMessage = "sector not found!";
            return;
        }

        $deletedSector = $sector->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Sector Deleted successfully";
        $this->outputData = $deletedSector;
        $this->success = true;
    }



    public function getAllSectorsTryToFixed()
    {
        $sectors = $this->account_sectors->with(['childrenRecursive'])
        // "account_type" => $sector->account_type,
            ->where('account_type', $this->params->account_type)
            ->where('parent_id', $this->params->sector_id)
            // parent_id
            ->where('status', 1)
            // ->where('parent_id', '=', 0)
            ->get() ;
            // ->groupBy("account_type");

        $this->responseMessage = "sectors list fetched successfully";
        $this->outputData = $sectors;
        $this->success = true;
    }
}
