<?php
namespace Opencart\Catalog\Controller\Extension\OpencartIban\Payment;

/**
 * Class OpencartIban
 *
 * @package Opencart\Catalog\Controller\Extension\OpencartIban\Payment
 */
class OpencartIban extends \Opencart\System\Engine\Controller {
	private const OPENDATABOT_ENDPOINT = 'https://iban.opendatabot.ua/api/invoice';
	private const OPENDATABOT_INVOICE_URL_PREFIX = 'https://iban.opendatabot.ua/invoice/';
	private const OPENDATABOT_CLIENT_KEY = 'KUI8gwVJb3OQN1LuTKEsBx8feSYOJK2m';
	private const OPENDATABOT_CLIENT_NAME = 'public';

	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/opencart_iban/payment/opencart_iban', $data);
	}

	/**
	 * Confirm
	 *
	 * @return void
	 */
	public function confirm(): void {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

		$json = [];

		// Order
		if (isset($this->session->data['order_id'])) {
			$this->load->model('checkout/order');

			$order_id = (int)$this->session->data['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);

			if (!$order_info) {
				$json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

				unset($this->session->data['order_id']);
			}
		} else {
			$json['error'] = $this->language->get('error_order');
		}

		// If the order is missing, do not continue with other checks.
		if (isset($json['redirect'])) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'opencart_iban.opencart_iban') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		$iban = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_iban'));
		$code = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_code'));

		if ($iban === '' || $code === '') {
			$json['error'] = $this->language->get('error_config');
		}

		if (!isset($json['redirect']) && isset($order_info) && strtoupper((string)$order_info['currency_code']) !== 'UAH') {
			$json['error'] = $this->language->get('error_currency');
		}

		if (!$json) {
			$this->load->model('checkout/order');

			$this->model_checkout_order->addHistory(
				$order_id,
				(int)($this->config->get('payment_opencart_iban_order_status_id') ?: $this->config->get('config_order_status_id')),
				$this->language->get('text_payment_comment'),
				false
			);

			$amount = number_format((float)$order_info['total'], 2, '.', '');

			$language_id = (int)$this->config->get('config_language_id');
			$purpose_template = trim((string)$this->config->get('payment_opencart_iban_purpose_' . $language_id));

			if ($purpose_template === '') {
				$purpose = sprintf($this->language->get('text_purpose'), $order_id);
			} elseif (strpos($purpose_template, '{order_id}') !== false) {
				$purpose = str_replace('{order_id}', (string)$order_id, $purpose_template);
			} else {
				$prefix = rtrim($purpose_template);
				$separator = preg_match('/[\\pL\\pN]$/u', $prefix) ? ' ' : '';

				$purpose = $prefix . $separator . $order_id;
			}

			$payload = [
				'code'          => $code,
				'iban'          => $iban,
				'amount'        => $amount,
				'purpose'       => $purpose,
				'x-client-key'  => self::OPENDATABOT_CLIENT_KEY,
				'x-client-name' => self::OPENDATABOT_CLIENT_NAME
			];

			$invoice_id = $this->createInvoice($payload);

			if ($invoice_id) {
				$json['redirect'] = self::OPENDATABOT_INVOICE_URL_PREFIX . $invoice_id;
			} else {
				$payload['redirect'] = 'true';

				$json['form'] = [
					'action' => self::OPENDATABOT_ENDPOINT,
					'fields' => $payload
				];
			}

			// Clear cart and checkout session (similar to checkout/success)
			$this->cart->clear();

			unset($this->session->data['order_id']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['agree']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Create invoice via Opendatabot API.
	 *
	 * Returns invoice ID on success, null otherwise.
	 *
	 * @param array<string, string> $payload
	 *
	 * @return string|null
	 */
	private function createInvoice(array $payload): ?string {
		if (!function_exists('curl_init')) {
			return null;
		}

		$ch = curl_init(self::OPENDATABOT_ENDPOINT);

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

		$response = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($response === false || $http_code < 200 || $http_code >= 300) {
			return null;
		}

		$decoded = json_decode($response, true);

		if (!is_array($decoded) || empty($decoded['id'])) {
			return null;
		}

		return (string)$decoded['id'];
	}
}
