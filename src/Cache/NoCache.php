<?php

namespace Lack\OpenAi\Cache;

use Phore\FileSystem\PhoreFile;

class NoCache implements RequestCacheInterface
{
    
        public function __construct(
        )
        {
        }
    
        public function set(string $key, string $result) : void {
            
        }
        
        public function clear() {
        }
        
        public function get(string $key) : string|null {
            return null;
        }
}