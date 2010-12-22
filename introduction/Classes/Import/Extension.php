<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Peter Beernink <p.beernink@drecomm.nl>
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

if (t3lib_div::int_from_ver(TYPO3_version) >= 4005000) {
	define('TYPO3_EM_PATH', PATH_site . TYPO3_mainDir . 'sysext/em/mod1/');
} else {
	define('TYPO3_EM_PATH', PATH_site . TYPO3_mainDir . 'mod/tools/em/');
}

require_once(TYPO3_EM_PATH . 'class.em_terconnection.php');

class tx_introduction_import_extension {

	/**
	 * The directory containing all extensions to install
	 *
	 * @var string
	 */
	private $sourceDirectory = 'Resources/Private/Extensions';

	/**
	 * @var SC_mod_tools_em_terconnection
	 */
	private $terConnection = null;

	/**
	 * @var SC_mod_tools_em_index
	 */
	private $emIndex = null;

	/**
	 * Initializes the extension manager
	 */
	public function __construct() {
		// Create an instance of language. Needed in order to make the em_index work
		$GLOBALS['LANG'] = t3lib_div::makeInstance('language');
		$GLOBALS['LANG']->csConvObj = t3lib_div::makeInstance('t3lib_cs');

		require_once(TYPO3_EM_PATH . 'class.em_index.php');
		$this->extensionManager = t3lib_div::makeInstance('SC_mod_tools_em_index');

		// Setting paths of install scopes for the extensionManager
		$this->extensionManager->typePaths = Array (
			'S' => TYPO3_mainDir.'sysext/',
			'G' => TYPO3_mainDir.'ext/',
			'L' => 'typo3conf/ext/'
		);
		$this->extensionManager->typeBackPaths = Array (
			'S' => '../../../',
			'G' => '../../../',
			'L' => '../../../../'.TYPO3_mainDir
		);

		// Make an array needed by removeRequiredExtFromListArr().
		$this->extensionManager->requiredExt = t3lib_div::trimExplode(',',$GLOBALS['TYPO3_CONF_VARS']['EXT']['requiredExt'],1);

		$this->terConnection = t3lib_div::makeInstance('SC_mod_tools_em_terconnection');
	}

	/**
	 * Resets the sourceDirectory based on the given subpackage
	 *
	 * @param string $subpackage
	 * @return void
	 */
	public function setSubpackage($subpackage) {
		$this->sourceDirectory = 'Resources/Private/Subpackages/' . $subpackage . '/Extensions';
	}

	/**
	 * Imports the extension from the t3x file based on the extension key and enables it
	 *
	 * @param string $extensionKey
	 * @return void
	 */
	public function importExtension($extensionKey) {
		$extensionDirectory = PATH_typo3conf . 'ext/' . $extensionKey . '/';

		$extension = file_get_contents(t3lib_extMgm::extPath('introduction', $this->sourceDirectory . '/' . $extensionKey . '.t3x'));
		$extensionArray = $this->terConnection->decodeExchangeData($extension);

		$EM_CONF = $this->extensionManager->fixEMCONF($extensionArray[0]['EM_CONF']);

		// Create the directories needed for the extension
		t3lib_div::mkdir($extensionDirectory);
		$directoriesInExtension = $this->extensionManager->extractDirsFromFileList(array_keys($extensionArray[0]['FILES']));
		foreach($directoriesInExtension as $directory) {
			t3lib_div::mkdir_deep($extensionDirectory, $directory);
		}

		// Now write all the files from the extension
		foreach($extensionArray[0]['FILES'] as $theFile => $fileData) {
			t3lib_div::writeFile($extensionDirectory . $theFile, $fileData['content']);
			if (!@is_file($extensionDirectory . $theFile)) {
				// Error handling
			} elseif (md5(t3lib_div::getUrl($extensionDirectory . $theFile)) != $fileData['content_md5']) {
				// Error handling
			}
		}

		// Create EMCONF file
		$extensionMD5Array = $this->extensionManager->serverExtensionMD5Array($extensionKey, array('type' => 'L', 'EM_CONF' => array(), 'files' => array()));
		$EM_CONF['_md5_values_when_last_written'] = serialize($extensionMD5Array);
		$emConfFile = $this->extensionManager->construct_ext_emconf_file($extensionKey, $EM_CONF);
		t3lib_div::writeFile($extensionDirectory . 'ext_emconf.php', $emConfFile);
	}

	/**
	 * Enables the extension
	 *
	 * @param string $extensionKey
	 * @return void
	 */
	public function enableExtension($extensionKey) {
		list($extensionList,) = $this->extensionManager->getInstalledExtensions();
		$newExtensionList = $this->extensionManager->addExtToList($extensionKey, $extensionList);
		$this->extensionManager->writeNewExtensionList($newExtensionList);
		$this->extensionManager->refreshGlobalExtList();
		$this->extensionManager->forceDBupdates($extensionKey, $extensionList[$extensionKey]);
	}
}
?>
