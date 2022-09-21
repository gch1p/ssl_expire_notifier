#!/usr/bin/env php
<?php

require_once __DIR__.'/lib/Logger.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = getenv('HOME').'/.config/ssl_expire_notifier.ini';
if (!file_exists($file))
    die('ERROR: config '.$file.' not found');

$config = parse_ini_file($file);

function ssl_expire_notifier() {
    global $config;
    $now = time();

    foreach ($config['hosts'] as $host) {
        $logger = new Logger($host);
        if (($pos = strpos($host, ':')) !== false) {
            $port = substr($host, $pos+1);
            if (!is_numeric($port)) {
                $logger->error("failed to parse host");
                continue;
            }
            $host = substr($host, 0, $pos);
        } else {
            $port = 443;
        }

        $ipv4 = gethostbyname($host);
        if (!$ipv4 || $ipv4 == $host) {
            $logger->error("failed to resolve");
            continue;
        }

        $logger->debug("resolved to $ipv4");

        $get = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'verify_depth' => 0,
            ]
        ]);
        $read = stream_socket_client('ssl://'.$host.':'.$port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
        $cert = stream_context_get_params($read);
        $cert_info = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);

        $valid_till = $cert_info['validTo_time_t'];
        $logger->debug("valid till ".date('d.m.Y, H:i:s', $valid_till));

        if ($valid_till <= $now) {
            $logger->fatal('already expired at '.date('d.m.Y, H:i:s', $valid_till));
        } else {
            $method = null;
            if ($valid_till-$now < 86400*$config['error_days'])
                $method = 'error';
            else if ($valid_till-$now < 86400*$config['warn_days'])
                $method = 'warn';

            if ($method !== null)
                call_user_func([$logger, $method], "expires at ".date('d.m.Y, H:i:s', $valid_till));
            else
                $logger->debug('ok');
        }
    }
}

ssl_expire_notifier();