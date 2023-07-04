<?php

namespace Lack\OpenAi;

class LackOpenAiResponse
{

    public function __construct(private string $response) {

    }

    public function getResponse() : string {
        return $this->response;
    }

    /**
     * Perform various clean operations
     *
     * @return string
     */
    public function getTextCleaned() : string {
        $text = trim ($this->response);
        // Remove trailing """ and ending """ from response
        $text = preg_replace("/^\"\"\"/", "", $text);
        $text = preg_replace("/\"\"\"$/", "", $text);
        return $text;
    }


    public function __toString() : string {
        return $this->response;
    }

}
