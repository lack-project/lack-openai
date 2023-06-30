<?php

namespace Lack\OpenAi;

use Lack\OpenAi\Attributes\AiFunction;
use Lack\OpenAi\Helper\ChatHistory;
use Lack\OpenAi\Helper\ChatRequest;
use Lack\OpenAi\Helper\FunctionDefinitionGenerator;
use Lack\OpenAi\Helper\OpenAiStreamResponse;
use Lack\OpenAi\Logger\CliLogger;
use Lack\OpenAi\Logger\LackOpenAiLogger;
use Leuffen\Brix\Api\OpenAiResult;

class LackOpenAiClient
{

    private ChatRequest $chatRequest;

    public function __construct(
        private string $apiKey,
        private LackOpenAiLogger|null $logger = null
    ) {
        $this->chatRequest = new ChatRequest();
        if ($this->logger === null)
            $this->logger = new CliLogger();
    }

    public function getApiKey() {
        return trim($this->apiKey);
    }

    public $functions = [];


    /***
     * Define a function. Should provide
     * @param $name
     * @param $callback
     * @return void
     * @throws \ReflectionException
     */
    public function addFunction ( \Closure|callable $callback) {
        if (is_array($callback))
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        else
            $reflection = new \ReflectionFunction($callback);

        $definition  = (new FunctionDefinitionGenerator())->getFunctionDefinition($reflection);
        $this->chatRequest->addFunctionDefinition($definition);

        $this->functions[$definition["name"]] = [
            "callback" => $callback,
        ];
    }


    public function addClass(object $object) {
        $reflection = new \ReflectionObject($object);
        foreach ($reflection->getMethods() as $method) {
            $attr = get_attribute(AiFunction::class, $method);
            if ($attr === null) continue;
            $this->addFunction([$object, $method->getName()]);
        }
    }






    public function textComplete($question=null, bool $streamOutput = false) : string
    {
        $api = \OpenAI::client($this->getApiKey());

        if ($question) {
            $this->chatRequest->addUserContent($question);
        }

        // Call OpenAI API
        $this->logger->logServerRequest($this->chatRequest->request);
        $stream = $api->chat()->createStreamed($this->chatRequest->request);


        // Evaluate the Stream Response
        $response = new OpenAiStreamResponse();
        foreach ($stream as $streamChunk) {
            $delta = $streamChunk->choices[0]->delta->toArray();
            $response->addData($delta);
            echo $delta["content"] ?? "";

        }
        $this->logger->logServerResponse($response->responseFull);

        // Add the Response to History
        $this->chatRequest->addResponse($response);

        if ($response->isFunctionCall()) {
            $functionName = $response->getFunctionName();
            $functionArguments = $response->getFunctionArguments();

            $function = $this->functions[$functionName]["callback"];
            $this->logger->logFunctionCall($functionName, $functionArguments);
            $return = $function(...$functionArguments);
            $this->logger->logFunctionResult($functionName, $return);

            $this->chatRequest->addFunctionResult($functionName, $return);
            $this->textComplete(null, $streamOutput);
        }


        return $response->responseFull["content"];
    }

}
