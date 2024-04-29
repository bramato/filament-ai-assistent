<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Jobs\CreateChapterJob;
use Bramato\FilamentAiAssistent\Jobs\CreateCoverBookJob;
use Bramato\FilamentAiAssistent\Jobs\CreateRequest;
use App\Models\Book;
use App\Models\Content;
use App\Models\Request;
use App\NotificationTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;

class AiKeyWordsService
{
    use NotificationTrait;
    public AiClientService $ai;
    public string $text;
    public int $user_id;

    public array $jsonModel = [
        "KeyWords" => "Parole chiave divise da virgola senza spazi (massimo 5)",
    ];

    public function __construct(string $text, int $user_id, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $user_id;
        $this->text = $text;
    }

    public static function create(string $text, int $user_id): AiKeyWordsService
    {
        $ai = AiClientService::create($user_id);
        return new self($text, $user_id, $ai);
    }

    public function generate():array
    {

        $this->ai->setModel(OpenAiModelEnum::GPT3_5_0125);
        $this->ai->token = 12000;
        $this->ai->temperature = 0.2;
        $this->ai->sys('Sei un generatore di keywords.');
        $this->ai->setJsonModelFromArray($this->jsonModel);
        $this->ai->message("Genera 3 parole chiave del soggetto principale del testo senza divagare (niente frasi solo parole chiave es: Antico egitto, Egitto, Astronomia) in inglese: " . $this->text);
        $this->ai->send();

        $data = $this->ai->getLastMessage();

        return explode(',',$data['KeyWords']);
    }



}
