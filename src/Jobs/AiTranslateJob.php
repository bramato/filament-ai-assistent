<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiCoverBookService;
use Bramato\FilamentAiAssistent\Services\AiTranslateService;
use App\Models\Book;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AiTranslateJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;

    public string $text;
    public string $languageDestination;
    /**
     * Create a new job instance.
     */
    public function __construct(string $text, $languageDestination)
    {
        $this->text = $text;
        $this->languageDestination = $languageDestination;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiTranslateService::create($this->text, $this->languageDestination)->generate();
    }
}
