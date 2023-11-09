<?php

namespace Lack\OpenAi\Helper;

class JobTemplate
{

    private $templateParts = [];
    private string $templateFileName;

    private array $data = [];
    private \Closure|null $dataLoader = null;
    public function __construct(string $templateFile = null)
    {
        if ($templateFile !== null)
            $this->loadTemplate($templateFile);
    }

    public function loadTemplate(string $filename)
    {
        $this->templateFileName = $filename;
        $this->templateParts = explode ("\n---\n", phore_file($filename)->assertFile()->get_contents());
        if (count($this->templateParts) < 2)
            array_unshift($this->templateParts, ""); // Empty system Message
    }

    public function setData(array $data)  {
        $this->data = $data;
    }

    public function setDataLoader(\Closure $loader) {
        $this->dataLoader = $loader;
    }


    /**
     * Search for {keyname} and replace it with the value from this->data or, undefined
     * call the dataLoader with key as parameter. if both are undefined, throw exception
     *
     * @param string $temaplte
     * @return string
     */
    private function _parseSection(string $template, string $debugSectionName) : string {

        return preg_replace_callback("/\{{([a-zA-Z0-9_]+)\}\}/", function ($matches) use($debugSectionName) {
            $key = $matches[1];
            if (isset ($this->data[$key])) {
                return $this->data[$key];
            }
            if ($this->dataLoader !== null) {
                return ($this->dataLoader)($key);
            }
            throw new \InvalidArgumentException("Key '$key' not found in template. (Section: $debugSectionName in file: {$this->templateFileName})");
        }, $template);

    }

    public function getSystemContent() : string {
        return $this->_parseSection($this->templateParts[0], "system");
    }


    public function getUserContent(int $index = 0) : string {
        return $this->_parseSection($this->templateParts[1 + $index], "user.$index");
    }
}
