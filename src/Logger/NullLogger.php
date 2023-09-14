<?php

namespace Lack\OpenAi\Logger;

class NullLogger implements LackOpenAiLogger
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
       
    }

    public function logFunctionCall(string $name, array $arguments): void
    {
       
    }

    public function logFunctionResult(string $name, mixed $result): void
    {
    }

    public function logServerRequest(array $request): void
    {
       
    }

    public function logServerResponse(array $response): void
    {
        
    }

    public function logStreamOutput(string $chars): void
    {
        
    }

    public function logCacheHit(string $key = ""): void
    {
      
    }
}
