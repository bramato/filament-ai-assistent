<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiSizeImages;
use Bramato\FilamentAiAssistent\Enum\OpenAiStyleImages;
use Bramato\FilamentAiAssistent\Jobs\CreateCoverBookJob;
use App\Enums\QueueEnum;
use App\Models\Book;
use App\NotificationTrait;

class AiImageFromTextService
{
    use NotificationTrait;
    public AiClientService $ai;
    public Book $book;
    public int $user_id;


    public function __construct(Book $book, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $book->user_id;
        $this->book = $book;
    }

    public static function create(Book $book): AiImageFromTextService
    {
        $ai = AiClientService::create($book->user_id);
        return new self($book, $ai);
    }

    public function execute(Book $book)
    {
        CreateCoverBookJob::dispatch($book)->onQueue(QueueEnum::BOOK->value);
    }

    public function generate()
    {
        $prompt = 'Crea una immagine con uno stile adatto a:'.$this->book->write_for.'. Deve rappresentare al meglio questo argomento:'.$this->book->title.':'.$this->book->recap.'. Usa un formato 16:9 verticale.';
        $url = $this->ai->image($prompt, OpenAiSizeImages::s1024x1792, OpenAiStyleImages::vivid);
        $this->book->cover = $url;
        $this->book->save();
        return true;
    }



}
