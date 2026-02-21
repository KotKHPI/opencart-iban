<?php
namespace Opencart\Catalog\Controller\Extension\OpencartIban\Event;

/**
 * Class OpencartIban
 *
 * @package Opencart\Catalog\Controller\Extension\OpencartIban\Event
 */
class OpencartIban extends \Opencart\System\Engine\Controller {
	/**
	 * Adds extension error message to the standard checkout/failure page.
	 *
	 * Trigger: catalog/view/checkout/failure/before
	 *
	 * @param string              $route route
	 * @param array<string, mixed> $data  view data (by reference)
	 *
	 * @return void
	 */
	public function failureMessage(string &$route, array &$data): void {
		if (empty($this->session->data['opencart_iban_error'])) {
			return;
		}

		$message = trim((string)$this->session->data['opencart_iban_error']);

		unset($this->session->data['opencart_iban_error']);

		if ($message === '') {
			return;
		}

		$safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
		$html = '<div class="alert alert-danger">' . $safe . '</div>';

		if (isset($data['text_message']) && is_string($data['text_message'])) {
			$data['text_message'] .= $html;
		} else {
			$data['text_message'] = $html;
		}
	}
}

