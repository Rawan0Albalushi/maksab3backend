<?php
namespace App\Library\SslCommerz;

class SslCommerzNotification extends AbstractSslCommerz
{
    protected $data = [];
    protected $config = [];

    private $successUrl;
    private $cancelUrl;
    private $failedUrl;
    private $error;

    public function __construct()
    {
        $this->config = config('sslcommerz');

        $this->setStoreId($this->config['apiCredentials']['store_id']);
        $this->setStorePassword($this->config['apiCredentials']['store_password']);
    }

    public function orderValidate($trx_id = '', $amount = 0, $currency = "BDT", $post_data)
    {
        if ($post_data == '' && $trx_id == '' && !is_array($post_data)) {
            $this->error = "Please provide valid transaction ID and post request data";
            return $this->error;
        }

        $validation = $this->validate($trx_id, $amount, $currency, $post_data);

        if ($validation) {
            return true;
        } else {
            return false;
        }
    }


    protected function validate($merchant_trans_id, $merchant_trans_amount, $merchant_trans_currency, $post_data)
    {
        if ($merchant_trans_id != "" && $merchant_trans_amount != 0) {

            $post_data['store_id'] = $this->getStoreId();
            $post_data['store_pass'] = $this->getStorePassword();

            if ($this->SSLCOMMERZ_hash_varify($this->getStorePassword(), $post_data)) {

                $val_id = urlencode($post_data['val_id']);
                $store_id = urlencode($this->getStoreId());
                $store_passwd = urlencode($this->getStorePassword());
                $requested_url = ($this->config['apiDomain'] . $this->config['apiUrl']['order_validate'] . "?val_id=" . $val_id . "&store_id=" . $store_id . "&store_passwd=" . $store_passwd . "&v=1&format=json");

                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $requested_url);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

                 if ($this->config['connect_from_localhost']) {
                     curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
                     curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
                 } else {
                     curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
                     curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 2);
                 }


                $result = curl_exec($handle);

                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($code == 200 && !(curl_errno($handle))) {


                    $result = json_decode($result);
                    $this->sslc_data = $result;

                    $status = $result->status;
                    $tran_date = $result->tran_date;
                    $tran_id = $result->tran_id;
                    $val_id = $result->val_id;
                    $amount = $result->amount;
                    $store_amount = $result->store_amount;
                    $bank_tran_id = $result->bank_tran_id;
                    $card_type = $result->card_type;
                    $currency_type = $result->currency_type;
                    $currency_amount = $result->currency_amount;

                    $card_no = $result->card_no;
                    $card_issuer = $result->card_issuer;
                    $card_brand = $result->card_brand;
                    $card_issuer_country = $result->card_issuer_country;
                    $card_issuer_country_code = $result->card_issuer_country_code;

                    $APIConnect = $result->APIConnect;
                    $validated_on = $result->validated_on;
                    $gw_version = $result->gw_version;

                    if ($status == "VALID" || $status == "VALIDATED") {
                        if ($merchant_trans_currency == "BDT") {
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $amount) < 1) && trim($merchant_trans_currency) == trim('BDT')) {
                                return true;
                            } else {
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        } else {
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $currency_amount) < 1) && trim($merchant_trans_currency) == trim($currency_type)) {
                                return true;
                            } else {
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        }
                    } else {
                        $this->error = "Failed Transaction";
                        return false;
                    }
                } else {
                    $this->error = "Faile to connect with SSLCOMMERZ";
                    return false;
                }
            } else {
                $this->error = "Hash validation failed";
                return false;
            }
        } else {
            $this->error = "Invalid data";
            return false;
        }
    }

    protected function SSLCOMMERZ_hash_varify($store_passwd = "", $post_data)
    {
        if (isset($post_data) && isset($post_data['verify_sign']) && isset($post_data['verify_key'])) {
            $pre_define_key = explode(',', $post_data['verify_key']);

            $new_data = array();
            if (!empty($pre_define_key)) {
                foreach ($pre_define_key as $value) {
                        $new_data[$value] = ($post_data[$value]);
                }
            }
            $new_data['store_passwd'] = md5($store_passwd);

            ksort($new_data);

            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . '=' . ($value) . '&';
            }
            $hash_string = rtrim($hash_string, '&');

            if (md5($hash_string) == $post_data['verify_sign']) {

                return true;

            } else {
                $this->error = "Verification signature not matched";
                return false;
            }
        } else {
            $this->error = 'Required data mission. ex: verify_key, verify_sign';
            return false;
        }
    }

    /**
     * @param array $requestData
     * @param string $type
     * @param string $pattern
     * @return false|mixed|string
     */
    public function makePayment(array $requestData, $type = 'checkout', $pattern = 'json')
    {
        if (empty($requestData)) {
            return "Please provide a valid information list about transaction with transaction id, amount, success url, fail url, cancel url, store id and pass at least";
        }

        $header = [];

        $this->setApiUrl($this->config['apiDomain'] . $this->config['apiUrl']['make_payment']);

        $this->setParams($requestData);

        $this->setAuthenticationInfo();

        $response = $this->callToApi($this->data, $header, $this->config['connect_from_localhost']);

        $formattedResponse = $this->formatResponse($response, $type, $pattern);

        if ($type == 'hosted') {
            if (isset($formattedResponse['GatewayPageURL']) && $formattedResponse['GatewayPageURL'] != '') {
                $this->redirect($formattedResponse['GatewayPageURL']);
            } else {
                $errorMessage = "No redirect URL found!";
                return $errorMessage;
            }
        } else {
            return $formattedResponse;
        }
    }


    protected function setSuccessUrl()
    {
        $this->successUrl = url('/') . $this->config['success_url'];
    }

    protected function getSuccessUrl()
    {
        return $this->successUrl;
    }

    protected function setFailedUrl()
    {
        $this->failedUrl = url('/') . $this->config['failed_url'];
    }

    protected function getFailedUrl()
    {
        return $this->failedUrl;
    }

    protected function setCancelUrl()
    {
        $this->cancelUrl = url('/') . $this->config['cancel_url'];
    }

    protected function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    public function setParams($requestData)
    {
        $this->setRequiredInfo($requestData);

        $this->setCustomerInfo($requestData);

        $this->setShipmentInfo($requestData);

        $this->setProductInfo($requestData);

        $this->setAdditionalInfo($requestData);
    }

    public function setAuthenticationInfo()
    {
        $this->data['store_id'] = $this->getStoreId();
        $this->data['store_passwd'] = $this->getStorePassword();

        return $this->data;
    }

    public function setRequiredInfo(array $info)
    {
        $this->data['total_amount'] = $info['total_amount'];
        $this->data['currency'] = $info['currency'];
        $this->data['tran_id'] = $info['tran_id'];
        $this->data['product_category'] = $info['product_category'];

        $this->setSuccessUrl();
        $this->setFailedUrl();
        $this->setCancelUrl();

        $this->data['success_url'] = $this->getSuccessUrl();
        $this->data['fail_url'] = $this->getFailedUrl();
        $this->data['cancel_url'] = $this->getCancelUrl();

        /*
         * IPN is very important feature to integrate with your site(s).
         * Some transaction could be pending or customer lost his/her session, in such cases back-end IPN plays a very important role to update your backend office.
         *
         * Type: string (255)
         * Important! Not mandatory, however better to use to avoid missing any payment notification - It is the Instant Payment Notification (IPN) URL of your website where SSLCOMMERZ will send the transaction's status (Length: 255).
         * The data will be communicated as SSLCOMMERZ Server to your Server. So, customer session will not work.
         * */
        $this->data['ipn_url'] = (isset($info['ipn_url'])) ? $info['ipn_url'] : null;

        /*
         * Type: string (30)
         * Do not Use! If you do not customize the gateway list - You can control to display the gateway list at SSLCommerz gateway selection page by providing this parameters.
         * Multi Card:
            brac_visa = BRAC VISA
            dbbl_visa = Dutch Bangla VISA
            city_visa = City Bank Visa
            ebl_visa = EBL Visa
            sbl_visa = Southeast Bank Visa
            brac_master = BRAC MASTER
            dbbl_master = MASTER Dutch-Bangla
            city_master = City Master Card
            ebl_master = EBL Master Card
            sbl_master = Southeast Bank Master Card
            city_amex = City Bank AMEX
            qcash = QCash
            dbbl_nexus = DBBL Nexus
            bankasia = Bank Asia IB
            abbank = AB Bank IB
            ibbl = IBBL IB and Mobile Banking
            mtbl = Mutual Trust Bank IB
            bkash = Bkash Mobile Banking
            dbblmobilebanking = DBBL Mobile Banking
            city = City Touch IB
            upay = Upay
            tapnpay = Tap N Pay Gateway
         * GROUP GATEWAY
            internetbank = For all internet banking
            mobilebank = For all mobile banking
            othercard = For all cards except visa,master and amex
            visacard = For all visa
            mastercard = For All Master card
            amexcard = For Amex Card
         * */
        $this->data['multi_card_name'] = (isset($info['multi_card_name'])) ? $info['multi_card_name'] : null;

        /*
         * Type: string (255)
         * Do not Use! If you do not control on transaction - You can provide the BIN of card to allow the transaction must be completed by this BIN. You can declare by coma ',' separate of these BIN.
         * Example: 371598,371599,376947,376948,376949
         * */
        $this->data['allowed_bin'] = (isset($info['allowed_bin'])) ? $info['allowed_bin'] : null;

        $this->data['emi_option'] = (isset($info['emi_option'])) ? $info['emi_option'] : null;
        $this->data['emi_max_inst_option'] = (isset($info['emi_max_inst_option'])) ? $info['emi_max_inst_option'] : null;
        $this->data['emi_selected_inst'] = (isset($info['emi_selected_inst'])) ? $info['emi_selected_inst'] : null;

        return $this->data;
    }

    public function setCustomerInfo(array $info)
    {
        $this->data['cus_name'] = $info['cus_name'];
        $this->data['cus_email'] = $info['cus_email'];
        $this->data['cus_add1'] = $info['cus_add1'];
        $this->data['cus_add2'] = $info['cus_add2'];
        $this->data['cus_city'] = $info['cus_city'];
        $this->data['cus_state'] = (isset($info['cus_state'])) ? $info['cus_state'] : null;
        $this->data['cus_postcode'] = $info['cus_postcode'];
        $this->data['cus_country'] = $info['cus_country'];
        $this->data['cus_phone'] = $info['cus_phone'];
        $this->data['cus_fax'] = (isset($info['cus_fax'])) ? $info['cus_fax'] : null;

        return $this->data;
    }

    public function setShipmentInfo(array $info)
    {

        $this->data['shipping_method'] = $info['shipping_method'];
        $this->data['num_of_item'] = isset($info['num_of_item']) ? $info['num_of_item'] : 1;
        $this->data['ship_name'] = $info['ship_name'];
        $this->data['ship_add1'] = $info['ship_add1'];
        $this->data['ship_add2'] = (isset($info['ship_add2'])) ? $info['ship_add2'] : null;
        $this->data['ship_city'] = $info['ship_city'];
        $this->data['ship_state'] = (isset($info['ship_state'])) ? $info['ship_state'] : null;
        $this->data['ship_postcode'] = (isset($info['ship_postcode'])) ? $info['ship_postcode'] : null;
        $this->data['ship_country'] = (isset($info['ship_country'])) ? $info['ship_country'] : null;

        return $this->data;
    }

    public function setProductInfo(array $info)
    {

        $this->data['product_name'] = (isset($info['product_name'])) ? $info['product_name'] : '';
        $this->data['product_category'] = (isset($info['product_category'])) ? $info['product_category'] : '';

        /*
         * String (100)
         * Mandatory - Mention goods vertical. It is very much necessary for online transactions to avoid chargeback.
         * Please use the below keys :
            1) general
            2) physical-goods
            3) non-physical-goods
            4) airline-tickets
            5) travel-vertical
            6) telecom-vertical
        */
        $this->data['product_profile'] = (isset($info['product_profile'])) ? $info['product_profile'] : '';

        $this->data['hours_till_departure'] = (isset($info['hours_till_departure'])) ? $info['hours_till_departure'] : null;
        $this->data['flight_type'] = (isset($info['flight_type'])) ? $info['flight_type'] : null;
        $this->data['pnr'] = (isset($info['pnr'])) ? $info['pnr'] : null;
        $this->data['journey_from_to'] = (isset($info['journey_from_to'])) ? $info['journey_from_to'] : null;
        $this->data['third_party_booking'] = (isset($info['third_party_booking'])) ? $info['third_party_booking'] : null;
        $this->data['hotel_name'] = (isset($info['hotel_name'])) ? $info['hotel_name'] : null;
        $this->data['length_of_stay'] = (isset($info['length_of_stay'])) ? $info['length_of_stay'] : null;
        $this->data['check_in_time'] = (isset($info['check_in_time'])) ? $info['check_in_time'] : null;
        $this->data['hotel_city'] = (isset($info['hotel_city'])) ? $info['hotel_city'] : null;
        $this->data['product_type'] = (isset($info['product_type'])) ? $info['product_type'] : null;
        $this->data['topup_number'] = (isset($info['topup_number'])) ? $info['topup_number'] : null;
        $this->data['country_topup'] = (isset($info['country_topup'])) ? $info['country_topup'] : null;

        /*
         * Type: JSON
         * JSON data with two elements. product : Max 255 characters, quantity : Quantity in numeric value and amount : Decimal (12,2)
         * Example:
           [{"product":"DHK TO BRS AC A1","quantity":"1","amount":"200.00"},{"product":"DHK TO BRS AC A2","quantity":"1","amount":"200.00"},{"product":"DHK TO BRS AC A3","quantity":"1","amount":"200.00"},{"product":"DHK TO BRS AC A4","quantity":"2","amount":"200.00"}]
         * */
        $this->data['cart'] = (isset($info['cart'])) ? $info['cart'] : null;
        $this->data['product_amount'] = (isset($info['product_amount'])) ? $info['product_amount'] : null;
        $this->data['vat'] = (isset($info['vat'])) ? $info['vat'] : null;
        $this->data['discount_amount'] = (isset($info['discount_amount'])) ? $info['discount_amount'] : null;
        $this->data['convenience_fee'] = (isset($info['convenience_fee'])) ? $info['convenience_fee'] : null;

        return $this->data;
    }

    public function setAdditionalInfo(array $info)
    {
        $this->data['value_a'] = (isset($info['value_a'])) ? $info['value_a'] : null;
        $this->data['value_b'] = (isset($info['value_b'])) ? $info['value_b'] : null;
        $this->data['value_c'] = (isset($info['value_c'])) ? $info['value_c'] : null;
        $this->data['value_d'] = (isset($info['value_d'])) ? $info['value_d'] : null;

        return $this->data;
    }
}
