<?php
define('api_id', 0);
define('api_hash', null);

if(api_id == 0) {
	throw new Exception('api_id is not set!');
}

function getSettings() {
	$sets = new \danog\MadelineProto\Settings;
	$app = new \danog\MadelineProto\Settings\AppInfo;
	$app->setApiId(api_id);
	$app->setApiHash(api_hash);
	$app->setAppVersion('0.1');
	$sets->setAppInfo($app);
	$peer = new \danog\MadelineProto\Settings\Peer;
	$peer->setFullFetch(false);
	$peer->setCacheAllPeersOnStartup(false);
	$sets->setPeer($peer);
	$db = $sets->getDb();
	$db->setEnableMinDb(false);
	$db->setEnableUsernameDb(false);
	$db->setEnableFullPeerDb(false);
	$db->setEnablePeerInfoDb(true);
	return $sets;
}
?>