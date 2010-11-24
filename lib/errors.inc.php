<?php

define('_CUSTOM_ERROR_HANDLER_ANTIBOMB_FILE_PREFIX',
       '/tmp/custom-error-handler-antibomb-');
define('_CUSTOM_ERROR_HANDLER_ANTIBOMB_INTERVAL', 300);
define('ONERROR_REPORT_EVEN_IGNORED', FALSE);
define('ONERROR_REPORT_EVEN_NOTICES', TRUE);
define('ONERROR_SHOW', TRUE);
define('ONERROR_URI_TEMPORARY', '/');
define('ERRORNOT_ENABLED', FALSE);
define('ERRORNOT_URI', 'http://errornot.example.com');
define('ERRORNOT_API_KEY', '<CHANGE ME>');

$custom_error_handler_redirection = null;

function _custom_error_handler_antibomb() {
    $now = time();

    $antibomb_file = _CUSTOM_ERROR_HANDLER_ANTIBOMB_FILE_PREFIX;
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $antibomb_file .= md5($_SERVER['SCRIPT_FILENAME']);
    }
    if (($st = @stat($antibomb_file)) === FALSE ||
        ($mtime = $st['mtime']) <= 0 || $mtime > $now ||
        ($now - $mtime) > _CUSTOM_ERROR_HANDLER_ANTIBOMB_INTERVAL) {
        @touch($antibomb_file);
        return 0;
    }
    return -1;
}

function _custom_error_handler_html($errno, $errstr, $errfile, $errline,
                                    $trace, $bt_dump, $server_dump) {
    $ret = <<<EOF
      
<!doctype html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>
    Erreur
  </title>
  <style type="text/css">
/* <![CDATA[ */
      
#error-handler {
  border: 4px solid #000000;
  padding: 0 1em;
}

p {
  font-size: 1.25em;
  font-weight: bold;
}

var {
  font-family: courier;
  font-style: normal;
  font-weight: bold;
}

#callchain {
  font-size: 1.25em;
  text-align: center;
  border: 1px solid #000000;
}

pre {
  font-size: 0.9em;
}

/* ]]> */      
  </style>
</head>
<body>
  
EOF
      ;
    $ret .= '  <div id="error-handler">' . "\n";
    $ret .= '    <h1>' . "\n";
    $ret .= '      Erreur ['. htmlentities($errno) . '] :<br />' . "\n";
    $ret .= '      ' . htmlentities($errstr) . "\n";
    $ret .= '    </h1>' . "\n";
    $ret .= '    <div>' . "\n";
    $ret .= '      <h2>Location</h2>' . "\n";
    $ret .= '      <p>' . "\n";
    $ret .= '        File: <var>' . htmlentities($errfile) . '</var>' .
      '<br />' . "\n";
    $ret .= '        Line: <var>' . htmlentities($errline) . '</var>' . "\n";
    $ret .= '      </p>' . "\n";
    $ret .= '    </div>' . "\n";
    $ret .= '    <div>' . "\n";
    $ret .= '      <h2>Calls chain</h2>' . "\n";
    $ret .= '      <p id="callchain">' . "\n";    
    $ret .= '        ' . htmlentities($trace) . "\n";
    $ret .= '      </p>' . "\n";
    $ret .= '    </div>' . "\n";
    $ret .= '    <div>' . "\n";
    $ret .= '      <h2>Stack trace</h2>' . "\n";
    $ret .= '      <pre>' . "\n";    
    $ret .= htmlentities($bt_dump);
    $ret .= '      </pre>' . "\n";
    $ret .= '    </div>' . "\n";
    $ret .= '    <div>' . "\n";
    $ret .= '      <h2>Environment</h2>' . "\n";
    $ret .= '      <pre>' . "\n";
    $ret .= htmlentities($server_dump);
    $ret .= '      </pre>' . "\n";
    $ret .= '    </div>' . "\n";
    $ret .= '  </div>' . "\n";
    $ret .= <<<EOF
</body>      
</html>

EOF
      ;

    return $ret;
}

function custom_error_handler_($errno, $errstr, $errfile, $errline, $bt = NULL)
{
    global $custom_error_handler_redirection;

    if (($errno == E_NOTICE || $errno == E_USER_NOTICE) &&
        ONERROR_REPORT_EVEN_NOTICES == FALSE) {
        return;
    }
    if (($errno & error_reporting()) === 0 && 
        ONERROR_REPORT_EVEN_IGNORED == FALSE) {
        return;
    }
    if ((ONERROR_SHOW | ERRORNOT_ENABLED) == FALSE) {
        return;
    }
    if (function_exists('skycache_no_store')) {
        skycache_no_store();
    }
    $trace = '';
    if (empty($bt)) {
        $bt = debug_backtrace();
        array_shift($bt);
    }
    foreach ($bt as $t) {
        if (!is_array($t) || !isset($t['function'])) {
            continue;
        }
        $trace = $t['function'] . '() -> ' . $trace;
    }
    $trace .= '*KABOOM*';
    $bt_dump = addcslashes(print_r($bt, TRUE), "\\\0\r");
    $server_dump = addcslashes(print_r($_SERVER, TRUE), "\\\0\r");
    $html = _custom_error_handler_html
      ($errno, $errstr, $errfile, $errline, $trace, $bt_dump, $server_dump);
    if (ONERROR_SHOW != FALSE) {
        print $html;
    } else {
        if (empty($custom_error_handler_redirection)) {
            $custom_error_handler_redirection = ONERROR_URI_TEMPORARY;
        }
        if (isset($_SERVER['HTTP_HOST']) &&
            preg_match('|^\w+://.|', $custom_error_handler_redirection) <= 0) {
            $custom_error_handler_redirection = 'http://' .
              rawurlencode($_SERVER['HTTP_HOST']) . $custom_error_handler_redirection;
        }
        @header('Location: ' . $custom_error_handler_redirection);
        print <<<EOF
          
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
               "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

EOF
          ;
        print '  <meta http-equiv="Refresh" content="0; url=' .
          $custom_error_handler_redirection . '">' . "\n";
        print <<<EOF

  <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15" />
  <title>
    ...
  </title>
</head>
<body>
  <div>
    ...          
  </div>
</body>
</html>

EOF
          ;
    }
    if (_custom_error_handler_antibomb() !== 0) {
        die();
    }

    if (ERRORNOT_ENABLED !== TRUE) {
        die();
    }
    require_once 'HTTP/Request2.php';
    require_once APP_LIB_DIR . '/ErrorNot/errornot.php';
    
    $errornot = new Services_ErrorNot(ERRORNOT_URI, ERRORNOT_API_KEY);
    
    $en_bt = '<h1>' . nl2br(htmlspecialchars($errstr)) . '</h1>';
    $en_bt .= '<ul>';
    
    foreach ($bt as $trace) {
        if (empty($trace['file']) || empty($trace['line'])) {
            continue;
        }
        if (strstr(__FILE__, $trace['file']) !== FALSE) {
            continue;
        }
        $en_bt .= '<li>';
        $en_bt .=
          '<b>File:</b> ' . nl2br(htmlspecialchars($trace['file']) . "\n") .
          '<b>Line:</b> ' . nl2br(htmlspecialchars($trace['line']) . "\n");
        if ($trace['function'] !== 'custom_error_handler') {
            $en_bt .= '<b>Function:</b> ' .
              nl2br(htmlspecialchars($trace['function']) . "()\n");
        }
        $en_bt .= '</li>';
    }
    $en_bt .= '</ul>';
    $en_bt = str_replace("\n", '', $en_bt);
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $uri = 'http://' . rawurlencode($_SERVER['HTTP_HOST']) .
          $_SERVER['REQUEST_URI'];
    } else {
        $uri = '(commandline)';
    }
    $errornot->notify(substr($errstr, 0, 100), NULL, $en_bt,
                      array('request' =>
                            array('uri' => $uri,
                                  'method' => @$_SERVER['REQUEST_METHOD'],
                                  'post' => $_POST, 'get' => $_GET,
                                  'cookies' => $_COOKIE),
                            'params' =>
                            array('svn_user' => @$_SERVER['SVN_USER'])
                            ),
                      $_SERVER);
    die();
}

function custom_error_handler($errno, $errstr, $errfile, $errline) {
    return custom_error_handler_($errno, $errstr, $errfile, $errline);
}

function custom_error_handler_set_redirect($url) {
    global $custom_error_handler_redirection;
    
    $custom_error_handler_redirection = $url;
}

function fatal($message = 'Incoherence') {
    trigger_error($message, E_USER_ERROR);
    die();
}

function show_or_mail_exception($e) {
    if (!($e instanceof Exception)) {
        fatal();
    }
    custom_error_handler_(E_USER_ERROR,
                          get_class($e) . ': ' . $e->getMessage(),
                          $e->getFile(), $e->getLine(),
                          $e->getTrace());
}   

function custom_exception_handler($e) {
    return show_or_mail_exception($e);
}

set_error_handler('custom_error_handler');
set_exception_handler('custom_exception_handler');

