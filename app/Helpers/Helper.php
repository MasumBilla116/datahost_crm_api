<?php

namespace App\Helpers;

use Illuminate\Database\Capsule\Manager as DB;
use PHPMailer\PHPMailer\PHPMailer;

class Helper
{

    static public function notification($customerId, $subject, $type, $message)
    {
        DB::table('notifications')->insert([
            "customer_id" => $customerId,
            "subject" => $subject,
            "type" => $type,
            "message" => $message,
            "is_read" => 0,
        ]);
    }



    public function additionNumber($a, $b)
    {
        return $a + $b;
    } //1

    /**
     * !Getting account_supplier lastest balance by ID
     */
    public function getSupplierLastBalance($id)
    {
        return DB::table('account_supplier')->latest('id')->where(['supplier_id' => $id])
            ->select('balance')
            ->first();
    }


    /**
     * !Getting account_laundry lastest balance by ID
     */
    public function getLaundryLastBalance($id)
    {
        return DB::table('account_agent')->latest('id')->where(['laundry_id' => $id])
            ->select('balance')
            ->first();
    }

    /**
     * !Getting Accounts Balance By ID
     */
    public function getBalanceById($table, $id)
    {
        $res = DB::select(DB::raw("SELECT SUM(`balance`) as `balance` FROM $table WHERE `account_id`= $id AND `status`='1'"));
        return $res;
    }


    /**
     * !Getting accounts data
     */
    public function getAccountsData()
    {
        $res = DB::select(DB::raw("SELECT ac.* from accounts ac"));
        return $res;
    }

    /**
     * !Getting last edit_attempt for supplier invoice
     * @param supplier_inv_id
     */
    public function getLastEditItem($supplier_inv_id)
    {
        $edit_attempt = DB::select(DB::raw("SELECT si.edit_attempt from supplier_invoice si where si.id = $supplier_inv_id"));
        return $edit_attempt;
    }

    /**
     * !Get Edit History Data for Supplier Invoice
     */
    public function editHistory($ref)
    {
        $edit_attempt = DB::select(DB::raw("SELECT si.edit_attempt from supplier_invoice si where si.id = $ref"));
        $edit_attempt = $edit_attempt[0]->edit_attempt;
        $history = DB::table('supplier_invoice_history')
            ->join('inventory_items', 'inventory_items.id', '=', 'supplier_invoice_history.invoice_item_id')
            ->join('org_users', 'org_users.id', '=', 'supplier_invoice_history.action_by')
            ->select('edit_attempt', 'supplier_invoice_history.*',  'org_users.name', 'inventory_items.name as itemName')
            ->where('reference', $ref)
            ->get()
            ->groupBy('edit_attempt');
        return $history;

        /*$edit_attempt = DB::select( DB::raw("SELECT si.edit_attempt from supplier_invoice si where si.id = $ref"));
        $edit_attempt = $edit_attempt[0]->edit_attempt;
        // dd($edit_attempt);
        $editHistory = array();

        for($i = 0; $i <= $edit_attempt; $i++){
            $history = DB::table('inventory_item_history')
            ->join('inventory_items','inventory_items.id','=','inventory_item_history.inventory_item_id')
            ->select('inventory_item_history.*','inventory_items.name as itemName')
            ->where('reference',$ref)->where('ref_type','supplier_purchase_invoice')
            ->where('edit_attempt',$i)
            ->get();

            $editHistory[] = $history;
        }*/
        /*$res = DB::select( DB::raw("SELECT iih.note, iih.old_qty,
        iih.affected_qty, iih.new_qty,
        iih.old_price, iih.new_price, iih.updated_at, 
        iih.action_by, iih.edit_attempt,
        (SELECT ii.name from inventory_items ii where ii.id = iih.inventory_item_id)
         as item_name 
         FROM inventory_item_history iih WHERE iih.reference = $ref 
         AND 
         iih.ref_type='supplier_purchase_invoice' AND iih.edit_attempt >= 1"));    //Working...
        return $res;*/
        // return $edit_attempt[0]->edit_attempt;
        //return $editHistory;
    }

    /**
     * !Email Config
     */

    public function sendEmail($username, $password, $from, $to, $body)
    {

        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->Mailer = "smtp";
        $mail->SMTPDebug  = 1;
        $mail->SMTPAuth   = TRUE;
        $mail->SMTPSecure = "tls";
        $mail->Port       = 587;
        $mail->Host       = "smtp.gmail.com";
        $mail->Username   = $username;
        $mail->Password   = "heziywkvpmlvdlpl";
        // $mail->Password   = $password;

        /**
         * @param Recipients
         */
        $mail->setFrom($from, 'Managebeds');
        //$mail->addAddress($to, 'Hasan');     //Add a recipient
        $mail->addAddress($to);               //Name is optional
        // $mail->addReplyTo('info@example.com', 'Information');
        // $mail->addCC('cc@example.com');
        // $mail->addBCC('bcc@example.com');

        /**
         *@param Content
         */
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Subject For Hotel';
        $mail->Body    = $body;
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        /**
         * @param Send
         */
        try {
            $response = $mail->send();
        } catch (\Exception $e) {
            $response = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return $response;
    }

    /**
     * !Get InvoiceDetails Except disabled items
     * @param supplier_invoice_item.status=1
     */

    public function getInvoiceDetails($supplier_invoice_id)
    {
        $res = DB::select(DB::raw("select `supplier_invoice`.`local_invoice`, 
        `supplier_invoice`.`invoice_date`, 
        `supplier_invoice_item`.`supplier_invoice_id`, `supplier_invoice_item`.`id`, 
        `supplier_invoice`.`supplier_id`, `inventory_items`.`id` as `itemCode`, 
        `supplier_invoice`.`edit_attempt` as `edit_attempt`,
        (select `supplier`.`name` from `supplier` where `supplier_invoice`.`supplier_id`
        = `supplier`.`id`) as `supplier_name`,
        `inventory_items`.`code` as `itemCodeName`, `inventory_items`.`id` as `itemId`, 
        `inventory_items`.`name` as `itemName`, `supplier_invoice_item`.`qty` as `item_qty`,
         `supplier_invoice`.`remarks` as `common_remarks`, 
         `supplier_invoice_item`.`remarks` as `item_remarks`, 
         `supplier_invoice`.`created_at`, 
         `supplier_invoice_item`.`unit_price` as `unitPrice`, 
         `supplier_invoice`.`total_amount` as `totalAmount`, 
         `supplier_invoice_item`.`status` as `status`,
         `supplier_invoice_item`.`is_returned` as `return`,
         `supplier_invoice_item`.`returned_qty` as `return_qty`, 
         (select unit_type from inventory_items where 
         inventory_items.id = supplier_invoice_item.item_id) as piece from 
         `supplier_invoice` inner join `supplier_invoice_item` on 
         `supplier_invoice`.`id` = `supplier_invoice_item`.`supplier_invoice_id` 
         inner join `inventory_items` on 
         `inventory_items`.`id` = `supplier_invoice_item`.`item_id` 
         where `supplier_invoice_item`.`status` = 1 and 
         `supplier_invoice`.`id` = $supplier_invoice_id"));
        return $res;
    }

    /**
     * !Get Invoice Details with return items
     * @param supplier_invoice_item.is_returned=1
     * ? ref:@mehedi
     */

    public function getReturnInvoiceDetails($supplier_invoice_id)
    {
        $res = DB::select(DB::raw("select `supplier_invoice`.`local_invoice`, 
        `supplier_invoice`.`invoice_date`, 
        `supplier_invoice_item`.`supplier_invoice_id`, `supplier_invoice_item`.`id`, 
        `supplier_invoice`.`supplier_id`, `inventory_items`.`id` as `itemCode`, 
        `supplier_invoice`.`edit_attempt` as `edit_attempt`,
        (select `supplier`.`name` from `supplier` where `supplier_invoice`.`supplier_id`
        = `supplier`.`id`) as `supplier_name`,
        `inventory_items`.`code` as `itemCodeName`, `inventory_items`.`id` as `itemId`, 
        `inventory_items`.`name` as `itemName`, `supplier_invoice_item`.`qty` as `item_qty`,
         `supplier_invoice`.`remarks` as `common_remarks`, 
         `supplier_invoice_item`.`remarks` as `item_remarks`, 
         `supplier_invoice`.`created_at`, 
         `supplier_invoice_item`.`unit_price` as `unitPrice`, 
         `supplier_invoice`.`total_amount` as `totalAmount`, 
         `supplier_invoice_item`.`status` as `status`,
         `supplier_invoice_item`.`is_returned` as `return`,
         `supplier_invoice_item`.`returned_qty` as `return_qty`, 
         (select unit_type from inventory_items where 
         inventory_items.id = supplier_invoice_item.item_id) as piece from 
         `supplier_invoice` inner join `supplier_invoice_item` on 
         `supplier_invoice`.`id` = `supplier_invoice_item`.`supplier_invoice_id` 
         inner join `inventory_items` on 
         `inventory_items`.`id` = `supplier_invoice_item`.`item_id` 
         where `supplier_invoice_item`.`status` = 1 and `supplier_invoice_item`.`is_returned` = 1 and
         `supplier_invoice`.`is_returned` = 1 and 
         `supplier_invoice`.`id` = $supplier_invoice_id"));
        return $res;
    }

    /**
     * !To get the Status with deleted items
     */
    public function getInvoiceDetailsAll($supplier_invoice_id)
    {
        $res = DB::select(DB::raw("select `supplier_invoice`.`local_invoice`, 
        `supplier_invoice`.`invoice_date`, 
        `supplier_invoice_item`.`supplier_invoice_id`, `supplier_invoice_item`.`id`, 
        `supplier_invoice`.`supplier_id`, `inventory_items`.`id` as `itemCode`, 
        (select `supplier`.`name` from `supplier` where `supplier_invoice`.`supplier_id`
        = `supplier`.`id`) as `supplier_name`,
        `inventory_items`.`code` as `itemCodeName`, `inventory_items`.`id` as `itemId`, 
        `inventory_items`.`name` as `itemName`, `supplier_invoice_item`.`qty` as `item_qty`,
         `supplier_invoice`.`remarks` as `common_remarks`, 
         `supplier_invoice_item`.`remarks` as `item_remarks`, 
         `supplier_invoice`.`created_at`, 
         `supplier_invoice_item`.`unit_price` as `unitPrice`, 
         `supplier_invoice`.`total_amount` as `totalAmount`, 
         `supplier_invoice_item`.`status` as `status`, 
         (select unit_type from inventory_items where 
         inventory_items.id = supplier_invoice_item.item_id) as piece from 
         `supplier_invoice` inner join `supplier_invoice_item` on 
         `supplier_invoice`.`id` = `supplier_invoice_item`.`supplier_invoice_id` 
         inner join `inventory_items` on 
         `inventory_items`.`id` = `supplier_invoice_item`.`item_id` 
         where `supplier_invoice`.`id` = $supplier_invoice_id"));
        return $res;
    }

    /**
     * !Getting type cash/bank from table accounts
     */
    public function getType($id)
    {
        return DB::select(DB::raw("SELECT `type` FROM `accounts` WHERE `id`=$id"));
    }


    public function getLastID($table)
    {
        // $getLastId = DB::raw('select id from'+$table)->orderBy('id','DESC')->get()->first();
        //var_dump($getLastId->id);
        // $getLastId = DB::raw('select id from'+$table')->orderBy('id','DESC')->get()->first();
        // $getLastId = DB::raw('select * from '.$table.' orderBy("id","DESC")');
        // $getLastId = DB::raw('select * from '.$table.' orderBy("id","DESC")');
        // $getLastId = DB::statement('select * from '.$table.' order by id DESC');
        // $getLastId = DB::select( DB::raw("SELECT * FROM '$table' WHERE some_col = '$someVariable'") );

        // $getLastId = DB::select( DB::raw("SELECT `id` FROM $table ORDER BY `id` DESC FETCH FIRST 1 ROWS ONLY"));    //Working...
        // SELECT max(id) FROM tableName
        $getLastId = DB::select(DB::raw("SELECT AUTO_INCREMENT as id FROM information_schema.TABLES WHERE TABLE_NAME = '$table'"));    //Working...
        //  dd($getLastId);
        // DB::statement("your query")
        // DB::select('select * from users where id = ?', [1]);
        // var_dump($getLastId[0]->id);
        echo !!!$getLastId[0]->id;
        if (!!!$getLastId[0]->id) {
            $getLastId = 1;
        } else {
            $getLastId = $getLastId[0]->id;
        }

        return $getLastId;
    }
    public function getItem($params, $table, $condition)
    {
        $getItem = DB::select(DB::raw("SELECT $params FROM $table WHERE `id`=$condition"));
        return $getItem;
    }

    public function getItemFromSupplierInvoice($params, $table, $condition)
    {
        $getItem = DB::select(DB::raw("SELECT $params FROM $table WHERE `id`=$condition"));
        return $getItem;
    }
    public function getLastSupplierAccountBalance($table, $supplier_id)
    {
        $getLastBalance = DB::select(DB::raw("SELECT `balance` FROM $table WHERE `supplier_id` = $supplier_id ORDER BY `id` DESC FETCH FIRST 1 ROWS ONLY"));
        return $getLastBalance;
    }
    public function getIdByInvoiceID($table, $invoice_id)
    {
        $getResult = DB::select(DB::raw("SELECT `id` FROM $table WHERE `supplier_invoice` = '$invoice_id'"));
        // $getResult = DB::select( DB::raw("SELECT AUTO_INCREMENT as id FROM information_schema.TABLES WHERE TABLE_NAME = $table"));
        return $getResult;
    }

    public function getInvDetailsBySupplierId($table, $supplierID)
    {
        $getResult = DB::select(DB::raw("SELECT `id` FROM `supplier_invoice` WHERE `id` = '$supplierID'"));
        if (!$getResult) {
            return null;
        } else {
            $supplierInvoiceId = $getResult[0]->id;
        }
        $getResult = DB::select(DB::raw("SELECT * FROM `supplier_invoice_item` WHERE `supplier_invoice_id` = $supplierInvoiceId AND `status` = 1"));
        return $getResult;
    }

    public function checkEmailData()
    {
        $getResult = DB::select(DB::raw("SELECT `type` FROM `config_email`"));
        return $getResult;
    }


    public static function encrypt($data)
    {
        $encryption_key = "i&n%#09^58#4@Ksg";

        $ciphering = "AES-128-CTR";
        $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;
        $encryption_iv = '5623187546923165';

        $encryption = openssl_encrypt(
            $data,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        );

        return $encryption;
    }

    public static function decrypt($data)
    {
        $encryption_key = "i&n%#09^58#4@Ksg";

        $ciphering = "AES-128-CTR";
        $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;
        $encryption_iv = '5623187546923165';

        $encryption = openssl_decrypt(
            $data,
            $ciphering,
            $encryption_key,
            $options,
            $encryption_iv
        );

        return $encryption;
    }
}
