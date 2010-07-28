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
packageversion="4.4.1"

mkdir $packagename-$packageversion
cd $packagename-$packageversion

# fetch the dummy and source package
wget -q http://downloads.sourceforge.net/project/typo3/TYPO3%20Source%20and%20Dummy/TYPO3%20$packageversion/typo3_src+dummy-$packageversion.zip?use_mirror=cdnetworks-us-2
unzip -q typo3_src+dummy-$packageversion.zip
mv typo3_src+dummy-$packageversion/* .
rm typo3_src+dummy-$packageversion.zip
rmdir typo3_src+dummy-$packageversion


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

\$TYPO3_CONF_VARS['EXT']['extList'] = 'tsconfig_help,context_help,extra_page_cm_options,impexp,sys_note,tstemplate,tstemplate_ceditor,tstemplate_info,tstemplate_objbrowser,tstemplate_analyzer,func_wizards,wizard_crpages,wizard_sortpages,lowlevel,install,belog,beuser,aboutmodules,setup,taskcenter,info_pagetsconfig,viewpage,rtehtmlarea,css_styled_content,t3skin,t3editor,reports,felogin,indexed_search,introduction,realurl,tt_news,automaketemplate';

\$typo_db_extTableDef_script = 'extTables.php';

## INSTALL SCRIPT EDIT POINT TOKEN - all lines after this points may be changed by the install script!

?>" > typo3conf/localconf.php

# create .htaccess
echo "<IfModule mod_rewrite.c>
RewriteEngine On

# Prevent serving TYPO3 404 pages for missing files
RewriteRule ^(typo3(conf|temp)?|fileadmin|uploads|t3lib|clear.gif|index.php|favicon.ico) - [L]

# Do not rewrite static resources
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d [OR]
RewriteCond %{REQUEST_FILENAME} -l
RewriteRule .* - [L]

# Rewrite the rest to index.php
RewriteRule .* index.php [L]
</IfModule>" > .htaccess

# create FIRST_INSTALL file
touch typo3conf/FIRST_INSTALL

# sanitize permissions
chmod -R g+w typo3temp/ typo3conf/ uploads/ fileadmin/
chgrp -R www-data fileadmin/ typo3conf/ typo3temp/ uploads/

# Zip it up
zip -9r -q ../$packagename-$packageversion.zip .htaccess *
#cd ..
#rm -rf $packagename-$packageversion
