<?php

define('OAUTH_DIGEST_METHOD', 'sha256');
define('OAUTH_VALIDATION_SCOPE', 'vSP');
define('OAUTH_VALIDATION_SECRET_KEY', '<CHANGE THIS>');
define('OAUTH_CODE_EXPIRATION', 5 * 60);
define('OAUTH_REFRESH_TOKEN_SCOPE', 'rSP');
define('OAUTH_REFRESH_TOKEN_SECRET_KEY', '<CHANGE THIS>');
define('OAUTH_REFRESH_TOKEN_EXPIRATION', 365 * 86400);
define('OAUTH_ACCESS_TOKEN_SCOPE', 'aSP');
define('OAUTH_ACCESS_TOKEN_SECRET_KEY', '<CHANGE THIS>');
define('OAUTH_ACCESS_TOKEN_EXPIRATION', 1 * 86400);
define('OAUTH_GLUE', '=');

class OAuth {
    static function get_validation_code_digest($user_id, $user_name, $expires_at, $client_id, $redirect_uri) {
        self::check_redirect_uri($client_id, $redirect_uri);
        return hash_hmac(OAUTH_DIGEST_METHOD,
                         OAUTH_VALIDATION_SCOPE . OAUTH_GLUE .
                         $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE .
                         $expires_at . OAUTH_GLUE .
                         $client_id . OAUTH_GLUE . $redirect_uri,
                         OAUTH_VALIDATION_SECRET_KEY);
    }

    static function get_client_secret($client_id) {
        $apps = self::get_apps();
        if (empty($apps[$client_id])) {
            throw new \Exception('App to be accessed isn\'t registered any more');
        }
        return $apps[$client_id]['okey'];
    }

    static function get_refresh_token_digest($user_id, $user_name, $refresh_token_expires_at, $client_id) {
        $client_secret = self::get_client_secret($client_id);

        return hash_hmac(OAUTH_DIGEST_METHOD,
                         OAUTH_REFRESH_TOKEN_SCOPE . OAUTH_GLUE .
                         $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE . $refresh_token_expires_at . OAUTH_GLUE .
                         $client_id . OAUTH_GLUE . $client_secret,
                         OAUTH_REFRESH_TOKEN_SECRET_KEY);
    }

    static function get_access_token_digest($user_id, $user_name, $access_token_expires_at, $client_id) {
        $client_secret = self::get_client_secret($client_id);

        return hash_hmac(OAUTH_DIGEST_METHOD,
                         OAUTH_ACCESS_TOKEN_SCOPE . OAUTH_GLUE .
                         $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE . $access_token_expires_at . OAUTH_GLUE .
                         $client_id . OAUTH_GLUE . $client_secret,
                         OAUTH_ACCESS_TOKEN_SECRET_KEY);
    }

    static function get_apps() {
        $ret = array('app_name_1' =>
                     array('okey' => '<CHANGE THIS (CAN BE EMPTY)>',
                           'uris_rx' => array('#^customappurl://#',
                                              '#^http://www[.]example[.]com(/|$)#')),
                     
                     'app_name_2' =>
                     array('okey' => '<CHANGE THIS (CAN BE EMPTY)>',
                           'uris_rx' => array('#^http://www[.]example[.]net(/|$)#')),                     
                     );
        }
        return $ret;
    }

    static function check_redirect_uri($client_id, $redirect_uri) {
        $apps = self::get_apps();
        if (empty($apps[$client_id])) {
            require_once SHARED_LIB_DIR . '/class.scribe.inc.php';
            Scribe::log('security', array('event' => 'unregistered oauth client',
                                          'event_data' => array
                                          ('client_id' => $client_id,
                                           'redirect_uri' => $redirect_uri)));
            throw new \Exception('Unregistered client');
        }
        $app = $apps[$client_id];
        foreach ($app['uris_rx'] as $uri_rx) {
            if (preg_match($uri_rx, $redirect_uri)) {
                return TRUE;
            }
        }
        require_once SHARED_LIB_DIR . '/class.scribe.inc.php';
        Scribe::log('security', array('event' => 'unauthorized oauth redirection URI',
                                      'event_data' => array
                                      ('client_id' => $client_id,
                                       'redirect_uri' => $redirect_uri)));
        throw new \Exception('Unauthorized');
    }
}
