<?php

namespace HuiZhiDa\Gateway\Domain\Models;

class MessageContent
{
    public string $text = '';
    public string $mediaUrl = '';
    public string $mediaType = '';
    public array $extra = [];

    public function toArray(): array
    {
        $data = [];
        
        if ($this->text) {
            $data['text'] = $this->text;
        }
        
        if ($this->mediaUrl) {
            $data['media_url'] = $this->mediaUrl;
            $data['media_type'] = $this->mediaType;
        }
        
        if (!empty($this->extra)) {
            $data['extra'] = $this->extra;
        }
        
        return $data;
    }
}
