<?php

defined('DEFAULT_CONTROLLERS_PATH') or
  define('DEFAULT_CONTROLLERS_PATH',
         dirname(__FILE__) . '/../app/controllers/');

define('DEFAULT_CONTROLLER_SUFFIX', '_controller.php');
define('DEFAULT_CONTROLLERS_FORMAT', 'html');

defined('MAGIC_QUOTES_ALREADY_DISABLED') or
  define('MAGIC_QUOTES_ALREADY_DISABLED', FALSE);

defined('FILTER_HTML_TAGS') or define('FILTER_HTML_TAGS', TRUE);
defined('FILTER_BOGUS_UTF8') or define('FILTER_BOGUS_UTF8', TRUE);

defined('JSONP_CB_REQUIRED_SUBSTR') or
  define('JSONP_CB_REQUIRED_SUBSTR', '_JSONP_');

defined('ENABLE_FRAGMENTS_CACHING') or
  define('ENABLE_FRAGMENTS_CACHING', FALSE);

class Route {
    static $routes = array();
    static $action_params = array();
    static $controllers_path = DEFAULT_CONTROLLERS_PATH;
    static $controller_suffix = DEFAULT_CONTROLLER_SUFFIX;

    protected

    static function _examine_splitted_path_component($params) {
        $found_action = FALSE;
        $found_params = array();
        $route = $params['route'];
        $scanned_component = $params['scanned_component'];
        $component = $params['component'];
        if (empty($scanned_component)) {
            if (empty($component)) {
                return array();
            }
            return FALSE;
        }
        if ($scanned_component[0] !== ':') {
            if ($scanned_component !== $component) {
                return FALSE;
            }
        } else {
            $matches = array();
            preg_match('/^:([a-z0-9_-]+)/i', $scanned_component, $matches);
            if (empty($matches[1])) {
                return FALSE;
            }
            $scanned_component_splitted_action =
              explode(';', $scanned_component);
            $component_splitted_action = explode(';', $component);
            if (!empty($scanned_component_splitted_action[1])) {
                if (empty($component_splitted_action[1])) {
                    return FALSE;
                }
                $component_action = $component_splitted_action[1];
                if (empty($scanned_component_splitted_action[1])) {
                    return FALSE;
                }
                $scanned_component_action =
                  $scanned_component_splitted_action[1];
                if ($scanned_component_action !== $component_action) {
                    return FALSE;
                }
                $found_action = $route['action'];
            } elseif (!empty($component_splitted_action[1])) {
                return FALSE;
            }
            $found_params[$matches[1]] = $component_splitted_action[0];
        }
        if (!empty($found_params['action'])) {
            $found_action = $found_params['action'];
        }
        return array('found_action' => $found_action,
                     'found_params' => $found_params);
    }

    static function _examine_splitted_paths($params) {
        $action = FALSE;
        $extra_params = array();
        $splitted_path = $params['splitted_path'];
        $route_splitted_path = $params['route_splitted_path'];
        $route = $params['route'];

        if (sizeof($splitted_path) !== sizeof($route_splitted_path)) {
            return FALSE;
        }
        $i = 0;
        foreach ($route_splitted_path as $scanned_component) {
            $component = $splitted_path[$i];
            $i++;
            if (($ret = self::_examine_splitted_path_component
                 (array('scanned_component' => $scanned_component,
                        'component' => $component,
                        'route' => $route))
                 ) === FALSE) {
                return FALSE;
            }
            if (!empty($ret['found_action'])) {
                $action = $ret['found_action'];
            }
            if (!empty($ret['found_params'])) {
                $extra_params = array_merge($extra_params,
                                            $ret['found_params']);
            }
        }
        if (empty($action)) {
            $action = $route['action'];
        }
        return array('action' => $action,
                     'extra_params' => $extra_params);
    }

    static function _init_params() {
        $g = $_GET;
        $p = $_POST;
        $f = $_FILES;        
        if (MAGIC_QUOTES_ALREADY_DISABLED === FALSE &&
            get_magic_quotes_gpc()) {
            self::strip_slashes_from_user_data($g);
            self::strip_slashes_from_user_data($p);
        }
        self::$action_params = array_merge(self::$action_params, $g);
        self::$action_params = array_merge(self::$action_params, $p);
        foreach ($_FILES as $param_name => $file) {
            if (!empty($file['tmp_name'])) {
                $file_content = @file_get_contents($file['tmp_name']);
                if (!empty($content)) {
                    self::$action_params[$param_name] = $file_content;
                }
                unset($file_content);                
            }
        }
    }

    static function _handle_put_data_multipart($boundary) {
        $data = file_get_contents("php://input");
        if (empty($data)) {
            return;
        }
        $p = 0;
        while (($bpos = strpos($data, $boundary . "\r\n", $p)) !== FALSE) {
            $p = $bpos + strlen($boundary . "\r\n");
            if (preg_match('~^Content-Disposition:\s*form-data;\s*name="(.+?)"~',
                           substr($data, $p), $matches) <= 0 ||
                ($var = $matches[1]) === '') {
                break;
            }
            $p += strlen($matches[0]);
            if (($p = strpos($data, "\r\n\r\n", $p)) === FALSE) {
                break;
            }
            $p += strlen("\r\n\r\n");
            $eop = strpos($data, $boundary, $p);
            if ($eop === FALSE) {
                $value = substr($data, $p);
            } else {
                $value = @substr($data, $p, $eop - $p - 4);
            }
            if (MAGIC_QUOTES_ALREADY_DISABLED === FALSE &&
                get_magic_quotes_gpc()) {
                $_POST[$var] = addslashes($value);
            } else {
                $_POST[$var] = $value;
            }
            $p = $eop;
        }
    }

    static function _handle_put_data_urlencoded() {
        $data = file_get_contents("php://input");
        if (empty($data)) {
            return;
        }
        foreach (explode('&', $data) as $d) {
            @list($var, $value) = explode('=', $d);
            $var = @urldecode($var);
            if (empty($var)) {
                continue;
            }
            if (MAGIC_QUOTES_ALREADY_DISABLED === FALSE &&
                get_magic_quotes_gpc()) {
                $_POST[$var] = addslashes(@urldecode($value));
            } else {
                $_POST[$var] = @urldecode($value);
            }
            $_REQUEST[$var] = $_POST[$var];            
        }
    }

    static function _handle_put_data() {
        if (strcasecmp($_SERVER['REQUEST_METHOD'], 'PUT') !== 0 ||
            ($content_type = @$_SERVER['CONTENT_TYPE']) === '' ||
            @$_SERVER['CONTENT_LENGTH'] <= 0) {
            return FALSE;
        }
        $matches = array();
        if (preg_match('~/x-www-form-urlencoded~',
                       $content_type, $matches) > 0) {
            self::_handle_put_data_urlencoded();
            return TRUE;
        }
        if (preg_match('~^multipart/form-data;\s*boundary=(.+)$~',
                       $content_type, $matches) <= 0 ||
            ($boundary = $matches[1]) === '') {
            return TRUE;
        }
        self::_handle_put_data_multipart($boundary);
        
        return TRUE;
    }

    static function _handle_json_encoded_data() {
        if (empty($_SERVER['CONTENT_TYPE'])) {
            return FALSE;
        }
        $content_type = (string) $_SERVER['CONTENT_TYPE'];
        if (preg_match('~/json($|\\s*;)~', $content_type) <= 0) {
            return FALSE;
        }
        $json_data = file_get_contents("php://input");
        if (empty($json_data)) {
            return TRUE;
        }
        $obj_data = @json_decode($json_data);
        if (empty($obj_data)) {
            return TRUE;
        }
        $data = (array) $obj_data;
        foreach ($data as $var => $value) {
            if (MAGIC_QUOTES_ALREADY_DISABLED === FALSE &&
                get_magic_quotes_gpc()) {
                $_POST[$var] = addslashes($value);
            } else {
                $_POST[$var] = $value;
            }
            $_REQUEST[$var] = $_POST[$var];        
        }
        return TRUE;
    }
    
    static function _output($content_type, $encoded_content) {
        header('Content-Type: ' . $content_type . '; charset=utf-8');
        header('Content-Length: ' . strlen($encoded_content));
        echo $encoded_content;
        flush();
    }

    public

    static function map_connect($params) {
        $extra_params = $params;
        foreach (array('path', 'controller', 'action', 'method') as $key) {
            unset($extra_params[$key]);
        }
        if (empty($params['method'])) {
            $params['method'] = 'GET';
        }
        array_push(self::$routes,
                   array('controller' => $params['controller'],
                         'path' => $params['path'],
                         'splitted_path' => explode('/', $params['path']),
                         'method' => strtoupper($params['method']),
                         'action' => $params['action'],
                         'extra_params' => $extra_params));
    }

    static function map_resources($params) {
        $resource_name = $params['resource'];
        if (empty($params['path_prefix'])) {
            $path_prefix = '/';
        } else {
            $path_prefix = $params['path_prefix'];
        }
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name . '/new',
                 'method' => 'GET',
                 'action' => 'new'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name . '/:id;edit',
                 'method' => 'GET',
                 'action' => 'edit'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name . '/:id',
                 'method' => 'GET',
                 'action' => 'show'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name . '/:id',
                 'method' => 'PUT',
                 'action' => 'update'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name . '/:id',
                 'method' => 'DELETE',
                 'action' => 'delete'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name,
                 'method' => 'POST',
                 'action' => 'create'));
        self::map_connect
          (array('controller' => $resource_name,
                 'path' => $path_prefix . $resource_name,
                 'method' => 'GET',
                 'action' => 'index'));
    }

    static function find_route($params) {
        $response_format = DEFAULT_CONTROLLERS_FORMAT;
        $method = $params['method'];
        $path = $params['path'];
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }
        $matches = array();
        if ((preg_match('/^(.+)[.]([a-z0-9-]+)$/i', $path, $matches)) > 0) {
            $path = $matches[1];
            $response_format = $matches[2];
        }
        unset($matches);
        $splitted_path = explode('/', $path);
        $sizeof_splitted_path = sizeof($splitted_path);

        $ret = FALSE;
        foreach (self::$routes as $route) {
            if (strcasecmp($route['method'], $method) !== 0) {
                continue;
            }
            if (($ret = self::_examine_splitted_paths
                 (array('route' => $route,
                        'splitted_path' => $splitted_path,
                        'route_splitted_path' => $route['splitted_path']))
                 ) === FALSE) {
                continue;
            }
            break;
        }
        if ($ret === FALSE) {
            return FALSE;
        }
        return array('route' => $route,
                     'action' => $ret['action'],
                     'response_format' => $response_format,
                     'extra_params' => $ret['extra_params']);
    }

    static function strip_slashes_from_user_data(&$array) {
        foreach($array as $k => $v) {
            if (is_array($v)) {
                strip_slashes_from_user_data($array[$k]);
                continue;
            }
            $array[$k] = stripslashes($v);
        }
    }

    static function trusted_binary_params($key) {
        if (!isset(self::$action_params[$key])) {
            return NULL;
        }
        return self::$action_params[$key];
    }

    static function trusted_params($key) {
        if (!isset(self::$action_params[$key])) {
            return NULL;
        }
        $value = self::$action_params[$key];
        if (FILTER_BOGUS_UTF8 === TRUE &&
            mb_check_encoding($value, 'UTF-8') === FALSE) {
            return NULL;
        }
        return $value;
    }

    static function params($key) {
        if (!isset(self::$action_params[$key])) {
            return NULL;
        }
        $value = self::$action_params[$key];
        if (FILTER_BOGUS_UTF8 === TRUE &&
            mb_check_encoding($value, 'UTF-8') === FALSE) {
            return NULL;
        }
        if (FILTER_HTML_TAGS === FALSE) {
            return $value;
        }
        $value_ = strtr($value, '<>', '  ');
        if (isset($_SERVER['TEST']) && $value_ !== $value) {
            fatal();
        }
        return trim($value_);
    }    
    
    static function run() {
        if (empty($_SERVER['HTTP_HOST'])) {
            die("\\o/\n");
        }
        $method = $_SERVER['REQUEST_METHOD'];
        $path = '';
        if (isset($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
        }
        $ret = self::find_route(array('method' => $method,
                                      'path' => $path));
        if ($ret === FALSE) {
            @header('HTTP/1.0 404 Not Found');
            die('<h1>404 - Introuvable.</h1>' . "\n");
        }
        if (self::_handle_json_encoded_data() === FALSE) {
            self::_handle_put_data();
        }
        self::_init_params();
        self::$action_params = array_merge(self::$action_params,
                                           $ret['extra_params']);
        $route = $ret['route'];
        $action = $ret['action'];
        $response_format = $ret['response_format'];
        $controller = $route['controller'];
        $controller_file = self::$controllers_path .
          preg_replace('/[^a-z0-9-]/i', '_', $controller) .
          self::$controller_suffix;
        if (file_exists($controller_file) === FALSE) {
            @header('HTTP/1.0 503 Service Unavailable');
            die('<h1>503 - Nonexistent controller.</h1>' . "\n");
        }
        require_once $controller_file;
        if (is_callable(array('Controller', $action)) === FALSE) {
            @header('HTTP/1.0 501 Not implemented');
            die('<h1>501 - Nonexistent action.</h1>' . "\n");
        }
        $ret = TRUE;
        try {
            $ret = call_user_func(array('Controller', $action),
                                  self::params('id'));
        } catch (Exception $e) {
            throw $e;
        }
        if ($ret === FALSE) {
            @header('HTTP/1.0 500 Internal Server Error');
            die('<h1>500 - Action sent a generic error</h1>' . "\n");
        }
        if (is_string($ret)) {
            if (ENABLE_FRAGMENTS_CACHING) {
                $known_fragments = (string) params('_fragments');
                $ret = FragmentCache::crunch($ret, $known_fragments);
            }
            echo $ret;
            return TRUE;
        }
        if (!is_array($ret)) {
            @header('HTTP/1.0 500 Internal Server Error');
            die('<h1>500 - Invalid response type</h1>' . "\n");
        }
        switch (strtolower($response_format)) {
         case 'json':
            self::_output('application/json', json_encode($ret));
            return TRUE;
         case 'jsonp':
            $jsonp_cb = (string) params('jsonp');
            if (strstr($jsonp_cb, JSONP_CB_REQUIRED_SUBSTR) === FALSE ||
                preg_match('/^[a-z_]+[a-z0-9_.]*$/i', $jsonp_cb) <= 0) {
                header('HTTP/1.0 400 Bad Request');
                echo '<h1>400 - Invalid JSON-P callback name</h1>';
                die();
            }
            self::_output('text/javascript',
                          $jsonp_cb . '(' . json_encode($ret) . ');');
            return TRUE;
         case 'html':
            if (empty($_SERVER['PROD'])) {
                self::_output('text/plain', htmlentities(serialize($ret)));
                return TRUE;
            }
            break;
         case 'igb':
            if (function_exists('igbinary_serialize')) {
                self::_output('application/igbinary-serialized',
                              igbinary_serialize($ret));
                return TRUE;
            }
            break;
         case 'phpser':
            self::_output('application/php-serialized', serialize($ret));
            return TRUE;
        }
        header('HTTP/1.0 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>500 - Unknown output format (' .
            htmlentities($response_format) . ').</h1>' . "\n";
        die(htmlentities('[' . $_SERVER['REQUEST_METHOD'] . '] [' .
                         $_SERVER['HTTP_HOST'] . '] [' .
                         $_SERVER['REQUEST_URI'] . ']') . "\n");
        /* NOTREACHED */
        return FALSE;
    }
}

class _FragmentCacheReplacer {
    var $fragments = array();
    
    function __construct($known_fragments) {
        if (empty($known_fragments)) {
            return;
        }
        foreach (explode(' ', $known_fragments) as $fragment) {
            if (empty($fragment)) {
                continue;
            }
            $fragment_array = explode('-', $fragment, 2);
            if (count($fragment_array) < 2) {
                continue;
            }
            list($fragment_name, $fragment_digest) = $fragment_array;
            if (empty($fragment_name) || empty($fragment_digest)) {
                continue;
            }
            $this->fragments[$fragment_name] = $fragment_digest;
        }
    }
    
    function replace_fragment($matches) {
        $fragment_name = $matches[1];
        $fragment_content = $matches[2];
        assert(htmlspecialchars($fragment_name) === $fragment_name);        
        $digest = md5($fragment_content);
        if (isset($this->fragments[$fragment_name]) &&
            $digest === $this->fragments[$fragment_name]) {
            return "<!--cached $fragment_name-$digest-->";
        }
        return "<!--fragment $fragment_name-$digest-->" .
          "$fragment_content<!--/fragment-->";
    }        
}

class FragmentCache {
    const FRAGMENT_RX =
      '#<!--\\s*fragment\\s+(.+?)\\s*-->(.+?)<!--\\s*/fragment\\s*-->#msi';
    
    static function crunch($document, $known_fragments) {
        $obj_fragments = new _FragmentCacheReplacer($known_fragments);
        $crunched_document = preg_replace_callback
          (self::FRAGMENT_RX,
           array($obj_fragments, 'replace_fragment'),
           $document);
        unset($obj_fragments);
        
        return $crunched_document;
    }
}

function params($key) {
    return Route::params($key);
}

function trusted_params($key) {
    return Route::trusted_params($key);
}

function trusted_binary_params($key) {
    return Route::trusted_binary_params($key);
}
