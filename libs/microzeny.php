<?php
    
$microzeny_lib_version = "v0.0.1";
    
/* 
faucet.microzney.com PHP Library

Changelog

v0.0.1
Forked from FaucetHub library

Credits to FaucetBOX.com and FaucetHub for creating the original version this library is based on
*/

class Microzeny
{
    protected $api_key;
    protected $currency;
    protected $timeout;
    public $last_status = null;
    protected $api_base = "https://faucet.microzeny.com/api/v1/";

    public function __construct($api_key, $currency = "ZNY", $disable_curl = false, $verify_peer = true, $timeout = null) {
        $this->api_key = $api_key;
        $this->currency = $currency;
        $this->disable_curl = $disable_curl;
        $this->verify_peer = $verify_peer;
        $this->curl_warning = false;
        $this->setTimeout($timeout);
    }

    public function setTimeout($timeout) {
        if($timeout === null) {
            $socket_timeout = ini_get('default_socket_timeout'); 
            $script_timeout = ini_get('max_execution_time');
            $timeout = min($script_timeout / 2, $socket_timeout);
        }
        $this->timeout = $timeout;
     }

    public function __execPHP($method, $params = array()) {
        $params = array_merge($params, array("api_key" => $this->api_key, "currency" => $this->currency));
        $opts = array(
            "http" => array(
                "method" => "POST",
                "header" => "Content-type: application/x-www-form-urlencoded\r\n",
                "content" => http_build_query($params),
                "timeout" => $this->timeout,
            ),
            "ssl" => array(
                "verify_peer" => $this->verify_peer
            )
        );
        $ctx = stream_context_create($opts);
        $fp = fopen($this->api_base . $method, 'rb', null, $ctx);
        
        if (!$fp) {
            return json_encode(array(
                'status' => 503,
                'message' => 'Connection to faucet.microzeny.com failed, please try again later',
            ), TRUE);
        }
        
        $response = stream_get_contents($fp);
        if($response && !$this->disable_curl) {
            $this->curl_warning = true;
        }
        fclose($fp);
        return $response;
    }

    public function __exec($method, $params = array()) {
        $this->last_status = null;
        if($this->disable_curl) {
            $response = $this->__execPHP($method, $params);
        } else {
            $response = $this->__execCURL($method, $params);
        }
        $response = json_decode($response, true);
        if($response) {
            $this->last_status = $response['status'];
        } else {
            $this->last_status = null;
            $response = array(
                'status' => 502,
                'message' => 'Invalid response',
            );
        }
        return $response;
    }

    public function __execCURL($method, $params = array()) {
        $params = array_merge($params, array("api_key" => $this->api_key, "currency" => $this->currency));

        $ch = curl_init($this->api_base . $method);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->timeout);

        $response = curl_exec($ch);
        if(!$response) {
            //$response = $this->__execPHP($method, $params); // disabled the exec fallback when using curl
            return json_encode(array(
                'status' => 504,
                'message' => 'Connection error',
            ), TRUE);
        }
        curl_close($ch);

        return $response;
    }

    public function send($to, $amount, $referral = false, $ip_address = "") {
        $referral = ($referral === true) ? 'true' : 'false';
        
        $r = $this->__exec("send", array("to" => $to, "amount" => $amount, "referral" => $referral, "ip_address" => $ip_address));
        if (array_key_exists("status", $r) && $r["status"] == 200) {
            return array(
                'success' => true,
                'message' => 'Payment sent to your address using faucet.microzeny.com',
                'html' => '<div class="alert alert-success">' . htmlspecialchars($amount) . ' ZNY が <a target="_blank" href="https://microzeny.com/balance' . '">あなたのmicrozenyのウォレット</a>へと送られました!</div>',
                'html_coin' => '<div class="alert alert-success">' . htmlspecialchars(rtrim(rtrim(sprintf("%.8f", $amount/100000000), '0'), '.')) . ' '.$this->currency.' が <a target="_blank" href="https://microzeny.com/balance' . '">あなたのmicrozenyのウォレット</a>へと送られました！</div>',
                'balance' => $r["balance"],
                'balance_bitzeny' => $r["balance_bitzeny"],
                'response' => json_encode($r)
            );
        }
        
        // Let the user know they need an account to claim
        if (array_key_exists("status", $r) && $r["status"] == 456) {
            return array(
                'success' => false,
                'message' => $r['message'],
                'html' => '<div class="alert alert-danger">Before you can receive payments at microzeny.com you must create an account. <a href="https://microzeny.com/login" target="_blank">Create an account at microzeny.com</a>, then come back and claim again.</div>',
                'response' => json_encode($r)
            );
        }

        if (array_key_exists("message", $r)) {
            return array(
                'success' => false,
                'message' => $r["message"],
                'html' => '<div class="alert alert-danger">' . htmlspecialchars($r["message"]) . '</div>',
                'response' => json_encode($r)
            );
        }

        return array(
            'success' => false,
            'message' => 'Unknown error.',
            'html' => '<div class="alert alert-danger">不明なエラーです</div>',
            'response' => json_encode($r)
        );
    }

    public function sendReferralEarnings($to, $amount, $ip_address = "") {
        return $this->send($to, $amount, true, $ip_address);
    }

    public function getPayouts($count) {
        $r = $this->__exec("payouts", array("count" => $count) );
        return $r;
    }

    public function getCurrencies() {
        $r = $this->__exec("currencies");
        return $r['currencies'];
    }

    public function getBalance() {
        $r = $this->__exec("balance");
        return $r;
    }
    
    public function checkAddress($address, $currency = "ZNY") {
        $r = $this->__exec("checkaddress", array('address' => $address, 'currency' => $currency));
        return $r;
    }
}
