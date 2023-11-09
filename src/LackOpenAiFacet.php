<?php

namespace Lack\OpenAi;

use Lack\OpenAi\Helper\JobTemplate;
use Lack\OpenAi\Helper\JsonSchemaGenerator;

class LackOpenAiFacet
{

    public function __construct(public LackOpenAiClient $client) {

    }


    public $model = "gpc-4";

    public function setModel(string $model) : self {
        $this->model = $model;
        return $this;
    }


    /**
     * Run the prompt provided in Template file and return the result (as string by default)
     *
     * @template T
     * @param string $templateFile
     * @param array $data
     * @param class-string<T>|null $cast
     * @return mixed|T
     * @throws \Exception
     */
    public function promptData(string $templateFile, array $data, string $cast = null) : mixed {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);

        

        if ($cast === null) {
            // Return string Text
            $this->client->reset($tpl->getSystemContent());
            $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: false);
            return $result->getTextCleaned();
        }
        
        $jsg = new JsonSchemaGenerator();
        
        phore_out($jsg->convertToJsonSchema($cast));
        $system = "You must output parsable json data as defined in the json-schema: `" . $jsg->convertToJsonSchema($cast) . "`! Evaluate and follow the json-schama descriptions on how to format data. No aditonal text is allowed!";
        $this->client->reset($system . "\n\n" . $tpl->getSystemContent());
        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: false);
        return phore_json_decode($result->getTextCleaned(), $cast);
    }

    public function promptStreamToFile(string $templateFile, array $data, string $targetFile) : void {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);

        $this->client->reset($tpl->getSystemContent());
        
        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: true,  streamer: function(LackOpenAiResponse $data) use ($targetFile) {
            phore_file($targetFile)->set_contents($data->getTextCleaned());
        });
    }

    /**
     * Format unsectured input text into a valid data struct
     * 
     * @template T
     * @param string $inputData
     * @param class-string<T> $class
     * @return T
     * @throws \Exception
     */
    public function promtDataSruct(string $inputData, string $class) {
        return $this->promptData(__DIR__ . "/tpl/prompt-data-struct.txt", [
            "input" => $inputData
        ], $class);
    }

}
