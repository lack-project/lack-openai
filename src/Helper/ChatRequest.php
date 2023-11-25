<?php

namespace Lack\OpenAi\Helper;

class ChatRequest
{

    public $request = [
        "model" => "gpt-4",
        "temperature" => 0.1,
        "messages" => []
    ];



    public function __construct(string $systemMessage = null) {
        $this->reset($systemMessage);
    }

    /**
     * Reset the message history and start new session
     *
     *
     * @param string|null $systemMessage
     * @return void
     */
    public function reset(string $systemMessage = null, float $temperature = 0.1, string $model = "gpt-4") {
        $this->request["temperature"] = $temperature;
        $this->request["model"] = $model;
        $this->request["messages"] = [];
        if ($systemMessage) {
            $this->request["messages"][] = [
                'content' => $systemMessage,
                'role' => 'system',
            ];
        }
    }

    public function addFunctionDefinition(array $functionDefinition) {
        if ( ! isset ($this->request["functions"]))
            $this->request["functions"] = [];
        $this->request["functions"][] = $functionDefinition;
    }

    public function addUserContent(string|array $content) {
        if ( ! is_array($content))
            $content = [$content];
        foreach ($content as $c) {
            $this->request["messages"][] = [
                'content' => $c,
                'role' => 'user',
            ];
        }
    }

    public function addResponse(OpenAiStreamResponse $streamResponse) {
        $response = $streamResponse->responseFull;
        if ($response["function_call"]["name"] === "")
            unset($response["function_call"]);
        $this->request["messages"][] = $response;
    }

    public function addFunctionResult(string $name, mixed $result) {
        $this->request["messages"][] = [
            'content' => json_encode($result),
            'role' => 'function',
            'name' => $name,
        ];
    }

}
