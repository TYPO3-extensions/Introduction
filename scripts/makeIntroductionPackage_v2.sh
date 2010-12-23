#!/bin/bash
# This script packages the introduction package
# 
# Take the core trunk and the dummy
# Add the following extensions to typo3conf/ext/:
#   * introduction
# 
# Change the typo3conf/localconf.php => extList and add:
# felogin,indexed_search,introduction,realurl,tt_news,automaketemplate
# for 4.5 also add: info,perm,func,filelist,about
# 
# Take the file translations/en/introduction.sql from the "introduction" trunk and copy it to
# typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database

packagename="introductionpackage"
packageversion="$1"

if [ -z "$packageversion" ]
then
	echo "Syntax: $0 <typo3-release>"
	echo " e.g. $0 4.4.2"
	echo "      $0 4.5.0alpha1"
	echo "<typo3-release> must be a valid release available for download at sourceforge"
	exit 1
fi

rm -rf $packagename-$packageversion
mkdir $packagename-$packageversion
cd $packagename-$packageversion

# fetch the dummy and source package
echo "Fetching typo3_src+dummy-$packageversion.zip"
wget -q http://downloads.sourceforge.net/project/typo3/TYPO3%20Source%20and%20Dummy/TYPO3%20$packageversion/typo3_src+dummy-$packageversion.zip
echo "Extracting"
unzip -q typo3_src+dummy-$packageversion.zip
mv typo3_src+dummy-$packageversion/* .
rm typo3_src+dummy-$packageversion.zip
rmdir typo3_src+dummy-$packageversion


# fetch introduction package data
# see http://forge.typo3.org/repositories/show/extension-introduction 
echo "Fetching typo3conf/ext/introduction"
svn -q export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/introduction/ typo3conf/ext/introduction
# not needed? svn export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/scripts/ typo3conf/ext/introduction/sourcescripts
echo "Fetching typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database"
rmdir typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database
svn -q export https://svn.typo3.org/TYPO3v4/Extensions/introduction/trunk/translations/en/ typo3conf/ext/introduction/Resources/Private/Subpackages/Introduction/Database

# update localconf.php
echo "Updating files"
echo "<?php
\$TYPO3_CONF_VARS['SYS']['sitename'] = 'New TYPO3 site';

	// Default password is 'joh316':
\$TYPO3_CONF_VARS['BE']['installToolPassword'] = 'bacb98acf97e0b6112b1d1b650b84971';

\$TYPO3_CONF_VARS['EXT']['extList'] = 'info,perm,func,filelist,about,tsconfig_help,context_help,extra_page_cm_options,impexp,sys_note,tstemplate,tstemplate_ceditor,tstemplate_info,tstemplate_objbrowser,tstemplate_analyzer,func_wizards,wizard_crpages,wizard_sortpages,lowlevel,install,belog,beuser,aboutmodules,setup,taskcenter,info_pagetsconfig,viewpage,rtehtmlarea,css_styled_content,t3skin,t3editor,reports,felogin,indexed_search,introduction,realurl,tt_news,automaketemplate';

\$typo_db_extTableDef_script = 'extTables.php';

## INSTALL SCRIPT EDIT POINT TOKEN - all lines after this points may be changed by the install script!

?>" > typo3conf/localconf.php

# create .htaccess
cp misc/advanced.htaccess .htaccess

# create FIRST_INSTALL file
touch typo3conf/FIRST_INSTALL

# sanitize permissions
chmod -R g+w typo3temp/ typo3conf/ uploads/ fileadmin/
chgrp -R www-data fileadmin/ typo3conf/ typo3temp/ uploads/

# Zip it up
echo "Zipping results"
zip -9r -q ../$packagename-$packageversion.zip .htaccess *
#cd ..
#rm -rf $packagename-$packageversion
