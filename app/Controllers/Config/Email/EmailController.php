<?php

namespace  App\Controllers\Config\Email;
use App\Helpers\Helper;
use App\Auth\Auth;
use App\Models\Email\EmailConfig;

use App\Requests\CustomRequestHandler;
use App\Response\CustomResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;
use App\Models\Users\ClientUsers;
use App\Validation\Validator;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules\Number;
use Respect\Validation\Validator as v;

/**
 * !External Packages
 */
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Database\Capsule\Manager as DB;

class EmailController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    private $helper;
    private $mail;

    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->emailConfig = new EmailConfig();
        $this->validator = new Validator();
        /**
         * !Initialization of SMTP MAIL Server settings
         */
        $this->mail = new PHPMailer();
        $this->mail->IsSMTP();
        $this->mail->Mailer = "smtp";
        $this->mail->SMTPDebug  = 1;
        $this->mail->SMTPAuth   = TRUE;
        $this->mail->SMTPSecure = "tls";
        $this->mail->Port       = 587;
        $this->mail->Host       = "smtp.gmail.com";
        $this->mail->Username   = "uiu025@gmail.com";
        $this->mail->Password   = "heziywkvpmlvdlpl";

        /**
         * !User Instance
         * @param $this->user->id
         */
        $this->user = new ClientUsers();
        $this->helper = new Helper;
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
            case 'test':
                $this->test();
                break;
            case 'saveInfo':
                $this->saveInfo();
                break;
            case 'getEmailConfig':
                $this->getEmailConfig();
                break;
            case 'sendEmail':
                $this->sendEmail();
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

    /**
     * !Sending Email
     */
    public function sendEmail(){

        // $to = "danonymousdk@gmail.com";
        // $from = "uiu025@gmail.com";
        // $body = "";

        // /**
        //  * @param Recipients
        //  */
        // $this->mail->setFrom($from, 'Eshad');
        // //$this->mail->addAddress($to, 'Hasan');     //Add a recipient
        // $this->mail->addAddress($to);               //Name is optional
        // // $this->mail->addReplyTo('info@example.com', 'Information');
        // // $this->mail->addCC('cc@example.com');
        // // $this->mail->addBCC('bcc@example.com');

        // /**
        //  *@param Content
        //  */
        // $this->mail->isHTML(true);                                  //Set email format to HTML
        // $this->mail->Subject = 'Here is the subject';
        // $this->mail->Body    = 'This is the HTML message body <b>in bold!</b>';
        // $this->mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        // /**
        //  * @param Send
        //  */
        // try{
        //     $this->mail->send();
        // }
        // catch(Exception $e){
        //     echo "Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}";
        // }
        $this->helper->sendEmail('uiu025@gmail.com','','hotel@managebeds.com','danonymousdk@gmail.com','This is the HTML message body <b>in bold!</b>');


        $this->responseMessage = "Ok";
        $this->outputData = "Email Send Successfully!";
        $this->success = true;
    }

    /**
     * !Email Data saving Options Start
     */
    public function saveInfo(){


        /**
         * !Checking existing Data
         */

        $type = $this->helper->checkEmailData();
        // dd($this->params->status1);

        if(!!$this->params->value["infoemail"] || !!$this->params->value["infousername"] || !!$this->params->value["infopassword"]  && !!$this->params->status1){
            if($type[0]->type == 'info' || $type[1]->type == 'info' || $type[2]->type == 'info'){
                /**
                 * !Updating ...
                 */
                $updatedData = $this->emailConfig
                ->where('type',"info")
                ->update([
                    'email' => $this->params->value["infoemail"],
                    'username' => $this->params->value["infousername"],
                    'password' => md5($this->params->value["infopassword"]),
                    'status' => $this->params->status1,
                    ]);
            }
            else{
                /**
                 * !New Entry
                 */
                $tableData [] = array(
                    "type" => "info",
                    "email" => $this->params->value["infoemail"],
                    "username" => $this->params->value["infousername"],
                    "password" => md5($this->params->value["infopassword"]),
                    "created_by" => $this->user->id,
                    "status" => 1,
                );
                DB::table('config_email')->insert($tableData);
            }
        }
        if(!!$this->params->value["salesemail"] || !!$this->params->value["salesusername"] || !!$this->params->value["salespassword"] && !!$this->params->status2){
            if($type[0]->type == 'sales' || $type[1]->type == 'sales' || $type[2]->type == 'sales'){
                /**
                 * !Updating ...
                 */
                $updatedData = $this->emailConfig
                ->where('type',"sales")
                ->update([
                    'email' => $this->params->value["salesemail"],
                    'username' => $this->params->value["salesusername"],
                    'password' => md5($this->params->value["salespassword"]),
                    'status' => $this->params->status2,
                    ]);
            }
            else{
                /**
                 * !New Entry
                 */
                $tableData [] = array(
                    "type" => "sales",
                    "email" => $this->params->value["salesemail"],
                    "username" => $this->params->value["salesusername"],
                    "password" => md5($this->params->value["salespassword"]),
                    "created_by" => $this->user->id,
                    "status" => 1,
                );
                DB::table('config_email')->insert($tableData);
            }

        }
        if(!!$this->params->value["supportemail"] || !!$this->params->value["supportusername"] || !!$this->params->value["supportpassword"] && !!$this->params->value["status3"]){
            if($type[0]->type == 'support' || $type[1]->type == 'support' || $type[2]->type == 'support'){
                /**
                 * !Updating ...
                 */
                $updatedData = $this->emailConfig
                ->where('type',"support")
                ->update([
                    'email' => $this->params->value["supportemail"],
                    'username' => $this->params->value["supportusername"],
                    'password' => md5($this->params->value["supportpassword"]),
                    'status' => $this->params->status3,
                    ]);
            }
            else{
                /**
                 * !New Entry
                 */
                $tableData [] = array(
                    "type" => "support",
                    "email" => $this->params->value["supportemail"],
                    "username" => $this->params->value["supportusername"],
                    "password" => md5($this->params->value["supportpassword"]),
                    "created_by" => $this->user->id,
                    "status" => 1,
                );
                DB::table('config_email')->insert($tableData);
            }

        }

        $this->responseMessage = "Ok";
        $this->outputData = $tableData;
        $this->success = true;
    }
    /**
     * !Email Data saving Options End
     */

    /**
     * !Getting Email Configurations Info
     */
    public function getEmailConfig(){
        $getData = $this->emailConfig
        ->all();
        $this->responseMessage = "Ok";
        $this->outputData = $getData;
        $this->success = true;
    }

    public function test(){
        $this->responseMessage = "Ok";
        $this->outputData = "Hello";
        // $this->outputData['creator'] = $department->creator;
        $this->success = true;
    }

}

