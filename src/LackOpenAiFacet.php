<?php

namespace Lack\OpenAi;

use Lack\OpenAi\Helper\JobTemplate;

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
     * @param string $templateFile
     * @param array $data
     * @param string|null $cast
     * @return mixed
     * @throws \Exception
     */
    public function promptData(string $templateFile, array $data, string $cast = null) : mixed {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);

        $this->client->reset($tpl->getSystemContent());

        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: false);

        return $result->getTextCleaned();

    }

    public function promptStreamToFile(string $templateFile, array $data, string $targetFile) : void {
        $tpl = new JobTemplate($templateFile);
        $tpl->setData($data);

        $this->client->reset($tpl->getSystemContent());

        $result = $this->client->textComplete($tpl->getUserContent(), streamOutput: true,  streamer: function(LackOpenAiResponse $data) use ($targetFile) {
            phore_file($targetFile)->set_contents($data->getTextCleaned());
        });
    }

}
