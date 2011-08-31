<?php
// wcf imports
require_once(WCF_DIR.'lib/acp/admintools/function/AbstractAdminToolsFunction.class.php');
require_once(WCF_DIR.'lib/system/language/LanguageEditor.class.php');

/**
 * Copies user group rights and users from one group to another
 *
 * This file is part of Admin Tools 2.
 *
 * Admin Tools 2 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Admin Tools 2 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Admin Tools 2.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author	Oliver Kliebisch
 * @copyright	2009 Oliver Kliebisch
 * @license	GNU General Public License <http://www.gnu.org/licenses/>
 * @package	net.hawkes.admintools
 * @subpackage acp.admintools.function
 * @category WCF
 */
class CopyUserGroupsAdminToolsFunction extends AbstractAdminToolsFunction {

	/**
	 * @see AdminToolsFunction::execute($data)
	 */
	public function execute($data) {
		parent::execute($data);

		$parameters = $data['parameters']['user.usergroupcopy'];
		if ($parameters['sourceGroup'] == $parameters['targetGroup']) {
			$this->setReturnMessage('error', WCF::getLanguage()->get('wcf.acp.admintools.function.'.$data['functionName'].'.sourceEqualsTarget'));
			return;
		}

		$sql = "DELETE FROM 	wcf".WCF_N."_group_option_value
			WHERE 		groupID = ".$parameters['targetGroup'];
		WCF::getDB()->sendQuery($sql);

		$sql = "INSERT INTO 	wcf".WCF_N."_group_option_value
                      			(groupID, optionID, optionValue)
               		SELECT 		".$parameters['targetGroup'].", optionID, optionValue
               		FROM 		wcf".WCF_N."_group_option_value
               		WHERE 		groupID = ".$parameters['sourceGroup'];
		WCF::getDB()->sendQuery($sql);

		if ($parameters['copyUsers']) {
			$sql = "DELETE FROM 	wcf".WCF_N."_user_to_groups
                    		WHERE 		groupID = ".$parameters['targetGroup'];
			WCF::getDB()->sendQuery($sql);
			$sql = "INSERT INTO 	wcf".WCF_N."_user_to_groups
                        			(userID, groupID)
                    		SELECT 		userID, ".$parameters['targetGroup']."
                      		FROM 		wcf".WCF_N."_user_to_groups
                     		WHERE 		groupID = ".$parameters['sourceGroup'];
			WCF::getDB()->sendQuery($sql);
		}

		WCF::getCache()->clear(WCF_DIR.'cache', 'cache.groups*.php', true);

		// reset all sessions
		Session::resetSessions();

		$this->executed();
	}
}
?>