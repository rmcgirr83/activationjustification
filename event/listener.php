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
	/** @var \phpbb\config\config */
	protected $config;

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

	/**
	* the path to the images directory
	*
	*@var string
	*/
	protected $genders_path;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		$phpbb_root_path,
		$php_ext)
	{
		$this->config = $config;
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
			'core.ucp_register_data_before'				=> 'user_justification_registration',
			'core.ucp_register_data_after'				=> 'user_justification_registration_validate',
			'core.ucp_register_user_row_after'			=> 'user_justification_registration_sql',
		);
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
			if (!$this->config['require_activation'] == USER_ACTIVATION_ADMIN)
			{
				return;
			}
			$error_array = $event['error'];
			//ensure justification reason isn't blank
			if (!function_exists('validate_data'))
			{
				include($this->root_path . 'includes/functions_user.' . $this->php_ext);
			}
			$validate_array = array(
				'user_justification'	=> array('string', false, 1),
			);
			$error = validate_data($event['data'], $validate_array);
			$event['error'] = array_merge($array, $error);
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
				'user_justification' => $this->request->variable('user_justification', '', true),
		));
	}
}
