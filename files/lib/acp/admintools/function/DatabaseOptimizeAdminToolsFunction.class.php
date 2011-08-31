<?php
// wcf imports
require_once(WCF_DIR.'lib/acp/admintools/function/AbstractAdminToolsFunction.class.php');

/**
 * Performs analyze and optimize options on the database
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
 *
 * @author	Oliver Kliebisch
 * @copyright	2009 Oliver Kliebisch
 * @license	GNU General Public License <http://www.gnu.org/licenses/>
 * @package	net.hawkes.admintools
 * @subpackage 	acp.admintools.function
 * @category 	WCF
 */
class DatabaseOptimizeAdminToolsFunction extends AbstractAdminToolsFunction {

	/**
	 * @see AdminToolsFunction::execute($data)
	 */
	public function execute($data) {
		parent::execute($data);

		$parameters = $data['parameters']['database.optimize'];
		$tables = WCF::getDB()->getTableNames();
		$message = WCF::getLanguage()->get('wcf.acp.admintools.function.success', array('$functionName' => WCF::getLanguage()->get('wcf.acp.admintools.function.'.$data['functionName']))).'<ul>';
		if ($parameters['analyze']) {
			foreach($tables as $table) {
				WCF::getDB()->sendQuery('ANALYZE TABLE '.$table);
			}
			$message .= WCF::getLanguage()->get('wcf.acp.admintools.function.database.optimize.analyze.success', array('$tableCount' => count($tables)));
		}

		if ($parameters['optimize']) {
			foreach($tables as $table) {
				WCF::getDB()->sendQuery('OPTIMIZE TABLE '.$table);
			}
			$message .= WCF::getLanguage()->get('wcf.acp.admintools.function.database.optimize.optimize.success', array('$tableCount' => count($tables)));
		}
		if (!count($tables) || (!$parameters['analyze'] && !$parameters['optimize'])) {
			$this->setReturnMessage('error', WCF::getLanguage()->get('wcf.acp.admintools.function.database.optimize.analyze.error'));
		}
		else $this->setReturnMessage('success', $message.'</ul>');

		$this->executed();
	}
}
?>