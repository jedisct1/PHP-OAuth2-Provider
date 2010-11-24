<?php

require_once SHARED_LIB_DIR . '/Smarty/Smarty.class.php';

class SkySmarty extends Smarty {
    public function __construct() {
        parent::__construct();
        $user_id = '-';
        if (Auth::is_logged() === TRUE) {
            $user_id = Auth::get_user_id();
            assert($user_id !== '-');
        }
        assert(strchr($user_id, "\r") === FALSE);
        assert(strchr($user_id, "\n") === FALSE);
        assert(trim($user_id) === $user_id);
        if (headers_sent() === FALSE) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: deny');
            header('X-Content-Security-Policy: ' .
                   'allow ' . STATIC_URL . ';' .
                   'options inline-script eval-script;' .
                   'script-src ' . STATIC_URL . ' ' .
                   'object-src ' . DYNAMIC_URL . ' ' . STATIC_URL . ';' .
                   'img-src *;' .
                   'frame-src none;' .
                   'frame-ancestors none;' .
                   'xhr-src self ' . DYNAMIC_URL . ' ' . STATIC_URL . ' ' . COMET_URL . ';');
            header('Vary: X-User-ID');
            header('X-User-ID: ' . $user_id);
            if (!empty($_SERVER) && !empty($_SERVER['SERVER_ADDR'])) {
                header('X-Backend: ' . substr(md5($_SERVER['SERVER_ADDR']), 0, 4));
            }
        }
        if (empty($_SERVER['PROD'])) {
            $this->setDebugging(TRUE);
            $this->setForceCompile(TRUE);
        } else {
            $this->setDebugging(FALSE);
        }
        $this->setErrorReporting(E_ALL & ~ (E_USER_NOTICE | E_NOTICE));
        $this->setCaching(FALSE);
        $this->setTemplateDir(APP_SMARTY_TEMPLATES_DIR);
        $this->setCompileDir(APP_SMARTY_COMPILE_DIR);
        $this->setConfigDir(APP_SMARTY_CONFIG_DIR);
        $this->addPluginsDir(APP_SMARTY_PLUGINS_DIR);

        $this->config_vars['STATIC_URL'] = htmlspecialchars(STATIC_URL);
        $this->config_vars['DYNAMIC_URL'] = htmlspecialchars(DYNAMIC_URL);
        $this->config_vars['JS_VERSION'] = htmlspecialchars(JS_VERSION);
        $this->config_vars['CSS_VERSION'] = htmlspecialchars(CSS_VERSION);
        $this->config_vars['IMAGES_VERSION'] = htmlspecialchars(IMAGES_VERSION);
        $this->config_vars['DEBUG_MODE'] = (bool) !empty($_COOKIE['debug']);

        $this->assign('current_uri', Utils::get_current_uri());

        return $this;
    }
}
