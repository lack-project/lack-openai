<?php

namespace Lack\OpenAi\Helper;

class ChatRequest
{

    public $request = [
        "model" => "gpt-3.5-turbo-16k",
        "messages" => [],
        "functions" => [],
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
    public function reset(string $systemMessage = null) {
        $this->request["messages"] = [];
        if ($systemMessage) {
            $this->request["messages"][] = [
                'content' => $systemMessage,
                'role' => 'system',
            ];
        }
    }

    public function addFunctionDefinition(array $functionDefinition) {
        $this->request["functions"][] = $functionDefinition;
    }

    public function addUserContent(string $content) {
        $this->request["messages"][] = [
            'content' => $content,
            'role' => 'user',
        ];
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
