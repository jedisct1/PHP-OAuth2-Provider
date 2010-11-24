<?php

define('UUID_KEY_LENGTH', 18);
define('CURL_DEFAULT_TIMEOUT', 10);

class GenericUtils {
    protected static function _cb_clean_uri_path_component($prx) {
        $component = $prx[1];
        return '/' . rawurlencode($component);
    }

    static function uri_clean($uri) {
        $matches = NULL;
        if (empty($uri)) {
            return $uri;
        }
        $ta = parse_url($uri);
        if (empty($ta)) {
            return $uri;
        }
        $scheme = $host = $path = $query = $fragment = '';
        if (!empty($ta['scheme'])) {
            $scheme = $ta['scheme'] . '://';
        }
        if (!empty($ta['host'])) {
            $host = $ta['host'];
        }
        if (!empty($ta['path'])) {
            $path = $ta['path'];
        }
        if (!empty($ta['query'])) {
            $query = '?' . $ta['query'];
        }
        if (!empty($ta['fragment'])) {
            $fragment = '#' . $ta['fragment'];
        }
        $fixed_path = preg_replace_callback
          ('#/([^/?]+)#Dus',
           'Utils::_cb_clean_uri_path_component', $path);
        $clean_uri = $scheme . $host . $fixed_path . $query . $fragment;

        return $clean_uri;
    }

    static function get_current_uri() {
        if (empty($_SERVER['HTTP_HOST'])) {
            die("command-line\n");
        }
        return 'http://' . rawurlencode($_SERVER['HTTP_HOST']) .
          $_SERVER['REQUEST_URI'];
    }

    static function expand_to_current_uri($uri_or_url) {
        if (empty($uri_or_url) || $uri_or_url[0] !== '#') {
            return $uri_or_url;
        }
        $tail = substr($uri_or_url, 1);
        $current_uri = Utils::get_current_uri();
        if (strchr($current_uri, '?') === FALSE ||
            empty($tail) || $tail[0] !== '?') {
            return $current_uri . $tail;
        }
        return $current_uri . '&' . substr($tail, 1);
    }

    static function redirect_to($uri_or_url_or_params) {
        if (empty($_SERVER['HTTP_HOST'])) {
            die("command-line\n");
        }
        if (is_array($uri_or_url_or_params)) {
            if (empty($uri_or_url_or_params['uri'])) {
                throw new \Exception('Missing uri');
            }
            $uri_or_url = $uri_or_url_or_params['uri'];
        } elseif (is_string($uri_or_url_or_params)) {
            $uri_or_url = $uri_or_url_or_params;
        } else {
            throw new \Exception('Bad type for uri_or_url_or_params');
        }
        $uri_or_url = Utils::expand_to_current_uri($uri_or_url);
        if (strstr($uri_or_url, '://') !== FALSE) {
            $uri = $uri_or_url;
        } elseif ($uri_or_url[0] === '/') {
            $uri = 'http://' . rawurlencode($_SERVER['HTTP_HOST']) .
              $uri_or_url;
        }
        if (empty($uri)) {
            throw new \Exception('Empty URI');
        }
        if (!empty($_SERVER['HTTP_REFERER']) &&
            strcmp($_SERVER['HTTP_REFERER'], $uri) === 0) {
            sleep(1);
        }
        if (!headers_sent()) {
            header('Location: ' . $uri);
            exit;
        }
        echo
          '<script type="text/javascript">' . "\n" .
          '// <![CDATA[' . "\n" .
          'window.location.href = "' . addslashes($uri) . '";' . "\n" .
          '// ]]>' . "\n" .
          '</script>' . "\n";
        exit;
    }

    static function construct_uri_from_base_uri_and_args($params) {
        if (empty($params['uri'])) {
            throw new \Exception('Empty URI');
        }
        $uri = Utils::expand_to_current_uri($params['uri']);
        if (strchr($uri, '?') === FALSE) {
            $first_param = TRUE;
        } else {
            $first_param = FALSE;
        }
        foreach ($params as $key => $value) {
            if ($key === 'uri') {
                continue;
            }
            if ($first_param === TRUE) {
                $uri .= '?';
                $first_param = FALSE;
            } else {
                $uri .= '&';
            }
            $uri .= urlencode($key) . '=' . urlencode($value);
        }
        return $uri;
    }

    static function redirect_to_with_args($params) {
        Utils::redirect_to(Utils::construct_uri_from_base_uri_and_args($params));
    }

    static function http_nocache() {
        header('Cache-Control: no-store, private, must-revalidate, ' .
               'proxy-revalidate, ' .
               'post-check=0, pre-check=0, max-age=0, s-maxage=0');
        header('Pragma: no-cache');
    }

    static function http_cache($duration) {
        $now = time();
        $last_modified_ts = $now;
        $expires_ts = $last_modified_ts + $duration;

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $ims_date = @strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if (!empty($ims_date) && $ims_date <= $now &&
                $now - $ims_date < $duration) {
                header('HTTP/1.1 304 Not Modified');
                exit(0);
            }
        }
        header('Cache-Control: private, must-revalidate, proxy-revalidate, ' .
               'max-age=' . $duration . ', ' .
               's-max-age=' . $duration . ', ' .
               'stale-while-revalidate=' . $duration . ', ' .
               'stale-if-error=86400');
        header('Last-Modified: ' . date('r', $last_modified_ts));
        header('Expires: ' . date('r', $expires_ts));
        header('Pragma: cache');
    }

    static function curl_simple_wrapper($method, $url, $data = array(),
                                        $timeout = CURL_DEFAULT_TIMEOUT,
                                        $headers = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_DEFAULT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, CURL_DEFAULT_TIMEOUT);
        $headers += array('Expect' => '');
        $curl_headers = array();
        foreach ($headers as $header_name => $header_property) {
            array_push($curl_headers, $header_name . ':' . $header_property);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        switch (strtoupper($method)) {
         case 'GET':
            break;
         case 'POST':
         case 'PUT':
            if (is_string($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
         default:
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        $ret = curl_exec($ch);
        $status = curl_getinfo($ch);
        curl_close($ch);
        if ($status['http_code'] < 200 || $status['http_code'] >= 300) {
            return FALSE;
        }
        return $ret;
    }

    static function secure_strings_are_equal($a, $b) {
        $la = strlen($a);
        if ($la !== strlen($b) || $la <= 0) {
            return FALSE;
        }
        $res = 0;
        do {
            $la--;
            $res |= ord($a[$la]) ^ ord($b[$la]);
        } while ($la != 0);

        return $res === 0;
    }

    static function anti_xsrf() {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            return;
        }
        if (empty($_SERVER['HTTP_HOST'])) {
            die("command-line\n");
        }
        if (empty($_POST) || empty($_SERVER['HTTP_REFERER']) ||
            preg_match('#^http(s)?://([^/]+[.])?(' .
                       preg_quote($_SERVER['HTTP_HOST']) .
                       '|' . AUTHORIZED_XSRF_DOMAINS .
                       ')($|/)#i', $_SERVER['HTTP_REFERER']) > 0) {
            return;
        }
        foreach (array_keys($_POST) as $k) {
            unset($_REQUEST[$k]);
        }
        $_POST = array();
    }

    static function uuid() {
        $cstrong = TRUE;
        return trim(strtr
                    (base64_encode
                     (openssl_random_pseudo_bytes(UUID_KEY_LENGTH,
                                                  $cstrong)), '+/=', '-. ')
                    );
    }
}

if (!function_exists('N_')) {
    function gettext_noop($str) {
        return $str;
    }

    function N_($str) {
        return $str;
    }
}

