<?php
/**
 * Copyright (c) 2016.  Zoho Corporation
 *  All rights reserved.
 *  Redistribution and use in source and binary forms, with or without modification, are permitted provided
 *  that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this list of conditions and the
 *  following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  Neither the name of Zoho Corp nor the names of its contributors may be used to endorse or
 *  promote products derived from this software without specific prior written permission.
 *
 *  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
 *  WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 *  PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 *  ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 *  TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *  NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Created by PhpStorm.
 * User: adarshsureshmangalath
 * Date: 7/19/16
 * Time: 12:17 PM
 */
require_once('zohoclient.php');
require_once('zohoobject.php');
require_once('zohoutils.php');
$client_id = '';
$client_secret = '';
$redirect_uri = '';
$refresh_token = '';
$access_token = '';
$zclient;

class ZClientTest extends PHPUnit_Framework_TestCase
{


    protected function setUp()
    {
        global $client_id, $client_secret, $redirect_uri, $access_token, $refresh_token, $zclient;
        $zclient = new ZClient($client_id, $client_secret, $redirect_uri, $access_token, $refresh_token);
    }
    /**
     * Test ZClient.loadmodules functionality.
     */
    public function test_load_module()
    {
        global $zclient;
        $zclient->loadModules(true);

    }

    /**
     * Test ZClient.users functionality.
     */
    public function test_users()
    {
        global $zclient;
        $zclient->users();
    }

    /**
     * Test ZClient.modules functionality.
     */
    public function test__modules()
    {
        global $zclient;
        $zclient->modules();
    }

    /**
     * Test ZClient.metadata functionality.
     */
    public function test__metadata()
    {
        global $zclient;
        $zclient->loadModules(true);
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $zclient->metadata($module);
        }
    }


    /**
     * Test ZClient.layouts functionality.
     */
    public function test__layouts()
    {
        global $zclient;
        $zclient->loadModules(true);
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $layout=$zclient->layouts(NULL, array("module" => $module));
        }
    }



    /**
     * Test insert update and delete functionality of all the modules.
     */
    public function test__insert_update_delete()
    {
        global $zclient;
        $zclient->loadModules(true);
        $recorddict=insert_data($zclient);
        $recorddict_update=update_data($zclient,$recorddict);
        delete_data($zclient,$recorddict);
    }

    /**
     * Test ZClient.get_deleted functionality of all the modules.
     */
    public function test__get_deleted()
    {
        global $zclient;
        $zclient->loadModules(true);
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $deleted=$zclient->get_deleted($module, array('type' => 'all'));
        }
    }
    /**
     * Test ZClient.upload_photo and ZClient.download_photo functionality for Leads and Contacts.
     */
    public function test__upload_download_photo()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=array('Leads','Contacts');
        $recorddict=insert_data($zclient,$modulelist);
        foreach ($modulelist as  $module)
        {
            $zclient->upload_photo($module, $recorddict[$module],'zoho-crm.png');
            $zclient->download_photo($module, $recorddict[$module],'zoho-crm-'.$module.'.png');
            unlink('zoho-crm-'.$module.'.png');
        }
        delete_data($zclient,$recorddict);
    }
    /**
     * Test ZClient.upload_file and ZClient.download_file functionality for all possible modules.
     */
    public function test__upload_download_file()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=get_related_modules($zclient,'Attachments');
        $recorddict=insert_data($zclient,$modulelist);
        foreach ($modulelist as  $module)
        {
            $result=$zclient->upload_file($module, $recorddict[$module],'zoho-crm.png');
            $zclient->download_file($module, $recorddict[$module],$result['details']['id'],'zoho-crm-'.$module.'.png');
            unlink('zoho-crm-'.$module.'.png');
        }
        delete_data($zclient,$recorddict);
    }

    public function test__insert_notes()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=get_related_modules($zclient,'Notes');
        $recorddict=insert_data($zclient,$modulelist);
        $mynote=array('Note_Title'=> 'Test Note',
                'Note_Content'=> 'Test Note Added');
        foreach ($modulelist as  $module)
        {
            $result=$zclient->insert_notes($module, $recorddict[$module],array($mynote));
        }
        delete_data($zclient,$recorddict);
    }

    public function test__related_list()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=array();
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $relations=$zclient->modules_metadata[$module]['modules'][0]['relations'];
            $related_modules=array();
            foreach($relations as $relation)
            {
                if (!empty($relation['href']))
                {
                    array_push($related_modules,$relation['api_name']);
                }
            }
            if(!empty($related_modules))
                $modulelist[$module]=$related_modules;
        }
        $recorddict=insert_data($zclient,array_keys($modulelist));
        foreach (array_keys($modulelist) as $module)
        {
            $related_module_list=$modulelist[$module];
            foreach ($related_module_list as $related_module)
            {
                $relatedlist=$zclient->related_list($module,$recorddict[$module],$related_module);
            }
        }
        delete_data($zclient,$recorddict);
    }


    public function test__search()
    {
        global $zclient;
        $zclient->loadModules(true);
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $result=$zclient->search($module,array('word'=>'Test'));
        }
    }
    /**
     * Test ZClient.taxes functionality.
     */
    public function test_taxes()
    {
        global $zclient;
        $zclient->taxes();

    }
    /**
     * Test ZClient.roles functionality.
     */
    public function test__roles()
    {
        global $zclient;
        $zclient->roles();

    }
    /**
     * Test ZClient.tab_groups functionality.
     */
    public function test__tab_groups()
    {
        global $zclient;
        $zclient->tab_groups();

    }
    /**
     * Test ZClient.convert_lead functionality.
     */
    public function test__convert_leads()
    {
        global $zclient;
        $zclient->loadModules(true);
        $retdict=insert_data($zclient,array('Leads'));
        $leadconverttdata=array("overwrite" => True, "notify_lead_owner"=> True, "notify_new_entity_owner"=> True,
            "potential"=> array(
                "Potential_Name"=> "Potential_Name0",
                "Closing_Date"=> date_formatter(time()),
                "Stage"=> "Stage0",
                "Amount"=> random_digits(4)));
        $result=$zclient->convert_lead($retdict['Leads'],array($leadconverttdata));
        delete_data($zclient,array('Accounts'=>$result['data'][0]['account']));
    }

    /**
     * Test ZObject.update and ZObject.delete functionality for all possible modules.
     */
 public function testobject_get_update_delete()
 {
     global $zclient;
     $zclient->loadModules(true);
     $recorddict=insert_data($zclient);
     $modulelist = array_keys($recorddict);
     if (($key = array_search('Products', $modulelist)) !== false)
     {unset($modulelist[$key]);array_push($modulelist,'Products');}
     if (($key = array_search('Accounts', $modulelist)) !== false)
     {unset($modulelist[$key]);array_push($modulelist,'Accounts');}
     foreach ($modulelist as $module)
     {
         $record=$zclient->get_id($module,$recorddict[$module]);
         $updatedata=create_data($zclient,true,array($module));
         $record->dict=array_merge($updatedata,$record->dict);
         $record->update();
         $record->delete();
     }
 }
    public function testobject_related_list()
    {
        global $zclient;
        $zclient->loadModules(true);
        $recorddict=insert_data($zclient);
        foreach (array_keys($zclient->modules_metadata) as  $module)
        {
            $relations=$zclient->modules_metadata[$module]['modules'][0]['relations'];
            $related_modules=array();
            foreach($relations as $relation)
            {
                if (!empty($relation['href']))
                {
                    array_push($related_modules,$relation['api_name']);
                }
            }
            if(!empty($related_modules))
                $modulelist[$module]=$related_modules;
        }
        $recorddict=insert_data($zclient,array_keys($modulelist));
        foreach (array_keys($modulelist) as $module)
        {
            $record=$zclient->get_id($module,$recorddict[$module]);
            $related_module_list=$modulelist[$module];
            foreach ($related_module_list as $related_module)
            {
                $relatedlist=$record->related_list($related_module);
            }
        }
        delete_data($zclient,$recorddict);
    }
    public function testobject_insert_update_notes()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=get_related_modules($zclient,'Notes');
        $recorddict=insert_data($zclient,$modulelist);
        $mynote=array('Note_Title'=> 'Test Note',
               'Note_Content'=> 'Test Note Added');
        foreach ($modulelist as  $module)
        {
            try{ 
            $record=$zclient->get_id($module,$recorddict[$module]);
            $result=$record->insert_notes($mynote);}
            catch (Exception $e) {
                    echo 'Caught exception in insert: ',$module,  $e->getMessage(), "\n";
                }
            try{
                $record->update_notes($result['id'],$mynote);
            }
            catch (Exception $e) {
                echo 'Caught exception in update: ',$module,  $e->getMessage(), "\n";
            }
    
        }
        delete_data($zclient,$recorddict);
    }
    public function testobject_upload_download_files()
    {
        global $zclient;
        $zclient->loadModules(true);
        $modulelist=array('Contacts');//get_related_modules($zclient,'Attachments');
        $recorddict=insert_data($zclient,$modulelist);
            foreach ($modulelist as  $module)
            {
                
                $record=$zclient->get_id($module,$recorddict[$module]);
                try{
                    $result=$record->upload_file('zoho-crm.png');}
                catch (Exception $e) {
                echo 'Caught exception in uploading file: ',$module,  $e->getMessage(), "\n";
            }
                try{
                $record->download_file($result['details']['id'],'zoho-crm-'.$module.'.png');}
            catch (Exception $e) {
                echo 'Caught exception in downloading file: ',$module,  $e->getMessage(), "\n";
            }
               unlink('zoho-crm-'.$module.'.png');
            }
            delete_data($zclient,$recorddict);
    }
}

function insert_data($zclient, $modulelist = array())
{
    $insert_data = create_data($zclient, false, $modulelist);
    $recorddict = array();
    $modulelist = array_keys($insert_data);
    foreach ($modulelist as $module) {
        $mydata = $insert_data[$module];
        add_record_ids($module, $mydata, $recorddict, false);
        try {
            $myobjects = $zclient->insert($module, array($mydata));
        } catch (Exception $e) {
            echo 'Caught exception in insert: ',$module,  $e->getMessage(), "\n";
        }

        sleep(1);
        if (!empty($myobjects)) {
            $myobject = $myobjects[0];
            if (strcmp($myobject->code, 'SUCCESS') != 0) {
                continue;
            } else
                $recorddict[$module] = $myobject['id'];
        }
    }
    return $recorddict;
}
function update_data($zclient, $recorddict )
{
    $update_data = create_data($zclient, true, array_keys($recorddict));
    $modulelist = array_keys($update_data);
    $recorddict_update=array();
    foreach ($modulelist as $module) {
        $mydata = $update_data[$module];
        add_record_ids($module, $mydata, $recorddict, true);
        try {
            $myobjects = $zclient->update($module, array($mydata));
        } catch (Exception $e) {
            echo 'Caught exception in update: ',$module,$e->getMessage(), "\n";
        }

        sleep(1);
        if (!empty($myobjects)) {
            $myobject = $myobjects[0];
            if (strcmp($myobject->code, 'SUCCESS') != 0) {
                continue;
            } else
                $recorddict_update[$module] = $myobject['id'];
        }
    }
    return $recorddict_update;
}
function delete_data($zclient, $recorddict )
{

    $modulelist = array_keys($recorddict);
    if (($key = array_search('Products', $modulelist)) !== false)
    {unset($modulelist[$key]);array_push($modulelist,'Products');}
    if (($key = array_search('Accounts', $modulelist)) !== false)
    {unset($modulelist[$key]);array_push($modulelist,'Accounts');}
    foreach ($modulelist as $module) {
        try {
            $result=$zclient->delete($module,$recorddict[$module]);
        } catch (Exception $e) {
            echo 'Caught exception in delete: ',$module,  $e->getMessage(), "\n";
        }
        sleep(1);
    }
}
function create_data($zclient, $update, $modulelist = array())
{
    if (empty($modulelist))
        $modulelist = array_keys($zclient->modules_metadata);
    if ($update)
        $text = 'Updated';
    else
        $text = 'Test';
    if (($key = array_search('Activities', $modulelist)) !== false) unset($modulelist[$key]);
    $inputlist = array();
    foreach ($modulelist as $module) {
        $moduledata = array();
        $modulemetadata = $zclient->modules_metadata[$module]['modules'][0];
        $fields = $modulemetadata['fields'];
        foreach ($fields as $field) {
            $field_api_name = $field['api_name'];
            if ($field['view_type']['create']) {
                if (strcmp($field['data_type'], 'bigint') == 0) {
                    if (strcmp($field_api_name, 'Layout') == 0) {
                        $moduledata[$field_api_name] = $modulemetadata['layouts'][0]['id'];
                    } elseif (strcmp($field_api_name, 'Participants') == 0) {
                        continue;
                    } elseif (strcmp($field_api_name, 'Visitor_Score') == 0) {
                        continue;
                    } else {
                        $moduledata[$field_api_name] = intval(random_digits($field['length'] - 1));
                    }
                } elseif (strcmp($field['data_type'], 'boolean') == 0) {
                    $moduledata[$field_api_name] = True;
                } elseif (strcmp($field['data_type'], 'currency') == 0) {
                    $moduledata[$field_api_name] = intval(random_digits($field['length'] - 1));
                } elseif (strcmp($field['data_type'], 'date') == 0) {
                    $moduledata[$field_api_name] = date_formatter(strtotime("-4 months"));
                } elseif (strcmp($field['data_type'], 'datetime') == 0) {
                    $moduledata[$field_api_name] = datetime_formatter(time());
                } elseif (strcmp($field['data_type'], 'double') == 0) {
                    $moduledata[$field_api_name] = intval(random_digits($field['length'] - 1));
                } elseif (strcmp($field['data_type'], 'email') == 0) {
                    $moduledata[$field_api_name] = $field_api_name . "@test" . rand_string(3) . '.com';
                } elseif (strcmp($field['data_type'], 'integer') == 0) {
                    $moduledata[$field_api_name] = intval(random_digits($field['length'] - 1));
                } elseif (strcmp($field['data_type'], 'lookup') == 0) {
                    if ($field_api_name != 'Who_Id' and $field_api_name != 'What_Id')
                        $moduledata[$field_api_name] = NULL;
                } elseif (strcmp($field['data_type'], 'ownerlookup') == 0) {
                    continue;
                } elseif (strcmp($field['data_type'], 'multiselectpicklist') == 0) {
                    if (!empty($field['pick_list_values']))
                        $moduledata[$field_api_name] = $field['pick_list_values'][0]['actual_value'];
                } elseif (strcmp($field['data_type'], 'phone') == 0) {
                    $moduledata[$field_api_name] = rand_string(10);
                } elseif (strcmp($field['data_type'], 'picklist') == 0) {
                    if (!empty($field['pick_list_values'])) {
                        if (strcmp($field_api_name, 'Remind_At') == 0) {
                            continue;
                        } elseif (strcmp($field_api_name, 'Stage') == 0) {
                            $moduledata[$field_api_name] = 'Needs Analysis';
                        } else {
                            $moduledata[$field_api_name] = $field['pick_list_values'][0]['actual_value'];
                        }
                    }
                } elseif (strcmp($field['data_type'], 'text') == 0) {
                    if (strcmp($field_api_name, 'Call_Duration') == 0) {
                        $moduledata[$field_api_name] = random_digits($field['length']-6);
                    } elseif (strcmp($field_api_name, 'Product_Details') == 0) {
                        $moduledata[$field_api_name] = array(array("Discount" => 0, "Tax" => 0, "book" => NULL, "list_price" => 12, "net_total" => 1476, "product" => "{Product_Id}", "product_description" => NULL, "quantity" => 123, "quantity_in_stock" => 0, "total" => 1476, "total_after_discount" => 1476, "unit_price" => 12));
                    } elseif (strcmp($field_api_name, 'Pricing_Details') == 0) {
                        $moduledata[$field_api_name] = array(array("discount" => 12, "from_range" => 123, "to_range" => 123123));
                    } else {
                        $moduledata[$field_api_name] = $text . $field_api_name . rand_string(5);
                    }
                } elseif (strcmp($field['data_type'], 'textarea') == 0) {
                    $moduledata[$field_api_name] = $text . $field_api_name . " " . rand_string(15);
                } elseif (strcmp($field['data_type'], 'website') == 0) {
                    $moduledata[$field_api_name] = "www.test" . $field_api_name . rand_string(4) . '.com';
                }
            }
        }
        $inputlist[$module] = $moduledata;
    }
    return $inputlist;
}

function add_record_ids($module, &$mydata, $recorddict, $update)
{
    if ($update)
        $mydata['id'] = $recorddict[$module];
    if (array_key_exists('Potential_Name', $mydata) &&  array_key_exists('Potential_Name', $recorddict) && strcmp($module, 'Potentials') != 0)
    {
        $mydata['Potential_Name'] = $recorddict['Potentials'];
    }
    if (array_key_exists('Product_Name', $mydata) && (($key = array_key_exists('Products', $recorddict)) !== false) && strcmp($module, 'Products') != 0)
    {
        $mydata['Product_Name'] = $recorddict['Products'];
    }
    if ( array_key_exists('Account_Name', $mydata) &&  array_key_exists('Accounts', $recorddict) && strcmp($module, 'Accounts') != 0)
    {
        $mydata['Account_Name'] = $recorddict['Accounts'];
    }
    if ( array_key_exists('What_Id', $mydata) && array_key_exists('Accounts', $recorddict) && strcmp($module, 'Accounts') != 0)
    {
        $mydata['What_Id'] = $recorddict['Accounts'];
    }
    if (array_key_exists('Contact_Name', $mydata) &&  array_key_exists('Contacts', $recorddict) && strcmp($module, 'Contacts') != 0)
    {
        $mydata['Contact_Name'] = $recorddict['Contacts'];
    }
    if (array_key_exists('Vendor_Name', $mydata) &&  array_key_exists('Vendors', $recorddict) && strcmp($module, 'Vendors') != 0)
    {
        $mydata['Vendor_Name'] = $recorddict['Vendors'];
    }
    if ( array_key_exists('Quote_Name', $mydata) &&  array_key_exists('Quotes', $recorddict) && strcmp($module, 'Quotes') != 0)
    {
        $mydata['Quote_Name'] = $recorddict['Quotes'];
    }
    if (array_key_exists('Product_Details', $mydata) && array_key_exists('Products', $recorddict))
    {   $products=&$mydata['Product_Details'];
        for ($item = 0, $size = count($products); $item < $size; $item++) {
            $products[$item]['product']=$recorddict['Products'];
        }
    }
    if (array_key_exists('Sales_Order', $mydata) &&  array_key_exists('Sales_Orders', $recorddict) && strcmp($module, 'Sales_Orders') != 0)
    {
        $mydata['Sales_Order'] = $recorddict['Sales_Orders'];
    }
}
function get_related_modules($zclient,$rmodule)
{
    $modulelist=array();
    foreach (array_keys($zclient->modules_metadata) as  $module)
    {
        $relations=$zclient->modules_metadata[$module]['modules'][0]['relations'];
        foreach($relations as $relation)
        {
            if (strcmp($relation['api_name'],$rmodule)==0)
            {
                array_push($modulelist,$module);
                break;
            }
        }

    }
    return $modulelist;
}
function rand_string($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function random_digits($length)
{
    $result = '';

    for ($i = 0; $i < $length; $i++) {
        $result .= mt_rand(0, 9);
    }

    return $result;
}

function date_formatter($time)
{
    $date = date('Y-m-d', $time);
    return $date;
}

function datetime_formatter($time)
{
    $date = date('c', $time);
    return $date;
}
