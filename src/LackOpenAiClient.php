<?php

namespace Lack\OpenAi;

use Lack\OpenAi\Attributes\AiFunction;
use Lack\OpenAi\Cache\FileRequestCache;
use Lack\OpenAi\Cache\RequestCacheInterface;
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
        private LackOpenAiLogger|null $logger = null,
        private RequestCacheInterface|null $requestCache = null
    ) {
        $this->chatRequest = new ChatRequest();
        if ($this->requestCache === null)
            $this->requestCache = new Cache\FileRequestCache();

        if ($this->logger === null)
            $this->logger = new CliLogger();
    }

    public function getApiKey() {
        return trim($this->apiKey);
    }

    /**
     * Load the Facet with nice helper functions
     * 
     * @return LackOpenAiFacet
     */
    public function getFacet() : LackOpenAiFacet {
        return new LackOpenAiFacet($this);
    }
    
    /**
     * Reset the Message history and start new chat session
     *
     * Provide a inital optional system message as parameter
     *
     * @param string|null $systemContent
     * @return void
     */
    public function reset(string $systemContent = null, float $temperature = 0.1, string $model = "gpt-4") {
        $this->chatRequest->reset($systemContent, $temperature, $model );
    }

    public function getCache() : FileRequestCache {
        return $this->requestCache;
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
        $function = $this->functions[$functionName]["callback"] ?? null;
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



    public function textComplete(string|array|null $question=null, bool $streamOutput = false, callable $streamer = null, bool $dump = false) : LackOpenAiResponse
    {
        $api = \OpenAI::client($this->getApiKey());

        $cacheKey = json_encode([$this->chatRequest->request, $question]);

        $cachedResult = $this->requestCache->get($cacheKey);
        if ($cachedResult !== null) {
            $this->logger->logCacheHit();
            $response = new LackOpenAiResponse($cachedResult);
            if ($streamer !== null) {
                $streamer(new LackOpenAiResponse($cachedResult));
            }
            return $response;
        }

        if ($question) {
            $this->chatRequest->addUserContent($question);
        }

        // Call OpenAI API
        $this->logger->logServerRequest($this->chatRequest->request);

        if ($dump) {
            print_r($this->chatRequest->request);
        }
        try {

            $stream = $api->chat()->createStreamed($this->chatRequest->request);
        } catch (\Exception $e) {
            print_r ($this->chatRequest->request);
            throw $e;
        }


        // Evaluate the Stream Response
        $response = new OpenAiStreamResponse();
        $lastFlush = 0;
        foreach ($stream as $streamChunk) {
            $delta = $streamChunk->choices[0]->delta->toArray();
            $response->addData($delta);
            $this->logger->logStreamOutput($delta["content"] ?? "");

            if ($streamer !== null) {
                if (strlen($response->responseFull["content"]) > $lastFlush + 250) {
                    $lastFlush = strlen($response->responseFull["content"]);
                    $streamer(new LackOpenAiResponse($response->responseFull["content"]));
                }
            }

        }
        $this->logger->logServerResponse($response->responseFull);

        // Add the Response to History
        $this->chatRequest->addResponse($response);

        if ($response->isFunctionCall()) {
            $functionName = $response->getFunctionName();
            // if functionName starts with functions. or function. - remove it
            if (str_starts_with($functionName, "functions."))
                $functionName = substr($functionName, strlen("functions."));
            $functionArguments = $response->getFunctionArguments();

            $this->logger->logFunctionCall($functionName, $functionArguments);
            try {
                $return = $this->runFucntion($functionName, $functionArguments);
                $this->logger->logFunctionResult($functionName, $return);
                $this->chatRequest->addFunctionResult($functionName, $return);
            } catch (\InvalidArgumentException $e) {
                $this->logger->logFunctionResult($functionName, "Error: " . $e->getMessage());
                $this->chatRequest->addFunctionResult($functionName, "Error: ". $e->getMessage(). ". Please append the missing parameter and try again. " . uniqid());
            }

            $this->textComplete(null, $streamOutput);
        }

        $this->requestCache->set($cacheKey, $response->responseFull["content"]);
        if ($streamer !== null) {
            $streamer(new LackOpenAiResponse($response->responseFull["content"]));
        }
        return new LackOpenAiResponse($response->responseFull["content"]);
    }


    public function dump() {
        print_r($this->chatRequest->request);
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
            echo "\n";
            $input = trim(readline("Your input: "));
            if ($input === "") {
                echo "\nExit - Goodbye\n";
                return;
            }



            $this->textComplete($input, streamOutput: true);
            echo "\n---end--;\n";
        }
    }

}
