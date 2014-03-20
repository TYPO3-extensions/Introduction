#!/bin/sh

# Tables which have a field deleted. Used to clean up deleted records before exporting
CLEANUP_TABLES=`xargs <<EOF
be_groups
be_users_shadow
fe_groups
fe_users
pages
pages_language_overlay
sys_filemounts
sys_news
sys_refindex
sys_template
tt_content tt_news
tt_news_cat
EOF`

TRUNCATE_TABLES=`xargs <<EOF
be_sessions
cache_extensions
cache_hash
cache_imagesizes
cache_md5params
cache_pages
cache_pagesection
cache_treelist
cache_typo3temp_log
cachingframework_cache_hash
cachingframework_cache_hash_tags
cachingframework_cache_pages
cachingframework_cache_pages_tags
cachingframework_cache_pagesection
cachingframework_cache_pagesection_tags
fe_session_data
fe_sessions
index_stat_search
index_stat_word
sys_history
sys_lockedrecords
sys_log
tt_news_cache
tt_news_cache_tags
tx_extbase_cache_object
tx_extbase_cache_object_tags
tx_extbase_cache_reflection
tx_extbase_cache_reflection_tags
tx_linkvalidator_links
tx_realurl_chashcache
tx_realurl_errorlog
tx_realurl_pathcache
tx_realurl_urldecodecache
tx_realurl_urlencodecache
EOF`

DATABASE=introduction45
OUTPUTFILE=introduction.sql

# Only dump the backend users we need
mysql ${DATABASE} -e '
DROP TABLE IF EXISTS be_users_shadow;
CREATE TABLE be_users_shadow SELECT * FROM be_users WHERE uid IN(2,3,4);
ALTER TABLE be_users_shadow ADD PRIMARY KEY (uid), ADD KEY parent (pid), ADD KEY username (username);
ALTER TABLE be_users_shadow CHANGE uid uid INT(11) unsigned NOT NULL auto_increment;
'

# Remove deleted records
for table in ${CLEANUP_TABLES}
do
	mysql ${DATABASE} -e "DELETE FROM ${table} WHERE deleted=1;"
done

# Truncate cache tables
for table in ${TRUNCATE_TABLES}
do
	mysql ${DATABASE} -e "TRUNCATE ${table};"
done

# Dump all tables and do some cleanup
mysqldump --disable-keys --skip-quote-names ${DATABASE} | sed \
	-e 's/AUTO_INCREMENT=[0-9]* //' \
	-e 's/be_users_shadow/be_users/g' \
	-e "s/ CHARACTER SET utf8//g" \
	-e "s/ DEFAULT CHARSET=latin1/ DEFAULT CHARSET=utf8/g" \
	-e "s/absRefPrefix/\# absRefPrefix/g" \
	-e "s/tx_realurl_enable = 1/tx_realurl_enable = \#\#\#ENABLE_REALURL\#\#\#/g" \
	-e "s/domain = [^\\]*/domain = ###HOSTNAME_AND_PATH###/g" > ${OUTPUTFILE}

# Cleanup temporary table
mysql ${DATABASE} -e 'DROP TABLE IF EXISTS be_users_shadow;'
