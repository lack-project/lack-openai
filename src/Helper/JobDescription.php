<?php

namespace Lack\OpenAi\Helper;

class JobDescription
{
    private string $data = "";
    private string $rules = "";

    public function addContext(string $context) {
        $this->data .= "\n" . '<CONTEXT>' . "\n" . $context . "\n" .  '</CONTEXT>' . "\n";
    }

    public function addInput(string $input) {
         $this->data .=  "\n" . '<INPUT>' . "\n" . $input . "\n" .  '</INPUT>' . "\n";
    }

    public function addRule(string $rule) {
        $this->rules .= $rule;
    }

    public function __toString() {
        return $this->data . "\n" . $this->rules;
    }
}
