<?php
/**
*
* @package - Activation Justification
* @copyright (c) 2015 RMcGirr83
* @author RMcGirr83 (Rich McGirr)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace rmcgirr83\activationjustification\migrations;

class m2_update_schema extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\rmcgirr83\activationjustification\migrations\m1_initial_schema');
	}

	public function update_schema()
	{
		return array(
			'change_columns'	=> array(
				$this->table_prefix . 'users'	=> array(
					'user_justification'	=> array('VCHAR', '', 'true_sort'),
				),
			),
		);
	}
}
