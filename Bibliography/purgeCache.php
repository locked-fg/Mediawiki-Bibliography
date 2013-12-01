<?php

require(dirname(__FILE__)."/../../maintenance/commandLine.inc");
//require("commandLine.inc");

/* copied from maintenance/updaters.inc */
function purge_cache() {
	global $wgDatabase;
        $dbw = wfGetDB( DB_MASTER );
	# We can't guarantee that the user will be able to use TRUNCATE,
	# but we know that DELETE is available to us
	wfOut( "Purging caches..." );
	$dbw->delete( 'objectcache', '*');
	wfOut( "done.\n" );
}
purge_cache();
