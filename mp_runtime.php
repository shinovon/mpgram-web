<?php
if (defined('mp_runtime_loaded')) return;
define('mp_runtime_loaded', true);

use danog\MadelineProto\Logger;

function mpAppInfo(bool $api = false): \danog\MadelineProto\Settings\AppInfo
{
    $app = new \danog\MadelineProto\Settings\AppInfo;
    $app->setApiId(api_id);
    $app->setApiHash(api_hash);
    $app->setShowPrompt(false);
    if ($api) {
        $app->setAppVersion($_SERVER['HTTP_X_MPGRAM_APP_VERSION'] ?? 'api');
        if (isset($_SERVER['HTTP_X_MPGRAM_DEVICE'])) {
            $app->setDeviceModel((string) $_SERVER['HTTP_X_MPGRAM_DEVICE']);
        }
        if (isset($_SERVER['HTTP_X_MPGRAM_SYSTEM'])) {
            $app->setSystemVersion((string) $_SERVER['HTTP_X_MPGRAM_SYSTEM']);
        }
        return $app;
    }
    try {
        if (ini_get('browscap') && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $b = get_browser($ua, true);
            if ($b && isset($b['parent'])) {
                $info = $br = $b['parent'];
                if (isset($b['device_name'])) {
                    $d = $b['device_name'];
                    if ($d !== 'unknown' && !str_starts_with($b['device_name'], 'general')) {
                        if (isset($b['device_brand_name']) && $b['device_brand_name'] !== 'unknown') {
                            $dbn = $b['device_brand_name'];
                            if (!stripos($dbn, 'desktop')) {
                                $d = $dbn.' '.$d;
                            }
                        }
                        $info = $br . ' on ' . $d;
                    }
                }
                $pl = null;
                if (str_contains($ua, 'Opera Mini')) {
                    if (str_contains($ua, 'J2ME/MIDP')) {
                        $pl = 'J2ME';
                    } elseif (str_contains($ua, 'BlackBerry')) {
                        $pl = 'BlackBerry OS';
                    } elseif (str_contains($ua, 'Android')) {
                        $pl = 'Android';
                    }
                } elseif (str_contains($ua, 'Series60/')) {
                    $s60 = substr($ua, strpos($ua, 'Series60/')+9, 3);
                    switch ($s60) {
                    case '3.0':
                        $pl = 'Symbian 9.1';
                        break;
                    case '3.1':
                        $pl = 'Symbian 9.2';
                        break;
                    case '3.2':
                        $pl = 'Symbian 9.3';
                        break;
                    case '5.0':
                        $pl = 'Symbian 9.4';
                        break;
                    case '5.2':
                        $pl = 'Symbian^3';
                        break;
                    case '5.3':
                        $pl = 'Symbian Belle';
                        break;
                    case '5.4':
                        $pl = 'Symbian Belle FP1';
                        break;
                    case '5.5':
                        $pl = 'Symbian Belle FP2';
                        break;
                    default:
                        if (str_contains($ua, 'Symbian/3')) {
                            $pl = 'Symbian^3';
                        } else {
                            $pl = 'Symbian OS';
                        }
                    }
                } elseif (isset($b['platform_description'])) {
                    $pl = $b['platform_description'];
                } elseif (isset($b['platform'])) {
                    $pl = $b['platform'];
                }
                if ($info) {
                    $app->setDeviceModel($info);
                }
                if ($pl) {
                    $app->setSystemVersion($pl);
                }
            }
        }
    } catch (Exception) {}
    $app->setAppVersion('web');
    return $app;
}

function mpSettings(bool $api = false): \danog\MadelineProto\Settings
{
    $sets = new \danog\MadelineProto\Settings;
    $sets->setAppInfo(mpAppInfo($api));

    $logger = $sets->getLogger();
    $logger->setType(Logger::FILE_LOGGER);
    $logger->setLevel(Logger::LEVEL_NOTICE);

    $files = $sets->getFiles();
    $files->setUploadParallelChunks(8);
    $files->setDownloadParallelChunks(8);

    $peer = $sets->getPeer();
    $peer->setFullFetch(false);
    $peer->setCacheAllPeersOnStartup(false);

    $c = $sets->getConnection();
    $c->setTimeout(10);
    $c->setMaxMediaSocketCount(3);

    if (!$api) {
        $c->setRetry(false);
        $db = $sets->getDb();
        $db->setEnableMinDb(false);
        $db->setEnableFullPeerDb(false);
        $db->setEnablePeerInfoDb(!defined('ENABLE_PEER_DB') || ENABLE_PEER_DB);
    }

    return $sets;
}

function mpCleanupWorkers(): void
{
    if (WINDOWS || !function_exists('exec')) return;
    $marker = __DIR__.DIRECTORY_SEPARATOR.'lastclean';
    $last = 0;
    try {
        $last = (int) file_get_contents($marker);
    } catch (Exception) {}
    if ($last && time() - $last < 1800) return;
    try {
        file_put_contents($marker, time());
    } catch (Exception) {}
    $pattern = 'MadelineProto worker '.sessionspath;
    $cmd = "pids=\$(ps -eo pid,args | grep -v grep | grep -F ".escapeshellarg($pattern)." | awk '{print \$1}'); if [ -n \"\$pids\" ]; then kill -TERM \$pids; sleep 5; kill -KILL \$pids 2>/dev/null; fi";
    exec('('.$cmd.') > /dev/null 2>&1 &');
}
