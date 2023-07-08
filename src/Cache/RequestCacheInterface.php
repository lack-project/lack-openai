<?php

namespace Lack\OpenAi\Cache;

interface RequestCacheInterface
{

    public function set(string $key, string $result) : void;
    
    public function get(string $key) : string|null;
    
    
    
}