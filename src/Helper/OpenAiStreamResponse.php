<?php

namespace Lack\OpenAi\Helper;

class OpenAiStreamResponse
{

    public $responseFull = [
        "role" => "assistant",
        "content" => "",
        "function_call" => [
            "name" => "",
            "arguments" => ""
        ]
    ];

    public function addData(array $delta) {
        if (isset($delta["function_call"])) {
            //echo "<FFF>";
            foreach ($delta["function_call"] as $key => $value) {

                $this->responseFull["function_call"][$key] .= $value;
            }
            return;
        }
        foreach ($delta as $key => $value) {
            if ($key === "role") continue;
            if (isset($this->responseFull[$key])) {
                $this->responseFull[$key] .= $value;
                continue;
            }
            $this->responseFull[$key] = $value;
        }

    }

    public function isFunctionCall() {
        return $this->responseFull["function_call"]["name"] !== "";
    }

    public function getFunctionArguments() : array {
        return json_decode($this->responseFull["function_call"]["arguments"] , true) ?? [];
    }

    public function getFunctionName() : string {
        return $this->responseFull["function_call"]["name"];
    }
}
