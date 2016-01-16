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

	/**
	 * Target user id
	 */
	private $user_id = 0;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\log\log $log,
		\phpbb\notification\manager $notification_manager,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		$phpbb_root_path,
		$php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->root_path = $phpbb_root_path;
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

		$this->user->add_lang_ext('rmcgirr83/activationjustification', 'common');

		if ($this->data['user_type'] != USER_INACTIVE && $this->data['user_inactive_reason'] != INACTIVE_REGISTER)
		{
			$aj_result = $this->request->variable('aj_res', '');
			if (!empty($aj_result))
			{
				if ($aj_result == 'success')
				{
					$aj_message = $this->user->lang('ACTIVATED_SUCCESS');

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
				'JUSTIFICATION'		=> empty($this->data['user_justification']) ? $this->user->lang('NO_JUSTIFICATION') : $this->data['user_justification'],
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
			$message = $this->user->lang('SURE_ACTIVATE', $this->data['username']);
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

		$this->user->add_lang_ext('rmcgirr83/activationjustification', 'common');

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
			$error_array[] = $this->user->lang('TOO_SHORT_JUSTIFICATION');
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
		$this->user->add_lang_ext('rmcgirr83/activationjustification', 'common');

		$this->template->assign_vars(array(
			'USER_JUSTIFICATION'		=> empty($event['user_row']['user_justification']) ? $this->user->lang('NO_JUSTIFICATION') : $event['user_row']['user_justification'],
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

		$this->log->add('user', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_ACTIVE', time(), array($user['username']));
	}
}
