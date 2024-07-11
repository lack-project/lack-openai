<?php

namespace Lack\OpenAi;

use Lack\OpenAi\Helper\JobTemplate;
use Lack\OpenAi\Helper\JsonSchemaGenerator;


class LackOpenAiFacet
{

    public function __construct(public LackOpenAiClient $client) {

    }


    public $model = "gpt-4o";

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
    public function promptData(string $templateFile, array $data, string $cast = null, bool $dump = false) : mixed {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);




        if ($cast === null) {
            // Return string Text
            $this->client->reset($tpl->getSystemContent(), 0.1, $this->model);
            $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: false);
            return $result->getTextCleaned();
        }

        $jsg = new JsonSchemaGenerator();

        $system = "You must output parsable json data as defined in the json-schema: `" . $jsg->convertToJsonSchema($cast) . "`! Evaluate and follow the json-schema descriptions on how to format data. No additional text is allowed!";
        $this->client->reset($system . "\n\n" . $tpl->getSystemContent(), 0.1, $this->model);
        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: false, dump: $dump, json: true);
        return phore_json_decode($result->getTextCleaned(), $cast);
    }

    public function dump() {
        $this->client->dump();
    }

    public function promptImage(string $templateFile, array $data, string $imageData, string $imageType="png", bool $dump = false) : string {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);


        $this->client->reset($tpl->getSystemContent(), 0.1, "gpt-4-vision-preview");

        $this->client->getChatRequest()->setMaxTokens(4000);
        $this->client->getChatRequest()->addImageContent($tpl->getUserContent(), "data:image/$imageType;base64,". base64_encode($imageData));

        $result = $this->client->textComplete(null, streamOutput: false, dump: $dump);
        return $result->getTextCleaned();
    }

    public function promptImageData(string $templateFile, array $data, string $imageData, string $imageType="png", string $cast = null, bool $dump = false) : array|object
    {
        return phore_json_decode($this->promptImage($templateFile, $data, $imageData, $imageType, $dump), $cast);
    }



    public function promptStreamToFile(string $templateFile, array $data, string $targetFile, bool $dump = false, bool $noAppend=false) : void {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);

        $this->client->reset($tpl->getSystemContent(), 0.1, $this->model);

        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: true,  streamer: function(LackOpenAiResponse $data) use ($targetFile, $noAppend) {
            if ($noAppend === false)
                phore_file($targetFile)->set_contents($data->getTextCleaned());
        }, dump: $dump);
        if ($noAppend === true)
            phore_file($targetFile)->set_contents($result->getTextCleaned());
    }

    /**
     * Format unsectured input text into a valid data struct
     *
     * @template T
     * @param string $inputData
     * @param class-string<T> $className
     * @return T
     * @throws \Exception
     */
    public function promptDataStruct(string $inputData, string $className, bool $dump = false) : mixed {
        return $this->promptData(__DIR__ . "/tpl/prompt-data-struct.txt", [
            "input" => $inputData
        ], $className, dump: $dump);
    }

}
