<?php

namespace Lack\OpenAi\Logger;

class CliLogger implements LackOpenAiLogger
{


    public function logFunctionCall(string $name, array $arguments): void
    {
        echo "\n";
        echo "> Function call: $name (" . json_encode($arguments) .")\n";
    }

    public function logFunctionResult(string $name, mixed $result): void
    {
        echo "\n";
        echo "< Function result of  $name: " . json_encode($result) ."\n";
    }

    public function logServerRequest(array $request): void
    {
        echo "\n";
        echo "> Server request: " . json_encode($request) ."\n";
    }

    public function logServerResponse(array $response): void
    {
        echo "\n";
        echo "< Server response: " . json_encode($response) ."\n";
    }
}
