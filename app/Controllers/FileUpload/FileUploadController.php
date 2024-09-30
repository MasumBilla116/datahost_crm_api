<?php

namespace  App\Controllers\FileUpload;


use App\Auth\Auth;
use App\Helpers\LocalDir;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\FileUpload\Upload;
use App\Models\Users\ClientUsers;
use Illuminate\Pagination\Paginator;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class FileUploadController
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
        $this->upload = new Upload();
        $this->validator = new Validator();

        $this->responseMessage = "";
        //$this->outputData = response->json([]);
        $this->success = false;
    }

    public function go(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);
        $action = isset($this->params->action) ? $this->params->action : "";

        $this->user = Auth::user($request);

        switch ($action) {
            case 'imageUpload':
                $this->imageUpload($request);
                break;
            case 'getAllUploadedFiles':
                $this->getAllUploadedFiles($response);
                break;
            case 'getSelectedFiles':
                $this->getSelectedFiles();
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

    // any  base 64 image through uploader

    public function imageUpload(Request $request)
    {
        //If Need more extentions are here
        // $type = array(
        //     "jpg"=>"image",
        //     "jpeg"=>"image",
        //     "png"=>"image",
        //     "svg"=>"image",
        //     "webp"=>"image",
        //     "gif"=>"image",
        //     "mp4"=>"video",
        //     "mpg"=>"video",
        //     "mpeg"=>"video",
        //     "webm"=>"video",
        //     "ogg"=>"video",
        //     "avi"=>"video",
        //     "mov"=>"video",
        //     "flv"=>"video",
        //     "swf"=>"video",
        //     "mkv"=>"video",
        //     "wmv"=>"video",
        //     "wma"=>"audio",
        //     "aac"=>"audio",
        //     "wav"=>"audio",
        //     "mp3"=>"audio",
        //     "zip"=>"archive",
        //     "rar"=>"archive",
        //     "7z"=>"archive",
        //     "doc"=>"document",
        //     "txt"=>"document",
        //     "docx"=>"document",
        //     "pdf"=>"document",
        //     "csv"=>"document",
        //     "xml"=>"document",
        //     "ods"=>"document",
        //     "xlr"=>"document",
        //     "xls"=>"document",
        //     "xlsx"=>"document"
        // );

        $uploadsServerDir = "/uploads/";
        $array = array();

        // Velidate if files exist
        if (!empty(array_filter($_FILES['image']['name']))) {

            // Loop through file items
            foreach ($_FILES['image']['name'] as $id => $val) {

                $file_name = str_replace(' ', '_', $_FILES['image']['name'][$id]);
                $file_name = rand(10, 1000000) . "-" . $file_name;
                $targetFilePath  = $uploadsServerDir . $file_name;
                $file_size = $_FILES['image']['size'][$id];
                $file_tmp = $_FILES['image']['tmp_name'][$id];
                $file_type = $_FILES['image']['type'][$id];
                $file_ext = strtolower(end(explode('.', $_FILES['image']['name'][$id])));

                $extensions = array("jpeg", "jpg", "png", "webp", "svg", "gif");

                $uploadsLocalDir = "uploads/";
                if (in_array($file_ext, $extensions)) {
                    if (move_uploaded_file($file_tmp, $uploadsLocalDir . $file_name)) {
                        $array[] = array(
                            'extension' => $file_ext,
                            'file_original_name' => $file_name,
                            'file_path' => $targetFilePath,
                            'user_id' => $this->user->id,
                            'type' => $file_type,
                            'file_size' => $file_size
                        );
                    } else {
                        $this->responseMessage = "File coud not be uploaded.";
                        $this->success = false;
                    }
                } else {
                    $this->responseMessage = "Only .jpg, .jpeg and .png file formats allowed.";
                    $this->success = false;
                }
            }

            // var_dump($array);
            DB::table('uploads')->insert($array);

            $this->responseMessage = "Files successfully uploaded.";
            $this->success = true;
        } else {
            // Error
            $this->responseMessage = "Please select a file to upload.";
            $this->success = false;
        }
    }

    public function getAllUploadedFiles(Response $response)
    {
        $files = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->orderBy('uploads.id', 'desc')->get();
        //$files = $this->upload->where('uploads.user_id','=',$this->user->id)->orderBy('uploads.id','desc')->paginate(10);

        if (!$files) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Fetched all uploaded files";
        $this->outputData = $files;
        $this->success = true;
    }

    public function getSelectedFiles()
    {

        $ids = $this->params->upload_files;
        $uploadsData = array();

        for ($i = 0; $i < count($ids); $i++) {
            $uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
        }


        if (!$uploadsData) {
            $this->success = false;
            $this->responseMessage = "No data found!";
            return;
        }

        $this->responseMessage = "Fetched all uploaded files";
        $this->outputData = $uploadsData;
        $this->success = true;
    }
}
