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
        
        // remove trailing ```json and ending ```
        $text = preg_replace("/^```json/", "", $text);
        $text = preg_replace("/```$/", "", $text);
        
        return $text;
    }

    /**
     * @template T
     * @param class-string<T>|null $cast
     * @return mixed|T
     */
    public function getJson(string $cast = null) : mixed {
        $cleande = $this->getTextCleaned();
        $json = phore_json_decode($cleande, $cast);
        return $json;
    }

    public function __toString() : string {
        return $this->response;
    }

}
