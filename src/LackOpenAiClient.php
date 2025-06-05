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
            $this->requestCache = new Cache\NoCache();

        if ($this->logger === null)
            $this->logger = new CliLogger();
    }

    public function getApiKey() {
        return trim($this->apiKey);
    }


    public function setLogger( LackOpenAiLogger $logger) {
        $this->logger = $logger;
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
    public function reset(string $systemContent = null, float $temperature = 0.1, string $model = "gpt-4.1-2025-04-14") {
        $this->chatRequest->reset($systemContent, $temperature, $model );
    }

    public function getCache() : RequestCacheInterface {
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


    public function getChatRequest() : ChatRequest {
        return $this->chatRequest;
    }


    public function textComplete(string|array|null $question = null, bool $streamOutput = false, callable $streamer = null, bool $dump = false, bool $json = false, array $schema = null): LackOpenAiResponse
    {
        $api = \OpenAI::client($this->getApiKey());

        $cacheKey = json_encode([$this->chatRequest->request, $question]);

        $cachedResult = $this->requestCache->get($cacheKey);
        if ($cachedResult !== null) {
            $this->logger->logCacheHit();
            $response = new LackOpenAiResponse($cachedResult);
            if ($streamer !== null) {
                $streamer($response);
            }
            return $response;
        }

        //$this->chatRequest->setMaxTokens(500);
        if ($question) {
            $this->chatRequest->addUserContent($question);
        }
        $this->chatRequest->setJson($json); // Skip schema for now - not supported 2024/10


        // Initialize variables for continuation logic
        $maxRetries = 2; // Maximum number of continuations
        $retryCount = 0;
        $completeResponseContent = ''; // To accumulate the assistant's responses
        $finishReason = null;
        $isFunctionCall = false;

        do {
            // Call OpenAI API
            $this->logger->logServerRequest($this->chatRequest->request);

            if ($dump) {
                print_r($this->chatRequest->request);
            }

            try {
                $stream = $api->chat()->createStreamed($this->chatRequest->request);
            } catch (\Exception $e) {
                throw $e;
            }

            // Evaluate the Stream Response
            $response = new OpenAiStreamResponse();
            $lastFlush = 0;
            $finishReason = null;

            foreach ($stream as $streamChunk) {
                $delta = $streamChunk->choices[0]->delta->toArray();
                $response->addData($delta);
                $this->logger->logStreamOutput($delta["content"] ?? "");

                if ($streamer !== null) {
                    if (strlen($response->responseFull["content"]) > $lastFlush + 550) {
                        $lastFlush = strlen($response->responseFull["content"]);
                        $streamer(new LackOpenAiResponse($response->responseFull["content"]));
                    }
                }

                // Check if finish_reason is set
                if (isset($streamChunk->choices[0]->finishReason)) {
                    $finishReason = $streamChunk->choices[0]->finishReason;

                }
            }

            $this->logger->logServerResponse($response->responseFull);

            // Append the assistant's content to the complete response
            $assistantContent = $response->responseFull["content"] ?? '';
            $completeResponseContent .= $assistantContent;

            // Add the assistant's partial response to the conversation history
            if ($assistantContent) {
                $this->chatRequest->addAssistantContent($assistantContent);
            }

            // Check if the assistant made a function call
            if ($response->isFunctionCall()) {
                $isFunctionCall = true;
                break; // Exit the loop to handle the function call
            }

            if ($finishReason === 'length') {
                if ($retryCount > $maxRetries) {
                    throw new \Exception("Max retries reached");
                }
                // Max token limit reached, instruct the assistant to continue
                $this->logger->logEvent("\nMax token limit reached, requesting continuation. ($retryCount / $maxRetries");
                $retryCount++;

                // Instruct the assistant to continue
                $continuationInstruction = "Please continue from exact the character from where you left off (last assistant message). Do not repeat aný characters of the last message.";
                $this->chatRequest->addUserContent($continuationInstruction);
            } else {
                // Response is complete or max retries reached
                break;
            }

        } while (true);

        // Handle function call if any
        if ($isFunctionCall) {
            $functionName = $response->getFunctionName();
            // Remove "functions." prefix if present
            if (str_starts_with($functionName, "functions.")) {
                $functionName = substr($functionName, strlen("functions."));
            }
            $functionArguments = $response->getFunctionArguments();

            $this->logger->logFunctionCall($functionName, $functionArguments);
            try {
                $return = $this->runFunction($functionName, $functionArguments);
                $this->logger->logFunctionResult($functionName, $return);
                $this->chatRequest->addFunctionResult($functionName, $return);
            } catch (\InvalidArgumentException $e) {
                $errorMessage = "Error: " . $e->getMessage() . ". Please append the missing parameter and try again. " . uniqid();
                $this->logger->logFunctionResult($functionName, $errorMessage);
                $this->chatRequest->addFunctionResult($functionName, $errorMessage);
            }

            // Reset retry count and continue the loop to get the assistant's response after the function call
            $retryCount = 0;
            $finishReason = null;
            $isFunctionCall = false;

            do {
                // Call OpenAI API to get the assistant's response after the function call
                $this->logger->logServerRequest($this->chatRequest->request);

                if ($dump) {
                    print_r($this->chatRequest->request);
                }

                try {
                    $stream = $api->chat()->createStreamed($this->chatRequest->request);
                } catch (\Exception $e) {
                    throw $e;
                }

                // Evaluate the Stream Response
                $response = new OpenAiStreamResponse();
                $lastFlush = 0;
                $finishReason = null;

                foreach ($stream as $streamChunk) {
                    $delta = $streamChunk->choices[0]->delta->toArray();
                    $response->addData($delta);
                    $this->logger->logStreamOutput($delta["content"] ?? "");

                    if ($streamer !== null) {
                        if (strlen($response->responseFull["content"]) > $lastFlush + 550) {
                            $lastFlush = strlen($response->responseFull["content"]);
                            $streamer(new LackOpenAiResponse($response->responseFull["content"]));
                        }
                    }

                    // Check if finish_reason is set
                    if (isset($streamChunk->choices[0]->finish_reason)) {
                        $finishReason = $streamChunk->choices[0]->finish_reason;
                    }
                }

                $this->logger->logServerResponse($response->responseFull);

                // Append the assistant's content to the complete response
                $assistantContent = $response->responseFull["content"] ?? '';
                $completeResponseContent .= $assistantContent;

                // Add the assistant's response to the conversation history
                if ($assistantContent) {
                    $this->chatRequest->addAssistantContent($assistantContent);
                }

                // Check for another function call (unlikely but possible)
                if ($response->isFunctionCall()) {
                    $this->logger->logStreamOutput("Nested function calls are not supported.");
                    break;
                }

                if ($finishReason === 'length' && $retryCount < $maxRetries) {
                    // Max token limit reached, instruct the assistant to continue
                    $this->logger->logStreamOutput("Max token limit reached, requesting continuation.");
                    $retryCount++;

                    // Instruct the assistant to continue
                    $continuationInstruction = "Please continue from where you left off.";
                    $this->chatRequest->addUserContent($continuationInstruction);
                } else {
                    // Response is complete or max retries reached
                    break;
                }

            } while (true);
        }

        // Cache the complete response
        $this->requestCache->set($cacheKey, $completeResponseContent);

        if ($streamer !== null) {
            $streamer(new LackOpenAiResponse($completeResponseContent));
        }

        return new LackOpenAiResponse($completeResponseContent);
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
