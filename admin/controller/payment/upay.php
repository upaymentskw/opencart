<?php
 /**
 * Extension name: UPay
 * Descrption: Using this extension we will show payment methods on the checkout page.
 * Author: UPayments
 *
 */
namespace Opencart\Admin\Controller\Extension\Upay\Payment;

class Upay extends \Opencart\System\Engine\Controller {

    /**
     * index
     *
     * @return void
     */
    public function index(): void {

        $this->load->language('extension/upay/payment/upay');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        if (!isset($this->request->get['module_id'])) {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/upay/payment/upay', 'user_token=' . $this->session->data['user_token'])
            ];
        } else {
            $data['breadcrumbs'][] = [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/upay/payment/upay', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'])
            ];
        }

        $data['save'] = $this->url->link('extension/upay/payment/upay.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        // getting payment extension config
        $data['payment_upay_order_status_id'] = $this->config->get('payment_upay_order_status_id');

        // loading order status model
        $this->load->model('localisation/order_status');

        // getting order status as array
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // zeo zone id
        $data['payment_upay_geo_zone_id'] = $this->config->get('payment_upay_geo_zone_id');

        // loading geo_zone model
        $this->load->model('localisation/geo_zone');

        // getting all zeo zones
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_upay_apikey'] = $this->config->get('payment_upay_apikey');
        $data['payment_upay_status'] = $this->config->get('payment_upay_status');
        $data['payment_upay_sort_order'] = $this->config->get('payment_upay_sort_order');

		$data['payment_upay_test_mode'] = $this->config->get('payment_upay_test_mode');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/upay/payment/upay', $data));
    }

    /**
     * save method
     *
     * @return void
     */
    public function save(): void {
        // loading example payment language
        $this->load->language('extension/upay/payment/upay');

        $json = [];

        // checking file modification permission
        if (!$this->user->hasPermission('modify', 'extension/upay/payment/upay')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_upay', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

}
