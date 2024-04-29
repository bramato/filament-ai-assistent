<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiBookService;
use Bramato\FilamentAiAssistent\Services\AiCoverBookService;
use App\Models\Book;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateCoverBookJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;

    public Book $book;
    /**
     * Create a new job instance.
     */
    public function __construct(Book $book)
    {
        $this->book = $book;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiCoverBookService::create($this->book)->generate();
    }
}
