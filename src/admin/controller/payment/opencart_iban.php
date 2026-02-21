<?php
namespace Opencart\Admin\Controller\Extension\OpencartIban\Payment;

/**
 * Class OpencartIban
 *
 * @package Opencart\Admin\Controller\Extension\OpencartIban\Payment
 */
class OpencartIban extends \Opencart\System\Engine\Controller {
	private const EVENT_CODE = 'opencart_iban';
	private const EVENT_TRIGGER_FAILURE = 'catalog/view/checkout/failure/before';
	private const EVENT_ACTION_FAILURE = 'extension/opencart_iban/event/opencart_iban.failureMessage';

	/**
	 * Install
	 *
	 * Registers events used by the extension.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('setting/event');

		if (!method_exists($this->model_setting_event, 'addEvent')) {
			return;
		}

		if (method_exists($this->model_setting_event, 'deleteEventByCode')) {
			$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		}

		$event = [
			'code'       => self::EVENT_CODE,
			'description' => 'Opendatabot IBAN Invoice Payment',
			'trigger'    => self::EVENT_TRIGGER_FAILURE,
			'action'     => self::EVENT_ACTION_FAILURE,
			'status'     => true,
			'sort_order' => 0
		];

		// OpenCart 4.0.2.0+ uses addEvent(array $data). Older 4.x builds used addEvent($code, $trigger, $action).
		try {
			$method = new \ReflectionMethod($this->model_setting_event, 'addEvent');
			$count = $method->getNumberOfParameters();
		} catch (\ReflectionException $e) {
			return;
		}

		if ($count === 1) {
			$this->model_setting_event->addEvent($event);
		} elseif ($count === 3) {
			$this->model_setting_event->addEvent($event['code'], $event['trigger'], $event['action']);
		} elseif ($count === 4) {
			$this->model_setting_event->addEvent($event['code'], $event['description'], $event['trigger'], $event['action']);
		} else {
			$this->model_setting_event->addEvent(
				$event['code'],
				$event['description'],
				$event['trigger'],
				$event['action'],
				(int)$event['status'],
				(int)$event['sort_order']
			);
		}
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/event');

		if (method_exists($this->model_setting_event, 'deleteEventByCode')) {
			$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);
		}
	}

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

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

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/opencart_iban/payment/opencart_iban', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/opencart_iban/payment/opencart_iban.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['payment_opencart_iban_iban'] = (string)$this->config->get('payment_opencart_iban_iban');
		$data['payment_opencart_iban_code'] = (string)$this->config->get('payment_opencart_iban_code');

		// Purpose (multi-language)
		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();
		$data['languages'] = $languages;

		$data['payment_opencart_iban_purpose'] = [];

		foreach ($languages as $language) {
			$language_id = (int)$language['language_id'];
			$key = 'payment_opencart_iban_purpose_' . $language_id;

			$value = trim((string)$this->config->get($key));

			if ($value === '') {
				// Reasonable defaults. Stores can customize per language in admin.
				if (($language['code'] ?? '') === 'uk-ua') {
					$value = 'Оплата за замовлення №{order_id}';
				} else {
					$value = 'Payment for order #{order_id}';
				}
			}

			$data['payment_opencart_iban_purpose'][$language_id] = $value;
		}

		// Order Status
		$data['payment_opencart_iban_order_status_id'] = (int)($this->config->get('payment_opencart_iban_order_status_id') ?: $this->config->get('config_order_status_id'));

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['payment_opencart_iban_status'] = $this->config->get('payment_opencart_iban_status');
		$data['payment_opencart_iban_sort_order'] = $this->config->get('payment_opencart_iban_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/opencart_iban/payment/opencart_iban', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/opencart_iban/payment/opencart_iban');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/opencart_iban/payment/opencart_iban')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_opencart_iban_iban'])) {
			$json['error']['iban'] = $this->language->get('error_iban');
		}

		if (empty($this->request->post['payment_opencart_iban_code'])) {
			$json['error']['code'] = $this->language->get('error_code');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_opencart_iban', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
