<?php

use Slim\App;
use App\Controllers\Auth\WebsiteReviewController;
use App\Controllers\HRM\LocationController;

return function (App $app) {


    //public group routes
    $app->group("/web", function ($app) {



        /**
         * -------------------------------------------------------
         * ------------------- Web User Login and Register --------
         * --------------------------------------------------------
         * */
        $app->post("/login", [\App\Controllers\Auth\AuthController::class, "webLogin"]);
        $app->post("/register", [\App\Controllers\Auth\AuthController::class, "webRegister"]);
        $app->post("/resetPassword", [\App\Controllers\Auth\AuthController::class, "webResetPassword"]);

        /**
         * -------------------------------------------------------
         * ------------------- Online Payment --------------------
         * --------------------------------------------------------
         * */

        $app->get("/app/bkash/initializePayment", [\App\Controllers\Payments\Bkash::class, "initializePayment"]);
        $app->post("/app/bkash/createPayment", [\App\Controllers\Payments\Bkash::class, "createPayment"]);
        $app->get("/app/bkash/paymentExecute", [\App\Controllers\Payments\Bkash::class, "paymentExecute"]);
        // $app->post("/app/customer/getOnlineRoomBookingInfo", [\App\Controllers\Website\CustomerBookingController::class, "getOnlineRoomBookingInfo"]);


        /**
         * -------------------------------------------------------
         * ----------------------- End Online Payment ------------
         * -------------------------------------------------------
         * */


        $app->get("/app/website/table-booking-food-list", [\App\Controllers\Website\WebsiteAboutUsPagesController::class, "go"]);
        $app->post("/app/website/table-booking-submit", [\App\Controllers\Website\WebsiteAboutUsPagesController::class, "createHoldFoodOrder"]);


        // settings    app\Controllers\Settings\GeneralController.php
        $app->get("/app/settings/houseRules", [\App\Controllers\Settings\GeneralController::class, "houseRules"]);

        // web user information
        $app->post("/app/settings/update/user-info", [\App\Controllers\Customers\CustomerController::class, "updateUserInfo"]);


        // get address
        $app->get("/app/get-all-countries", [\App\Controllers\Auth\AuthController::class, "allCountries"]);
        $app->get("/app/get-state/{countryId}", [\App\Controllers\Auth\AuthController::class, "getState"]);
        $app->get("/app/get-city/{stateId}", [\App\Controllers\Auth\AuthController::class, "getCity"]);
        $app->get("/app/get/invoiceTermsAndConditions", [\App\Controllers\Auth\AuthController::class, "invoiceTermsAndConditions"]);
        $app->get("/app/get/currency", [\App\Controllers\Auth\AuthController::class, "getActiveCurrency"]);



        $app->get("/frontend", [\App\Controllers\Customers\CustomerController::class, "web"]); // frontend route demo
        $app->post("/app/website/register", [\App\Controllers\Auth\AuthController::class, "register"]); // frontend route demo


        //home slider routes
        $app->get("/app/website/slide", [\App\Controllers\Website\WebsiteSlideController::class, "homeSliderInfo"]);


        // ROOMS ACCOMMODATION
        $app->get("/app/roomManagement/checkin-checkout-time", [\App\Controllers\RoomManagement\RoomType\RoomTypeController::class, "getCheckinCheckoutTime"]);
        $app->get("/app/roomManagement/room_facility", [\App\Controllers\RoomManagement\RoomType\RoomTypeController::class, "allRoomTypesForWeb"]);
        $app->get("/app/roomManagement/room_facility/{room_type_id}", [\App\Controllers\RoomManagement\RoomType\RoomTypeController::class, "roomTypeInfoForWeb"]);
        $app->get("/app/roomManagement/user_info/{state_id}/{city_id}", [\App\Controllers\RoomManagement\RoomType\RoomTypeController::class, "userInfo"]);
        // check promo code
        $app->get("/app/roomManagement/get-promo-code/{promocode}", [\App\Controllers\RoomManagement\RoomType\RoomTypeController::class, "getPromoCode"]);

        // reservation info
        $app->get("/app/reservation/getAllHotelConfigData", [\App\Controllers\Settings\GeneralController::class, "getAllHotelConfigData"]);


        //Customers
        $app->get("/app/customer/profile/{id}", [\App\Controllers\Website\CustomerController::class, "customerInfo"]);
        $app->put("/app/customer/profile/{id}", [\App\Controllers\Website\CustomerController::class, "updateCustomer"]);


        //Customers booking
        // $app->post("/app/customer/booking", [\App\Controllers\Website\CustomerBookingController::class, "bookingCreation"]);
        // $app->get("/app/customer/booking/{userId}", [\App\Controllers\Website\CustomerBookingController::class, "getCustomerBookingInfo"]);
        // $app->get("/app/customer/booking/cancel-by-user/{invoice_id}", [\App\Controllers\Website\CustomerBookingController::class, "roomBookingCancelByUser"]);
        // $app->post("/app/roomManagement/roomPrice", [\App\Controllers\Website\CustomerBookingController::class, "additionalPriceDetails"]);

        // Facilities
        $app->get("/app/facilities", [\App\Controllers\Facilities\FacilitiesController::class, "getAllFacilitiesForWeb"]);
        $app->get("/app/resort-facilities/permissions", [\App\Controllers\Facilities\FacilitiesController::class, "checkResortFacilitiesPermission"]);
        $app->get("/app/facilities/{facilityId}", [\App\Controllers\Facilities\FacilitiesController::class, "getfacilityDetails"]);

        //section
        $app->get("/app/section/home", [\App\Controllers\Website\WebsiteSectionController::class, "getAllHomeSection"]);
        $app->get("/app/webpage/logo", [\App\Controllers\Website\WebsiteSectionController::class, "webLogo"]);

        $app->get("/app/notification/{id}", [\App\Controllers\Website\WebsiteSectionController::class, "notification"]);
        $app->get("/app/get/unread-notification/{id}", [\App\Controllers\Website\WebsiteSectionController::class, "getUnreadnotification"]);
        $app->get("/app/notification/read/{uid}/{notificationId}", [\App\Controllers\Website\WebsiteSectionController::class, "notificationRead"]);
        $app->get("/app/get/notification/{id}", [\App\Controllers\Website\WebsiteSectionController::class, "getAllNotification"]);

        $app->get("/app/webpage/headerSetting", [\App\Controllers\Website\WebsiteSectionController::class, "getHomePageStngInfo"]);
        $app->get("/app/section/nearby", [\App\Controllers\Website\WebsiteSectionController::class, "getNearBySection"]);
        $app->get("/app/section/latest-news", [\App\Controllers\Website\WebsiteSectionController::class, "getLatestNewsSection"]);
        $app->post("/app/section/get-news/{id}", [\App\Controllers\Website\WebsiteSectionController::class, "getNews"]);


        //About Us Pages
        $app->get("/app/website/aboutusInfoWeb", [\App\Controllers\Website\WebsiteAboutUsPagesController::class, "getaboutUsPageInfoFrweb"]);

        // FAQ
        $app->get("/app/website/get-all-faq", [\App\Controllers\Website\WebsiteFAQController::class, "allFAQ"]);


        //Privacy Policies Pages
        $app->get("/app/website/privacy_policies", [\App\Controllers\Website\WebsitePrivacyPolicyPagesController::class, "getPrivacyPoliciesInfoFrweb"]);
        // Terms ConditionPages
        $app->get("/app/website/terms_condition", [\App\Controllers\Website\WebsiteTermsConditionPagesController::class, "getTermsConditionInfoFrweb"]);

        //return_refund Pages
        $app->get("/app/website/return_refund", [\App\Controllers\Website\WebsiteReturnRefundController::class, "getReturnRefundInfoFrweb"]);

        // Review
        $app->post("/app/website/review", [\App\Controllers\Website\WebsiteReviewController::class, "websiteReview"]);
        $app->get("/app/website/allreview", [\App\Controllers\Website\WebsiteReviewController::class, "getAllReviewfrWeb"]);


        // contact
        $app->post("/app/website/contact_us", [\App\Controllers\Website\WebsiteContactUsController::class, "createWebsiteContact"]); // frontend route demo
        $app->get("/app/website/contact_us", [\App\Controllers\Website\WebsiteReviewController::class, "getAllReviewfrWeb"]);


        //Gallery Pages
        $app->get("/app/website/gallery", [\App\Controllers\Website\WebsiteGalleryPagesController::class, "getGalleryPageInfoFrweb"]);
    });
};
