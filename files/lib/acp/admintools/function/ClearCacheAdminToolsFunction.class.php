<?php
/**
 *   This file is part of Admin Tools 2.
 *
 *   Admin Tools 2 is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   Admin Tools 2 is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with Admin Tools 2.  If not, see <http://www.gnu.org/licenses/>.
 *
 * 
 */

// wcf imports
require_once(WCF_DIR.'lib/acp/admintools/function/AbstractAdminToolsFunction.class.php');
require_once(WCF_DIR.'lib/acp/option/Options.class.php');
require_once(WCF_DIR.'lib/system/language/LanguageEditor.class.php');

/**
 * Clears several caches. This is the only concrete function inside the net.hawkes.admintools package
 *
 * @author	Oliver Kliebisch
 * @copyright	2009 Oliver Kliebisch
 * @license	GNU General Public License <http://www.gnu.org/licenses/>
 * @package	net.hawkes.admintools
 * @subpackage 	acp.admintools.function
 * @category 	WCF 
 */
class ClearCacheAdminToolsFunction extends AbstractAdminToolsFunction {

	/**
	 * @see AdminToolsFunction::execute($data)
	 */
	public function execute($data) {
		parent::execute($data);		
		$parameters = $data['parameters']['cache.clearCache'];
							
		if ($parameters['clearWCFCache']) {
			WCF::getCache()->clear(WCF_DIR.'cache', '*.php', true);
		}
		
		if ($parameters['clearStandaloneCache']) {
			$sql = "SELECT 	packageDir 
				FROM 	wcf".WCF_N."_package 
				WHERE 	packageID = ".PACKAGE_ID;
			$row = WCF::getDB()->getFirstRow($sql);
			WCF::getCache()->clear($row['packageDir'].'cache', '*.php', true);
		}
		
		if ($parameters['clearTemplateCache']) {
			require_once(WCF_DIR.'lib/system/template/ACPTemplate.class.php');
			ACPTemplate::deleteCompiledACPTemplates();
			Template::deleteCompiledTemplates();
		}
		
		if ($parameters['clearLanguageCache']) {
			LanguageEditor::deleteLanguageFiles('*', '*', '*');
		}
		
		if ($parameters['clearStandaloneOptions']) {
			Options::resetCache();
			Options::resetFile();
		}				
		
		$this->executed();		
	}
}
?>