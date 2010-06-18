#!/bin/sh
TABLES="be_groups be_users_shadow fe_groups fe_users index_fulltext index_grlist index_phash index_rel index_section index_stat_word index_words pages pages_language_overlay sys_be_shortcuts sys_filemounts sys_language sys_refindex sys_template tt_content tt_news tt_news_cat tt_news_cat_mm tx_realurl_pathcache tx_realurl_uniqalias tx_realurl_urldecodecache tx_realurl_urlencodecache"
DATABASE=typo3
OUTPUTFILE=introduction.sql
USER=root
PASSWORD=1234


mysql -u ${USER} --password=${PASSWORD} ${DATABASE} -e 'DROP TABLE IF EXISTS be_users_shadow; CREATE TABLE be_users_shadow SELECT * FROM be_users WHERE uid IN(2,3,4)'

mysqldump -u ${USER} -p${PASSWORD} --disable-keys --skip-quote-names ${DATABASE} ${TABLES} | sed 's/AUTO_INCREMENT=[0-9]* //' > ${OUTPUTFILE}_dump

# Rename table be_users_shadow to be_users
sed "s/be_users_shadow/be_users/g" ${OUTPUTFILE}_dump > ${OUTPUTFILE}_shadow

# Comment absRefPrefix
sed "s/absRefPrefix/\# absRefPrefix/g" ${OUTPUTFILE}_shadow > ${OUTPUTFILE}_absRefPrefix

# Replace ENABLE REALURL
sed "s/tx_realurl_enable = 1/tx_realurl_enable = \#\#\#ENABLE_REALURL\#\#\#/g" ${OUTPUTFILE}_absRefPrefix > ${OUTPUTFILE}_realURL

# Replace Site path
sed "s/domain = [^\\]*/domain = ###HOSTNAME_AND_PATH###/g" ${OUTPUTFILE}_realURL > ${OUTPUTFILE}

# Cleanup
rm ${OUTPUTFILE}_absRefPrefix ${OUTPUTFILE}_dump ${OUTPUTFILE}_realURL ${OUTPUTFILE}_shadow
