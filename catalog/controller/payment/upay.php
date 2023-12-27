<?php
/**
 * Extension name: UPay
 * Descrption: Using this extension we will show payment methods on the checkout page.
 * Author: UPayments.
 *
 */
namespace Opencart\Catalog\Controller\Extension\Upay\Payment;

class Upay extends \Opencart\System\Engine\Controller
{
    /**
     * index
     *
     * @return mix
     */
    public function index(): string
    {
        // loading example payment language
        $this->load->language('extension/upay/payment/upay');

        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/upay/payment/upay', $data);
    }

    /**
     * confirm
     *
     * @return json|string
     */
    public function confirm(): void
    {
    	$json = [];
		$this->load->language('extension/upay/payment/upay');
		$this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$unique_order_id = md5($order_info['order_id'] * time());
        $src=str_replace('upay.', '', $this->session->data['payment_method']['code']);
		$customer_unq_token = $this->getCustomerUniqueToken($order_info['telephone']);
		$return_url=$this->url->link('extension/upay/payment/upay.callback','opencart_order_id=' . $order_info["order_id"]);
        $return_url=str_replace('&amp;', '&', $return_url);
        if($src == 'upay'){
        	$src = null;
        }
        $params = json_encode([
                "returnUrl" => $return_url,
                "cancelUrl" => $return_url,
                "notificationUrl" => $return_url,
                "order" =>[
                            "amount" => $order_info['total'],
                            "currency" => $order_info['currency_code'] ,
                            "id" => $unique_order_id,
                          ],
                "reference" => [
                            "id" => "".$order_info['order_id'],
                            ],
                "customer" => [
                            "uniqueId" => $customer_unq_token,
                            "name" => $order_info['firstname']." ".$order_info['lastname'],
                            "email" => $order_info['email'],
                            "mobile" => $order_info['telephone'],
                            ],
                "plugin" => [
                            "src" => "opencart",
                            ],
                "language" => "en",
                "paymentGateway" => ["src" => $src,],
                "tokens" => [
                            "creditCard" => '',
                            "customerUniqueToken" => $customer_unq_token,
                            ],
                "device" => [
                            "browser" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36 OPR/93.0.0.0",
                            "browserDetails" => [
                                            "screenWidth" => "1920",
                                            "screenHeight" => "1080",
                                            "colorDepth" => "24",
                                            "javaEnabled" => "false",
                                            "language" => "en",
                                            "timeZone" => "-180",
                                            "3DSecureChallengeWindowSize" => "500_X_600", ],
                            ],
                ]);
        $curl = curl_init();

		if($this->config->get('payment_upay_test_mode')) {
			$apiUrl = $this->language->get('sandbox_api_url');
		} else {
			$apiUrl = $this->language->get('api_url');
		}

		curl_setopt_array($curl, array(
		  CURLOPT_URL =>  $apiUrl.'charge',
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
		    'Content-Type: application/json',
		    'Authorization: Bearer '.$this->config->get('payment_upay_apikey')
		  ),
		));

		$response = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($response, true);
		if($result){
			if (isset($result["errors"])) {
			$json['error'] = "Error from UPayments: ".$result["message"];
			} elseif (isset($result["message"]) && (!isset($result["status"]) || $result["status"] == false)){
			$json['error'] = "Error from UPayments: ".$result["message"];
			}
			if (!$json){
			$json['redirect']=$result["data"]["link"];
			}
		} else {
			$json['error'] = "Error from UPayments: Your IP is not whiltelisted";
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));

    }

    public function callback(): void {
		$this->load->language('extension/upay/payment/upay');
		$order_id=0;
		$order_id = $this->request->get['opencart_order_id'];
        $payment_id = "";
        $pos = strpos($order_id, "?payment_id");
        if ($pos !== false)
        {
            $payment_id = substr($order_id, $pos + strlen("?payment_id") + 1);
            $order_id = (int)substr($order_id, 0, $pos);
        }
		$this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
		$transauthorised = false;
		if ($order_info) {
			$error = '';

			$refid = $this->request->get['ref'];
			$api_key = $this->config->get('payment_upay_apikey');
			$status = $this->request->get['result'];

			//means not valid record
			$order_done = true;
			if($status == "CAPTURED" || $status == "SUCCESS"){
				$transauthorised = true;
			}else if($status == "CANCELED" || $status == "CANCELLED"){
				$this->model_checkout_order->addHistory($order_id, 7, "Thank you for shopping with us. However, the transaction has been canceled.(Track ID = ".$this->request->get['track_id'].")", true);
				$error = $this->language->get('text_cancel_label');
			}
			else{
				$this->model_checkout_order->addHistory($order_id, 10, "Thank you for shopping with us. However, the transaction has been declined.(Track ID = ".$this->request->get['track_id'].")", true);
				$error = $this->language->get('text_declined');
			}


		} else {
			$error = $this->language->get('text_unable');
		}

		if ($error) {
			//$this->response->redirect($this->url->link('checkout/checkout'));
			$this->response->redirect($this->url->link('extension/upay/payment/upay.cancel','status=' . $status.'&oc_order_id='.$order_id));
		} else {
			$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_upay_order_status_id'),"Thank you for shopping with us. Your account has been charged and your transaction is successful.(Track ID = ".$this->request->get['track_id'].")", true);
			$this->response->redirect($this->url->link('checkout/success'));
		}
    }

    public function cancel():void {
		$this->load->language('extension/upay/payment/upay');

		$order_id=$this->request->get['oc_order_id'];
		$status=$this->request->get['status'];
		$this->load->model('checkout/order');
		if($status == "CANCELED" || $status == "CANCELLED"){
			$this->model_checkout_order->addHistory($order_id, 7, "Thank you for shopping with us. However, the transaction has been canceled.", true);
			$error = $this->language->get('text_cancel_label');
		}
		else{
			$this->model_checkout_order->addHistory($order_id, 10, "Thank you for shopping with us. However, the transaction has been declined.", true);
			$error = $this->language->get('text_declined');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', 'SSL')
		);

		if($error == 'Your transaction is cancelled'){
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_cancel_header'),

			);
			$data['heading_title'] = $this->language->get('text_cancel_header');
		}else{
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_failed'),

			);
			$data['heading_title'] = $this->language->get('text_failed');
		}


		if($error == "text_cancel")
			$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));
		else
			$data['text_message'] = sprintf($this->language->get('text_failed_message'), $error, $this->url->link('information/contact'));

		$data['button_continue'] = $this->language->get('button_continue');

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/common/success')) {
			$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/common/success', $data));
		} else {
			$this->response->setOutput($this->load->view('common/success', $data));
		}
    }

	public function getCustomerUniqueToken($phone): string {
		$api_key =  $this->config->get('payment_upay_apikey');
		$token = '';
		if (!empty($phone))
		{
			$token = $phone;
			$params = json_encode(["customerUniqueToken" => $token, ]);

			$curl = curl_init();

			if($this->config->get('payment_upay_test_mode')) {
				$apiUrl = $this->language->get('sandbox_api_url');
			} else {
				$apiUrl = $this->language->get('api_url');
			}

			curl_setopt_array($curl, [CURLOPT_URL => $apiUrl.'create-customer-unique-token' , CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $params, CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json", "Authorization: Bearer " . $api_key, ], ]);

			$response = curl_exec($curl);
			if ($response)
			{
				$result = json_decode($response, true);
				if (isset($result["status"]) && $result["status"] == true)
				{
					$token = $token;

				}
			}
		}
		return $token;
	}


}
