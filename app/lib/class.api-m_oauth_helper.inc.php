<?php

require_once SHARED_LIB_DIR . '/class.sky_smarty.inc.php';
require_once SHARED_LIB_DIR . '/class.scribe.inc.php';
require_once APP_LIB_DIR . '/class.oauth.inc.php';

class ApiMOAuthHelper {
    protected static function _get_form($obj_smarty, $user_name, $user_password, $flash,
                                        $redirect_uri, $client_id, $facebook_login_uri,
                                        $signon_sky_uri) {
        OAuth::check_redirect_uri($client_id, $redirect_uri);
        $wanted_form_check = Utils::get_csrf_token('oauth_login_form', FALSE);        
        $obj_smarty->assign('form_check', $wanted_form_check);
        $obj_smarty->assign('user_name', $user_name);
        $obj_smarty->assign('user_password', $user_password);
        $obj_smarty->assign('oauth_redirect_uri', $redirect_uri);
        $obj_smarty->assign('oauth_client_id', $client_id);
        $obj_smarty->assign('facebook_login_uri', $facebook_login_uri);
        $obj_smarty->assign('signon_sky_uri', $signon_sky_uri);
        $obj_smarty->assign('flash', $flash);

        return $obj_smarty->fetch('api-m/authorize.tpl');
    }

    static function _view_form($obj_smarty, &$user_id, &$user_name, $facebook_login_uri, $signon_sky_uri,
                               $client_id, $redirect_uri) {
        $user_id = NULL;
        $user_name = NULL;

        header('Content-Type: text/html; charset=UTF-8');
        $user_name = trim((string) params('user_name'));
        $user_password = trim((string) params('user_password'));
        $form_check = (string) params('form_check');
        assert(!empty($facebook_login_uri));
        $flash = array();
        if (empty($redirect_uri)) {
            return array('return_code' => -1,
                         'error_message' => 'Missing redirect URI');
        }
        if (empty($client_id)) {
            return array('return_code' => -1,
                         'error_message' => 'Missing client ID');
        }
        if (empty($form_check)) {
            return self::_get_form($obj_smarty, $user_name, $user_password, $flash,
                                   $redirect_uri, $client_id, $facebook_login_uri,
                                   $signon_sky_uri);
        }
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') !== 0) {
            return array('return_code' => -1,
                         'error_message' => 'Unsupported method');
        }
        if (Utils::check_csrf_token((string) $form_check, 'oauth_login_form', FALSE) !== TRUE) {
            return array('return_code' => -1,
                         'error_message' => 'Form check mismatch');
        }
        if (empty($user_name)) {
            $flash['user_name'] = 'Missing username';
        }
        if (empty($user_password)) {
            $flash['user_password'] = 'Missing password';
        }
        if (!empty($flash)) {
            return self::_get_form($obj_smarty, $user_name, $user_password, $flash,
                                   $redirect_uri, $client_id, $facebook_login_uri,
                                   $signon_sky_uri);
        }
        $user_id = NULL;
        try {
            if (Auth::connect_with_skyrock_account($user_name, $user_password) === TRUE) {
                $user_id = Auth::get_user_id();
                $user_name_ = Auth::get_user_name();
                assert(strcasecmp($user_name_, $user_name) === 0);
                $user_name = $user_name_;
            }
        } catch (\Exception $e) { }
        if (empty($user_id) || empty($user_name)) {
            $flash['form'] = 'The username or password is incorrect';

            return self::_get_form($obj_smarty, $user_name, $user_password, $flash,
                                   $redirect_uri, $client_id, $facebook_login_uri,
                                   $signon_sky_uri);
        }
        return NULL;
    }

    static function authorize() {
        Utils::http_nocache();
        $type = (string) params('type');
        $redirect_uri = (string) params('redirect_uri');
        $client_id = (string) params('client_id');
        $state = (string) params('state');
        if (empty($type)) {
            $type = 'user_agent';
        }
        $display = NULL;
        if (!empty($_SERVER['HTTP_USER_AGENT']) &&
            preg_match('/Apple.+Mobile/', $_SERVER['HTTP_USER_AGENT']) > 0) {
            $display = 'touch';
        }
        if (empty($redirect_uri)) {
            return array('return_code' => -1,
                         'error_message' => 'Missing redirect URI');
        }
        if (empty($client_id)) {
            return array('return_code' => -1,
                         'error_message' => 'Missing client ID');
        }
        try {
            OAuth::get_client_secret($client_id);
        } catch (\Exception $e) {
            return array('return_code' => -1,
                         'error_message' => 'Unregistered application');
        }
        $oauth_redirect_uri = $redirect_uri;
        OAuth::check_redirect_uri($client_id, $oauth_redirect_uri);
        if (!empty($state)) {
            $oauth_redirect_uri = Utils::construct_uri_from_base_uri_and_args
              (array('uri' => $oauth_redirect_uri,
                     'state' => $state));
        }
        $facebook_redirect_uri = Utils::construct_uri_from_base_uri_and_args
          (array('uri' => FBLINK_VERIFY_URL,
                 'oauth_client_id' => $client_id,
                 'oauth_redirect_uri' => $oauth_redirect_uri));
        $args = array('uri' => FACEBOOK_AUTHORIZE_URI_BASE,
                      'redirect_uri' => $facebook_redirect_uri);
        if (!empty($display)) {
            $args['display'] = $display;
        }
        $facebook_login_uri = Utils::construct_uri_from_base_uri_and_args($args);
        $args = array('uri' => '/auth.php/signon-sky.html',
                      'oauth_client_id' => $client_id,
                      'oauth_redirect_uri' => $oauth_redirect_uri);
        if (!empty($display)) {
            $args['display'] = $display;
        }
        $signon_sky_uri = Utils::construct_uri_from_base_uri_and_args($args);
        $user_id = NULL;
        $user_name = NULL;
        if (!Auth::is_logged()) {
            $obj_smarty = new SkySmarty();
            $res = self::_view_form($obj_smarty, $user_id, $user_name, $facebook_login_uri, $signon_sky_uri,
                                    $client_id, $oauth_redirect_uri);
            if (!empty($res)) {
                return $res;
            }
        } else {
            $user_id = Auth::get_user_id();
            $user_name = Auth::get_user_name();
        }
        assert($user_id !== NULL);
        assert(!empty($user_name));
        $expires_at = time() + OAUTH_CODE_EXPIRATION;
        $validation_code =
          OAUTH_VALIDATION_SCOPE . OAUTH_GLUE . $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE .
          $expires_at . OAUTH_GLUE .
          OAuth::get_validation_code_digest($user_id, $user_name, $expires_at, $client_id, $redirect_uri);
        $args = array('uri' => $redirect_uri,
                      'code' => $validation_code,
                      'expires_in' => OAUTH_CODE_EXPIRATION);
        if (!empty($state)) {
            $args['state'] = $state;
        }
        Utils::redirect_to_with_args($args);
    }

    static function access_token() {
        Utils::http_nocache();
        $now = time();
        $type = (string) params('type');
        $state = (string) params('state');
        $client_id = (string) params('client_id');
        $redirect_uri = (string) params('redirect_uri');
        $code = (string) params('code');
        $refresh_token = (string) params('refresh_token');
        $client_secret = (string) params('client_secret');

        if (empty($type)) {
            $type = 'web_server';
        }
        if (strcasecmp($type, 'web_server') === 0) {
            if (empty($redirect_uri)) {
                return array('error' => 'redirect_uri_mismatch');
            }
            if (empty($code)) {
                return array('error' => 'bad_verification_code');
            }
        } else if (strcasecmp($type, 'refresh') === 0) {
            $code = '';
            $redirect_uri = '';
        }
        if (empty($client_id)) {
            return array('error' => 'incorrect_client_credentials');
        }
        try {
            $wanted_client_secret = OAuth::get_client_secret($client_id);
            if (Utils::secure_strings_are_equal($wanted_client_secret, $client_secret) !== TRUE) {
                throw new \Exception('Invalid client secret');
            }
        } catch (\Exception $e) {
            return array('error' => 'incorrect_client_credentials');
        }
        if (strcasecmp($type, 'web_server') === 0) {
            @list($scope, $user_id, $user_name, $expires_at, $digest) = explode(OAUTH_GLUE, $code);
            if ($scope !== OAUTH_VALIDATION_SCOPE) {
                return array('error' => 'incorrect_client_credentials');
            }
        } else if (strcasecmp($type, 'refresh') === 0) {
            @list($scope, $user_id, $user_name, $expires_at, $digest) = explode(OAUTH_GLUE, $refresh_token);
            if ($scope !== OAUTH_REFRESH_TOKEN_SCOPE) {
                return array('error' => 'incorrect_client_credentials');
            }
        } else {
            return array('error' => 'incorrect_client_credentials');
        }
        if (empty($user_id) || empty($user_name) || empty($expires_at) || empty($digest)) {
            return array('error' => 'incorrect_client_credentials');
        }
        if ($now > $expires_at) {
            return array('error' => 'code_expired');
        }
        try {
            if (strcasecmp($type, 'web_server') === 0) {
                $wanted_digest =
                  OAuth::get_validation_code_digest($user_id, $user_name, $expires_at, $client_id, $redirect_uri);
            } else if (strcasecmp($type, 'refresh') === 0) {
                $wanted_digest = OAuth::get_refresh_token_digest($user_id, $user_name, $expires_at, $client_id);
            } else {
                throw new \Exception('Unsupported type');
            }
        } catch (\Exception $e) {
            return array('error' => 'incorrect_client_credentials');
        }
        if (Utils::secure_strings_are_equal($digest, $wanted_digest) !== TRUE) {
            return array('error' => 'bad_verification_code');
        }
        $access_token_expires_at = $now + OAUTH_ACCESS_TOKEN_EXPIRATION;
        $access_token = OAUTH_ACCESS_TOKEN_SCOPE . OAUTH_GLUE . $client_id . OAUTH_GLUE .
          $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE . $access_token_expires_at . OAUTH_GLUE .
          OAuth::get_access_token_digest($user_id, $user_name, $access_token_expires_at, $client_id);

        $refresh_token_expires_at = $now + OAUTH_REFRESH_TOKEN_EXPIRATION;
        $refresh_token = OAUTH_REFRESH_TOKEN_SCOPE . OAUTH_GLUE .
          $user_id . OAUTH_GLUE . $user_name . OAUTH_GLUE . $refresh_token_expires_at . OAUTH_GLUE .
          OAuth::get_refresh_token_digest($user_id, $user_name, $refresh_token_expires_at, $client_id);

        $args = array('access_token' => $access_token,
                      'refresh_token' => $refresh_token,
                      'expires_in' => OAUTH_ACCESS_TOKEN_EXPIRATION);
        if (!empty($state)) {
            $args['state'] = $state;
        }
        Scribe::log('accounts', array('event' => 'logged in through oauth',
                                      'event_data' =>
                                      array('client_id' => $client_id,
                                            'type' => $type)));
        return $args;
    }
    
    static function logout() {
        $oauth_client_id = Auth::get_oauth_client_id();
        if (empty($oauth_client_id)) {
            return array('return_code' => -1,
                         'error_message' => 'Forbidden');
        }
        Auth::logout();
        Scribe::log('accounts', array('event' => 'logged out through oauth',
                                      'event_data' =>
                                      array('client_id' => $oauth_client_id))
                    );
        return array('return_code' => 1,
                     'error_message' => '');
    }
}
