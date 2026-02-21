<?php
namespace Opencart\Catalog\Controller\Extension\OpencartIban\Payment;

/**
 * Class OpencartIban
 *
 * @package Opencart\Catalog\Controller\Extension\OpencartIban\Payment
 */
class OpencartIban extends \Opencart\System\Engine\Controller {
	private const OPENDATABOT_ENDPOINT = 'https://iban.opendatabot.ua/api/invoice';
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

			$order_info = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);

			if (!$order_info) {
				$json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

				unset($this->session->data['order_id']);
			}
		} else {
			$json['error'] = $this->language->get('error_order');
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
			$json['redirect'] = $this->url->link('extension/opencart_iban/payment/opencart_iban.redirect', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Redirect
	 *
	 * Renders an auto-submitting POST form to Opendatabot.
	 *
	 * @return void
	 */
	public function redirect(): void {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

		$fail = function(string $message): void {
			// Use a standard route so stores with customized "failure" page keep their UX.
			$this->session->data['error'] = $message;
			$this->session->data['opencart_iban_error'] = $message;

			$this->response->redirect($this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true));
		};

		if (isset($this->session->data['payment_method']['code']) && strpos((string)$this->session->data['payment_method']['code'], 'opencart_iban.') !== 0) {
			$fail($this->language->get('error_payment_method'));

			return;
		}

		if (isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		} elseif (isset($this->request->post['order_id'])) {
			$order_id = (int)$this->request->post['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$order_id) {
			$fail($this->language->get('error_order'));

			return;
		}

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

			if (!$order_info) {
				unset($this->session->data['order_id']);

				$fail($this->language->get('error_order'));

				return;
			}

			if (strtoupper((string)$order_info['currency_code']) !== 'UAH') {
				$fail($this->language->get('error_currency'));

			return;
		}

		$code = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_code'));
		$iban = preg_replace('/\s+/', '', (string)$this->config->get('payment_opencart_iban_iban'));

		if ($iban === '' || $code === '') {
			$fail($this->language->get('error_config'));

			return;
		}

		$this->load->model('checkout/order');

		$this->model_checkout_order->addHistory(
			$order_id,
			(int)($this->config->get('payment_opencart_iban_order_status_id') ?: $this->config->get('config_order_status_id')),
			$this->language->get('text_payment_comment'),
			false
		);

			$data['action'] = self::OPENDATABOT_ENDPOINT;
			$data['code'] = $code;
			$data['iban'] = $iban;
			$data['amount'] = number_format((float)$order_info['total'], 2, '.', '');

			$language_id = (int)$this->config->get('config_language_id');
			$purpose_template = trim((string)$this->config->get('payment_opencart_iban_purpose_' . $language_id));

			if ($purpose_template === '') {
				$data['purpose'] = sprintf($this->language->get('text_purpose'), $order_id);
			} elseif (strpos($purpose_template, '{order_id}') !== false) {
				$data['purpose'] = str_replace('{order_id}', (string)$order_id, $purpose_template);
			} else {
				$prefix = rtrim($purpose_template);
				$separator = preg_match('/[\\pL\\pN]$/u', $prefix) ? ' ' : '';

				$data['purpose'] = $prefix . $separator . $order_id;
			}

			$data['x_client_key'] = self::OPENDATABOT_CLIENT_KEY;
			$data['x_client_name'] = self::OPENDATABOT_CLIENT_NAME;
			$data['redirect'] = 'true';

		$data['text_redirecting'] = $this->language->get('text_redirecting');
		$data['text_redirect_notice'] = $this->language->get('text_redirect_notice');
		$data['button_pay'] = $this->language->get('button_pay');

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

		$this->response->setOutput($this->load->view('extension/opencart_iban/payment/opencart_iban_redirect', $data));
	}
}
