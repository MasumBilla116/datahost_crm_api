<?php

namespace  App\Controllers\Permissions;

use App\Auth\Auth;
use App\Models\Permission\AccessPermission;
use App\Models\Permission\AccessRole;
use App\Models\Permission\RolePermission;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class PermissionController
{


    protected $customResponse;
    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;

    /** Permission ini */
    public $RolePermission;
    private $AccessPermissions;
    private $AccessRole;
    private $permissionList;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        //Model Instance
        $this->AccessPermissions = new AccessPermission();
        $this->AccessRole = new AccessRole();
        $this->RolePermission = new RolePermission();
        /*Model Instance END */
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
            case 'getAllPermissions':
                $this->getAllPermissions();
                break;
            case 'getAllPermissionNew':
                $this->getAllPermissionNew();
                break;
            case 'getPermissionsTreeList':
                $this->getPermissionsTreeList();
                break;
            case 'getAllRoles':
                $this->getAllRoles();
                break;
            case 'createRolePermission':
                $this->createRolePermission($request);
                break;
            case 'editRolePermission':
                $this->editRolePermission($request);
                break;
            case 'createAccessRole':
                $this->createAccessRole($request);
                break;
            case 'createAccessPermission':
                $this->createAccessPermission($request);
                break;
            case 'AccessPermissionInfo':
                $this->AccessPermissionInfo($request);
                break;
            case 'editAccessPermission':
                $this->editAccessPermission($request);
                break;
            case 'getAllRolePermission':
                $this->getAllRolePermission();
                break;
            case 'getRoleById':
                $this->getRoleById($request);
                break;
            case 'getPermissionByRoleID':
                $this->getPermissionByRoleId($request);
                break;
            case 'updateRolePermission':
                $this->updatePermissionRole();
                break;
            case 'deletePermissionByRoleID':
                $this->deletePermissionByRoleID();
                break;
            case 'getPermissionSet':
                $this->getPermissionSet();
            case 'getPermissionIdsByRoleId':
                $this->getPermissionIdsByRoleId();
                break;
            case 'getPermissionByUserRole':
                $this->getPermissionByUserRole();
                break;
            default:
                $this->responseMessage = "Invalid request!";
                return $this->customResponse->is400Response($response, $this->responseMessage);
        }

        if (!$this->success) {
            return $this->customResponse->is400Response($response, $this->responseMessage, $this->outputData);
        }

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
    /**Wiping data by Role ID */
    public function deletePermissionByRoleID()
    {

        DB::table('roles')->where(['status' => 1, 'id' => $this->params->role_id])->update([
            'status' => 0
        ]);

        $accessPermission = $this->RolePermission
            ->where(["role_id" => $this->params->role_id])
            ->delete();

        $this->responseMessage = "Role has been removed successfully!";
        $this->outputData = $accessPermission;
        $this->success = true;
    }

    /**Updating Role-Permission */
    public function updatePermissionRole()
    {
        /** Wiping out existing all params->role_id */
        $accessPermission = $this->RolePermission
            ->where(["role_id" => $this->params->role_id])
            ->delete();
        /**Creating New Data For params->role_id */

        $this->permissionList = array($this->params->permissions_id);

        $array = array();
        foreach ($this->permissionList[0] as $key => $permission) {
            array_push($array, $permission);
            /**Database Insertion part */
            $this->RolePermission->insert([
                "role_id" => $this->params->role_id,
                "permission_id" => $permission,
                "status" => 1
            ]);

            /**Database Insertion part End*/
        }
        $this->responseMessage = "Role-Permission Data updated successfully";
        $this->outputData = $accessPermission;
        $this->success = true;
    }
    /**Updating Role-Permission END */


    /** Get Role By ID */
    public function getRoleById()
    {
        $accessRoles = $this->AccessRole->selectRaw('title as value')->where(["id" => $this->params->role_id['id']])->first();
        if (!$accessRoles) {
            $this->success = false;
            $this->responseMessage = "Role Data not found!";
            return;
        }

        $this->responseMessage = "All Role-Permission Data fetched successfully";
        $this->outputData = $accessRoles;
        $this->success = true;
    }

    /**Get Permission By ID */
    /** Simple Return type */
    public function getPermissionSet()
    {
        $accessRolePermission = $this->RolePermission
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where(["role_permission.role_id" => $this->params->role_id])
            ->select('permissions.title', 'permissions.access_code', 'permissions.module')
            ->get();
        if (!$accessRolePermission) {
            $this->success = false;
            $this->responseMessage = "No Permission Set Data found!";
            return;
        }
        $this->responseMessage = "All Permission Set Data fetched successfully";
        $this->outputData = $accessRolePermission;
        $this->success = true;
    }
    public function getPermissionByRoleIdReturn($role_id)
    {
        $accessRolePermission = $this->RolePermission
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where(["role_permission.role_id" => $role_id])
            ->select('permissions.title', 'permissions.access_code', 'permissions.module')
            ->get();

        return $accessRolePermission;
    }
    public function getPermissionByRoleId(Request $request)
    {

        if ($this->params->type === 'only_access_code') {
            $accessRolePermission = DB::table('role_permission')->where(["role_id" => $this->params->role_id])->pluck('access_code')->toArray();
        } else {
            $accessRolePermission = $this->RolePermission
                ->where(["role_id" => $this->params->role_id])
                ->get();
        }


        // $accessRolePermission = $this->RolePermission->all()->groupBy('role_id');
        // ->join('roles', 'role_permission.role_id', '=', 'roles.id');
        if (!$accessRolePermission) {
            $this->success = false;
            $this->responseMessage = "No Role-Permission Data found!";
            return;
        }
        $this->responseMessage = "All Role-Permission Data fetched successfully";
        $this->outputData = $accessRolePermission;
        $this->success = true;
    }

    /**Get All Role-Permission data */
    public function getAllRolePermission()
    {

        $accessRolePermission = $this->RolePermission
            ->join('roles', 'role_permission.role_id', '=', 'roles.id')
            ->select('role_permission.role_id', 'role_permission.access_code', 'roles.title', 'roles.status')
            ->get()
            ->groupBy('role_id');


        if (!$accessRolePermission) {
            $this->success = false;
            $this->responseMessage = "No Role-Permission Data found!";
            return;
        }
        $this->responseMessage = "All Role-Permission Data fetched successfully";
        $this->outputData = $accessRolePermission;
        $this->success = true;
    }

    //Getting All Roles
    public function getAllRoles()
    {
        $accessRoles = $this->AccessRole->where('status', 1)->get();
        if (!$accessRoles) {
            $this->success = false;
            $this->responseMessage = "Role Data not found!";
            return;
        }
        $this->responseMessage = "All Roles Data fetched successfully";
        $this->outputData = $accessRoles;
        $this->success = true;
    }

    //Creating Access Permission
    public function createAccessPermission(Request $request)
    {

        //############ Validation Start
        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "access_code" => v::notEmpty(),
            "module" => v::notEmpty()
        ]);
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        //############ Validation End

        //################ Database insertion Start
        $Permission = $this->AccessPermissions->insert([
            "title" => $this->params->title,
            "access_code" => $this->params->access_code,
            "module" => $this->params->module,
            "parent_id" => $this->params->parent_id,
            "description" => $this->params->description,
            "status" => 1,
        ]);
        //################ Database insertion End


        $this->responseMessage = "New Access Permission Created Successfully";
        $this->outputData = $Permission;
        $this->success = true;
    }

    //Permission Info
    public function AccessPermissionInfo(Request $request)
    {
        if (!isset($this->params->permission_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $permission = DB::table('permissions')->where(['status' => 1, 'id' => $this->params->permission_id])->first();

        if (!$permission) {
            $this->success = false;
            $this->responseMessage = "Data not found !";
            return;
        }

        $this->success = true;
        $this->responseMessage = "Permission info fetched !";
        $this->outputData = $permission;
        return;
    }

    //Creating Access Permission
    public function editAccessPermission(Request $request)
    {
        if (!isset($this->params->permission_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        //############ Validation Start
        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "access_code" => v::notEmpty()
        ]);
        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        //############ Validation End

        //################ Database insertion Start
        $permission = DB::table('permissions')->where(['id' => $this->params->permission_id])->update([
            "title" => $this->params->title,
            "access_code" => $this->params->access_code,
            "module" => $this->params->module,
            "parent_id" => $this->params->parent_id,
            "description" => $this->params->description,
            "status" => 1,
        ]);
        //################ Database insertion End

        if (!$permission) {
            $this->responseMessage = "Something went wrong, Please try again !";
            $this->outputData = [];
            $this->success = false;
        }

        $this->responseMessage = "New Access Permission has been updated Successfully";
        $this->outputData = $permission;
        $this->success = true;
    }

    //Creating Access Role
    public function createAccessRole($request)
    {
        if (!isset($this->params)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        //########### Validation Start
        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "description" => v::notEmpty(),
            "status" => v::notEmpty()->intVal()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }
        //########### Validation End


        //########### Duplicate Check Start
        $AccessRole = $this->AccessRole->where(["title" => $this->params->title])->first();
        if ($AccessRole) {
            $this->success = false;
            $this->responseMessage = "Title with the same name already exists!";
            return;
        }
        //######### Duplicate Check End

        //########### Database Insertion Start

        $newRole = $this->AccessRole->create([
            "title" => $this->params->title,
            "description" => $this->params->description,
            "created_by" => $this->user->id,
            // "updated_by" => $this->params->updated_by,
            "status" => $this->params->status,
        ]);
        //########### Database Insertion End



        $this->responseMessage = "New Access Role Created Successfully";
        $this->outputData = $this->newRole;
        $this->success = true;
    }

    //Creating New Role
    public function createRolePermission(Request $request)
    {

        //################## Validation Start
        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "permission_ids" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $existingRole = $this->AccessRole->where(['title' => $this->params->title, 'status' => 1])->first();
        if ($existingRole) {
            $this->responseMessage = "This role already exist !";
            $this->success = false;
            return;
        }

        $role = $this->AccessRole;
        $role->title = $this->params->title;
        $role->description = $this->params->description;
        $role->status = 1;
        $role->created_by = $this->user->id;
        $role->save();

        $permissionsArr = $this->params->permission_ids;
        // dd($this->params->permissions_id);


        if (count($permissionsArr) > 0) {
            $arr = [];

            foreach ($permissionsArr as $permission) {

                $arr[] = array(
                    "role_id" => $role->id,
                    "access_code" => $permission,
                    "status" => 1
                );
            }

            DB::table('role_permission')->insert($arr);
        }

        $this->responseMessage = "New Role Created Successfully";
        $this->outputData = $role;
        $this->success = true;
    }

    //update Role permissions
    public function editRolePermission(Request $request)
    {

        if (!isset($this->params->role_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        //################## Validation Start
        $this->validator->validate($request, [
            "title" => v::notEmpty(),
            "access_codes" => v::notEmpty()
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $role = $this->AccessRole->where(['id' => $this->params->role_id, 'status' => 1])->first();

        if (!$role) {
            $this->success = false;
            $this->responseMessage = "Role not found !";
            return;
        }

        $existingRole = $this->AccessRole->where(['title' => $this->params->title, 'status' => 1])->first();
        // dd($existingRole);
        if ($existingRole && ($existingRole->id !== $role->id)) {
            $this->responseMessage = "This role already exist !";
            $this->success = false;
            return;
        }

        //delete roles permissions
        DB::table('role_permission')
            ->where('role_id', '=', $role->id)
            ->delete();


        $role->title = $this->params->title;
        $role->description = $this->params->description;
        $role->updated_by = $this->user->id;
        $role->save();

        $permissionsArr = $this->params->access_codes;
        // dd($this->params->permissions_id);


        if (count($permissionsArr) > 0) {
            $arr = [];

            foreach ($permissionsArr as $permission) {

                $arr[] = array(
                    "role_id" => $role->id,
                    "access_code" => $permission,
                    "status" => 1
                );
            }

            DB::table('role_permission')->insert($arr);
        }

        $this->responseMessage = "Role has been updated !";
        $this->outputData = $role;
        $this->success = true;
    }



    public function getAllPermissions()
    {
        $AccessPermissions = $this->AccessPermissions->where('status', 1)->get();
        if (!$AccessPermissions) {
            $this->success = false;
            $this->responseMessage = "Permission Data not found!";
            return;
        }
        $this->responseMessage = "All Permissions fetched successfully";
        $this->outputData = $AccessPermissions;
        $this->success = true;
    }


    public function getAllPermissionNew()
    {

        $AccessPermissions = DB::table('mb_modules')
            ->select(
                'mb_modules.id',
                'mb_modules.title as label',
                'mb_modules.access_code as value',
                DB::raw('JSON_ARRAYAGG(JSON_OBJECT("value", permission_access_codes.access_code, "label", permission_access_codes.title)) as children')
            )
            ->join('permission_access_codes', 'mb_modules.id', '=', 'permission_access_codes.module_id')
            ->whereNull('permission_access_codes.parent_id')
            ->groupBy('mb_modules.id', 'mb_modules.key_name')
            ->get();

        $parentAccessCodes = [];

        //        $parentAccessCodes = DB::table('permission_access_codes')
        //            ->leftJoin('mb_modules', 'mb_modules.id', '=', 'permission_access_codes.module_id')
        //            ->pluck('mb_modules.access_code as m_access_code', 'permission_access_codes.access_code')
        //            ->toArray();


        //        $AccessPermissions = DB::table('mb_modules')
        //            ->select(
        //                'mb_modules.*',
        //                'permission_access_codes.access_code as access_code',
        ////                'permission_access_codes.title as menu_title',
        ////                'permission_access_codes.id as menu_id',
        ////                'permission_access_codes.key_name as module',
        ////                'permission_access_codes.module_id as parent_id',
        ////                'permission_access_codes.description',
        ////                'mb_modules.is_active as status',
        //            )
        //            ->leftJoin('permission_access_codes', 'permission_access_codes.module_id', '=', 'mb_modules.id')
        //            ->distinct()
        //            ->groupBy('permission_access_codes.key_name')
        //            ->where('mb_modules.is_active', 1)->get();

        if (!$AccessPermissions) {
            $this->success = false;
            $this->responseMessage = "Permission Data not found!";
            return;
        }
        $this->responseMessage = "All Permissions fetched successfully";
        $this->outputData = [
            "accessPermissions" => $AccessPermissions,
            "parentAccessCode" => $parentAccessCodes,
        ];
        $this->success = true;
    }




    public function getPermissionsTreeList()
    {
        // dd($this->params->module_id) ;
        if ($this->params->module_id !== null) {
            $permissions = AccessPermission::whereNull('parent_id')
                ->with('children')
                ->where('module', $this->params->module_id)
                ->get();
        } else {
            $permissions = AccessPermission::whereNull('parent_id')
                ->with('children')
                ->get();
        }

        if (!$permissions) {
            $this->success = false;
            $this->responseMessage = "Permission Data not found!";
            return;
        }
        $this->responseMessage = "All Permissions fetched successfully";
        $this->outputData = $permissions;
        $this->success = true;
    }

    public function getPermissionIdsByRoleId()
    {
        $role_id = $this->params->role_id;

        $role = $this->AccessRole->find($role_id);

        $role_permission = DB::table('role_permission')->where('role_id', '=', $role_id)->select('access_code')->get();

        $permission_ids = [];
        foreach ($role_permission as $permission) {
            array_push($permission_ids, $permission->access_code);
        }

        $this->responseMessage = "All Permission ids fetched successfully";
        $this->outputData['role'] = $role;
        $this->outputData['access_codes'] = $permission_ids;
        $this->success = true;
    }

    public function getPermissionByUserRole()
    {
        try {
            $role_id = $this->user->role->id;
            $permissions = $this->RolePermission
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->where(["role_permission.role_id" => $role_id])
                ->select('permissions.access_code')
                ->get();

            if (!$permissions) {
                $this->success = false;
                $this->responseMessage = "No Permission Set Data found!";
                return;
            }

            $permissionArr = [];
            foreach ($permissions as $permission) {
                $permissionArr[] = $permission->access_code;
            }

            $this->responseMessage = "All Permission ids fetched successfully";
            $this->outputData['permissionArr'] = $permissionArr;
            $this->success = true;
        } catch (\Exception $th) {
            // echo $th;
            exit;
        }
    }
}
