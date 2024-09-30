<?php

namespace App\Helpers;

use App\Auth\Auth;
use App\Models\HRM\Employee;
use App\Validation\Validator;
use App\Response\CustomResponse;
use App\Models\Accounts\Accounts;
use App\Models\Purchase\Supplier;
use App\Models\Customers\Customer;
use App\Models\Housekeeping\Laundry;
use Illuminate\Support\Facades\Redis;
use App\Requests\CustomRequestHandler;
use Respect\Validation\Validator as v;
use App\Models\GeneralLedger\GeneralLedger;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class Accounting
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

        $this->validator = new Validator();

        $this->responseMessage = "";
        $this->outputData = [];
        $this->success = false;
    }

    //function for insert account customer table // update account customer table also in same function// while update booking, passing differece net amount
    public static function accountCustomer($credit, $customer_id, $invoice_id, $invoice_num, $invoice_type, $net_amount, $paid_amount, $credited_note, $debited_note, $user_id, $payment_collection): bool
    {

        $customer = Customer::where(['id' => $customer_id, 'status' => 1])->first();
        // dd($customer);

        // @TODO: check debit credit, if want to credit amount, then pass crediting = true, otherwise false
        if ($credit === true) {
            $balance = floatval($customer->balance);
            $balance += $net_amount;

            $customer->balance = $balance;
            $customer->save();

            DB::table('account_customer')->insert([
                'customer_id' => $customer_id,
                'invoice_id' => $invoice_id,
                'inv_type' => $invoice_type,
                'reference' => $invoice_num,
                'debit' => 0,
                'credit' => $net_amount,
                'balance' => $customer->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        } else if (!$payment_collection) {
            $balance = floatval($customer->balance);
            $balance -= $net_amount; // -57 - 10

            $customer->balance = $balance;
            $customer->save();

            DB::table('account_customer')->insert([
                'customer_id' => $customer_id,
                'invoice_id' => $invoice_id,
                'inv_type' => $invoice_type,
                'reference' => $invoice_num,
                'debit' => - ($net_amount),
                'credit' => 0,
                'balance' => $customer->balance,
                'note' => $debited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }

        if (!empty($paid_amount) && ((float)$paid_amount > 0)) {
            $balance = floatval($customer->balance);
            $balance += $paid_amount;

            $customer->balance = $balance;
            $customer->save();

            DB::table('account_customer')->insert([
                'customer_id' => $customer_id,
                'invoice_id' => $invoice_id,
                'inv_type' => $invoice_type,
                'reference' => 'PAYSLIP-' . $invoice_id . '-' . strtotime('now'),
                'debit' => 0,
                'credit' => $paid_amount,
                'balance' => $customer->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }

        return true;
    }

    //Accounts //Account_bank // Account_cash only for debit and credit accounts: if debit is true, then debited accounts otherwise, credit accounts
    public static function Accounts($account_id, $invoice_id, $invoice_type, $paid_amount, $credited_note, $debited_note, $user_id, $credit)
    {
        if (empty($credit)) {
            $credit = false;
        }
        $account = Accounts::where('status', 1)->find($account_id);
        if ($account) {

            //here will check if the account is paid or not 
            if ($credit == false) {

                $balance = floatval($account->balance);
                $balance -= $paid_amount;

                $account->balance = $balance;
                $account->save();

                //Account Bank
                if ($account->type === "BANK") {
                    DB::table('account_bank')->insert([
                        'invoice' => $invoice_id,
                        'inv_type' => $invoice_type,
                        'account_id' => $account->id,
                        'debit' => $paid_amount,
                        'credit' => 0,
                        'balance' => $account->balance,
                        'note' => $debited_note,
                        'created_by' => $user_id,
                        'status' => 1
                    ]);
                }
                //Account Cash
                if ($account->type === "CASH") {
                    DB::table('account_cash')->insert([
                        'invoice' => $invoice_id,
                        'inv_type' => $invoice_type,
                        'account_id' => $account->id,
                        'debit' => $paid_amount,
                        'credit' => 0,
                        'balance' => $account->balance,
                        'note' => $debited_note,
                        'created_by' => $user_id,
                        'status' => 1
                    ]);
                }
            }
            if ($credit == true) {
                //if debited amount
                $balance = floatval($account->balance);
                $balance += $paid_amount;

                $account->balance = $balance;
                $account->save();

                //Account Bank
                if ($account->type === "BANK") {
                    DB::table('account_bank')->insert([
                        'invoice' => $invoice_id,
                        'inv_type' => $invoice_type,
                        'account_id' => $account->id,
                        'debit' => 0,
                        'credit' => ($paid_amount),
                        'balance' => $account->balance,
                        'note' => $debited_note,
                        'created_by' => $user_id,
                        'status' => 1
                    ]);
                }
                //Account Cash
                if ($account->type === "CASH") {
                    DB::table('account_cash')->insert([
                        'invoice' => $invoice_id,
                        'inv_type' => $invoice_type,
                        'account_id' => $account->id,
                        'debit' => 0,
                        'credit' => ($paid_amount),
                        'balance' => $account->balance,
                        'note' => $debited_note,
                        'created_by' => $user_id,
                        'status' => 1
                    ]);
                }
            }
        }
    }



    public static function accountEmployee($credit, $from_id, $to_id, $result, $voucher_type, $voucher_no, $amount, $credited_note, $debited_note, $user_id)
    {
        if (empty($credit)) {
            $credit = false;
        }
        if ($credit == false) {

            $employee = Employee::where(['id' => $from_id, 'status' => 1])->first();
            $balance = floatval($employee->balance);
            $balance -= floatval($amount);
            $employee->balance = $balance;
            $employee->save();

            DB::table('account_employee')->insert([
                'employee_id' => $from_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'reference' => $voucher_no,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $employee->balance,
                'note' => $debited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }


        if ($credit == true) {

            $employee = Employee::where(['id' => $to_id, 'status' => 1])->first();
            $balance = floatval($employee->balance);
            $balance += floatval($amount);
            $employee->balance = $balance;
            $employee->save();

            DB::table('account_employee')->insert([
                'employee_id' => $to_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'reference' => $voucher_no,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $employee->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }
    }



    public static function accountSupplier($credit, $from_id, $to_id, $result, $voucher_type, $voucher_no, $amount, $credited_note, $debited_note, $user_id)
    {

        if (empty($credit)) {
            $credit = false;
        }
        if ($credit == false) {

            $supplier = Supplier::where(['id' => $from_id, 'status' => 1])->first();
            $balance = floatval($supplier->balance);
            $balance -= floatval($amount);
            $supplier->balance = $balance;
            $supplier->save();

            DB::table('account_supplier')->insert([
                'supplier_id' => $from_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $supplier->balance,
                'note' => $debited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }


        if ($credit == true) {

            $supplier = Supplier::where(['id' => $to_id, 'status' => 1])->first();
            $balance = floatval($supplier->balance);
            $balance += floatval($amount);
            $supplier->balance = $balance;
            $supplier->save();

            DB::table('account_supplier')->insert([
                'supplier_id' => $to_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $supplier->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }
    }



    public static function accountLaundry($credit, $from_id, $to_id, $result, $voucher_type, $voucher_no, $amount, $credited_note, $debited_note, $user_id)
    {

        if (empty($credit)) {
            $credit = false;
        }
        if ($credit == false) {

            $laundry = Laundry::where(['id' => $from_id, 'status' => 1])->first();
            $balance = floatval($laundry->balance);
            $balance -= floatval($amount);
            $laundry->balance = $balance;
            $laundry->save();

            DB::table('account_laundry')->insert([
                'laundry_id' => $from_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $laundry->balance,
                'note' => $debited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }


        if ($credit == true) {

            $laundry = Laundry::where(['id' => $to_id, 'status' => 1])->first();
            $balance = floatval($laundry->balance);
            $balance += floatval($amount);
            $laundry->balance = $balance;
            $laundry->save();

            DB::table('account_laundry')->insert([
                'laundry_id' => $to_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $laundry->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }
    }


    public static function accountGeneralLedger($credit, $from_id, $to_id, $result, $voucher_type, $voucher_no, $amount, $credited_note, $debited_note, $user_id)
    {
        if (empty($credit)) {
            $credit = false;
        }
        if ($credit == false) {

            $generalLedger = GeneralLedger::where(['id' => $from_id, 'status' => 1])->first();
            $balance = floatval($generalLedger->balance);
            $balance -= floatval($amount);
            $generalLedger->balance = $balance;
            $generalLedger->save();

            DB::table('account_general_ledger')->insert([
                'general_ledger_id' => $from_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => $amount,
                'credit' => 0,
                'balance' => $generalLedger->balance,
                'note' => $debited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }


        if ($credit == true) {

            $generalLedger = GeneralLedger::where(['id' => $to_id, 'status' => 1])->first();
            $balance = floatval($generalLedger->balance);
            $balance += floatval($amount);
            $generalLedger->balance = $balance;
            $generalLedger->save();

            DB::table('account_general_ledger')->insert([
                'general_ledger_id' => $to_id,
                'invoice_id' => $result,
                'inv_type' => $voucher_type,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $generalLedger->balance,
                'note' => $credited_note,
                'created_by' => $user_id,
                'status' => 1
            ]);
        }
    }
}
