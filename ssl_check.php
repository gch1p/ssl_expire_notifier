#!/usr/bin/env php
<?php

function notify($text) {
    $fields = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $text,
    ];

    $ch = curl_init();
    $url = 'https://api.telegram.org/bot'.TELEGRAM_BOT_TOKEN.'/sendMessage';
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_exec($ch);
    curl_close($ch);
}

$domains = [
    'example.com',
    'example.org',
    // add domains here
];
$now = time();

const TELEGRAM_CHAT_ID = 0;
const TELEGRAM_BOT_TOKEN = '';

foreach ($domains as $d) {
    $ipv4 = gethostbyname($d);
    if ($ipv4 == $d) {
        echo $d.": gethostbyname did not found ipv4\n";
        continue;
    }

    $get = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'verify_depth' => 0,
        ]
    ]);
    $read = stream_socket_client('ssl://'.$d.':443', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $get);
    $cert = stream_context_get_params($read);
    $certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);

    $valid_to = $certinfo['validTo_time_t'];
    if ($valid_to - $now < 86400*7) {
        $text = "SSL-сертификат для {$d} истекает ".date('d.m.Y H:i:s', $valid_to);
        notify($text);
    }
}
