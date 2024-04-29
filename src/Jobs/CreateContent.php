<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiBookService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateContent implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;


    public int $book_id;
    public int $id;
    public AiBookService $service;
    public int $user_id;
    public int $token_max;
    /**
     * Create a new job instance.
     */
    public function __construct($book_id, $id, $token_max, $user_id)
    {
        $this->book_id = $book_id;
        $this->id = $id;
        $this->user_id = $user_id;
        $this->token_max = $token_max;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->service = app(AiBookService::class);
        $this->service->createSection($this->book_id, $this->id, $this->token_max,$this->user_id);
    }
}
