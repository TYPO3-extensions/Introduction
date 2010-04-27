<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Peter Beernink <p.beernink@drecomm.nl>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
require_once(t3lib_extMgm::extPath('demo', 'Classes/Configuration/Configuration.php'));
require_once(t3lib_extMgm::extPath('demo', 'Classes/Import/Database.php'));
require_once(t3lib_extMgm::extPath('demo', 'Classes/Import/Filestructure.php'));

class tx_demo_controller {
	/**
	 * The view object
	 *
	 * @var tx_demo_view_finish
	 */
	private $view;

	/**
	 * The installer Object
	 *
	 * @var tx_install
	 */
	private $installer;

	/**
	 * The configuration object which can modify the localconf.php file
	 *
	 * @var tx_demo_configuration
	 */
	private $configuration;

	/**
	 * The page to request in order to test if realURL is working correctly and thus can be enabled
	 *
	 * @var string
	 */
	private $realURLTestPath = 'about-typo3/';

	/**
	 * The default color to use
	 *
	 * @var string
	 */
	private $defaultColor = '#F18F0B';

	/**
	 * Handle the incoming steps
	 *
	 * @param array $markers The markers which are used in the install tool
	 * @param string $step The step in the install process
	 * @param tx_install $callerObject The install object
	 * @return void
	 */
	public function executeStepOutput(&$markers, $step, &$callerObject) {
		$this->configuration = t3lib_div::makeInstance('tx_demo_configuration');
		$this->databaseImporter = t3lib_div::makeInstance('tx_demo_import_database');
		$this->filestructureImporter = t3lib_div::makeInstance('tx_demo_import_filestructure');
		$this->installer = $callerObject;
		$this->configuration->setInstallerObject($callerObject);
		$this->databaseImporter->setInstallerObject($this->installer);
		$message = '';

		switch($step) {
			case '4':
				$markers['header'] = 'Choose a package';
				$this->installPackageAction($message);
				break;
			case '5':
				if ($this->installer->INSTALL['database_import_all']) {
					$this->importDefaultTables();
				}

				if (t3lib_div::_GP('systemToInstall') == 'blank') {
					$markers['header'] = 'Congratulations,';
					$this->finishBlankAction($message);
					break;
				}

				if (t3lib_div::_GP('systemToInstall') == 'demo') {
					$this->configuration->applyDefaultConfiguration();
					$this->configuration->modifyLocalConfFile();
				}

				$this->performUpdates();
				$markers['header'] = 'Introduction package';
				$this->passwordAction($message);
				break;
			case '6':
				$markers['header'] = 'Congratulations,';
				$this->finishAction($message);
				break;
		}
		if ($message != '') {
			$markers['step'] = $message;
		}
	}

	/**
	 * Imports the default database tables which would normally be done in step 4
	 *
	 * @return void
	 */
	private function importDefaultTables() {
		$_POST['goto_step'] = $this->installer->step;
		$this->installer->action = str_replace('&step='.$this->installer->step, '&systemToInstall='.t3lib_div::_GP('systemToInstall'), $this->installer->action);
		$this->installer->checkTheDatabase();
	}

	/**
	 * Try to set NegateMask in the localconf.php, import the database structure
	 *
	 * @return void
	 */
	private function performUpdates() {
		// As we use some GD functions to deterime the negate mask we need to check if GD is available
		if ($this->installer->isGD()) {
			$this->configuration->modifyNegateMask();
		}

		$this->databaseImporter->changeCharacterSet();
		$this->databaseImporter->importDatabase();
		$baseHref = t3lib_div::getIndpEnv('HTTP_HOST').t3lib_div::getIndpEnv('TYPO3_SITE_PATH');
		// Remove last slash
		$baseHref = substr($baseHref, 0, -1);
		$this->databaseImporter->updateBaseHref($baseHref);

		$this->filestructureImporter->importFiles();
	}

	/**
	 * Renders the choose a package form
	 *
	 * @param $message
	 * @return void
	 */
	public function installPackageAction(&$message) {
		require_once(t3lib_extMgm::extPath('demo', 'Classes/View/Installdemo.php'));
		$this->view = t3lib_div::makeInstance('tx_demo_view_installdemo');
		$message = $this->view->render();
	}

	/**
	 * Renders the password form
	 *
	 * @param $message
	 * @param $displayError = false Whether or not the missing password message should be displayed
	 * @return void
	 */
	public function passwordAction(&$message, $displayError = false) {
		require_once(t3lib_extMgm::extPath('demo', 'Classes/View/Password.php'));
		$this->view = t3lib_div::makeInstance('tx_demo_view_password');

		$this->installer->javascript[] = '<script type="text/javascript" src="' .
			t3lib_div::createVersionNumberedFilename(
				'../contrib/prototype/prototype.js'
		) . '"></script>';

		$this->view->assign('ENTER_PASSWORD' , '');
		$this->view->assign('PASSWORD', '');
		if ($displayError) {
			$this->view->assign('ENTER_PASSWORD' , 'The entered password is too short');
			$this->view->assign('PASSWORD', t3lib_div::GPvar('password'));
		}

		$this->view->assign('CHECK_REAL_URL_COMPLIANCE_URL' , '');
		if ($this->configuration->isModRewriteEnabled()) {
			// Try to copy _.htaccess to .htaccess
			if ($this->filestructureImporter->copyHtAccessFile()) {
				$this->view->assign('CHECK_REAL_URL_COMPLIANCE_URL' , t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->realURLTestPath);
			}
		}

		if (t3lib_div::GPvar('colorPicker')) {
			$this->view->assign('COLOR', t3lib_div::GPvar('colorPicker'));
		} else {
			$this->view->assign('COLOR', $this->defaultColor);
		}

		$message = $this->view->render();
	}

	/**
	 * Action to perform when the blank system has been installed
	 *
	 * @param string $message The message to display
	 * @return void
	 */
	public function finishBlankAction(&$message) {
		require_once(t3lib_extMgm::extPath('demo', 'Classes/View/Finishblank.php'));
		$this->view = t3lib_div::makeInstance('tx_demo_view_finishblank');

		$message = $this->view->render();
	}

	/**
	 * Action when everything has been finished
	 *
	 * Render the template and show the logins for front- and backend
	 *
	 * @param string $message The message to show
	 * @return void
	 */
	public function finishAction(&$message) {
		require_once(t3lib_extMgm::extPath('demo', 'Classes/View/Finish.php'));

		// Enable or disable realURL
		$this->databaseImporter->updateRealURLConfiguration(t3lib_div::_GP('useRealURL'));

		$newPassword = t3lib_div::_GP('password');
		if (strlen(trim($newPassword)) < 6) {
			$this->passwordAction($message, true);
			return;
		}
		$this->configuration->modifyPasswords($newPassword);
		$this->filestructureImporter->changeColor(t3lib_div::GPvar('colorPicker'));

		$this->view = t3lib_div::makeInstance('tx_demo_view_finish');

		// Try to remove ENABLE_INSTALL_TOOL
		@unlink(PATH_typo3conf . 'ENABLE_INSTALL_TOOL');

		$this->view->assign('REMOVE_ENABLE_INSTALL_TOOL', '');
		if (is_file(PATH_typo3conf . 'ENABLE_INSTALL_TOOL')) {
			$this->view->assign('REMOVE_ENABLE_INSTALL_TOOL', 'Unfortunately it was not possible to remove the \'ENABLE_INSTALL_TOOL\' file.<br/>As this might be a security risk, please remove the file manually.');
		}
		$message = $this->view->render();
	}
}
?>
