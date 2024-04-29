<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiVoiceEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiVoiceModelEnum;
use App\Models\Book;
use App\Models\Content;
use App\NotificationTrait;

class AiChapterAudioService
{
    public AiClientService $ai;
    public Content $chapter;

    public function __construct(Content $chapter, AiClientService $ai)
    {
        $this->chapter = $chapter;
        $this->ai = $ai;
    }

    public static function create(Content $chapter): AiChapterAudioService
    {
        $ai = AiClientService::create($chapter->user_id);
        return new self($chapter, $ai);
    }


    public function generate(): bool
    {
        //elimina tag html da $this->chapter->text
        $url = $this->ai->text2Speech($this->chapter->text??'', OpenAiVoiceEnum::nova, OpenAiVoiceModelEnum::tts1Hd);
        return true;

    }



}
