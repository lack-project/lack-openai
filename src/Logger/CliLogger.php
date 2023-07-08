<?php

namespace Lack\OpenAi\Logger;

class CliLogger implements LackOpenAiLogger
{
    private const FONT_BOLD = "\033[1m";
    private const COLOR_BLUE = "\033[34m";
    private const COLOR_GREEN = "\033[32m";
    private const COLOR_CYAN = "\033[36m";
    private const COLOR_PURPLE = "\033[35m";
    private const COLOR_GRAY = "\033[37m";
    private const COLOR_RESET = "\033[0m";

    private $logFunction = true;
    private $logServer = false;

    public function setLogLevel(bool $logFunction = true, bool $logServer = true): void
    {
        $this->logFunction = $logFunction;
        $this->logServer = $logServer;
    }

    public function logFunctionCall(string $name, array $arguments): void
    {
        if (! $this->logFunction)
            return;
        echo "\n";
        echo  self::COLOR_BLUE . "\e[1m> Function call: $name (" . self::COLOR_RESET . substr(json_encode($arguments), 0, 40) . self::FONT_BOLD . self::COLOR_BLUE . ")" . self::COLOR_RESET;
        echo "\n";
    }

    public function logFunctionResult(string $name, mixed $result): void
    {
        if (! $this->logFunction)
            return;
        echo self::COLOR_GREEN . "< Result of $name: " . substr(json_encode($result), 0, 40) . self::COLOR_RESET;
        echo "\n";
    }

    public function logServerRequest(array $request): void
    {
        if (! $this->logServer)
            return;
        echo self::FONT_BOLD . self::COLOR_CYAN . "\e[1m> Server request: " . json_encode($request) . self::COLOR_RESET;
        echo "\n";
    }

    public function logServerResponse(array $response): void
    {
        if (! $this->logServer)
            return;
        echo self::FONT_BOLD . self::COLOR_PURPLE . "\e[1m< Server response: " . json_encode($response) . self::COLOR_RESET;
        echo "\n";
    }

    public function logStreamOutput(string $chars): void
    {
        $chars = str_replace("\n", "\n" . self::COLOR_GRAY . "[stream] ", $chars);
        echo self::COLOR_GRAY . $chars . self::COLOR_RESET;
    }

    public function logCacheHit(string $key = ""): void
    {
        echo self::COLOR_GRAY . "[cache] " . self::COLOR_RESET . "Cache hit for key $key";
        echo "\n";
    }
}
