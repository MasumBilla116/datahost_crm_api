<?php

use Slim\App;
use App\Controllers\CurrencyController;
use App\Controllers\HRM\LoanController;
use Psr\Http\Message\ResponseInterface;
use App\Controllers\DashboardController;
use App\Controllers\HRM\LeaveController;
use App\Middleware\LaravelApiMiddleware;
use App\Controllers\HRM\SalaryController;
use App\Controllers\Users\UserController;
use App\Controllers\HRM\HolidayController;
use App\Controllers\HRM\PayRollController;
use App\Controllers\Stock\StockController;
use App\Controllers\HRM\EmployeeController;
use App\Controllers\HRM\LocationController;
use App\Controllers\Settings\TaxController;
use App\Controllers\Locker\LockerController;
use Psr\Http\Message\ServerRequestInterface;
use App\Controllers\HRM\DepartmentController;
use App\Controllers\Inventory\ItemController;
use App\Controllers\Booking\BookingController;
use App\Controllers\HRM\DesignationController;
use App\Controllers\Restaurant\FoodController;
use App\Controllers\BusinessSettingsController;
use App\Controllers\Purchase\InvoiceController;
use App\Controllers\Restaurant\FloorController;
use App\Controllers\Restaurant\PromoController;
use App\Controllers\Restaurant\TableController;
use App\Controllers\Settings\GeneralController;
use App\Controllers\Settings\ServiceController;
use App\Controllers\Transport\DriverController;
use App\Controllers\Accounts\AccountsController;
use App\Controllers\HRM\HRM_DashboardController;
use App\Controllers\Inventory\VoucherController;
use App\Controllers\Purchase\SupplierController;
use App\Controllers\Transport\VehicleController;
use App\Controllers\Config\Email\EmailController;
use App\Controllers\Customers\CustomerController;
use App\Controllers\Inventory\CategoryController;
use App\Controllers\Purchase\QuotationController;
use App\Controllers\Restaurant\SetmenuController;
use App\Controllers\Website\WebsiteFAQController;
use App\Controllers\Booking\BookingNoteController;
use App\Controllers\Booking\RoomServiceController;
use App\Controllers\Inventory\WarehouseController;
use App\Controllers\Restaurant\MenutypeController;
use App\Controllers\Website\WebsiteMenuController;
use App\Controllers\Website\WebsiteNewsController;
use App\Controllers\Website\WebsitePageController;
use App\Controllers\HouseKeeping\LaundryController;
use App\Controllers\Locker\LockerEntriesController;
use App\Controllers\Payments\PaymentSlipController;
use App\Controllers\Restaurant\FoodorderController;
use App\Controllers\Restaurant\RestaurantDashboard;
use App\Controllers\Website\WebsitePagesController;
use App\Controllers\Website\WebsiteSlideController;
use App\Controllers\Attendance\AttendanceController;
use App\Controllers\Facilities\FacilitiesController;
use App\Controllers\FileUpload\FileUploadController;
use App\Controllers\Settings\UserSettingsController;
use App\Controllers\Website\WebsiteReviewController;
use App\Controllers\Accounts\AccountSectorController;
use App\Controllers\Locker\LockerDashboardController;
use App\Controllers\Permissions\PermissionController;
use App\Controllers\Restaurant\ResCategoryController;
use App\Controllers\Accounts\AccountVoucherController;
use App\Controllers\Accounts\PaymentVoucherController;
use App\Controllers\HouseKeeping\AssigntaskController;
use App\Controllers\RoomManagement\RoomTowerDashboard;
use App\Controllers\Website\CustomerBookingController;
use App\Controllers\Website\WebsiteTemplateController;
use App\Controllers\Booking\BookingDashboardController;
use App\Controllers\Customers\CUST_DashboardController;
use App\Controllers\Transport\VehicleBookingController;
use App\Controllers\Website\WebsiteContactUsController;
use App\Controllers\Customers\CorporateClientController;
use App\Controllers\HouseKeeping\HousekeepingController;
use App\Controllers\Locker\LockerLuggageItemsController;
use App\Controllers\Accounts\ACCOUNT_DashboardController;
use App\Controllers\Restaurant\RestaurantTableController;
use App\Controllers\RoomManagement\Tower\TowerController;
use App\Controllers\Settings\SETTING_DashboardController;
use App\Controllers\Transport\VehicleDashboardController;
use App\Controllers\GeneralLedger\GeneralLedgerController;
use App\Controllers\Inventory\WarehouseLocationController;
use App\Controllers\Purchase\SUPPLIER_DashboardController;
use App\Controllers\Website\WebsiteAboutUsPagesController;
use App\Controllers\Website\WebsiteGalleryPagesController;
use App\Controllers\Website\WebsiteReturnRefundController;
use App\Controllers\HouseKeeping\HousekeepingSlipController;
use App\Controllers\HouseKeeping\LaundryDashboardController;
use App\Controllers\Inventory\INVENTORY_DashboardController;
use App\Controllers\RosterManagement\Roster\RosterController;
use App\Controllers\RoomManagement\RoomType\RoomTypeController;
use App\Controllers\Roomservice\Roomservice_DashboardController;
use App\Controllers\Website\WebsitePrivacyPolicyPagesController;
use App\Controllers\HouseKeeping\HouseKeepingDashboardController;
use App\Controllers\RoomManagement\RoomPrice\RoomPriceController;
use App\Controllers\Website\WebsiteTermsConditionPagesController;

use App\Controllers\HouseKeeping\LaundryReceiveBackSlipController;
use App\Controllers\RosterManagement\DutyShift\DutyShiftController;
use App\Controllers\RoomManagement\RoomCategory\RoomCategoryController;
use App\Controllers\RoomManagement\RoomFacility\RoomFacilityController;
use App\Controllers\RoomManagement\RoomPrice\HourlyRoomPriceController;
use App\Controllers\HouseKeeping\LaundryVoucher\LaundryVoucherController;
use App\Controllers\Inventory\UnitTypeController;
use App\Controllers\Inventory\ItemTypeController;
use App\Controllers\Purchase\PurchaseController;
use App\Controllers\RosterManagement\Assignment\RosterAssignmnetController;

return function (App $app) {



    $app->group("/auth", function ($app) {
        $app->post("/login", [\App\Controllers\Auth\AuthController::class, "login"]);
        $app->post("/logout", [\App\Controllers\Auth\AuthController::class, "logout"]);
    });

    // HRM DASHBOAD
    $app->get('/app/hrm/dashboard', [HRM_DashboardController::class, 'go']);
    $app->post('/app/hrm/dashboard', [HRM_DashboardController::class, 'go']);

    // Employee
    $app->get('/app/hrm/employee', [EmployeeController::class, 'go']);
    $app->post('/app/hrm/employee', [EmployeeController::class, 'go']);
    //Employee End

    //Location
    $app->get('/app/hrm/location', [LocationController::class, 'go']);
    $app->post('/app/hrm/location', [LocationController::class, 'go']);


    //File Uploader
    $app->get('/app/uploader/upload', [FileUploadController::class, 'go']);
    $app->post('/app/uploader/upload', [FileUploadController::class, 'go']);
    //Customer
    $app->get('/app/customers/addNewCustomer', [CustomerController::class, 'go']);
    $app->post('/app/customers/addNewCustomer', [CustomerController::class, 'go']);

    //Customer dashboard
    $app->get('/app/customers/dashboard', [CUST_DashboardController::class, 'go']);
    $app->post('/app/customers/dashboard', [CUST_DashboardController::class, 'go']);


    //customer payment collections
    $app->get('/app/booking/payment/slip', [PaymentSlipController::class, 'go']);
    $app->post('/app/booking/payment/slip', [PaymentSlipController::class, 'go']);
    //Room Service dashboard
    $app->get('/app/room-service/dashboard', [Roomservice_DashboardController::class, 'go']);
    $app->post('/app/room-service/dashboard', [Roomservice_DashboardController::class, 'go']);

    //Currency settings
    $app->get('/app/business-settings', [BusinessSettingsController::class, 'go']);
    $app->post('/app/business-settings', [BusinessSettingsController::class, 'go']);

    //Currency settings
    $app->get('/app/currency', [CurrencyController::class, 'go']);
    $app->post('/app/currency', [CurrencyController::class, 'go']);

    //Dashboard settings
    $app->get('/app/dashboard', [DashboardController::class, 'go']);
    $app->post('/app/dashboard', [DashboardController::class, 'go']);

    $app->get('/app/accounts/payment/voucher', [PaymentVoucherController::class, 'go']);
    $app->post('/app/accounts/payment/voucher', [PaymentVoucherController::class, 'go']);

    //user settings
    $app->get('/app/settings/site', [UserSettingsController::class, 'go']);
    $app->post('/app/settings/site', [UserSettingsController::class, 'go']);

    //General Ledger
    $app->get('/app/general-ledger', [GeneralLedgerController::class, 'go']);
    $app->post('/app/general-ledger', [GeneralLedgerController::class, 'go']);

    ######### -> Permission Controller Start
    $app->get('/app/permissions/permission', [PermissionController::class, 'go']);
    $app->post('/app/permissions/permission', [PermissionController::class, 'go']);
    ######### -> Permission Controller End

    ######### -> Supplier Controller Start
    $app->get('/app/purchase/supplier', [SupplierController::class, 'go']);
    $app->post('/app/purchase/supplier', [SupplierController::class, 'go']);
    ######### -> Supplier Controller End

    ######### ->Quotations Controller Start
    $app->get('/app/purchase/quotation', [QuotationController::class, 'go']);
    $app->post('/app/purchase/quotation', [QuotationController::class, 'go']);
    ######### ->Quotations Controller End

    ######### ->Invoice Controller Start
    $app->get('/app/purchase/invoice', [InvoiceController::class, 'go']);
    $app->post('/app/purchase/invoice', [InvoiceController::class, 'go']);


    ######### -> Purchase Controller
    $app->get('/app/purchase-product', [PurchaseController::class, 'go']);
    $app->post('/app/purchase-product', [PurchaseController::class, 'go']);
    ######### ->Invoice Controller End

    ######### -> Departments
    $app->get('/app/hrm/departments', [DepartmentController::class, 'go']);
    $app->post('/app/hrm/departments', [DepartmentController::class, 'go']);

    ######## -> Email Configuration
    $app->get('/app/config/email', [EmailController::class, 'go']);
    $app->post('/app/config/email', [EmailController::class, 'go']);

    ######## -> Accounts Controller
    $app->get('/app/accounts', [AccountsController::class, 'go']);
    $app->post('/app/accounts', [AccountsController::class, 'go']);

    // Designations
    $app->get('/app/hrm/designations', [DesignationController::class, 'go']);
    $app->post('/app/hrm/designations', [DesignationController::class, 'go']);

    // Holidays
    $app->get('/app/hrm/holidays', [HolidayController::class, 'go']);
    $app->post('/app/hrm/holidays', [HolidayController::class, 'go']);

    // Leaves
    $app->get('/app/hrm/leaves', [LeaveController::class, 'go']);
    $app->post('/app/hrm/leaves', [LeaveController::class, 'go']);

    //Inventory Management
    $app->get('/app/inventory/category', [CategoryController::class, 'go']);
    $app->post('/app/inventory/category', [CategoryController::class, 'go']);

    $app->get('/app/inventory/unitType', [UnitTypeController::class, 'go']);
    $app->post('/app/inventory/unitType', [UnitTypeController::class, 'go']);

    $app->get('/app/inventory/itemType', [ItemTypeController::class, 'go']);
    $app->post('/app/inventory/itemType', [ItemTypeController::class, 'go']);

    //Warehouse Management
    $app->get('/app/inventory/warehouse', [WarehouseController::class, 'go']);
    $app->post('/app/inventory/warehouse', [WarehouseController::class, 'go']);
    //Warehouse Location Management
    $app->get('/app/inventory/warehouse/location', [WarehouseLocationController::class, 'go']);
    $app->post('/app/inventory/warehouse/location', [WarehouseLocationController::class, 'go']);
    //Inventory Item
    $app->get('/app/inventory/items', [ItemController::class, 'go']);
    $app->post('/app/inventory/items', [ItemController::class, 'go']);
    //consumption-voucher
    $app->get('/app/inventory/consumption-voucher', [VoucherController::class, 'go']);
    $app->post('/app/inventory/consumption-voucher', [VoucherController::class, 'go']);


    // Manage Tax settings
    $app->get('/app/settings/tax', [TaxController::class, 'go']);
    $app->post('/app/settings/tax', [TaxController::class, 'go']);

    // Manage Tax Service
    $app->get('/app/settings/service', [ServiceController::class, 'go']);
    $app->post('/app/settings/service', [ServiceController::class, 'go']);

    // Manage Account Sector
    $app->get('/app/accounts/sector', [AccountSectorController::class, 'go']);
    $app->post('/app/accounts/sector', [AccountSectorController::class, 'go']);

    // Manage Account Voucher
    $app->get('/app/accounts/voucher', [AccountVoucherController::class, 'go']);
    $app->post('/app/accounts/voucher', [AccountVoucherController::class, 'go']);

    // Vehicle dashboard
    $app->get('/app/vehicle/dashboard', [VehicleDashboardController::class, 'go']);
    $app->post('/app/vehicle/dashboard', [VehicleDashboardController::class, 'go']);


    // Manage Account Voucher
    $app->get('/app/transport/vehicles', [VehicleController::class, 'go']);
    $app->post('/app/transport/vehicles', [VehicleController::class, 'go']);

    // General setting
    $app->get('/app/settings/general', [GeneralController::class, 'go']);
    $app->post('/app/settings/general', [GeneralController::class, 'go']);

    // Manage duty shifts
    $app->get('/app/rosterManagement/dutyShift', [DutyShiftController::class, 'go']);
    $app->post('/app/rosterManagement/dutyShift', [DutyShiftController::class, 'go']);

    // Manage roster management
    $app->get('/app/rosterManagement/roster', [RosterController::class, 'go']);
    $app->post('/app/rosterManagement/roster', [RosterController::class, 'go']);

    // Roster Assignment
    $app->get('/app/rosterManagement/assignment', [RosterAssignmnetController::class, 'go']);
    $app->post('/app/rosterManagement/assignment', [RosterAssignmnetController::class, 'go']);

    // Website Menus
    $app->get('/app/website/menu', [WebsiteMenuController::class, 'go']);
    $app->post('/app/website/menu', [WebsiteMenuController::class, 'go']);

    // Website Template
    $app->get('/app/website/template', [WebsiteTemplateController::class, 'go']);
    $app->post('/app/website/template', [WebsiteTemplateController::class, 'go']);

    // Website Page
    $app->get('/app/website/page', [WebsitePageController::class, 'go']);
    $app->post('/app/website/page', [WebsitePageController::class, 'go']);

    // Locker management
    $app->get('/app/locker/dashboard', [LockerDashboardController::class, 'go']);
    $app->post('/app/locker/dashboard', [LockerDashboardController::class, 'go']);


    // Locker management
    $app->get('/app/locker', [LockerController::class, 'go']);
    $app->post('/app/locker', [LockerController::class, 'go']);

    // Locker Entry management/app/locker/entry
    $app->get('/app/locker/entry', [LockerEntriesController::class, 'go']);
    $app->post('/app/locker/entry', [LockerEntriesController::class, 'go']);

    // Luggage Items management
    $app->get('/app/locker/luggage/entry', [LockerLuggageItemsController::class, 'go']);
    $app->post('/app/locker/luggage/entry', [LockerLuggageItemsController::class, 'go']);

    //Customer management
    $app->get('/app/customers', [CustomerController::class, 'go']);
    $app->post('/app/customers', [CustomerController::class, 'go']);

    //Client management
    $app->get('/app/customers/clients', [CorporateClientController::class, 'go']);
    $app->post('/app/customers/clients', [CorporateClientController::class, 'go']);


    // Manage Drivers
    $app->get('/app/transport/drivers', [DriverController::class, 'go']);
    $app->post('/app/transport/drivers', [DriverController::class, 'go']);

    // Manage vehicle booking
    $app->get('/app/transport/vehicle-booking', [VehicleBookingController::class, 'go']);
    $app->post('/app/transport/vehicle-booking', [VehicleBookingController::class, 'go']);

    // Manage loan
    $app->get('/app/hrm/loan', [LoanController::class, 'go']);
    $app->post('/app/hrm/loan', [LoanController::class, 'go']);


    // Manage Stock
    $app->get('/app/stock', [StockController::class, 'go']);
    $app->post('/app/stock', [StockController::class, 'go']);


    // Website slide
    $app->get('/app/website/slide', [WebsiteSlideController::class, 'go']);
    $app->post('/app/website/slide', [WebsiteSlideController::class, 'go']);

    // website news
    $app->get("/app/website/news", [WebsiteNewsController::class, "go"]);
    $app->post("/app/website/news", [WebsiteNewsController::class, "go"]);


    // Website Facilities
    $app->get('/app/facilities', [FacilitiesController::class, 'go']);
    $app->post('/app/facilities', [FacilitiesController::class, 'go']);


    // Website Pages
    $app->get('/app/website/pages', [WebsitePagesController::class, 'go']);
    $app->post('/app/website/pages', [WebsitePagesController::class, 'go']);



    // Website Pages
    $app->get('/app/website/review', [WebsiteReviewController::class, 'go']);
    $app->post('/app/website/review', [WebsiteReviewController::class, 'go']);

    // Website FAQ page
    $app->get('/app/website/faq', [WebsiteFAQController::class, 'go']);
    $app->post('/app/website/faq', [WebsiteFAQController::class, 'go']);


    // Website About Us Page
    $app->get('/app/website/aboutus', [WebsiteAboutUsPagesController::class, 'go']);
    $app->post('/app/website/aboutus', [WebsiteAboutUsPagesController::class, 'go']);


    // Website Privacy Policies Page
    $app->get('/app/website/privacy-policy', [WebsitePrivacyPolicyPagesController::class, 'go']);
    $app->post('/app/website/privacy-policy', [WebsitePrivacyPolicyPagesController::class, 'go']);


    // Website Terms & Condition Page
    $app->get('/app/website/terms_condition', [WebsiteTermsConditionPagesController::class, 'go']);
    $app->post('/app/website/terms_condition', [WebsiteTermsConditionPagesController::class, 'go']);



    // Website Return & Refund Page 
    $app->get('/app/website/return_refund', [WebsiteReturnRefundController::class, 'go']);
    $app->post('/app/website/return_refund', [WebsiteReturnRefundController::class, 'go']);


    // Contact US  Pages
    $app->get('/app/website/contact_us', [WebsiteContactUsController::class, 'go']);
    $app->post('/app/website/contact_us', [WebsiteContactUsController::class, 'go']);


    // Gallery  Pages
    $app->get('/app/website/gallery', [WebsiteGalleryPagesController::class, 'go']);
    $app->post('/app/website/gallery', [WebsiteGalleryPagesController::class, 'go']);


    // payroll settings
    $app->get('/app/payroll/setting', [PayRollController::class, 'go']);
    $app->post('/app/payroll/setting', [PayRollController::class, 'go']);


    // Salary
    $app->get('/app/payroll/salary', [SalaryController::class, 'go']);
    $app->post('/app/payroll/salary', [SalaryController::class, 'go']);



    // attendance  
    $app->get('/app/attendance', [AttendanceController::class, 'go']);
    $app->post('/app/attendance', [AttendanceController::class, 'go']);

    // Users  
    $app->get('/app/users', [UserController::class, 'go']);
    $app->post('/app/users', [UserController::class, 'go']);

    // settings DASHBOAD
    $app->get('/app/settings/dashboard', [SETTING_DashboardController::class, 'go']);
    $app->post('/app/settings/dashboard', [SETTING_DashboardController::class, 'go']);


    // accounts DASHBOAD
    $app->get('/app/accounts/dashboard', [ACCOUNT_DashboardController::class, 'go']);
    $app->post('/app/accounts/dashboard', [ACCOUNT_DashboardController::class, 'go']);

    // supplier DASHBOAD
    $app->get('/app/supplier/dashboard', [SUPPLIER_DashboardController::class, 'go']);
    $app->post('/app/supplier/dashboard', [SUPPLIER_DashboardController::class, 'go']);


    // inventory DASHBOAD
    $app->get('/app/inventory/dashboard', [INVENTORY_DashboardController::class, 'go']);
    $app->post('/app/inventory/dashboard', [INVENTORY_DashboardController::class, 'go']);



    $app->get('/', function (
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $response->getBody()->write('Welcome to ManageBeds');

        return $response;
    });
};
