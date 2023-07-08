<?php

namespace Lack\OpenAi\Logger;

interface LackOpenAiLogger
{

    public function logFunctionCall(string $name, array $arguments) : void;

    public function logFunctionResult(string $name, mixed $result) : void;

    public function logServerRequest(array $request) : void;

    public function logServerResponse(array $response) : void;

    public function logStreamOutput(string $chars) : void;

    public function logCacheHit(string $key = "") : void;
}
