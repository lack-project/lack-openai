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
        // Parse {% ifdef key %}...{% endif %} and remove if key is not set (orherwise return the inner section without the if)
        $template = preg_replace_callback("/\{%\s*ifdef\s+([a-zA-Z0-9_]+)\s*%\}(.+?)\{%\s*endif\s*%\}/s", function ($matches) use($debugSectionName) {
            $key = $matches[1];
            if (isset ($this->data[$key]) && $this->data[$key] !== null) {
                return $matches[2];
            }
            if ($this->dataLoader !== null && ($this->dataLoader)($key) !== null) {
                return $matches[2];
            }
            return ""; // Skip section if not found
        }, $template);
        
        
        // Replace all {{key}} with value from data
        // Allows to use {{ key | htmlentitys }} to apply a filter
        
        $template =  preg_replace_callback("/\{{([a-zA-Z0-9_| ]+)\}\}/", function ($matches) use($debugSectionName) {
            // Split by pipe
            $parts = explode("|", $matches[1]);
            $key = trim (array_shift($parts));
            $value = null;
            if (isset ($this->data[$key])) {
                $value = $this->data[$key];
            } else if ($this->dataLoader !== null) {
                $value = ($this->dataLoader)($key);
            } else {
                throw new \InvalidArgumentException("Key '$key' not found in template. (Section: $debugSectionName in file: {$this->templateFileName})");
            }
           
            // Filter
            foreach ($parts as $filter) {
                $filter = trim($filter);
                if ($filter == "htmlentities") {
                    $value = htmlentities($value);
                } else {
                    throw new \InvalidArgumentException("Unknown filter '$filter' in template. (Section: $debugSectionName in file: {$this->templateFileName})");
                }
            }
            
            return $value;
            
        }, $template);
        return $template;

    }

    public function getSystemContent() : string {
        return $this->_parseSection($this->templateParts[0], "system");
    }


    public function getUserContent(int $index = 0) : string {
        return $this->_parseSection($this->templateParts[1 + $index], "user.$index");
    }
}
