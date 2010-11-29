<?php

require_once APP_LIB_DIR . '/class.api-m_oauth_helper.inc.php';

class Controller {
    static function authorize() {
        $ret = ApiMOAuthHelper::authorize();
        if (!is_string($ret) || empty($ret)) {
            Utils::redirect_to(IDENT_REQUIRED_URL);
        }
        return $ret;
    }
    
    static function access_token() {
        return ApiMOAuthHelper::access_token();
    }

    static function logout() {
        return ApiMOAuthHelper::logout();
    }
}
