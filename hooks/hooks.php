<?php

class Hooks {

	const STORAGE_OPTION_NAME = 'tb_hooks_config';

	function __construct() {
		add_action('profile_update', array($this, 'callProfileUpdateHooks'), 10, 2);
		add_action('user_register', array($this, 'callUserRegisterHooks'), 10, 1);
	}

	/**
	 * Retourne un tableau de config des hooks
	 *
	 * @return     array
	 */
	static function getConfig() {
		// chargement de la config depuis la BdD
		$hooks_config = json_decode(get_option(self::STORAGE_OPTION_NAME), true);
		if (! $hooks_config) {
			$hooks_config = array();
		}
		// chargement de la config par défaut
		$hooks_config_defaut = json_decode(file_get_contents(__DIR__ . '/hooks_config.json'), true);

		// si un champ est vide on utilise celui par défaut
		$hooks_config = array_merge($hooks_config_defaut, $hooks_config);

		return $hooks_config;
	}

	private function handleErrors($ch) {
		$message = 'Uhoh, we\'ve got a problemouz ! ' . "\r\n"
			. "\r\n"
			. 'URL du hook appelé : ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . "\r\n"
			. 'et son code d\'erreur : ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' (' . curl_error($ch) . ')'
		;

		$headers = 'Content-Type: text/plain; charset="utf-8"' . "\r\n"
			. 'Content-Transfer-Encoding: 8bit' . "\r\n"
			. 'From: wp-hooks@tela-botanica.org' . "\r\n"
			. 'Reply-To: no-reply@example.com' . "\r\n"
			. 'X-Mailer: PHP/' . phpversion()
		;

		foreach ($this->getConfig()['error-recipients-emails'] as $error_recipient) {
			if ($error_recipient && '#' !== substr($error_recipient, 0, 1)) {
				error_log($message, 1, $error_recipient, $headers);
				error_log($message);
			}
		}
	}

	private function callCaptainHooks($hooks_name, $user_id, $new_email, $old_email = '') {
		foreach ($this->getConfig()[$hooks_name] as $hook_service_pattern) {
			if ($hook_service_pattern && '#' !== substr($hook_service_pattern, 0, 1)) {
				$count = 0;

				$hook_service_url = preg_replace(
					array('/{old_email}/i', '/{new_email}/i', '/{user_id}/i'),
					array($old_email, $new_email, $user_id),
					$hook_service_pattern, -1, $count
				);

				if ($count > 0) {
					$ch = curl_init();
					curl_setopt_array($ch, array(
						CURLOPT_RETURNTRANSFER => 1,
						CURLOPT_FAILONERROR => 1,
						CURLOPT_URL => $hook_service_url
					));

					curl_exec($ch);

					$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					if ($http_code != 200) {
						$this->handleErrors($ch);
					}

					curl_close($ch);
				}
			}
		}
	}

	function callProfileUpdateHooks($user_id, $old_user_data) {
		$user = get_userdata( $user_id );

		if ($old_user_data->user_email != $user->user_email) {
			$this->callCaptainHooks('email-modification-urls', $user_id, $user->user_email, $old_user_data->user_email);
		}
	}

	function callUserRegisterHooks($user_id) {
		$user = get_userdata( $user_id );

		$this->callCaptainHooks('user-creation-urls', $user_id, $user->user_email);
	}
}
