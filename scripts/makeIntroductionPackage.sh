#!/bin/bash
# This script packages the introduction package
# 
# Take the core trunk and the dummy
# Add the following extensions to typo3conf/ext/:
#   * introduction
# 
# Change the typo3conf/localconf.php => extList and add:
# felogin,indexed_search,introduction,realurl,tt_news,automaketemplate
# 
# Take the file translations/en/introduction.sql from the "introduction" trunk and copy it to
# typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database

packagename="introductionpackage"

#packageversion="4.4-latest"
#corerepository="trunk"
#dummypackageversion="4.4.0RC1"

packageversion="4.4.0-rc1"
corerepository="tags/TYPO3_4-4-0RC1"
dummypackageversion="4.4.0RC1"

cd /home/ldx/tmp/packages
mkdir $packagename-$packageversion
cd $packagename-$packageversion

# fetch the dummy package
wget -q http://downloads.sourceforge.net/project/typo3/TYPO3%20Source%20and%20Dummy/TYPO3%20$dummypackageversion/dummy-$dummypackageversion.zip?use_mirror=cdnetworks-us-2
unzip -q dummy-$dummypackageversion.zip
mv dummy-$dummypackageversion/* .
rm dummy-$dummypackageversion.zip
rmdir dummy-$dummypackageversion

# fetch the latest TYPO3 version
svn -q export https://svn.typo3.org/TYPO3v4/Core/$corerepository/ typo3_src
mv -f typo3_src/* .
rmdir typo3_src

# fetch introduction package data
# see http://forge.typo3.org/repositories/show/extension-introduction 
svn -q export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/introduction/ typo3conf/ext/introduction
# not needed? svn export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/scripts/ typo3conf/ext/introduction/sourcescripts
rmdir typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database
svn -q export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/translations/en/ typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database

# update localconf.php
echo "<?php
\$TYPO3_CONF_VARS['SYS']['sitename'] = 'New TYPO3 site';

	// Default password is 'joh316':
\$TYPO3_CONF_VARS['BE']['installToolPassword'] = 'bacb98acf97e0b6112b1d1b650b84971';

\$TYPO3_CONF_VARS['EXT']['extList'] = 'tsconfig_help,context_help,extra_page_cm_options,impexp,sys_note,tstemplate,tstemplate_ceditor,tstemplate_info,tstemplate_objbrowser,tstemplate_analyzer,func_wizards,wizard_crpages,wizard_sortpages,lowlevel,install,belog,beuser,aboutmodules,setup,taskcenter,info_pagetsconfig,viewpage,rtehtmlarea,css_styled_content,t3skin,felogin,indexed_search,introduction,realurl,tt_news,automaketemplate';

\$typo_db_extTableDef_script = 'extTables.php';

## INSTALL SCRIPT EDIT POINT TOKEN - all lines after this points may be changed by the install script!

?>" > typo3conf/localconf.php

# create ENABLE_INSTALL_TOOL file
# Waiting for issue http://bugs.typo3.org/view.php?id=14719 to land in the core
# When that is fixed, this line must be removed
touch typo3conf/ENABLE_INSTALL_TOOL

# sanitize permissions
chmod -R g+w typo3temp/ typo3conf/ uploads/ fileadmin/
chown -R :www-data fileadmin/ typo3conf/ typo3temp/ uploads/

# Zip it up
zip -9r -q ../$packagename-$packageversion.zip *
cd ..
rm -rf $packagename-$packageversion
