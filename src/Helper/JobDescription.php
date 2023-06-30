<?php

namespace Lack\OpenAi\Helper;

class JobDescription
{
    private string $data = "";
    private string $rules = "";

    public function addContext(string $context) {
        $this->data .= "\n" . 'Context (extract data from it): """' . "\n" . $context . "\n" .  '"""' . "\n";
    }

    public function addInput(string $input) {
         $this->data .=  "\n" . 'Input: """' . "\n" . $input . "\n" .  '"""' . "\n";
    }

    public function addRule(string $rule) {
        $this->rules .= $rule;
    }

    public function __toString() {
        return $this->data . "\n" . $this->rules;
    }
}
