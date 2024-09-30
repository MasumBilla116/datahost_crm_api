<?php

namespace  App\Controllers\Booking;

use App\Auth\Auth;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Users\ClientUsers;
use Illuminate\Database\Capsule\Manager as DB;
use App\Requests\CustomRequestHandler;

use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class BookingNoteController
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
         
            case 'createBookingNote':
                $this->createBookingNote($request);
                break;    

            case 'bookingNoteInfo':
                $this->bookingNoteInfo($request);
                break; 
            case 'updateBookingNote':
                $this->updateBookingNote($request);
                break;                  
            case 'removeBookingNote':
                $this->removeBookingNote();
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

    public function createBookingNote(Request $request){
        $this->validator->validate($request, [
            "note"=>v::notEmpty(),
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $booking_note = DB::table('customer_booking_notes')->insert([
            'booking_master_id'=>$this->params->booking_master_id,
            'note'=>$this->params->note,
            'created_by'=>$this->user->id,
            'status'=>1
        ]);

        $this->responseMessage = "New booking note has been created successfully";
        $this->outputData = $booking_note;
        $this->success = true;
    }

    public function bookingNoteInfo(Request $request){

        $this->validator->validate($request, [
            "booking_note_id"=>v::notEmpty(),
         ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        if(!isset($this->params->booking_note_id)){
            $this->success = false;
            $this->responseMessage = "Parameter missing";
            return;
        }

        $booking_note = DB::table('customer_booking_notes')->where(['id'=>$this->params->booking_note_id, 'status'=>1])->first();

        if(!$booking_note){
            $this->responseMessage = "No data available !";
            $this->outputData = [];
            $this->success = false;    
        }

        $this->responseMessage = "Booking note has been fetched successfully";
        $this->outputData = $booking_note;
        $this->success = true;
    }

    public function updateBookingNote(Request $request){
        $this->validator->validate($request, [
            "booking_note_id"=>v::notEmpty(),
            "note"=>v::notEmpty()
         ]);


        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $booking_note = DB::table('customer_booking_notes')->where(['id'=>$this->params->booking_note_id, 'status'=>1])
            ->update([
                'note'=>$this->params->note,
                'updated_by'=>$this->user->id
            ]);

        $this->responseMessage = "Booking note has been updated successfully !";
        $this->outputData = $booking_note;
        $this->success = true;
    }

    public function removeBookingNote(){

        $booking_note = DB::table('customer_booking_notes')->where(['id'=>$this->params->booking_note_id, 'status'=>1])
            ->update([
                'status'=>0,
                'updated_by'=>$this->user->id
            ]);

        $this->responseMessage = "Booking note has been successfully removed !";
        $this->outputData = $booking_note;
        $this->success = true;
    }
}