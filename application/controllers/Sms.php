<?php
/**
 * Geo POS -  Accounting,  Invoicing  and CRM Application
 * Copyright (c) Rajesh Dukiya. All Rights Reserved
 * ***********************************************************************
 *
 *  Email: support@ultimatekode.com
 *  Website: https://www.ultimatekode.com
 *
 *  ************************************************************************
 *  * This software is furnished under a license and may be used and copied
 *  * only  in  accordance  with  the  terms  of such  license and with the
 *  * inclusion of the above copyright notice.
 *  * If you Purchased from Codecanyon, Please read the full License from
 *  * here- http://codecanyon.net/licenses/standard/
 * ***********************************************************************
 */

defined('BASEPATH') OR exit('No direct script access allowed');

use Twilio\Rest\Client;

class Sms Extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('plugins_model', 'plugins');

        $this->load->library("Aauth");
        if (!$this->aauth->is_loggedin()) {
            redirect('/user/', 'refresh');
        }
        $this->load->library('parser');

    }

    //todo section

    public function template()
    {

        $id = $this->input->post('invoiceid');
        $ttype = $this->input->post('ttype');
        if ($ttype == 'quote') {

            $invoice['tid'] = $id;
            $this->load->model('quote_model', 'quote');
            $invoice = $this->quote->quote_details($id);
            $validtoken = hash_hmac('ripemd160', 'q' . $id, $this->config->item('encryption_key'));

            $link = base_url('billing/quoteview?id=' . $id . '&token=' . $validtoken);
        } elseif ($ttype == 'purchase') {
            $invoice['tid'] = $id;
            $this->load->model('purchase_model', 'purchase');
            $invoice = $this->purchase->purchase_details($id);
            $validtoken = hash_hmac('ripemd160', $id, $this->config->item('encryption_key'));

            $link = base_url('billing/purchase?id=' . $id . '&token=' . $validtoken);
        } else {
            $invoice['tid'] = $id;

            $this->load->model('invoices_model', 'invoices');
            $invoice = $this->invoices->invoice_details($id);

            $validtoken = hash_hmac('ripemd160', $id, $this->config->item('encryption_key'));

            $link = base_url('billing/view?id=' . $id . '&token=' . $validtoken);
        }

        $sms_service = $this->plugins->universal_api(1);

        if ($sms_service['active']) {

            $this->load->library("Shortenurl");
            $this->shortenurl->setkey($sms_service['key1']);
            $link = $this->shortenurl->shorten($link);

        }

        $this->load->model('templates_model', 'templates');
        switch ($ttype) {
            case 'notification':
                $template = $this->templates->template_info(30);
                break;

            case 'reminder':
                $template = $this->templates->template_info(31);
                break;

            case 'refund':
                $template = $this->templates->template_info(32);
                break;


            case 'received':
                $template = $this->templates->template_info(33);
                break;

            case 'overdue':
                $template = $this->templates->template_info(34);
                break;


            case 'quote':
                $template = $this->templates->template_info(35);
                break;


            case 'purchase':
                $template = $this->templates->template_info(36);
                break;


        }

        $data = array(
            'BillNumber' => $invoice['tid'],
            'URL' => $link,
            'DueDate' => dateformat($invoice['invoiceduedate']),
            'Amount' => amountExchange($invoice['total'], $invoice['multi'])
        );
        $message = $this->parser->parse_string($template['other'], $data, TRUE);


        echo json_encode(array('message' => $message));
    }


    public function send_sms()
    {
        /*
         * Define The SMS Gateway - Default Gateway is Twilio which can be configured via user interface
         * Other providers like 'TextLocal' need a manual configuration
         *   $gateway_code = 1;
         *  1 for twilio
         *  2 for TextLocal
         *  3 for Clockwork
         * 4 For Any Generic
         */
        #################################
        ########### SWITCH HERE###########
        $gateway_code = 1;
        ################################

        $mobile = $this->input->post('mobile');
        $text_message = $this->input->post('text_message');

        switch ($gateway_code) {
            case 1:
                $this->twilio($mobile, $text_message);
                break;
            case 2:
                $this->textlocal($mobile, $text_message);
                break;
            case 3:
                $this->clockwork($mobile, $text_message);
                break;
            case 4:
                $this->generic($mobile, $text_message);
                break;
            case 5:
                $this->msg91($mobile, $text_message);
                break;
        }
    }


    private function twilio($mobile, $text_message)
    {
        require APPPATH . 'third_party/twilio-php-master/Twilio/autoload.php';

        $sms_service = $this->plugins->universal_api(2);


// Your Account SID and Auth Token from twilio.com/console
        $sid = $sms_service['key1'];
        $token = $sms_service['key2'];
        $client = new Client($sid, $token);


        $message = $client->messages->create(
        // the number you'd like to send the message to
            $mobile,
            array(
                // A Twilio phone number you purchased at twilio.com/console
                'from' => $sms_service['url'],
                // the body of the text message you'd like to send
                'body' => $text_message
            )
        );

        if ($message->sid) {
            echo json_encode(array('status' => 'Success', 'message' => 'Message sending successful. Current Message Status is ' . $message->status));
        } else {
            echo json_encode(array('status' => 'Error', 'message' => 'SMS Service Error'));
        }
    }


    private function textlocal($mobile, $text_message)
    {

        $apiKey = urlencode('Your apiKey');
        // Message details
        $numbers = array($mobile);
        $sender = urlencode('TXTLCL');
        $text_message = rawurlencode($text_message);
        $numbers = implode(',', $numbers);
        // Prepare data for POST request
        $data = array('apikey' => $apiKey, 'numbers' => $numbers, "sender" => $sender, "message" => $text_message);
        // Send the POST request with cURL
        $ch = curl_init('https://api.textlocal.in/send/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        // Process your response here
        $result = json_decode($response, true);

        if ($result['status'] == 'success') {
            echo json_encode(array('status' => 'Success', 'message' => 'Message sending successful. Current Message Status is ' . $result['status']));
        } else {
            echo json_encode(array('status' => 'Error', 'message' => 'SMS Service Error'));
        }

    }

    private function clockwork($mobile, $text_message)
    {
        $apiKey = urlencode('Your apiKey');
        // Message details

        $sender = urlencode('TXTLCL');
        $text_message = rawurlencode($text_message);

        // Prepare data for POST request
        $data = array('key' => $apiKey, 'to' => $mobile, "sender" => $sender, "content" => $text_message);
        // Send the POST request with cURL
        $ch = curl_init('https://api.clockworksms.com/http/send.aspx');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        // Process your response here
        //$result= json_decode($response,true);

        if ($response) {
            echo json_encode(array('status' => 'Success', 'message' => 'Message sending successful. Current Message Status is ' . $response));
        } else {
            echo json_encode(array('status' => 'Error', 'message' => 'SMS Service Error'));
        }
    }


    private function generic($mobile, $text_message)
    {

        $apiKey = urlencode('Your apiKey');

        //Optional
        $sender = urlencode('SENDER_ID');

        //Message
        $text_message = rawurlencode($text_message);


        //Please enter correct parameter1,parameter2,parameter3 - it can any as per your gateway variable
        $data = array('parameter1' => $apiKey, 'parameter2' => $mobile, "parameter3" => $sender, "message" => $text_message);


        // Prepare data for POST request
        // Send the POST request with cURL
        $ch = curl_init('https://your-sms-api-url.com');


        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        // Process your response here
        $result = json_decode($response, true);

        if ($response) {
            echo json_encode(array('status' => 'Success', 'message' => 'Message sending successful. Current Message Status is ' . $result['status']));
        } else {
            echo json_encode(array('status' => 'Error', 'message' => 'SMS Service Error'));
        }

    }

    private function nexmo($mobile, $text_message)
    {
        //pending for release
    }

    private function msg91($mobile, $text_message)
    {
        $country = 91;
        $sender_id = 'SOCKET';
        $route = '4';
        $authkey = '';


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://api.msg91.com/api/v2/sendsms?country=$country&sender=$sender_id&route=$route&mobiles=$mobile&authkey=$authkey&encrypt=&message=$text_message&flash=&unicode=&schtime=&afterminutes=&response=&campaign=",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{ \"sender\": \"SOCKET\", \"route\": \"4\", \"country\": \"SOCKET\", \"sms\": [ { \"message\": \"$text_message\", \"to\": [ \"$mobile\" ] } ] }",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array(
                "authkey: ",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
            echo json_encode(array('status' => 'Error', 'message' => 'Message sending Error. ' . $err));
        } else {
            $xml = simplexml_load_string($response);
            $json = json_encode($xml);
            $arr = json_decode($json, true);

            $temp = array();
            foreach ($arr as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $temp[$k][$k1] = $v1;
                }
            }
            echo json_encode(array('status' => 'Success', 'message' => 'Message sending successful. Current Message Status is ' . $response['TYPE']));

        }
    }


}


