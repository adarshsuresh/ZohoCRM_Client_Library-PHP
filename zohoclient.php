<?php
/**
 *
 * This file deals with all the API requests and it contains the main class ZClient which inturn makes all the call to ZohoCRM.
 *
 * @package ZohoCRM_Client_Library
 */
/**
 * @copyright  (c) 2016.  Zoho Corporation
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
 *
 * @author Adarsh Suresh Managalth <adarsh@zohocorp.com
 *
 */

require_once('zohoobject.php');
require_once ('zohoutils.php');

/**
 * Class ZClient
 * Performs requests to the Zoho CRM Rest API services.
 */
class ZClient
{
    /**
     * Accounts object that stores the username password and account.
     * @access private
     * @var object
     */
    private $account;
    /**
     * Authtoken to autorize all api calls.
     * @access private
     * @var string
     */
    private $authtoken;
    /**
     * Curl Object that makes the call.
     * @access private
     * @var curlObject
     */
    private $cURL;
    /**
     * Default url for all Zoho CRM API requets.
     */
    const _DEFAULT_ZOHO_URL = 'https://crm.localzoho.com/crm/v2/';
    /**
     * Default file name to store metadata.
     */
    const _METADATA_CONF = "metadata.conf";
    /**
     * Returned header of the last request.
     * @access public
     * @var array
     */
    public $headers;
    /**
     * Returned body of last request.
     * @access public
     * @var array
     */
    public $body;
    /**
     * No of api calls allowed per day.
     * @access public
     * @var string
     */
    public $X_RATELIMIT_DAY_LIMIT;
    /**
     * No of remaining api calls on a particular day.
     * @access public
     * @var string
     */
    public $X_RATELIMIT_DAY_REMAINING;
    /**
     * No of api calls are allowed per minute.
     * @access public
     * @var string
     */
    public $X_RATELIMIT_LIMIT;
    /**
     * No of remaining api calls which are allowed in the current window.
     * @access public
     * @var string
     */
    public $X_RATELIMIT_REMAINING;
    /**
     * In case of window limit exceeded, it indicated the expire time of the current window.
     * @access public
     * @var string
     */
    public $X_RATELIMIT_RESET;
    /**
     * In case of access token limit exceeded, it indicated the expire time of the current access token.
     * @access public
     * @var string
     */
    public $X_ACCESSTOKEN_RESET;
    /**
     * Metadata of all modules loaded.
     * @access public
     * @var string
     */
    public $modules_metadata;

    /**
     * ZClient constructor. Should either provide authtoken or username/password. Genereates authtoken if not provided.
     * @param string $authtoken Authtoken to make autherize API call.
     * @param string $username Username of the user.
     * @param string $password Password of the user.
     */
    function __construct($client_id = NULL, $client_secret = NULL,$redirect_uri=NULL,$access_token = NULL, $refresh_token=NULL)
    {
        $this->access_token = $access_token;
        $this->cURL = curl_init();
        $this->headers = array();
        $this->body = NULL;
        $this->X_RATELIMIT_DAY_LIMIT = NULL;
        $this->X_RATELIMIT_DAY_REMAINING = NULL;
        $this->X_RATELIMIT_LIMIT = NULL;
        $this->X_RATELIMIT_REMAINING = NULL;
        $this->X_RATELIMIT_RESET = NULL;
        $this->account = new ZAccounts($client_id, $client_secret,$redirect_uri,$refresh_token);
        if (!isset($access_token)) {
                $this->access_token = $this->account->get_access_token();
        }
    }

    /**
     * Destructor to close the curl,delete authtoken and destroy object.
     */
    public function __destruct()
    {
        //curl_close($this->cURL);
    }

    /**
     * @internal
     * GET request formater for the ZClient.
     * @param $request_url URL for the get request.
     * @param $params HTTP parameters for the request.
     * @param array $headers HTTP headers for the request.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _get($request_url, $params, $headers = array(),$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        if (!empty($params)) {
            $request_url .= '?' . http_build_query($params);
        }
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_get($request_url,$params,$headers,0);}
        else
            $this->_parse_header_body($response);

    }

    /**
     * @internal
     * POST request formater for the ZClient.
     * @param $request_url URL for the post request.
     * @param $params HTTP parameters for the request.
     * @param $payload JSON data that should be sent with post request.
     * @param array $headers HTTP headers for the request.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _post($request_url, $params, $payload, $headers = array(),$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        if (!empty($params)) {
            $request_url .= '?' . http_build_query($params);
        }
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, array_merge(array('Content-Type: application/json', 'Content-Length: ' . strlen($payload)),$headers));
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_post($request_url,$params,$payload,$headers,0);}
        else
            $this->_parse_header_body($response);
    }

    /**
     * @internal
     * PUT request formater for the Zclient.
     * @param $request_url URL for the put request.
     * @param $params HTTP parameters for the request.
     * @param $payload JSON data that should be sent with put request.
     * @param array $headers HTTP headers for the request.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _put($request_url, $params, $payload, $headers = array(),$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        if (!empty($params)) {
            $request_url .= '?' . http_build_query($params);
        }
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER,array_merge(array('Content-Type: application/json', 'Content-Length: ' . strlen($payload)),$headers));
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_put($request_url,$params,$payload,$headers,0);}
        else
            $this->_parse_header_body($response);
    }

    /**
     * @internal
     * DELETE request formater for the Zclient.
     * @param $request_url URL for the delete request.
     * @param array $headers HTTP headers for the request.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _delete($request_url, $headers = array(),$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_delete($request_url,$headers,0);}
        else
            $this->_parse_header_body($response);
    }

    /**
     * @internal
     * Primary upload a file request formater for the Zclient
     * @param $request_url Url for the request
     * @param $headers headers passed to request
     * @param $filename filename with location of uploading file
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _upload_file($request_url, $headers, $filename,$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        $curl_file = curl_file_create($filename);
        $data = array('file' => $curl_file);
        curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_upload_file($request_url,$headers,$filename,0);}
        else
            $this->_parse_header_body($response);
    }

    /**
     * @internal
     * Primary download a file request formater for the Zclient
     * @param $request_url Url for the request
     * @param $headers headers passed to request
     * @param $filename Location where the file should be downloaded to
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    private function _download_file($request_url, $headers, $filename,$retry=1)
    {
        $this->_autherise_url($headers);
        curl_reset($this->cURL);
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->cURL, CURLOPT_HEADER, false);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        $fp = fopen($filename, 'w+');
        curl_setopt($this->cURL, CURLOPT_FILE, $fp);
        curl_setopt($this->cURL, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        if(curl_getinfo($this->cURL,CURLINFO_HTTP_CODE )==401 && $retry==1)
        {$this->access_token=$this->account->get_access_token();
            $this->_download_file($request_url,$headers,$filename,0);}
        else
            $this->_parse_header_body($response);
        fclose($fp);

    }

    /**
     * @internal
     * To authorize the url with authtoken.
     * @param $header The array of headers for the request
     */
    private function _autherise_url(&$header)
    {
        array_push($header, "Authorization:Zoho-authtoken " . $this->access_token);
    }

    /**
     * @internal
     * This function parses the response to retrive the header to an array and stores in the zclients header.
     * @param $response The response of curl request.
     */
    private function _parse_header_body($response)
    {
        $header_size = curl_getinfo($this->cURL, CURLINFO_HEADER_SIZE);
        $headerContent = substr($response, 0, $header_size);
        $headers = array();
        $arrRequests = explode("\r\n\r\n", $headerContent);
        for ($index = 0; $index < count($arrRequests) - 1; $index++) {

            foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
                if ($i === 0)
                    $headers[$index]['http_code'] = $line;
                else {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$index][$key] = $value;
                }
            }
        }
        $headers = array_pop($headers);
        if (isset($headers)) {
            if (array_key_exists('X-RATELIMIT-DAY-LIMIT', $headers)) {
                $this->X_RATELIMIT_DAY_LIMIT = $headers['X-RATELIMIT-DAY-LIMIT'];
            }
            if (array_key_exists('X-RATELIMIT-DAY-REMAINING', $headers)) {
                $this->X_RATELIMIT_DAY_REMAINING = $headers['X-RATELIMIT-DAY-REMAINING'];
            }
            if (array_key_exists('X-RATELIMIT-LIMIT', $headers)) {
                $this->X_RATELIMIT_LIMIT = $headers['X-RATELIMIT-LIMIT'];
            }
            if (array_key_exists('X-RATELIMIT-REMAINING', $headers)) {
                $this->X_RATELIMIT_REMAINING = $headers['X-RATELIMIT-REMAINING'];
            }
            if (array_key_exists('X-RATELIMIT-RESET', $headers)) {
                $this->X_RATELIMIT_RESET = $headers['X-RATELIMIT-RESET'];
            }
            if (array_key_exists('X-ACCESSTOKEN-RESET', $headers)) {
                $this->X_ACCESSTOKEN_RESET = $headers["X-ACCESSTOKEN-RESET"];
            }
        }
        $this->headers = $headers;
        $body = substr($response, $header_size);
        $this->body = json_decode($body, true);
        $this->_exception_handler($this->body);
    }

    /**
     * @internal
     * This function parses the body to an json array and stores in the body of zclient.
     * @param $response The response of curl request.
     */
    private function _exception_handler()
    {
    if(!empty($this->body) && array_key_exists('code', $this->body) && strcmp(  $this->body['code'], 'SUCCESS') != 0)
    {
        throw new Exception( $this->body['message'], curl_getinfo($this->cURL,CURLINFO_HTTP_CODE ));
    }
//        elseif result . status_code == 400:
//    raise ZohoError(result . url, result . status_code, "BAD REQUEST")
//    elif result . status_code == 401:
//    raise ZohoError(result . url, result . status_code, "AUTHORIZATION ERROR")
//    elif result . status_code == 403:
//    raise ZohoError(result . url, result . status_code, "FORBIDDEN")
//    elif result . status_code == 404:
//    raise ZohoError(result . url, result . status_code, "NOT FOUND")
//    elif result . status_code == 405:
//    raise ZohoError(result . url, result . status_code, "METHOD NOT ALLOWED")
//    elif result . status_code == 413:
//    raise ZohoError(result . url, result . status_code, "REQUEST ENTITY TOO LARGE")
//    elif result . status_code == 415:
//    raise ZohoError(result . url, result . status_code, "UNSUPPORTED MEDIA TYPE")
//    elif result . status_code == 429:
//    raise ZohoError(result . url, result . status_code, "TOO MANY REQUEST")
//    elif result . status_code == 500:
//    raise ZohoError(result . url, result . status_code, "INTERNAL SERVER ERROR")
//    elif result . status_code > 400:
//    raise ZohoError(result . url, result . status_code, "UNEXPECTED ERROR")
    }

    /**
     * @internal
     * To generate ZObjects for all get type requests depending on entity.
     * @param $myjson The JSON data returned from the get requests.
     * @param $type Type of the data that is being called. <br> eg : 'data' for all records , 'users' for user details etc.
     * @param $module Module of the data retrived.
     * @return array|null  An array of ZObjects or null if empty.
     */
    private function _generate_zobjects($myjson, $type, $module)
    {
        $zobjects = array();
        if (empty($myjson))
            return array();
        if (array_key_exists($type, $myjson)) {
            $mylist = $myjson[$type];
        } else {
            return $myjson;
        }
        if (strcmp($type, "data") == 0) {
            foreach ($mylist as $item) {
                if (array_key_exists($module,$this->modules_metadata))
                   $module_metadata=&$this->modules_metadata[$module];
                else
                    $module_metadata=null;
                $zobject = new ZObject($this, $item, $module, $module_metadata);
                array_push($zobjects, $zobject);
            }
            return $zobjects;
        }
        return $mylist;
    }

    /**
     * @internal
     * To generate ZObjects for all put and post type requests depending on entity.
     * @param $data The JSON data returned from the get requests.
     * @param $module Module of the data retrived.
     * @return array An array of ZObjects or null if empty.
     */
    private function _generate_zobjects_iu($data, $module)
    {
        $zobjects = array();
        $retdata = $this->body["data"];
        for ($i = 0; $i < count($retdata); $i++) {
            if (strcmp($retdata[$i]['code'], "SUCCESS") == 0) {
                $data[$i]["id"] = $retdata[$i]["details"]["id"];
                $data[$i]["created_by"] = $retdata[$i]["details"]["created_by"];
                $data[$i]["modified_by"] = $retdata[$i]["details"]["modified_by"];
                $data[$i]["modified_time"] = $retdata[$i]["details"]["modified_time"];
                $data[$i]["created_time"] = $retdata[$i]["details"]["created_time"];
            }
            if (array_key_exists($module,$this->modules_metadata))
            { $moduledata=&$this->modules_metadata[$module];}
            else
            {  $module=null;}
            $zobject = new ZObject($this, $data[$i], $module, $moduledata, $retdata[$i]['message'], $retdata[$i]['status'],"",$retdata[$i]['details'],$retdata[$i]['code']);
            array_push($zobjects, $zobject);
        }
        return $zobjects;
    }

    /**
     * @internal
     * To get relatedlist id if provided NULL from metadata.conf.
     * @param $related_module Related module .
     * @param $module Module.
     * @return mixed The list_relation_id from the metadata of the Related module.
     */
    private function _get_related_list_id($related_module, $module)
    {
        $metadata = $this->modules_metadata[$related_module];
        foreach ($metadata["relations"] as $relmodule) {
            if (strcmp($relmodule["module"], $module) == 0) {
                return $relmodule["list_relation_id"];
            }
        }
    }

    /**
     * Loads all module details for easy use of the ZohoCRM APIs.
     * @param bool $file Depending on $file value fetches from the server or local file.
     * <br>
     * True : Tries to fetch from local and if unavailable fetches from the server.
     * <br>
     * False: Fetched from the server and updates the loacl file.
     * @param array $modules Use if only perticular modules is needed to be loaded.
     */
    public function loadModules($file, $modules = array())
    {
        if ($file) {
            if (file_exists($this::_METADATA_CONF)) {
                $str = file_get_contents($this::_METADATA_CONF);
                $metadata = unserialize($str);
                $this->modules_metadata = $metadata;
            } else
                $file = false;
        }
        if (!$file) {
            $metadata = array();

            if (empty($modules)) {
                $objects = $this->modules();
                foreach ($objects as $object) {
                    if ($object['api_supported'])
                        array_push($modules, $object['api_name']);
                }
            }
            foreach ($modules as $module) {
                $mymetadata = $this->metadata($module);
                $metadata[$module] = $mymetadata;
            }
            $this->modules_metadata = $metadata;
            if (!empty($metadata)) {
                $str = serialize($metadata);
                $fh = fopen($this::_METADATA_CONF, 'w');
                fwrite($fh, $str);
                fclose($fh);
            }
        }
        return $this->modules_metadata;
    }

    /**
     * To get the users in the organization.
     * @param array $params Parameters for the user get requests.
     * <br>type={AllUsers|ActiveUsers|DeactiveUsers|ConfirmedUsers|NotConfirmedUsers|DeletedUsers|ActiveConfirmedUsers|AdminUsers|ActiveConfirmedAdmins|CurrentUser}
     * @return array|null An array of users and their details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function users($params = array())
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "users";
        $this->_get($url_path, $params);
        return $this->_generate_zobjects($this->body, "users", "users");
    }

    /**
     * To get the module related data.
     * <br>
     * <b>Note :</b>The key "api_name" in each module will be used to access the resource.
     * @return array|null A json array with modules details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function modules()
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "settings/modules";
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, "modules", "modules");
    }

    /**
     * To get the meta data (fields, related list, module, layouts data) for the modules (Contacts,Leads).
     * @param $module The module for which metadata should be fetched.
     * @return array|null A json array with module's metadata
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function metadata($module)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "settings/modules/" . $module;
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, NULL, $module . " Metadata");
    }

    /**
     * To get the layouts associated with the particular module or to get the particular layout details.
     * @param null $id Id of the layout to be fetched (optional)
     * @param array $params Parameters of the get request.
     *      <br>module={MODULE}
     * @return array|null A json array with layout's details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function layouts($id = NULL, $params = array())
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "settings/layouts";
        if (!is_null($id)) {
            if (is_int($id))
                $url_path .= "/" . strval($id);
            elseif (is_string($id))
                $url_path .= "/" . $id;
        }
        $this->_get($url_path, $params);
        return $this->_generate_zobjects($this->body, "layouts", "layouts");
    }

    /**
     * To get the records from a module.
     * @param $module  The module of the record to be fetched.
     * @param array $params Optional parameters for the request:<br>
     *              sort_by, fields (comma separated), sort_order (asc or desc), converted (true or false), approved (true or false), page(1,2..),per_page(200)
     * @param array $header Optional header for request:<br> array(If-Modified-Since=>last modified time)
     * @return array|null An array of ZObject with fetched records.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function get($module, $params = array(), $header = array())
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module;
        $this->_get($url_path, $params, $header);
        return $this->_generate_zobjects($this->body, "data", $module);
    }

    /**
     * To get the record from a module by id.
     * @param $module  The module of the record to be fetched.
         * @param $id Id of the record to be fetched.
     * @return null|ZObject A ZObject with the details of that perticular record.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function get_id($module, $id)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id;
        $this->_get($url_path, array());
        
        if (array_key_exists('data', $this->body)) {
            if (array_key_exists($module,$this->modules_metadata))
                $module_metadata=&$this->modules_metadata[$module];
            else
                $module_metadata=null;
            return new ZObject($this, $this->body['data'][0], $module, $module_metadata);
        }
        return NULL;
    }

    /**
     * To insert a new record into the module.
     * @param $module  The module of the record to be inserted.
     * @param $data Data to be inserted in a JSON array.
     *              <br><code>array(array("Last_Name" => "Last_Name"), array("Last_Name" => "Last_Name"),<br> array("First_Name" => "First_Name"))</code>
     * @return array An array of ZObjects with inserted records
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function insert($module, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module;
        $this->_post($url_path, array(), json_encode(array("data" => $data)));
        return $this->_generate_zobjects_iu($data, $module);
    }

    /**
     * To update the existing entity into the module.
     * @param $module The module of the record to be updated.
     * @param $data Data to be update in a json array.<br>
     *          <code>array(array("id"=>"410888000000482039","Last_Name" => "Last_Name"),<br> array("id"=>"410888000000482039","Last_Name" => "Last_Name")</code>
     * @return array  An array of ZObjects with updated records.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function update($module, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module;
        $this->_put($url_path, array(), json_encode(array("data" => $data)));
        return $this->_generate_zobjects_iu($data, $module);
    }

    /**
     * To delete the particular entity.
     * @param $module The module of the record to be deleted.
     * @param $id Id of the record to be deleted.
     * @return array An json array having details of deletion.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function delete($module, $id)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id;
        $this->_delete($url_path);
        return $this->body;
    }

    /**
     *  To insert the record if not exists already (checked based on duplicate check). If the records exists , then it'll be updated.
     * @param $module  The module of the record to be upserted.
     * @param $data  Data to be inserted in a json array. <br><b>Note:</b>Data format same as insert and update
     * @return array An array of ZObjects with updated records details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function upsert($module, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/upsert";
        $this->_put($url_path, array(), json_encode(array("data" => $data)));
        return $this->_generate_zobjects_iu($data, $module);

    }

    /**
     * To get the deleted records.
     * @param $module The module from deleted records to be fetched.
     * @param array $params Optional parameters for the request <br> eg:type (all, recycle, permanent) - recycle bin records or permanently deleted records
     * @return array|null An array of ZObject with updated records details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function get_deleted($module, $params = array())
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/deleted";
        $this->_get($url_path, $params);
        return $this->_generate_zobjects($this->body, "data", $module);
    }

    /**
     * To get the related list records.
     * <b>Note:</b>
     * related_module: Leads | related_id: 3000000029001 | module: Notes | related_list_id: 3000000013433
     * The function will fetch the Notes which are located under the lead with id 3000000029001 and Note's related list id is 3000000013433
     * @param $related_module The module of the record.
     * @param $related_id Id of the record of related module.
     * @param $module The module to which the record is related.
     * @param null $related_list_id Related list id feteched from the relations in the metadata of the module.
     * <br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @return array|null  An array of ZObjects with related records
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     *
     */
    public function related_list($related_module, $related_id, $module)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $related_module . "/" . $related_id . "/" . $module;
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, "data", $module);
    }

    /**
     * To insert notes to the records.
     * @param $related_module The module of the record.
     * @param $related_id Id of the record of related module.
     * @param $data Data to be inserted in a array of JSON.
     *              <br><code>array(array("Note_Title"=>"Contacted","Note_Content"=> "Tracking done. Happy with the customer"),<br>array("Note_Title"=>"Contacted2","Note_Content"=> "Tracking done. Happy with the customer"))</code>
     * @param null $related_list_id Related list id feteched from the relations in the metadata of the module
     * <br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @return array An array of ZObject with inserted note details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function insert_notes($related_module, $related_id, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $related_module . "/" . $related_id . "/" . "Notes";
        $this->_post($url_path, array(), json_encode(array("data" => $data)));
        return $this->_generate_zobjects_iu($data, "Notes");

    }

    /**
     * To update the relation between the records.
     * Supported Modules :
     * Campaigns -> Leads, Contacts
     * Products -> Leads, Contacts, Accounts, Potentials, Price Books
     * or their reverse as in Leads -> Campaings , Products
     *
     * Sample Data For Adding Relation Between Leads, Campaigns (Not Mandatory, you can send the request without body)
     * datalist:[ {"Status" : "active"}]
     *
     * Sample Data For Adding Relation Between Contact, Potentials (Mandatory, you need to send body)
     * datalist :[ {"Contact_Role" : "1000000024229"}]
     *
     * Sample Data For Adding Relation Between Products, Price_Books (Mandatory, you need to send body)
     * datalist :[ {"list_price" : 50.56}]
     *
     * Adding Relation Between Other Modules, Need Not To Send The Body
     * @param $related_module The module of the record.
     * @param $related_id Id of the record of related module.
     * @param $module The module to which the record is related.
     * @param $related_list_id Related list id feteched from the relations in the metadata of the module.
     * <br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @param $module_id Id of the record that has to be updated in the relation.
     * @param $data Data to be inserted in a JSON array.
     * @return array a array of ZObject with related records.
     * @throws For all invalid requests see http status codes for Zoho CRM APIs for more details.
     *
     */
    public function update_relation($related_module, $related_id, $module,$relation_name, $module_id, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $related_module . "/" . $related_id  . "/" . $relation_name."/" . $module_id;
        $this->_put($url_path, array(), json_encode(array("data" => $data)));
        return $this->_generate_zobjects_iu($data, $module);

    }

    /**
     * To delete the association between modules.
     * @param $related_module The module of the record.
     * @param $related_id Id of the record of related module.
     * @param $module The module to which the record is related.
     * @param $related_list_id Related list id feteched from the relations in the metadata of the module.
     *<br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @param $module_id Id of the record that has to be updated in the module.
     * @return null an array of json with deleted details.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function delete_relation($related_module, $related_id, $module,$relation_name, $module_id)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $related_module . "/" . $related_id . "/" . $relation_name."/" . $module_id;
        $this->_delete($url_path);
        return $this->body;
    }


    /**
     * To convert a lead.
     * @param $id Id of the lead record to be converted.
     * @param $data Data to be inserted as json array.
     *      <br> <code>array(array("overwrite"=>True, "notify_lead_owner"=> True, "notify_new_entity_owner"=> True,<br>
     *                          "account"=> "1892544000000118116", "contact"=> "1892544000000101144",<br>
     *                           "potential"=> array(
     *                                               "Potential_Name"=> "Potential_Name0",<br>
     *                                                "Closing_Date"=> "2016-02-18",<br>
     *                                                 "Stage"=> "Stage0",<br>
     *                                                 "Amount"=> 56.6)))</code>
     * @return array  A json array with details of conversion.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function convert_lead($id, $data)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "Leads/" . $id . "/convert";
        $this->_post($url_path, array(), json_encode(array("data" => $data)));
        return $this->body;
    }

    /**
     * To upload a photo (as MULTIPART) to a record in Leads or Contacts.
     * @param $module The module to which the record has to be updated(Contacts or Leads).
     * @param $id Id of the record where the photo should be added.
     * @param $file Filename of the photo(full path).
     * @param $mimetype Mimetype of the image.
     * @return array A json array with details of insertion.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function upload_photo($module, $id, $file)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id . "/photo";
        $this->_upload_file($url_path, array(), $file);
        return $this->body;
    }

    /**
     * To download the photo associated with a Lead/Contact.
     * @param $module The module from which photo to be downloaded.
     * @param $id Id of the record from which photo should be downloaded.
     * @param $filename Filename to be saved.
     * @return null
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function download_photo($module, $id, $filename)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id . "/photo";
        return $this->_download_file($url_path, array(), $filename);
    }

    /**
     * To upload a file (as MULTIPART) as attachment.
     * @param $module The module to which the file has to be uploaded.
     * @param $id Id of the record where the file should be added.
     * @param $related_id Related list id feteched from the relations in the metadata of the module
     * <br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @param $file_name Filename of the file.
     * @return null A json array with details of insertion.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function upload_file($module, $id, $file_name)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id . "/Attachments";
        $this->_upload_file($url_path, array(), $file_name);
        return $this->body;
    }

    /**
     * To download a file.
     * @param $module The module from which the file has to be download.
     * @param $id Id of the record  from where the file should be downloaded.
     * @param $related_id Related list id feteched from the relations in the metadata of the module.
     * <br><b>Note:</b> If loadmodules is used and related_list_id if provided null ,related _list_id will be fetched from metadata.conf
     * @param $file_id Id of the file from related list records of attachments.
     * @param $file_name Filename of the file from related list records of attachments.
     * @return null
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function download_file($module, $id, $file_id, $file_name)
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/" . $id . "/Attachments/".$file_id;
        return $this->_download_file($url_path, array(), $file_name);
    }

    /**
     * To search the records.
     * @param $module The module which should be searched.
     * @param array $params Parameters for the request passed as an array:
     * <br>
     * Search By Criteria
     * criteria = (({apiname}:{starts_with|equals}=>{value}) and ({apiname}:{starts_with|equals}=>{value}))...
     * Global Search By Email (all the email fields in particular module)
     * email = {email}
     * Global Search By Phone (similar to email)
     * phone = {phone}
     * Global Search By Word (searching on all the fields in particular module)
     * word = {word}
     * all the get api supported params are available (fields, converted, approved, page, per_page)
     * @return array|null An array of ZObject with fetched records.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function search($module, $params = array())
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . $module . "/search";
        $this->_get($url_path, $params);
        return $this->_generate_zobjects($this->body, "data", $module);
    }

    /**
     * To get all the taxes.
     * @return array|null A json array with all the taxes.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function taxes()
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "taxes";
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, "taxes", "taxes");

    }

    /**
     * To get all the contact roles.
     * @return array|null A json array with all the contact roles.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function roles()
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "Contacts/roles";
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, "contact_roles", "contact_roles");

    }

    /**
     *  To get all the tab groups.
     * @return array|null  A json array with all the tob groups.
     * @throws Exception For all invalid requests see http status codes for Zoho CRM APIs for more details.
     */
    public function tab_groups()
    {
        $url_path = $this::_DEFAULT_ZOHO_URL . "settings/tab_groups";
        $this->_get($url_path, array());
        return $this->_generate_zobjects($this->body, "tab_groups", "tab_groups");

    }

}