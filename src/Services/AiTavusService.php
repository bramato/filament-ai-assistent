<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Jobs\CreateQuiz;
use App\Domains\Tavus\Services\TavusClientService;
use App\Models\Book;
use App\Models\Content;
use App\Models\Wikipedia;
use App\Models\WikipediaMedia;
use App\NotificationTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiTavusService
{
    use NotificationTrait;
    public AiClientService $ai;
    public Content $chapter;

    public int $user_id;

    public array $jsonModel = [
        'Riassunto' => 'Testo del riassunto'
    ];

    public function __construct(Content $chapter, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $chapter->user_id;
        $this->chapter = $chapter;
    }

    public static function create(Content $chapter): AiTavusService
    {
        $ai = AiClientService::create($chapter->user_id);
        return new self($chapter, $ai);
    }


    public function generate(): bool
    {

        $this->ai->setModel(OpenAiModelEnum::GPT3_5_1106);
        $this->ai->token = 150000;
        $this->ai->temperature = 0.8;
        $this->ai->sys("Ho creato un'applicazione in cui a sinistra c'è un testo e a destra c'è un video con uno speaker che parla.
Il tuo compito e creare il testo dello speaker che fa un riassunto del testo in modo sintetico, informale e allegro.
Il testo dello speaker deve essere un riassunto abbastanza breve (deve essere letto in un minuto).
Inizia sempre con frasi tipo: 'In questo paragrafo parleremo' o 'in questa sezione affronteremo'.");
        $this->ai->setJsonModelFromArray($this->jsonModel);
        $this->ai->message($this->chapter->text);
        $this->ai->send();


        $data = $this->ai->getLastMessage();

        //$this->addRecap($chapter->book_id, $chapter->index.PHP_EOL.$chapter->title.PHP_EOL.$chapter->book_id);
        $riassunto = '';
        if(($data['Riassunto']??'')===''){
            Log::error('Riassunto non riuscito');
            return false;
        }
        $riassunto = $data['Riassunto'];
        TavusClientService::create($this->user_id)->createVideo($riassunto,$this->chapter->id);
        return true;
    }

    public static function resetCache(int $book_id){
        cache()->forget('book_'.$book_id);
    }

    public function addRecap(int $book_id, $testo){
        $recap = Cache::get('book_'.$book_id, '').$testo;
        Cache::put('book_'.$book_id, $recap);
    }

    public function getRecap(int $book_id){
        return Cache::get('book_'.$book_id,'');
    }



}
