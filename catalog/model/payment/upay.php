<?php
/**
 * Extension name: UPay
 * Descrption: Using this extension we will show payment methods on the checkout page.
 * Author: UPayments
 *
 */
namespace Opencart\Catalog\Model\Extension\Upay\Payment;

class Upay extends \Opencart\System\Engine\Model {

    /**
     * getMethods
     *
     * @param  mixed $address
     * @return array
     */
    public function getMethods(array $address = []): array {

        // loading example payment language
        $this->load->language('extension/upay/payment/upay');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->cart->hasShipping()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_upay_geo_zone_id')) {
            $status = true;
        } else {
            // getting payment data using zeo zone
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_upay_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

            // if the rows found the status set to True
            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];
        $apple_pay_available = true;
        $whitelabeled = false;
        $whitelabeled = $this->check_user_is_whitelabeled();
        if ($status) {

            if ($whitelabeled == true){
            $payment_methods_result = $this->get_upay_payment_methods();
            $payment_buttons=[];
            if ($payment_methods_result["status"] == true)
            {
                $payment_buttons = $payment_methods_result['data']['payButtons'];
            }
            if(!empty($payment_buttons)){
            if($payment_buttons['knet'] == 1){
                $option_data['knet'] = [
                'code' => 'upay.knet',
                'name' => 'Knet'
                ];
            }
            if($payment_buttons['credit_card'] == 1){
                $option_data['cc'] = [
                    'code' => 'upay.cc',
                    'name' => 'Card'
                ];
            }
            if($payment_buttons['samsung_pay'] == 1){
                $option_data['samsung-pay'] = [
                    'code' => 'upay.samsung-pay',
                    'name' => 'Samsung Pay'
                ];
            }
            if($payment_buttons['google_pay'] == 1){
                $option_data['google-pay'] = [
                    'code' => 'upay.google-pay',
                    'name' => 'Google Pay'
                ];
            }
            if($payment_buttons['apple_pay'] == 1 && $apple_pay_available == true){
                $option_data['apple-pay'] = [
                    'code' => 'upay.apple-pay',
                    'name' => 'Apple Pay'
                ];
            }
            if($payment_buttons['amex'] == 1){
                $option_data['amex'] = [
                    'code' => 'upay.amex',
                    'name' => 'Amex'
                ];
            }

           $method_data = [
                'code'       => 'upay',
                'name'       => $this->language->get('heading_title').'<img src="https://upay.upayments.com/assets/global/img/opencart_img.png" style="height:30px;" />',
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_upay_sort_order')
            ];
            }


            } else {
                $option_data['upay'] = [
                'code' => 'upay.upay',
                'name' => 'Upay'
            ];
            $method_data = [
                'code'       => 'upay',
                'name'       => $this->language->get('heading_title').'<img src="https://upay.upayments.com/assets/global/img/opencart_img.png" style="height:30px;" />',
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_upay_sort_order')
            ];
            }



        }

        return $method_data;
    }

    public function check_user_is_whitelabeled(): string {
		$api_key =  $this->config->get('payment_upay_apikey');
        $result = [];
        $whitelabeled = false;
		if (!empty($api_key))
		{
			$params = json_encode(["apiKey" => $api_key, ]);

			$curl = curl_init();

			if($this->config->get('payment_upay_test_mode')) {
				$apiUrl = $this->language->get('sandbox_api_url');
			} else {
				$apiUrl = $this->language->get('api_url');
			}

			curl_setopt_array($curl, array(
			CURLOPT_URL =>$apiUrl.'check-merchant-api-key',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>$params,
			CURLOPT_HTTPHEADER => array(
				'Accept: application/json',
				'Content-Type: application/json'
			),
			));

			$response = curl_exec($curl);
            if ($response)
			{
				$result = json_decode($response, true);
                if($result){
                    if($result['data']){
                    $whitelabeled = $result['data']['isWhiteLabel'];
                    } else{
                    $whitelabeled= false;
                    }
                }

			}
        }
		return $whitelabeled;
	}

    public function get_upay_payment_methods(): array {
        $api_key =  $this->config->get('payment_upay_apikey');
        $payment_methods=[];
        if (!empty($api_key))
        {
            $curl = curl_init();

			if($this->config->get('payment_upay_test_mode')) {
				$apiUrl = $this->language->get('sandbox_api_url');
			} else {
				$apiUrl = $this->language->get('api_url');
			}

            curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl.'check-payment-button-status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ),
            ));

            $response = curl_exec($curl);
            if ($response)
            {
                $payment_methods = json_decode($response, true);

            }
        }
        return $payment_methods;
    }

}
