<?php
/**
 *
 * MyInvoices for xBilling Module for ZPanel 10.1.0
 * Version : 1.1.0
 * Author :  Aderemi Adewale (modpluz @ ZPanel Forums)
 * Email : goremmy@gmail.com
 */

class module_controller {

    static $complete;
    static $error;
    static $file_error;
    static $view;
    static $ok;
    static $module_db = 'zpanel_xbilling';



/*START - Check for updates added by TGates*/
// Module update check functions
    static function getModuleVersion() {
        global $zdbh, $controller;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_version = $mod_config->document->version[0]->tagData;
        return "v".$module_version."";
    }
    
    static function getCheckUpdate() {
        global $zdbh, $controller;
        $module_path="./modules/" . $controller->GetControllerRequest('URL', 'module');
        
        // Get Update URL and Version From module.xml
        $mod_xml = "./modules/" . $controller->GetControllerRequest('URL', 'module') . "/module.xml";
        $mod_config = new xml_reader(fs_filehandler::ReadFileContents($mod_xml));
        $mod_config->Parse();
        $module_updateurl = $mod_config->document->updateurl[0]->tagData;
        $module_version = $mod_config->document->version[0]->tagData;

        // Download XML in Update URL and get Download URL and Version
        $myfile = self::getCheckRemoteXml($module_updateurl, $module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml");
        $update_config = new xml_reader(fs_filehandler::ReadFileContents($module_path."/" . $controller->GetControllerRequest('URL', 'module') . ".xml"));
        $update_config->Parse();
        $update_url = $update_config->document->downloadurl[0]->tagData;
        $update_version = $update_config->document->latestversion[0]->tagData;

        if($update_version > $module_version)
            return true;
        return false;
    }

/*END - Check for updates added by TGates*/

/*START - Check for updates added by TGates*/
// Function to retrieve remote XML for update check
    static function getCheckRemoteXml($xmlurl,$destfile){
        $feed = simplexml_load_file($xmlurl);
        if ($feed)
        {
            // $feed is valid, save it
            $feed->asXML($destfile);
        } elseif (file_exists($destfile)) {
            // $feed is not valid, grab the last backup
            $feed = simplexml_load_file($destfile);
        } else {
            //die('Unable to retrieve XML file');
            echo('<div class="alert alert-danger">Unable to check for updates, your version may be outdated!.</div>');
        }
    }
/*END - Check for updates added by TGates*/

   /* Load CSS and JS files */
    static function getInit() {
        global $controller;
        $line = '<link rel="stylesheet" type="text/css" href="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/myinvoices.css">';
        $line .= '<script type="text/javascript" src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/myinvoices.js"></script>';
        return $line;
    }


    
    static function getisModuleInstalled(){
        global $zdbh;
        
        $numrows = $zdbh->prepare("SELECT SCHEMA_NAME AS database_name FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '".self::$module_db."'");
        $numrows->execute(); 
        $db_info = $numrows->fetch();
       // var_dump($db_info);
        //exit;
        
        if (isset($db_info['database_name'])){
           //self::$ok = true;
           return true;
        }
        
        return false;    
    }

    static function getCSFR_Tag() {
        return runtime_csfr::Token();
    }

    static function getModuleName() {
        $module_name = ui_module::GetModuleName();
        return $module_name;

    }

    static function getModuleIcon() {
        global $controller;
        $module_icon = "/modules/" . $controller->GetControllerRequest('URL', 'module') . "/assets/icon.png";
        return $module_icon;
    }


    static function getModuleDesc() {
        $message = ui_language::translate(ui_module::GetModuleDescription());
        return $message;
    }

    static function getModuleDir(){
        global $controller;
        $name = $controller->GetControllerRequest('URL', 'module');
        return "/modules/".$name;
    }
    
    static function getResellerID(){
        //global $zdbh;

        $currentuser = ctrl_users::GetUserDetail(); 
        $reseller_id = $currentuser['resellerid'];
        if(!$reseller_id){
            $reseller_id = $currentuser['userid'];
        }
        return $reseller_id;
    }
    
    static function getBillingURL(){
        if(self::getisModuleInstalled()){
            return self::appSetting('website_billing_url');
        }
    }

    static function getCurrency(){
        if(self::getisModuleInstalled()){
           return self::appSetting('currency');
        }
    }

    
    /* Settings */

    /* Settings */

    /* Orders / Invoices */
    static function getOrdersInvoices(){
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        
        $orders = $zdbh->prepare("SELECT * FROM ".self::$module_db.".x_invoices_orders 
                                    INNER JOIN ".self::$module_db.".x_orders ON ".self::$module_db.".x_orders.order_id=".self::$module_db.".x_invoices_orders.order_id 
                                    INNER JOIN ".self::$module_db.".x_invoices ON 
                                    ".self::$module_db.".x_invoices.invoice_id=".self::$module_db.".x_invoices_orders.invoice_id 
                                    WHERE ".self::$module_db.".x_invoices.ac_id_fk=:user_id 
                                    AND ".self::$module_db.".x_orders.ac_id_fk=:user_id 
                                    GROUP BY ".self::$module_db.".x_invoices.invoice_id 
                                    ORDER BY ".self::$module_db.".x_invoices.invoice_id DESC;");
        $orders->bindParam(':user_id', $currentuser['userid']);
        $orders->execute();
        $res = array();
        
        $currency = self::appSetting('currency');

        if (!fs_director::CheckForEmptyValue($orders)) {
            while ($row = $orders->fetch()) {

              $button_html = '';               
              $order_status = ($row['invoice_status'] == 1) ? 'Paid':'Pending';

                //view
                $button_html = '<a href="javascript:void(0);" class="btn btn-info btn-small" onclick="_view_invoice(\''.$row['invoice_reference'].'\'); ">'.ui_language::translate("View").'</a>&nbsp;';


              array_push($res, array('order_no' => $row['invoice_reference'],
                                      'order_date' => date("Y-m-d H:i", strtotime($row['invoice_dated'])),
                                      'order_amount' => number_format($row['invoice_total_amount'],2),
                                      'order_desc' => $row['order_desc'],
                                      'order_status' => $order_status,
                                      'button' => $button_html));  
            }

            return $res;
        } else {
            return false;
        }            
    }   
    
    static function appSetting($setting_name){
       global $zdbh;
       
       $reseller_id = self::getResellerID();
       
       if($reseller_id && $setting_name){
             //settings
             $settings = $zdbh->prepare("SELECT setting_value FROM 
                                        ".self::$module_db.".x_settings WHERE
                                         reseller_ac_id_fk=:zpx_uid AND setting_name=:setting_name;");
             $settings->bindParam(':zpx_uid', $reseller_id);
             $settings->bindParam(':setting_name', $setting_name);
             $settings->execute();
             if (!fs_director::CheckForEmptyValue($settings)){
                while ($row = $settings->fetch()) {                
                    return $row['setting_value'];
                }
             }
       }
    }
     
    /* Orders / Invoices */
    
    /* Web Service */

    /* Web Service */

    /* Executions */

    /* Executions */


    static function getResult() {
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Operation completed successfully."), "zannounceok");
        }


        if(isset(self::$error['invalid_order']) && !fs_director::CheckForEmptyValue(self::$error['invalid_order'])) {
            return ui_sysmessage::shout(ui_language::translate("The Invoice you selected does not exist on the system."), "zannounceerror");
        }

        if (!fs_director::CheckForEmptyValue(self::$error)) {
            return ui_sysmessage::shout(ui_language::translate("An error has occurred while processing your request, please try again."), "zannounceerror");
        }
        return;
    }


}

?>
