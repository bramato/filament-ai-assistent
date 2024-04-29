<?php

namespace Bramato\FilamentAiAssistent\Jobs;

use Bramato\FilamentAiAssistent\Services\AiBookService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateRequest implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;

    public int $id;
    public $user_id;
    /**
     * Create a new job instance.
     */
    public function __construct($id, $user_id)
    {
        $this->id = $id;
        $this->user_id = $user_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AiBookService::create($this->id,$this->user_id)->generate();
    }
}
