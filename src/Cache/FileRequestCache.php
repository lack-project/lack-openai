<?php

namespace Lack\OpenAi\Cache;

use Phore\FileSystem\PhoreFile;

class FileRequestCache implements RequestCacheInterface
{
    
        public function __construct(
            public string|PhoreFile $cacheFile = "/tmp/openai_cache.json"
        )
        {
            $this->cacheFile = phore_file($this->cacheFile);
            if ($this->cacheFile->exists())
                $this->cacheFile->assertFile();
            else
                $this->cacheFile->set_json(["init"=>true]);
        }
    
        public function set(string $key, string $result) : void {
            $data = $this->cacheFile->get_json();
            $data[$key] = $result;
            $this->cacheFile->set_json($data);
        }
        
        public function get(string $key) : string|null {
            $data = $this->cacheFile->get_json();
            if ( ! isset ($data[$key]))
                return null;
            return $data[$key];
        }
}