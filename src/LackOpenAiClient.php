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

    /**
     * Reset the Message history and start new chat session
     *
     * Provide a inital optional system message as parameter
     *
     * @param string|null $systemContent
     * @return void
     */
    public function reset(string $systemContent = null) {
        $this->chatRequest->reset($systemContent);
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



    private function runFucntion(string $functionName, array $functionArguments) : mixed {
        $function = $this->functions[$functionName]["callback"];
        if ($function === null) {
            throw new \InvalidArgumentException("Undefined function '$functionName'");
        }


        if (is_array($function)) {
            $fnRef = new \ReflectionMethod($function[0], $function[1]);
        } else {
            $fnRef = new \ReflectionFunction($function);
        }

        $args = [];
        foreach ($fnRef->getParameters() as $param) {
            if (isset ($functionArguments[$param->getName()])) {
                $args[] = $functionArguments[$param->getName()];
            } else {
                if ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException("Missing required parameter '{$param->getName()}");
                }
            }
        }
        return $function(...$args);

    }



    public function textComplete($question=null, bool $streamOutput = false) : string
    {
        $api = \OpenAI::client($this->getApiKey());

        if ($question) {
            $this->chatRequest->addUserContent($question);
        }

        // Call OpenAI API
        $this->logger->logServerRequest($this->chatRequest->request);

        try {

            $stream = $api->chat()->createStreamed($this->chatRequest->request);
        } catch (\Exception $e) {
            print_r ($this->chatRequest->request);
            throw $e;
        }


        // Evaluate the Stream Response
        $response = new OpenAiStreamResponse();
        foreach ($stream as $streamChunk) {
            $delta = $streamChunk->choices[0]->delta->toArray();
            $response->addData($delta);
            $this->logger->logStreamOutput($delta["content"] ?? "");

        }
        $this->logger->logServerResponse($response->responseFull);

        // Add the Response to History
        $this->chatRequest->addResponse($response);

        if ($response->isFunctionCall()) {
            $functionName = $response->getFunctionName();
            $functionArguments = $response->getFunctionArguments();

            $this->logger->logFunctionCall($functionName, $functionArguments);
            try {
                $return = $this->runFucntion($functionName, $functionArguments);
                $this->logger->logFunctionResult($functionName, $return);
                $this->chatRequest->addFunctionResult($functionName, $return);
            } catch (\InvalidArgumentException $e) {
                $this->logger->logFunctionResult($functionName, "Error: " . $e->getMessage());
                $this->chatRequest->addFunctionResult($functionName, "Error: ". $e->getMessage());
            }

            $this->textComplete(null, $streamOutput);
        }


        return $response->responseFull["content"];
    }

    /**
     * Interactive chat with user
     *
     * @return void
     */
    public function interactive(string $question = null) {
        if ($question !== null)
            $this->textComplete($question, streamOutput: true);
        while (true) {
            $input = trim(readline("Your input: "));
            if ($input === "") {
                echo "\nExit - Goodbye\n";
                return;
            }


            $this->textComplete($question, streamOutput: true);
            echo "\nEoR;\n";
        }
    }

}
