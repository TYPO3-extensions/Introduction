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

class tx_introduction_configuration {

	/**
	 * The installer object
	 *
	 * @var tx_install
	 */
	private $InstallerObject;

	/**
	 * The configuration as determined by the install tool
	 *
	 * @var array
	 */
	private $calculatedConfiguration;

	/**
	 * Location of the configuration to be added to the localconf.php
	 *
	 * @var string
	 */
	private $packageConfigurationPath = 'Configuration/PackageConfiguration.php';

	/**
	 * String to search for in the configuration file.
	 *
	 * @var string
	 */
	private $packageConfigurationStartingPoint = '## INSTALL SCRIPT POINT - all lines after this point will be included by the install script. Do not remove!';

	/**
	 * The step to perform after basic configuration is done
	 *
	 * @var string
	 */
	private $stepAfterConfigurationUpdate = 5;

	/**
	 * Sets the InstallerObject.
	 *
	 * @param tx_install $InstallerObject
	 * @return void
	 */
	public function setInstallerObject($installerObject) {
		$this->InstallerObject = $installerObject;
	}

	/**
	 * Modifies the typo3conf/localconf.php file with calculated values.
	 *
	 * @return void
	 */
	public function modifyLocalConfFile() {
		$itemsToModify = array (
			'disable_exec_function',
			'im_combine_filename',
			'gdlib',
			'gdlib_png',
			'im',
			'im_path',
			'im_path_lzw',
		);

		$this->InstallerObject->checkIM=1;
		$this->InstallerObject->checkTheConfig();

		$this->calculatedConfiguration = $this->InstallerObject->setupGeneralCalculate();

		foreach($itemsToModify as $key) {
			if (is_array($this->calculatedConfiguration[$key])) {
				switch ($key) {
					case 'im_path':
					case 'im_path_lzw':
						if (!empty($this->calculatedConfiguration[$key][0])) {
							$imVersion = current($this->InstallerObject->config_array['im_versions'][$this->calculatedConfiguration[$key][0]]);
							$this->calculatedConfiguration[$key][0] .= '|'.$imVersion;
						}
						break;
					case 'gdlib_png':
						if ($this->calculatedConfiguration[$key][1]) {
							$this->calculatedConfiguration[$key][0] = $this->calculatedConfiguration[$key][1];
						}
						break;
					default:
				}
				$this->InstallerObject->INSTALL['localconf.php'][$key] = $this->calculatedConfiguration[$key][0];
			}
		}

		if ($this->InstallerObject->isGD()) {
			$this->InstallerObject->INSTALL['localconf.php']['TTFdpi'] = $this->determineDPI();
			$gdInfo = gd_info();
			if (intval($gdInfo['GD Version']) >= 2) {
				// 2.0 or higher
				$localConfigurationLines = $this->InstallerObject->writeToLocalconf_control();
				$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'GFX\'][\'gdlib_2\']', 1);
				$this->InstallerObject->writeToLocalconf_control($localConfigurationLines, false);
			}
		}

		// Replace the step in the action, as we would run into a loop.
		$this->InstallerObject->action = str_replace('step='.$this->InstallerObject->step, 'step='.$this->stepAfterConfigurationUpdate, $this->InstallerObject->action);

		$this->InstallerObject->setupGeneral();
	}

	/**
	 * Modifies the passwords of the installtool and be_user to the given password
	 *
	 * @param string $newPassword
	 * @return void
	 */
	public function modifyPasswords($newPassword) {
		// Change password of the installtool
		$localConfigurationLines = $this->InstallerObject->writeToLocalconf_control();
		$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'BE\'][\'installToolPassword\']', md5($newPassword));
		$this->InstallerObject->writeToLocalconf_control($localConfigurationLines, false);

		// Change password of the be_users
		$GLOBALS['TYPO3_DB']->exec_updateQuery('be_users', '', array('password' => md5($newPassword)));
	}

	/**
	 * Tries to determine which im_negate_mask/im_imvMaskState setting should be used
	 * and modifies the typo3conf/localconf.php.
	 * As the GFX settings should be set, we cannot run it in the same step as the other image modification.
	 *
	 * @return void
	 */
	public function modifyNegateMask() {
		$imageProcessor = t3lib_div::makeInstance('t3lib_stdGraphic');
		$imageProcessor->init();
		$imageProcessor->tempPath = $this->InstallerObject->typo3temp_path;
		$imageProcessor->dontCheckForExistingTempFile=1;
		$imageProcessor->filenamePrefix='install_';
		$imageProcessor->dontCompress=1;
		$imageProcessor->alternativeOutputKey='TYPO3_INSTALL_SCRIPT';
		$imageProcessor->noFramePrepended=$GLOBALS['TYPO3_CONF_VARS']['GFX']['im_noFramePrepended'];

		$imageProcessor->dontUnlinkTempFiles=0;
		$imageProcessor->IM_commands=array();

		$input = t3lib_extMgm::extPath('install').'imgs/greenback.gif';
		$overlay = t3lib_extMgm::extPath('install').'imgs/jesus.jpg';
		$mask = t3lib_extMgm::extPath('install').'imgs/blackwhite_mask.gif';
		$output = $imageProcessor->tempPath.$imageProcessor->filenamePrefix.t3lib_div::shortMD5($imageProcessor->alternativeOutputKey.'combine1').'.jpg';
		$imageProcessor->combineExec($input,$overlay,$mask,$output, true);

		$imageResource = imagecreatefromjpeg($output);
		$color1 = imagecolorat($imageResource, 1,1);
		$color2 = imagecolorat($imageResource, 20,20);

		// if $color1 equals $color2 the mask is applied to the top. We should change the negate mask
		if ($color1 == $color2) {
			$localConfigurationLines = $this->InstallerObject->writeToLocalconf_control();
			if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['im_imvMaskState'] == 1) {
				$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'GFX\'][\'im_imvMaskState\']', 0);
				$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'GFX\'][\'im_negate_mask\']', 1);
			} else {
				$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'GFX\'][\'im_imvMaskState\']', 1);
				$this->InstallerObject->setValueInLocalconfFile($localConfigurationLines, '$TYPO3_CONF_VARS[\'GFX\'][\'im_negate_mask\']', 0);
			}
			$this->InstallerObject->writeToLocalconf_control($localConfigurationLines, false);
		}
	}

	/**
	 * Determine which DPI should be used in FreeType by creating an image and adding a text on it.
	 * After adding the text, it checks whether the color at 14,1 is the background color or the text color.
	 * When the DPI should be changed, it will be the front color, as the text is redered to big.
	 *
	 * @return int The DPI to use.
	 */
	private function determineDPI() {
		if ($TYPO3_CONF_VARS['GFX']['TTFdpi']) {
			// If already set, no need to check it again
			return $TYPO3_CONF_VARS['GFX']['TTFdpi'];
		}
		if (function_exists('imagettftext')) {
			$imageResource = @imagecreate (300, 50);
			$backgroundColor = imagecolorallocate ($imageResource, 255, 255, 55);
			$textColor = imagecolorallocate ($imageResource, 233, 14, 91);
			imagettftext($imageResource, t3lib_div::freetypeDpiComp(20), 0, 10, 20, $textColor, PATH_t3lib."/fonts/vera.ttf", 'Testing Truetype support');

			// If DPI is set to 72 and we should use 96, the color at 14,1 will be the textColor
			$testColor = imagecolorat($imageResource, 14, 1);
			if ($testColor == $textColor) {
				return 96;
			}
		}
		return 72;
	}

	/**
	 * Sets the default configuration which is needed for this package.
	 * This includes UTF-8 settings, but also extension settings.
	 *
	 * @return void
	 */
	public function applyDefaultConfiguration() {
		$localConfigurationLines = $this->InstallerObject->writeToLocalconf_control();

		$packageConfiguration = explode(chr(10),str_replace(chr(13),'',trim(t3lib_div::getUrl(t3lib_extMgm::extPath('introduction', $this->packageConfigurationPath)))));
		// Remove the PHP ending tag
		array_pop($packageConfiguration);
		// Strip off everything till our starting point
		reset($packageConfiguration);
		while (true) {
			$currentLine = array_shift($packageConfiguration);
			if (trim($currentLine) == $this->packageConfigurationStartingPoint) {
				break;
			}
			if (count($packageConfiguration) == 0) {
				return;
			}
		}

		foreach($packageConfiguration as $configurationLine) {
			array_push($localConfigurationLines, $configurationLine);
		}
		$this->InstallerObject->setLocalconf = true;
		$this->InstallerObject->writeToLocalconf_control($localConfigurationLines, false);
	}

	/**
	 * Checks whether or not mod_rewrite is enabled in Apache.
	 * Unfortunately we have to do this by checking the output of phpinfo()
	 *
	 * @return boolean
	 */
	public function isModRewriteEnabled() {
		ob_start();
		phpinfo();
		$output = ob_get_contents();
		ob_end_clean();
		if (strpos($output, 'mod_rewrite')) {
			return true;
		}
		return false;
	}
}
?>
