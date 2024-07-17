<?php
/**
*
* @package - Activation Justification
* @copyright (c) 2015 RMcGirr83
* @author RMcGirr83 (Rich McGirr)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace rmcgirr83\activationjustification\event;

/**
* @ignore
*/
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface as db;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\notification\manager as notification_manager;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/**
	 * Target user data
	 */
	private $data = array();

	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var db */
	protected $db;

	/** @var language */
	protected $language;

	/** @var log */
	protected $log;

	/** @var notification_manager */
	protected $notification_manager;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var string root_path */
	protected $root_path;

	/** @var string php_ext */
	protected $php_ext;

	public function __construct(
		auth $auth,
		config $config,
		db $db,
		language $language,
		log $log,
		notification_manager $notification_manager,
		request $request,
		template $template,
		user $user,
		string $root_path,
		string $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.ucp_register_data_before'		=> 'user_justification_registration',
			'core.ucp_register_data_after'		=> 'user_justification_registration_validate',
			'core.ucp_register_user_row_after'	=> 'user_justification_registration_sql',
			'core.memberlist_view_profile'		=> 'user_justification_display',
			'core.acp_users_display_overview'	=> 'acp_user_justification_display',
		);
	}

	/**
	* Allow justification to display
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function user_justification_display($event)
	{
		if (!$this->auth->acl_get('a_user'))
		{
			return;
		}
		$this->data		= $event['member'];
		$this->user_id	= (int) $this->data['user_id'];

		$this->language->add_lang('common', 'rmcgirr83/activationjustification');

		if ($this->data['user_type'] != USER_INACTIVE && $this->data['user_inactive_reason'] != INACTIVE_REGISTER)
		{
			$aj_result = $this->request->variable('aj_res', '');
			if (!empty($aj_result))
			{
				if ($aj_result == 'success')
				{
					$aj_message = $this->language->lang('ACTIVATED_SUCCESS');

					$this->template->assign_vars(array(
						'AJ_MESSAGE'		=> $aj_message,
					));
				}
			}
			return;
		}

		if (!$this->request->is_set('aj') || ($this->request->is_set('aj') && $this->request->is_set('confirm_key') && !confirm_box(true)))
		{
			$params = array(
				'mode'	=> 'viewprofile',
				'u'		=> $this->user_id,
				'aj'	=> 1,
			);

			$this->template->assign_vars(array(
				'JUSTIFICATION'		=> empty($this->data['user_justification']) ? $this->language->lang('NO_JUSTIFICATION') : $this->data['user_justification'],
				'U_ACTIVATE'		=> append_sid($this->root_path . 'memberlist.' . $this->php_ext, $params),
				'S_JUSTIFY'			=> true,
			));
			return;
		}

		// Time to activate a user. But are you sure?
		if (!confirm_box(true))
		{
			$hidden_fields = array(
				'mode'				=> 'viewprofile',
			);
			$message = $this->language->lang('SURE_ACTIVATE', $this->data['username']);
			confirm_box(false, $message, build_hidden_fields($hidden_fields));
		}
		$this->user_justification_activate();

		// The page needs to be reloaded to show the new status.
		$args = array(
			'mode'		=> 'viewprofile',
			'u'			=> $this->user_id,
			'aj_res'	=> 'success',
		);

		$url	= generate_board_url();
		$url	.= ((substr($url, -1) == '/') ? '' : '/') . 'memberlist.' . $this->php_ext;
		$url	= append_sid($url, $args);

		redirect($url);
	}

	/**
	* Allow users to enter a justification
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function user_justification_registration($event)
	{
		// Request the user option vars and add them to the data array
		$event['data'] = array_merge($event['data'], array(
			'user_justification'	=> $this->request->variable('justify', '', true),
		));

		$this->language->add_lang('common', 'rmcgirr83/activationjustification');

		$this->template->assign_vars(array(
			'JUSTIFICATION'		=> $event['data']['user_justification'],
			'S_JUSTIFY'			=> ($this->config['require_activation'] == USER_ACTIVATION_ADMIN) ? true : false,
		));
	}

	/**
	* Validate users changes to their justification
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function user_justification_registration_validate($event)
	{
		if ($event['submit'] && empty($event['data']['user_justification']) && $this->config['require_activation'] == USER_ACTIVATION_ADMIN)
		{
			$error_array = $event['error'];
			$error_array[] = $this->language->lang('TOO_SHORT_JUSTIFICATION');
			$event['error'] = $error_array;
		}
	}

	/**
	* Update registration data
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function user_justification_registration_sql($event)
	{
		$event['user_row'] = array_merge($event['user_row'], array(
				'user_justification' => $this->request->variable('justify', '', true),
		));
	}

	/**
	* Display Justification in ACP
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function acp_user_justification_display($event)
	{
		$this->language->add_lang('common', 'rmcgirr83/activationjustification');

		$this->template->assign_vars(array(
			'USER_JUSTIFICATION'		=> empty($event['user_row']['user_justification']) ? $this->language->lang('NO_JUSTIFICATION') : $event['user_row']['user_justification'],
		));
	}

	/**
	* Activate user
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	private function user_justification_activate()
	{
		$user = $this->data;

		if (!function_exists('user_active_flip'))
		{
			include($this->root_path . 'includes/functions_user.' . $this->php_ext);
		}
		if (!class_exists('messenger'))
		{
			include($this->root_path . 'includes/functions_messenger.' . $this->php_ext);
		}
		user_active_flip('activate', $user['user_id']);
		$messenger = new \messenger(false);

		$messenger->template('admin_welcome_activated', $user['user_lang']);

		$messenger->to($user['user_email'], $user['username']);

		$messenger->anti_abuse_headers($this->config, $this->user);

		$messenger->assign_vars(array(
			'USERNAME'	=> htmlspecialchars_decode($user['username']))
		);

		$messenger->send(NOTIFY_EMAIL);

		$messenger->save_queue();
		// Remove the notification
		$this->notification_manager->delete_notifications('notification.type.admin_activate_user', $user['user_id']);

		$sql = 'UPDATE ' . USERS_TABLE . "
			SET user_actkey = ''
			WHERE user_id = " . (int) $user['user_id'];
		$this->db->sql_query($sql);

		// Create the correct logs
		$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_ACTIVE_USER', false, array('reportee_id' => $user['user_id']));
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_ACTIVE', false, array($user['username']));
	}
}
