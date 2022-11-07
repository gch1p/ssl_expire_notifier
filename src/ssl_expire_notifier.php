#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/lib/Logger.php';

use Iodev\Whois\Factory;

error_reporting(E_ALL);
ini_set('display_errors', 1);

const TIME_FMT = 'd.m.Y, H:i:s';
const TYPE_SSL = 0;
const TYPE_WHOIS = 1;

$file = getenv('HOME').'/.config/ssl_expire_notifier.ini';
if (!file_exists($file))
    die('ERROR: config '.$file.' not found');

$now = time();
$config = parse_ini_file($file);

function handle_result(int $type, string $host, int $exp, Logger $logger) {
    global $now, $config;

    static $cfg_prefixes = [
        TYPE_SSL => 'ssl_',
        TYPE_WHOIS => 'reg_'
    ];
    static $subtitles = [
        TYPE_SSL => 'SSL',
        TYPE_WHOIS => 'REGISTRATION'
    ];

    $cfg_prefix = $cfg_prefixes[$type];
    $subtitle = $subtitles[$type];

    $logger->debug("{$subtitle}: valid till ".date(TIME_FMT, $exp));

    if ($exp <= $now) {
        $logger->fatal($subtitle.': already expired at '.date(TIME_FMT, $exp));
    } else {
        $method = null;
        if ($exp-$now < 86400*$config[$cfg_prefix.'error_days'])
            $method = 'error';
        else if ($exp-$now < 86400*$config[$cfg_prefix.'warn_days'])
            $method = 'warn';

        if ($method !== null)
            call_user_func([$logger, $method], "{$subtitle}: expires at ".date(TIME_FMT, $exp));
        else
            $logger->debug('ok');
    }
}

function get_top_domains() {
    global $config;
    $domains = array_map(function(string $d) {
        if (($pos = strpos($d, ':')) !== false)
            $d = substr($d, 0, $pos);
        $words = explode('.', $d);
        if (count($words) < 2) {
            trigger_error('weird domain: '.$d);
            return $d;
        }
        $words = array_reverse($words);
        return "{$words[1]}.{$words[0]}";
    }, $config['hosts']);
    return array_values(array_unique($domains));
}

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

        handle_result(TYPE_SSL, $host, $cert_info['validTo_time_t'], $logger);
    }
}

function whois_expire_notifier() {
    $whois = Factory::get()->createWhois();

    $domains = get_top_domains();
    foreach ($domains as $domain) {
        $logger = new Logger($domain);
        try {
            $info = $whois->loadDomainInfo($domain);
            handle_result(TYPE_WHOIS, $domain, $info->expirationDate, $logger);
        } catch (\Iodev\Whois\Exceptions\WhoisException $e) {
            $logger->error("WhoisException: ".$e->getMessage());
        }
    }
}

ssl_expire_notifier();
whois_expire_notifier();