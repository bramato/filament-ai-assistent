<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiBookService;
use Bramato\FilamentAiAssistent\Services\AiChapterAudioService;
use App\Models\Content;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateAudioContentJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;


    public Content $content;
    /**
     * Create a new job instance.
     */
    public function __construct(COntent $content)
    {
        $this->content = $content;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiChapterAudioService::create($this->content)->generate();
    }
}
