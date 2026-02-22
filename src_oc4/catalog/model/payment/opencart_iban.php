<?php
namespace Opencart\Catalog\Model\Extension\OpencartIban\Payment;

/**
 * Class OpencartIban
 *
 * Can be called from $this->load->model('extension/opencart_iban/payment/opencart_iban');
 *
 * @package Opencart\Catalog\Model\Extension\OpencartIban\Payment
 */
class OpencartIban extends \Opencart\System\Engine\Model {
	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

		$iban = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_iban'));
		$code = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_code'));

		$currency = $this->session->data['currency'] ?? $this->config->get('config_currency');

		$status = (bool)$this->config->get('payment_opencart_iban_status');

		if ($this->cart->hasSubscription()) {
			$status = false;
		}

		if ($iban === '' || $code === '') {
			$status = false;
		}

		if (strtoupper((string)$currency) !== 'UAH') {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$option_data['opencart_iban'] = [
				'code' => 'opencart_iban.opencart_iban',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'opencart_iban',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_opencart_iban_sort_order')
			];
		}

		return $method_data;
	}
}

