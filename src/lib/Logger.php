<?php

class Logger {

    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const FATAL = 4;

    protected static array $levelColors = [
        self::INFO => 34,
        self::WARNING => 33,
        self::ERROR => 31,
        self::FATAL => 91,
    ];

    protected static array $levelEmojis = [
        self::INFO => 'ℹ️',
        self::WARNING => '⚠️',
        self::ERROR => '‼️',
        self::FATAL => '⚡️'
    ];

    protected string $domain;

    public function __construct(string $domain) {
        $this->domain = $domain;
    }

    protected function stderr(string $message, $color = null) {
        $fmt = "[%s] %s";
        if (is_int($color))
            $fmt = "\033[{$color}m$fmt\033[0m";
        $fmt .= "\n";
        $message = strip_tags($message);
        fprintf(STDERR, $fmt, $this->domain, $message);
    }

    protected function telegram(string $message) {
        global $config;

        $url = 'https://api.telegram.org/bot'.$config['telegram_token'].'/sendMessage';
        $query_content = http_build_query([
            'chat_id' => $config['telegram_chat_id'],
            'text' => $message,
            'parse_mode' => 'html'
        ]);

        $ctx = stream_context_create([
            'http' => [
                'header' => [
                    'Content-type: application/x-www-form-urlencoded',
                    'Content-Length: '.strlen($query_content)
                ],
                'method'  => 'POST',
                'content' => $query_content
            ]
        ]);

        $fp = @fopen($url, 'r', false, $ctx);
        if ($fp === false) {
            $this->stderr("fopen failed");
            return;
        }

        $result = stream_get_contents($fp);
        fclose($fp);

        $result = json_decode($result, true);
        if (!$result['ok'])
            $this->stderr("telegram did not OK");
    }

    protected function report(int $level, string $message) {
        global $config;

        if ($config['verbose'])
            $this->stderr($message, self::$levelColors[$level] ?? null);

        if ($level != self::DEBUG && ($config['telegram_enabled'] ?? 1) == 1)
            $this->telegram(self::$levelEmojis[$level].' '.$this->domain.': '.$message);
    }

    public function debug(string $message) {
        $this->report(self::DEBUG, $message);
    }

    public function info(string $message) {
        $this->report(self::INFO, $message);
    }

    public function warn(string $message) {
        $this->report(self::WARNING, $message);
    }

    public function error(string $message) {
        $this->report(self::ERROR, $message);
    }

    public function fatal(string $message) {
        $this->report(self::FATAL, $message);
    }

}