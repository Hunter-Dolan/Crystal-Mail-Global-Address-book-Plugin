<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @version 1.4
 * @author Philip Weir
 */
class globaladdressbook extends crystal_plugin
{
	public $task = 'mail|addressbook';
	private $abook_id = 'global';
	private $readonly;
	private $groups;
	private $user_id;
	private $user_name;
	private $host = 'localhost';

	public function init()
	{
		$cmail = cmail::get_instance();
		$this->load_config();
		$this->user_name = $cmail->config->get('globaladdressbook_user');
		$this->user_name = str_replace('%d', $cmail->user->get_username('domain'), $this->user_name);
		$this->user_name = str_replace('%h', $_SESSION['imap_host'], $this->user_name);
		$this->readonly = $this->_is_readonly();
		$this->groups = $cmail->config->get('globaladdressbook_groups', false);

		// check if the global address book user exists
		if (!($user = crystal_user::query($this->user_name, $this->host))) {
			// this action overrides the current user information so make a copy and then restore it
			$cur_user = $cmail->user;
			$user = crystal_user::create($this->user_name, $this->host);
			$cmail->user = $cur_user;

			// prevent new_user_dialog plugin from triggering
			$_SESSION['plugin.newuserdialog'] = false;
		}

		$this->user_id = $user->ID;

		// use this address book for autocompletion queries
		if ($cmail->config->get('globaladdressbook_autocomplete')) {
			$sources = $cmail->config->get('autocomplete_addressbooks', array('sql'));
			if (!in_array($this->abook_id, $sources)) {
				$sources[] = $this->abook_id;
				$cmail->config->set('autocomplete_addressbooks', $sources);
			}
		}

		$this->add_hook('addressbooks_list', array($this, 'address_sources'));
		$this->add_hook('addressbook_get', array($this, 'get_address_book'));
	}

	public function address_sources($args)
	{
		$this->add_texts('localization/');
		$args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->gettext('globaladdressbook'), 'readonly' => $this->readonly, 'groups' => $this->groups);
		return $args;
	}

	public function get_address_book($args)
	{
		if ($args['id'] === $this->abook_id) {
			$args['instance'] = new crystal_contacts(cmail::get_instance()->db, $this->user_id);
			$args['instance']->readonly = $this->readonly;
			$args['instance']->groups = $this->groups;
		}

		return $args;
	}

	private function _is_readonly()
	{
		$cmail = cmail::get_instance();

		if (!$cmail->config->get('globaladdressbook_readonly'))
			return false;

		if ($admin = $cmail->config->get('globaladdressbook_admin')) {
			if (!is_array($admin)) $admin = array($admin);

			foreach ($admin as $user) {
				if (strpos($user, '/') == 0 && substr($user, -1) == '/') {
					if (preg_match($user, $_SESSION['username']))
						return false;
				}
				elseif ($user == $_SESSION['username'])
					return false;
			}
		}

		return true;
	}
}

?>