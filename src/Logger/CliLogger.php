<?php

namespace Lack\OpenAi\Logger;

class CliLogger implements LackOpenAiLogger
{

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
        echo "> Function call: $name (" . json_encode($arguments) .")\n";
    }

    public function logFunctionResult(string $name, mixed $result): void
    {
        if (! $this->logFunction)
            return;
        echo "\n";
        echo "< Function result of  $name: " . json_encode($result) ."\n";
    }

    public function logServerRequest(array $request): void
    {
        if ( ! $this->logServer)
            return;
        echo "\n";
        echo "> Server request: " . json_encode($request) ."\n";
    }

    public function logServerResponse(array $response): void
    {
        if ( ! $this->logServer)
            return;
        echo "\n";
        echo "< Server response: " . json_encode($response) ."\n";
    }
}
