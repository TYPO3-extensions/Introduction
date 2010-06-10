#!/bin/sh
TABLES="be_groups be_users fe_groups fe_users index_fulltext index_grlist index_phash index_rel index_section index_stat_word index_words pages pages_language_overlay sys_be_shortcuts sys_filemounts sys_language sys_refindex sys_template tt_content tt_news tt_news_cat tt_news_cat_mm tx_realurl_pathcache tx_realurl_uniqalias tx_realurl_urldecodecache tx_realurl_urlencodecache"
DATABASE=typo3
OUTPUTFILE=introduction.sql
USER=root
PASSWORD=1234

mysqldump -u ${USER} -p${PASSWORD} ${DATABASE} ${TABLES} | sed 's/AUTO_INCREMENT=[0-9]* //' > ${OUTPUTFILE}_dump

# Comment absRefPrefix
sed "s/absRefPrefix/\# absRefPrefix/g" ${OUTPUTFILE}_dump > ${OUTPUTFILE}_absRefPrefix

# Replace ENABLE REALURL
sed "s/tx_realurl_enable = 1/tx_realurl_enable = \#\#\#ENABLE_REALURL\#\#\#/g" ${OUTPUTFILE}_absRefPrefix > ${OUTPUTFILE}_realURL

# Replace Site path
sed "s/domain = [^\\\r]*\\\r\\\n/domain = \#\#\#HOSTNAME_AND_PATH\#\#\#\\\r\\\n/g" ${OUTPUTFILE}_realURL > ${OUTPUTFILE}

# Cleanup
rm ${OUTPUTFILE}_absRefPrefix ${OUTPUTFILE}_dump ${OUTPUTFILE}_realURL
