<?php
// wcf imports
require_once(WCF_DIR.'lib/acp/admintools/function/AbstractAdminToolsFunction.class.php');
require_once(WCF_DIR.'lib/system/database/ConditionBuilder.class.php');
require_once(WCF_DIR.'lib/data/mail/Mail.class.php');
require_once(WCF_DIR.'lib/data/user/UserEditor.class.php');

/**
 * Warns oder deletes inactive users
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
 * @package	net.hawkes.admintools.wcf.functions
 * @subpackage 	acp.admintools.function
 * @category 	WCF
 */
class InactiveUsersAdminToolsFunction extends AbstractAdminToolsFunction {
	public $ignoreCondition;
	public $warnedInactiveUsers = array();
	public $deletedInactiveUsers = array();
	public $message = '';
	public $messageTableHeader = '';

	/**
	 * @see AdminToolsFunction::execute($data)
	 */
	public function execute($data) {
		parent::execute($data);

		$generalOptions = $data['parameters']['user.inactiveUsers.general'];

		// initalize the default condition to filter certain users
		$this->ignoreCondition = new ConditionBuilder(false);
		if (!empty($generalOptions['ignoredUserIDs'])) $this->ignoreCondition->add('user.userID NOT IN ('.$generalOptions['ignoredUserIDs'].')');
		if (!empty($generalOptions['ignoredUsergroupIDs'])) $this->ignoreCondition->add('user.userID NOT IN (SELECT userID FROM wcf'.WCF_N.'_user_to_groups WHERE groupID IN ('.$generalOptions['ignoredUsergroupIDs'].'))');
		$this->ignoreCondition->add('user.registrationDate < '.(TIME_NOW - $generalOptions['periodOfGrace'] * 86400));
		$this->handleUserDelete($generalOptions);
		if($generalOptions['sendProtocol']) {
			$this->sendProtocol();
		}

		$this->setReturnMessage('success', WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.success', array('$countDeleted' => count($this->deletedInactiveUsers), '$countWarned' => count($this->warnedInactiveUsers))));

		$this->executed();
	}

	/**
	 * Handles the deletion or warning of inactive users
	 *
	 * @param array $generalOptions
	 */
	protected function handleUserDelete($generalOptions) {
		$deleteOptions = $this->data['parameters']['user.inactiveUsers.inactive'];

		switch($deleteOptions['action']) {
			case 'none' : return;
			case 'warn' : $this->warnUsers($generalOptions, true);
			break;
			case 'delete' : $this->deleteUsers($generalOptions);
			break;
			case 'warnanddelete' : $this->warnUsers($generalOptions);
			$this->deleteUsers($generalOptions);
			break;
		}
	}

	/**
	 * Warns users of the immenating deletion
	 *
	 * @param array $generalOptions
	 * @param boolean $warnOnly
	 */
	protected function warnUsers($generalOptions, $warnOnly = false) {
		if (!$generalOptions['sendWarnMail']) return;
		$deleteOptions = $this->data['parameters']['user.inactiveUsers.inactive'];

		$sql = "SELECT 		user.* 
			FROM 		wcf".WCF_N."_user user
			LEFT JOIN 	wcf".WCF_N."_user_option_value user_option 
			ON 		(user_option.userID = user.userID)				
			WHERE 		user_option.useroption".WCF::getUser()->getUserOptionID('adminCanMail')." = 1
			".($deleteOptions['notActivated'] ? " AND user.activationCode > 0" : "")." 
			AND 		user.lastActivityTime < ".(TIME_NOW - ($deleteOptions['time'] - $generalOptions['warnTime']) * 86400)."
			AND 		user.lastActivityTime > ".(TIME_NOW - ($deleteOptions['time'] - $generalOptions['warnTime'] + 1) * 86400)."
			AND 		".$this->ignoreCondition->get()."
			GROUP BY 	user.userID";		
		$result = WCF::getDB()->sendQuery($sql);
		$users = array();

		while($row = WCF::getDB()->fetchArray($result)) {
			$users[] = new User(null, $row);
		}
		foreach($users as $user) {
			$messageData = array(
				'$username' => $user->username,
				'$pagetitle' => PAGE_TITLE,
				'$lastvisit' => ($deleteOptions['time'] - $generalOptions['warnTime']),
				'$warntime' => $generalOptions['warnTime'],
				'$warnonly' => $warnOnly
			);

			$languageID = $user->languageID;
			if (!$languageID > 0) $languageID = '';
			$mail = new Mail(array($user->username => $user->email), WCF::getLanguage($languageID)->get('wcf.acp.admintools.function.user.inactiveUsers.inactive.mailsubject', array('$pagetitle' => PAGE_TITLE)), WCF::getLanguage($languageID)->get('wcf.acp.admintools.function.user.inactiveUsers.inactive.mailmessage', $messageData));
			$mail->send();
			$this->warnedInactiveUsers[] = $user;
		}
	}

	/**
	 * Deletes users
	 *
	 * @param array $generalOptions
	 */
	protected function deleteUsers($generalOptions) {
		$deleteOptions = $this->data['parameters']['user.inactiveUsers.inactive'];

		$sql = "SELECT 		user.* 
			FROM 		wcf".WCF_N."_user user
			LEFT JOIN 	wcf".WCF_N."_user_option_value user_option ON (user_option.userID = user.userID)				
			WHERE 		user.lastActivityTime < ".(TIME_NOW - ($deleteOptions['time'] * 86400))."	
			".($deleteOptions['notActivated'] ? " AND user.activationCode > 0" : "")."			
			AND 		".$this->ignoreCondition->get()."
			GROUP BY 	user.userID";		
		$result = WCF::getDB()->sendQuery($sql);
		$userIDs = array();

		while($row = WCF::getDB()->fetchArray($result)) {
			$this->deletedInactiveUsers[] = new User(null, $row);
			$userIDs[] = $row['userID'];
		}

		UserEditor::deleteUsers($userIDs);
	}

	/**
	 * Informs admins about the current warnings and deletions
	 *
	 */
	protected function sendProtocol() {
		$generalOptions = $this->data['parameters']['user.inactiveUsers.general'];
		$deleteOptions = $this->data['parameters']['user.inactiveUsers.inactive'];
		$actionDate = DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.dateFormat'), TIME_NOW);
		$mailUserTableHeader = "\n".str_pad("USER", 26, " ").str_pad("USERID", 12, " ", STR_PAD_LEFT)."    ".str_pad("REG-DATE", 20, " ")."LAST-ACTIVE"."\n".str_repeat('-', 80);
		$subject = WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.mailsubject', array('PAGE_TITLE' => PAGE_TITLE, '$actionDate' => $actionDate));
		$message = WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.message', array('PAGE_TITLE' => PAGE_TITLE, '$actionDate' => $actionDate))."\n";

		if ($generalOptions['appendNewUsers']) {
			$regTimeStart = mktime(0, 0, 0, (int) date("m",TIME_NOW), (int) date("d",TIME_NOW) - 1, (int) date("Y",TIME_NOW));
			$regTimeEnd = mktime(0, 0, 0, (int) date("m",TIME_NOW), (int) date("d",TIME_NOW), (int) date("Y",TIME_NOW));
			$regDate = DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.dateFormat'), $regTimeStart);
			$sql = "SELECT 		user.* 
				FROM 		wcf".WCF_N."_user user
                		WHERE 		registrationDate >= ".$regTimeStart."
                		AND 		registrationDate < ".$regTimeEnd."
                		ORDER BY 	LOWER(username)";
			$result = WCF::getDB()->sendQuery($sql);
			$users = array();
			while($row = WCF::getDB()->fetchArray($result)) {
				$users[] = new User(null, $row);
			}
			$message .= "\n\n";
			$message .= WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.newUsers', array('$count' => count($users), '$registrationDate' => $regDate)).$mailUserTableHeader;
			foreach($users as $user) {
				$message  .= "\n".str_pad($user->username, 26, " ")
				.str_pad($user->userID, 12, " ", STR_PAD_LEFT)
				."    "
				.str_pad(DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->registrationDate), 20, " ")
				.DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->lastActivityTime);
			}
		}

		if (($deleteOptions['action'] == 'warn' || $deleteOptions['action'] == 'warnanddelete') && $generalOptions['sendWarnMail']) {
			$message .= "\n\n";
			$message .= WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.inactive.mailwarned', array('$count' => count($this->warnedInactiveUsers))).$mailUserTableHeader;
			if(count($this->warnedInactiveUsers)) {
				foreach($this->warnedInactiveUsers as $user) {
					$message .= "\n".str_pad($user->username, 26, " ")
					.str_pad($user->userID, 12, " ", STR_PAD_LEFT)
					."    "
					.str_pad(DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->registrationDate), 20, " ")
					.DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->lastActivityTime);
				}
			}
			else $message .= "\n-";
		}

		if ($deleteOptions['action'] == 'delete' || $deleteOptions['action'] == 'warnanddelete') {
			$message .= "\n\n";
			$message .= WCF::getLanguage()->get('wcf.acp.admintools.function.user.inactiveUsers.inactive.adminmail', array('$count' => count($this->deletedInactiveUsers))).$mailUserTableHeader;
			if(count($this->deletedInactiveUsers)) {
				foreach($this->deletedInactiveUsers as $user) {
					$message .= "\n".str_pad($user->username, 26, " ")
					.str_pad($user->userID, 12, " ", STR_PAD_LEFT)
					."    "
					.str_pad(DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->registrationDate), 20, " ")
					.DateUtil::formatDate(WCF::getLanguage()->get('wcf.global.timeFormat'), $user->lastActivityTime);
				}
			}
			else $message .= "\n-";
		}

		$this->message = $message;
		$this->messageTableHeader = $mailUserTableHeader;
		EventHandler::fireAction($this, 'generateMessage');

		$mail = new Mail(array(MAIL_FROM_NAME => MAIL_FROM_ADDRESS), $subject, $this->message);
		$mail->send();
	}
}
?>