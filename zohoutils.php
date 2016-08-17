<?php
/**
 * The file contains utility functionalies that can be used to make the api call easier.
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

/**
 * Class ZAccounts Generates authtoken and deletes authtoken for api purpose.
 */
class ZAccounts
{
    /**
     * Username of the user.
     * @access private
     * @var string
     */
    private $client_id;
    /**
     * Password of the user.
     * @access private
     * @var string
     */
    private $client_secret;
    /**
     * Generated Authtoken is stored.
     * @access private
     * @var string
     */
    private $refresh_token;
    /**
     * Display name of authtoken.
     * @access private
     * @var string
     */
    private $redirect_uri;
    /**
     * Curl Object that makes the call.
     * @access private
     * @var curlObject
     */
    private $cURL;
    /**
     * Header of the returned result.
     * @access private
     * @var array
     */
    public $header;
    /**
     * Body of the returned result.
     * @access private
     * @var array|string
     */
    public $body;

    /**
     * Accounts URL for zoho.
     */
    const _DEFAULT_ACCOUNTS_URL = "https://accounts.zoho.com/";
    const _DEFAULT_OAUTH2_URL = "https://accounts.zoho.com/oauth/v2/";
    /**
     * ZAccounts constructor.
     * @param $username Username of the user.
     * @param $password Password of the user.
     * @param $displayname Display name of authtoken.
     */
    function __construct($client_id, $client_secret, $redirect_uri,$refresh_token)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->refresh_token = $refresh_token;
        $this->cURL = curl_init();

    }

    private function _post($request_url, $params)
    {
        curl_reset($this->cURL);
        if (!empty($params)) {
            $request_url .= '?' . http_build_query($params);
        }
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->cURL, CURLOPT_FAILONERROR, true);
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->cURL, CURLOPT_HEADER, true);
        curl_setopt($this->cURL, CURLOPT_URL, $request_url);
        $response = curl_exec($this->cURL);
        if (!$response)
            throw new Exception(curl_error($this->cURL), curl_errno($this->cURL));
        $this->_parse_header_body($this->cURL,$response);
        curl_close($this->cURL);
    }
    /**
     * @internal 
     * Generates the auth url and makes the request.
     * @param $urlpath  URL to which generate authtoken request is sent.
     * @return mixed  Authtoken if the request is success.
     * @throws Exception For all invalid access throws exception.Check error message for more details.
     */
    private function _generate_access_token()
    {
        $req_url = $this::_DEFAULT_OAUTH2_URL.'token';
        $params = array("refresh_token" => $this->refresh_token, "client_id" => $this->client_id, "client_secret" => $this->client_secret,
            "redirect_uri" => $this->redirect_uri, "grant_type" => "refresh_token");
        $this->_post($req_url,$params);
        return $this->body['access_token'];
    }


    public function revoke_refresh_token()
    {
        $req_url = $this::_DEFAULT_OAUTH2_URL.'token';
        $params = array("token" => $this->refresh_token);
        $this->_post($req_url,$params);
    }

    /**
     * @internal 
     * Parses header and body of the request.
     * @param $cURL The curl object that made the request.
     * @param $response Response received from the request.
     */
    private function _parse_header_body($cURL,$response)
    {
        $header_size = curl_getinfo($cURL, CURLINFO_HEADER_SIZE);
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
        $body = substr($response, $header_size);
        $this->header = $headers;
        $this->body = json_decode($body, true);
        if(!isset($this->body))
            $this->body=$body;

    }
    /**
     * @internal 
     * Checks any error in the authtoken request.
     * @throws Exception For all invalid access throws exception.Check error message for more details.
     */
    private function _check_error()
    {
        $ret = $this->body;
        if (!$ret['status']) {
            if (array_key_exists('error',$ret))
                throw new Exception($this->_error_message($ret['error']));
            else
                throw new Exception($this->_error_message("null"));

        }

    }

    /**
     * @internal 
     * Return the error message depending on error code,
     * @param $value  Value of the error code recived.
     * @return string Error message for the request depending on value provided.
     */
    private function _error_message($value)
    {
        if (strcmp("WEB_LOGIN_REQUIRED", $value) == 0) {
            return "Please use application specific password instead of your password.";
        } else if (strcmp("EXCEEDED_MAXIMUM_ALLOWED_AUTHTOKENS", $value) == 0) {
            return "Exceeded maximum allowed authtokens";
        } else if (strcmp("null", $value) == 0) {
            return "Incorrect Email or Password.";
        } else if (strcmp("ACCOUNT_REGISTRATION_NOT_CONFIRMED", $value) == 0) {
            return "Email address has not been confirmed. Please confirm your email address to login to the application.";
        } else if (strcmp("REMOTE_SERVER_ERROR", $value) == 0) {
            return "Unable to log in. Please Contact your CRM administrator for further assistance.";
        } else if (strcmp("USER_NOT_ACTIVE", $value) == 0) {
            return "You are InActive. Please Contact your CRM administrator for further assistance.";
        } else if (strcmp("API_REQUEST_BLOCKED", $value) == 0) {
            return "Your request have been blocked. Please Contact your CRM administrator for further assistance.";
        } else if (strcmp("INVALID_PASSWORD", $value) == 0) {
            return "Incorrect Password";
        }
    }

    /**
     * To retreive an authtoken.
     * @return mixed Authtoken  if sucess.
     * @throws Exception For all invalid access throws exception.Check error message for more details.
     */
    public function get_access_token()
    {
        if (isset($this->client_id)||isset($this->client_secret)||isset($this->redirect_uri)||isset($this->refresh_token))
            return $this->_generate_access_token();
        else
            throw new Exception("Please provide client id, Client secret, redirect uri and refresh token to generate access token.", "401");

    }

}


?>