<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiChapterService;
use App\Models\Content;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateChapterJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;

    public Content $chapter;
    public bool $has_quiz;
    public bool $has_evidence;
    public bool $has_video;
    /**
     * Create a new job instance.
     */
    public function __construct(Content $chapter, bool $has_quiz, bool $has_evidence,bool $has_video)
    {
        $this->chapter = $chapter;
        $this->has_quiz = $has_quiz;
        $this->has_video = $has_video;
        $this->has_evidence = $has_evidence;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiChapterService::create($this->chapter)->generate($this->has_quiz, $this->has_video, $this->has_evidence);
    }
}
