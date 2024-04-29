<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use App\Domains\Tavus\Services\TavusClientService;
use App\Models\Content;
use App\NotificationTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiChapterEvidenceService
{
    public AiClientService $ai;
    public Content $chapter;

    public int $user_id;

    public array $jsonModel = [
        'Testo' => 'Testo modificato'
    ];

    public function __construct(Content $chapter, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $chapter->user_id;
        $this->chapter = $chapter;
    }

    public static function create(Content $chapter): AiChapterEvidenceService
    {
        $ai = AiClientService::create($chapter->user_id);
        return new self($chapter, $ai);
    }


    public function generate(): bool
    {

        $this->ai->setModel(OpenAiModelEnum::GPT3_5_1106);
        $this->ai->token = 150000;
        $this->ai->temperature = 0.8;
        $this->ai->sys("Aggiungi tag html al testo html secondo le istruzioni.");
        $this->ai->setJsonModelFromArray($this->jsonModel);
        $this->ai->message('Aggiungi il tag strong a tutti i concetti fondamentali per ogni frase e che possono essere utili ai fini dello studio, senza esaregare.');
        $this->ai->message($this->chapter->text);
        $this->ai->send();

        $data = $this->ai->getLastMessage();

        if(($data['Testo']??'')===''){
            Log::error('Formattazione non riuscita');
            return false;
        }
        $this->chapter->text = $data['Testo'];
        $this->chapter->save();
        return true;
    }





}
