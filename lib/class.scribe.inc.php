<?php

defined('SCRIBE_HOST') || define('SCRIBE_HOST', 'localhost');
defined('SCRIBE_PORT') || define('SCRIBE_PORT', 1463);

class Scribe {
    static function log($category, $message) {
        require_once THRIFT_ROOT . '/Thrift.php';    
        require_once THRIFT_ROOT . '/protocol/TBinaryProtocol.php';
        require_once THRIFT_ROOT . '/transport/TSocket.php';
        require_once THRIFT_ROOT . '/transport/TFramedTransport.php';
        require_once THRIFT_ROOT . '/packages/scribe/scribe.php';

        switch ($category) {
         case 'general':
         case 'accounts':
         case 'actions':
         case 'status':
         case 'security':
         case 'debug':
         case 'history':
         case 'regulation':
         case 'specials':
            break;
         default:
            fatal();
        }
        if (!is_string($message)) {
            if (is_array($message)) {
                if (!isset($message['ts'])) {
                    $message['ts'] = time();
                }
                if (!isset($message['client_ip'])) {
                    $message['client_ip'] = Utils::get_client_ip();
                }
                if (!isset($message['user_id']) && Auth::is_logged()) {
                    $message['user_id'] = Auth::get_user_id();
                }
                if (!isset($message['user_name']) && Auth::is_logged()) {
                    $message['user_name'] = Auth::get_user_name();
                }
                $oauth_client_id = Auth::get_oauth_client_id();
                if (!isset($message['oauth_client_id']) && !empty($oauth_client_id)) {
                    $message['oauth_client_id'] = $oauth_client_id;
                }
            }
            $message = json_encode($message);
        }
        try {
            $log_entry = new \LogEntry(array('category' => $category, 'message' => $message));
            $messages = array($log_entry);
            $socket = new \TSocket(SCRIBE_HOST, SCRIBE_PORT, FALSE);
            $transport = new \TFramedTransport($socket);
            $protocol = new \TBinaryProtocolAccelerated($transport, FALSE, FALSE);
            $client = new \scribeClient($protocol, $protocol);
            $transport->open();
            $client->send_log($messages);
            $transport->close();
        } catch (\TException $e) {
            return FALSE;
        }
        return TRUE;
    }
}
