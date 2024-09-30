<?php

namespace  App\Controllers\Inventory;

use App\Auth\Auth;
use App\Models\Inventory\InventoryCategory;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as v;

class CategoryController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $categories;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->categories = new InventoryCategory();
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
            case 'createCategory':
                $this->createCategory($request, $response);
                break;
            case 'getAllCategories':
                $this->getAllCategories($request, $response);
                break;
            case 'getSubCategories':
                $this->getSubCategories($request, $response);
                break;
            case 'getCategoryInfo':
                $this->getCategoryInfo($request, $response);
                break;
            case 'editCategory':
                $this->editCategory($request, $response);
                break;
            case 'deleteCategory':
                $this->deleteCategory($request, $response);
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


    public function createCategory(Request $request, Response $response)
    {
        $this->validator->validate($request, [
            "name" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate category
        $current_category = $this->categories->where(["name" => $this->params->name])->where('status', 1)->first();
        if ($current_category) {
            $this->success = false;
            $this->responseMessage = "Category with the same name already exists!";
            return;
        }

        $category = $this->categories->create([
            "name" => $this->params->name,
            "parent_id" => $this->params->parentId,
            "description" => $this->params->description,
            "created_by" => $this->user->id,
            "status" => 1,
        ]);

        $this->responseMessage = "New Category created successfully";
        $this->outputData = $category;
        $this->success = true;
    }

    public function getAllCategories()
    {
        $categories = $this->categories->with(['items', 'parent', 'creator', 'updator'])->where('status', 1)->orderBy("id", "desc")->get();

        $this->responseMessage = "Categories list fetched successfully";
        $this->outputData = $categories;
        $this->success = true;
    }

    public function getSubCategories()
    {
        $categories = $this->categories->with(['childrenRecursive'])->where('status', 1)->where('parent_id', '=', 0)->get();

        $this->responseMessage = "Categories list fetched successfully";
        $this->outputData = $categories;
        $this->success = true;
    }

    public function getCategoryInfo(Request $request, Response $response)
    {
        if (!isset($this->params->category_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $category = $this->categories->find($this->params->category_id);

        if ($category->status == 0) {
            $this->success = false;
            $this->responseMessage = "Category missing!";
            return;
        }

        if (!$category) {
            $this->success = false;
            $this->responseMessage = "Category not found!";
            return;
        }

        $this->responseMessage = "Category info fetched successfully";
        $this->outputData = $category;
        $this->success = true;
    }

    public function editCategory(Request $request, Response $response)
    {
        if (!isset($this->params->category_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $category = $this->categories->where('status', 1)->find($this->params->category_id);

        if (!$category) {
            $this->success = false;
            $this->responseMessage = "Category not found!";
            return;
        }

        $this->validator->validate($request, [
            "name" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        //check duplicate category
        $current_category = $this->categories->where(["name" => $this->params->name])->where('status', 1)->first();
        if ($current_category && $current_category->id != $this->params->category_id) {
            $this->success = false;
            $this->responseMessage = "Category with the same name has already exists!";
            return;
        }

        $editedCategory = $category->update([
            "name" => $this->params->name,
            "parent_id" => $this->params->parentId,
            "description" => $this->params->description,
            "updated_by" => $this->user->id,
            "status" => 1,
        ]);

        $this->responseMessage = "Category Updated successfully";
        $this->outputData = $editedCategory;
        $this->success = true;
    }

    public function deleteCategory(Request $request, Response $response)
    {
        if (!isset($this->params->category_id)) {
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }
        $category = $this->categories->find($this->params->category_id);

        if (!$category) {
            $this->success = false;
            $this->responseMessage = "Category not found!";
            return;
        }

        $deletedCategory = $category->update([
            "status" => 0,
        ]);

        $this->responseMessage = "Category Deleted successfully";
        $this->outputData = $deletedCategory;
        $this->success = true;
    }
}
