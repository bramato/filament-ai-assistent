<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiBookService;
use Bramato\FilamentAiAssistent\Services\AiChapterEvidenceService;
use Bramato\FilamentAiAssistent\Services\AiQuizService;
use App\Models\Content;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateEvidence implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;


    public AiBookService $service;
    public Content $chapter;
    /**
     * Create a new job instance.
     */
    public function __construct(Content $chapter)
    {
        $this->chapter = $chapter;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiChapterEvidenceService::create($this->chapter)->generate();
    }
}
