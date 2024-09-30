<?php

namespace  App\Controllers\Website;

use App\Auth\Auth;
use App\Helpers\Accounting;
use App\Models\Restaurant\RestaurantInvoice;
use Carbon\Carbon;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Website\WebsitePage;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\Website\WebsiteAboutUsPage;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class WebsiteAboutUsPagesController
{

    protected $customResponse;

    protected $validator;

    protected $params;
    protected $responseMessage;
    protected $outputData;
    protected $success;
    protected $user;
    protected $page;
    protected $aboutUsPage;
    protected $invoices;


    public function __construct()
    {
        $this->customResponse = new CustomResponse();
        $this->page = new WebsitePage();
        $this->aboutUsPage = new WebsiteAboutUsPage();
        $this->validator = new Validator();
        $this->invoices = new RestaurantInvoice();

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
            case 'createAboutUsPage':
                $this->createAboutUsPage($request);
                break;

            case 'getAllAboutUsPages':
                $this->getAllAboutUsPages();
                break;


            case 'getaboutUsPageInfo':
                $this->getaboutUsPageInfo();
                break;

            case 'tableBookingFoodList':
                $this->tableBookingFoodList($request);
                break;

            case 'createHoldFoodOrder':
                $this->createHoldFoodOrder($request, $response);
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

    public function tableBookingFoodList(Request $request)
    {
        $foods =  DB::table('restaurant_foods')
            ->select('restaurant_foods.*', 'tax_heads.tax', 'uploads.file_path as image_path')
            ->join('tax_heads', 'restaurant_foods.tax_head_id', '=', 'tax_heads.id')
            ->leftJoin('uploads', function ($join) {
                $join->on('uploads.id', '=', DB::raw('JSON_UNQUOTE(JSON_EXTRACT(restaurant_foods.image, "$[0]"))'));
            })
            ->where('restaurant_foods.status', 1)
            ->orderBy('restaurant_foods.id', 'desc')
            ->get();


        $this->responseMessage = "food list fetched successfully";
        $this->outputData = $foods;
        $this->success = true;
    }

    public function createHoldFoodOrder(Request $request, Response $response)
    {
        $this->params = CustomRequestHandler::getAllParams($request);

        $this->validator->validate($request, [
            "customer_type" => v::notEmpty(),
            "invoice_type" => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            $this->success = false;
            $this->responseMessage = $this->validator->errors;
            return;
        }

        $now = Carbon::now();
        $date = $now->format('ym');
        $last_invoice = $this->invoices->select('id')->orderBy('id', 'DESC')->first();

        // $account = $this->accounts->where('id', $this->params->account)->where('status', 1)->first();

        $invoice_id = $last_invoice->id + 1;
        if ($invoice_id == null) {
            $invoice_id = 1;
        }
        $invoice_number = sprintf("RI-%s000%d", $date, $invoice_id);

        if ($this->params->customer_type == 'hotel-customer') {
            $customer_id = $this->params->customer_id;
            $guest = null;
        } else {
            $customer_id = null;
            $guest = $this->params->customer_name;
        }

        $items = $this->params->items;
        $count = count($items);

        if ($count == 0) {
            $this->success = false;
            $this->responseMessage = 'Add at-least one item';
            return;
        }

        $totalQty = 0;
        $netVat = 0;
        $net_total = 0;
        $total_amount = $this->params->grandTotalAmount;
        // Loop through the items and sum up the quantities
        foreach ($items as $item) {
            $totalQty += $item['qty'];
            $netVat += $item['taxCalculate'];
            $net_total += $item['unit_price'] * $item['qty'];
        }
        // $this->params->craetionType

        $is_hold =  $this->params->creationType === "due" ? 0 : 1;
        $discount = 0;
        if (!empty($this->params->discount)) {
            $discount = intval($this->params->discount);
        }
        $invoice =  DB::table('restaurant_invoices')
            ->insertGetId([
                "invoice_number" => $invoice_number,
                "customer_id" => $customer_id,
                "customer_type" => $this->params->customer_type,
                "guest_customer" => $guest,
                "remarks" => 'food order',
                "invoice_date" => $this->params->inv_date,
                "invoice_type" => $this->params->invoice_type,
                "total_item" => $count,
                "total_item_qty" => $totalQty,
                "restaurant_table_id" => null,
                "service_charge" => 0,
                "discount" => $discount,
                "net_vat" => $netVat,
                "net_promo" => 0,
                "net_total" => $net_total,
                "total_amount" => $total_amount + $netVat,
                "paid_amount" => $this->params->paid_amount ?? 0,
                "due_amount" => $total_amount - ($this->params->paid_amount ?? 0),
                "is_paid" => 0,
                "is_hold" => $is_hold,
                "delivery_charge" => intval($this->params->deliveryCharge),
                "table_no" => $this->params->table,
                "edit_attempt" => 0,
                "created_by" => 1,
                "status" => 1,
            ]);


        $itemList = [];
        foreach ($items as $item) {
            $itemList[] = array(
                'restaurant_invoice_id' => $invoice,
                'food_type' => 'add-food',
                'restaurant_food_id' => $item['id'],
                'restaurant_setmenu_id' => null,
                'remarks' => 'food order',
                'unit_price' => $item['unit_price'],
                'tax_amount' => $item['tax'],
                'promo_amount' => 0,
                'qty' => $item['qty'],
                'total_price' => $item['total_price'],
                'total_tax' => $item['taxCalculate'],
                'total_promo' => 0,
                'sub_total' => $item['total_price'],
                'created_by' => 1,
                'status' => 1
            );
        }


        if ($this->params->creationType === "hold") {
            DB::table('restaurant_invoices_hold')
                ->insertGetId([
                    'restaurant_invoice_id' => $invoice,
                    "invoice_date" => $this->params->inv_date,
                    'reference' => $this->params->reference,
                    "status" => 1,
                ]);
        }
        $credit = false;
        $credited_note = "";
        $debited_note = "Restaurant Payment";
        $invoice_type = "Restaurant payment";
        if (($this->params->creationType === "due") && ($this->params->customer_type !== "walk-in-customer")) {
            Accounting::accountCustomer($credit, $customer_id, $invoice, $invoice_number, $invoice_type, $total_amount, 0, $credited_note, $debited_note, $this->user->id, false);
        }

        DB::table('restaurant_invoice_items')->insert($itemList);


        $this->responseMessage = "Food Order has been created successfully!";
        $this->outputData = $invoice;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }


    public function createAboutUsPage(Request $request)
    {



        $id = $this->params->id;


        if (!$id) {
            $aboutUsPage = $this->aboutUsPage
                ->insert([
                    "section1_title" => $this->params->section1_title,
                    "section1_description" => $this->params->section1_description,
                    "section1_isChecked" => $this->params->section1_isChecked,


                    "section2_title" => $this->params->section2_title,
                    "section2_photos" => json_encode($this->params->section2_upload_ids),
                    "section2_isChecked" => $this->params->section2_isChecked,


                    "section3_description" => $this->params->section3_description,
                    "section3_photos" => json_encode($this->params->section3_upload_ids),
                    "section3_isChecked" => $this->params->section3_isChecked,

                    "section4_description" => $this->params->section4_description,
                    "section4_isChecked" => $this->params->section4_isChecked,

                    "section5_title" => $this->params->section5_title,
                    "section5_description" => $this->params->section5_description,
                    "section5_photos" => json_encode($this->params->section5_upload_ids),
                    "section5_isChecked" => $this->params->section5_isChecked,



                    "section6_title" => $this->params->section6_title,
                    "section6_1_description" => $this->params->section6_1_description,
                    "section6_2_description" => $this->params->section6_2_description,
                    "section6_photos" => json_encode($this->params->section6_upload_ids),
                    "section6_isChecked" => $this->params->section6_isChecked,


                    "section7_1_title" => $this->params->section7_1_title,
                    "section7_2_title" => $this->params->section7_2_title,
                    "section7_1_description" => $this->params->section7_1_description,
                    "section7_2_description" => $this->params->section7_2_description,
                    "section7_photos" => json_encode($this->params->section7_upload_ids),
                    "section7_isChecked" => $this->params->section7_isChecked,



                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
        } else {

            $aboutUsPage = $this->aboutUsPage->where(["id" => $this->params->id])
                ->update([
                    "section1_title" => $this->params->section1_title,
                    "section1_description" => $this->params->section1_description,
                    "section1_isChecked" => $this->params->section1_isChecked,


                    "section2_title" => $this->params->section2_title,
                    "section2_photos" => json_encode($this->params->section2_upload_ids),
                    "section2_isChecked" => $this->params->section2_isChecked,


                    "section3_description" => $this->params->section3_description,
                    "section3_photos" => json_encode($this->params->section3_upload_ids),
                    "section3_isChecked" => $this->params->section3_isChecked,

                    "section4_description" => $this->params->section4_description,
                    "section4_isChecked" => $this->params->section4_isChecked,

                    "section5_title" => $this->params->section5_title,
                    "section5_description" => $this->params->section5_description,
                    "section5_photos" => json_encode($this->params->section5_upload_ids),
                    "section5_isChecked" => $this->params->section5_isChecked,



                    "section6_title" => $this->params->section6_title,
                    "section6_1_description" => $this->params->section6_1_description,
                    "section6_2_description" => $this->params->section6_2_description,
                    "section6_photos" => json_encode($this->params->section6_upload_ids),
                    "section6_isChecked" => $this->params->section6_isChecked,


                    "section7_1_title" => $this->params->section7_1_title,
                    "section7_2_title" => $this->params->section7_2_title,
                    "section7_1_description" => $this->params->section7_1_description,
                    "section7_2_description" => $this->params->section7_2_description,
                    "section7_photos" => json_encode($this->params->section7_upload_ids),
                    "section7_isChecked" => $this->params->section7_isChecked,



                    "created_by" => $this->user->id,
                    "status" => 1,
                ]);
        }











        $this->responseMessage = "Page has been created successfully";
        $this->outputData = $aboutUsPage;
        $this->success = true;
    }



    public function getAllAboutUsPages()
    {
        $aboutUsPages = DB::table("about_us_page")
            ->orderBy('id', 'desc')
            ->where('status', 1)
            ->get();

        $this->responseMessage = "Pages list fetched successfully";
        $this->outputData = $aboutUsPages;
        $this->success = true;
    }



    public function getaboutUsPageInfo()
    {
        // if (!isset($this->params->id)) {
        //     $this->success = false;
        //     $this->responseMessage = "Parameter missing";
        //     return;
        // }

        $aboutUsPage = $this->aboutUsPage::orderBy('id', 'desc')->first();
        // $aboutUsPage = $this->aboutUsPage
        //     ->find($this->params->id);

        if ($aboutUsPage->status == 0) {
            $this->success = false;
            $this->responseMessage = "slide missing!";
            return;
        }

        if (!$aboutUsPage) {
            $this->success = false;
            $this->responseMessage = "slide not found!";
            return;
        }

        $section2_photos = json_decode($aboutUsPage->section2_photos);


        if (count($section2_photos) > 0) {

            $ids = $section2_photos;
            $section2_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section2_uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }





        $section3_photos = json_decode($aboutUsPage->section3_photos);


        if (count($section3_photos) > 0) {

            $ids = $section3_photos;
            $section3_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section3_uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }


        $section5_photos = json_decode($aboutUsPage->section5_photos);


        if (count($section5_photos) > 0) {

            $ids = $section5_photos;
            $section5_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section5_uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section6_photos = json_decode($aboutUsPage->section6_photos);


        if (count($section6_photos) > 0) {

            $ids = $section6_photos;
            $section6_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section6_uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section7_photos = json_decode($aboutUsPage->section7_photos);


        if (count($section7_photos) > 0) {

            $ids = $section7_photos;
            $section7_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section7_uploadsData[] = DB::table('uploads')->where('uploads.user_id', '=', $this->user->id)->where('uploads.id', '=', $ids[$i])->first();
            }
        }







        $this->responseMessage = "aboutUsPage info fetched successfully";
        $this->outputData = $aboutUsPage;
        $this->outputData['section2_photos'] = $section2_photos;
        $this->outputData['section3_photos'] = $section3_photos;
        $this->outputData['section5_photos'] = $section5_photos;
        $this->outputData['section6_photos'] = $section6_photos;
        $this->outputData['section7_photos'] = $section7_photos;
        $this->outputData['section2_uploadsData'] = $section2_uploadsData;
        $this->outputData['section3_uploadsData'] = $section3_uploadsData;
        $this->outputData['section5_uploadsData'] = $section5_uploadsData;
        $this->outputData['section6_uploadsData'] = $section6_uploadsData;
        $this->outputData['section7_uploadsData'] = $section7_uploadsData;
        $this->success = true;
    }



    public function getaboutUsPageInfoFrweb(Request $request, Response $response)
    {

        $this->params = CustomRequestHandler::getAllParams($request);


        $aboutUsPage = $this->aboutUsPage::orderBy('id', 'desc')->first();

        $section2_photos = json_decode($aboutUsPage->section2_photos);
        if (count($section2_photos) > 0) {

            $ids = $section2_photos;
            $section2_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section2_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }





        $section3_photos = json_decode($aboutUsPage->section3_photos);


        if (count($section3_photos) > 0) {

            $ids = $section3_photos;
            $section3_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section3_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }


        $section5_photos = json_decode($aboutUsPage->section5_photos);


        if (count($section5_photos) > 0) {

            $ids = $section5_photos;
            $section5_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section5_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section6_photos = json_decode($aboutUsPage->section6_photos);


        if (count($section6_photos) > 0) {

            $ids = $section6_photos;
            $section6_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section6_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }




        $section7_photos = json_decode($aboutUsPage->section7_photos);


        if (count($section7_photos) > 0) {

            $ids = $section7_photos;
            $section7_uploadsData = array();

            for ($i = 0; $i < count($ids); $i++) {
                $section7_uploadsData[] = DB::table('uploads')->where('uploads.id', '=', $ids[$i])->first();
            }
        }







        $this->responseMessage = "aboutUsPage info fetched successfully";
        $this->outputData = $aboutUsPage;
        $this->outputData['section2_photos'] = $section2_photos;
        $this->outputData['section3_photos'] = $section3_photos;
        $this->outputData['section5_photos'] = $section5_photos;
        $this->outputData['section6_photos'] = $section6_photos;
        $this->outputData['section7_photos'] = $section7_photos;
        $this->outputData['section2_uploadsData'] = $section2_uploadsData;
        $this->outputData['section3_uploadsData'] = $section3_uploadsData;
        $this->outputData['section5_uploadsData'] = $section5_uploadsData;
        $this->outputData['section6_uploadsData'] = $section6_uploadsData;
        $this->outputData['section7_uploadsData'] = $section7_uploadsData;
        $this->success = true;

        return $this->customResponse->is200Response($response, $this->responseMessage, $this->outputData);
    }
}
