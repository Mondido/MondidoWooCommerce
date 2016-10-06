<?php namespace MondidoBase;
class Log
{
    public function send($message, $component = "web", $program = "next_big_thing") {
        $PAPERTRAIL_HOSTNAME = 'logs4.papertrailapp.com';
        $PAPERTRAIL_PORT = '20177';
        try {
//            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            foreach(explode("\n", $message) as $line) {
                $syslog_message = "<22>" . date('M d H:i:s ') . $program . ' ' . $component . ': ' . $line;
              //  socket_sendto($sock, $syslog_message, strlen($syslog_message), 0, $PAPERTRAIL_HOSTNAME, $PAPERTRAIL_PORT);
            }
//            socket_close($sock);
        }  catch (Exception $e) {
            //socket error
        }
    }
}