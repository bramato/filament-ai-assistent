<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Jobs\CreateEvidence;
use Bramato\FilamentAiAssistent\Jobs\CreateQuiz;
use App\Enums\QueueEnum;
use App\Models\Book;
use App\Models\Content;
use App\Models\Wikipedia;
use App\Models\WikipediaMedia;
use App\NotificationTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Rajentrivedi\TokenizerX\TokenizerX;

class AiChapterService
{
    use NotificationTrait;
    public AiClientService $ai;
    public int $chapter_id;
    public int $nrChapter;
    public int $nrSubChapther;
    public array $paths;

    public int $user_id;

    public array $jsonModel = [
        'Paragrafi' =>
        [
            '1' => "Paragrafo 1",
            '2' => "Paragrafo 2",
            '3' => "Paragrafo 3"
        ],
        'Immagini_id' => 'id immagini divisi dalla virgola'
    ];

    public function __construct(int $nrChapter, int $nrSubChapter, array $paths, int $chapter_id, int $user_id, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $user_id;
        $this->nrChapter = $nrChapter;
        $this->nrSubChapther = $nrSubChapter;
        $this->paths = $paths;
        $this->chapter_id = $chapter_id;
    }

    public static function create(Content $chapter): AiChapterService
    {
        $chapterData = explode('.',$chapter->index);
        $nrChapter = $chapterData[0];
        $nrSubChapter = $chapterData[1];
        $book = Book::where('id',$chapter->book_id)->first();
        $paths = json_decode($book->structure_json,true);
        $ai = AiClientService::create($chapter->user_id);
        return new self($nrChapter, $nrSubChapter, $paths, $chapter->id, $chapter->user_id, $ai);
    }


    public function generate(bool $has_quiz = false, bool $has_video = false, bool $has_evidence = false): bool
    {

        $chapter = Content::find($this->chapter_id);
        if ($chapter === null) {
            return false;
        }
        $research = '';
        $media = '';
        try {
            $wiki_id = explode(',', $chapter->research);
            $wiki_id = array_map('intval', $wiki_id);
            $research = Wikipedia::whereIn('id',$wiki_id)->select('id','title','wikitext')->get()->toArray();
            $media = WikipediaMedia::whereIn('page_id',$wiki_id)->get()->toArray();
            $research = json_encode($research, true);
            $media = json_encode($media);
        }catch (\Throwable $e){
            Log::error($e->getMessage());
        }
        $countToken = TokenizerX::count($research);
        $this->ai->setModel(OpenAiModelEnum::GPT3_5_1106);
        if($countToken > 10000){
            $this->ai->setModel(OpenAiModelEnum::GPT4_TURBOPREVIEW);
        }
        $this->ai->token = 150000;
        $this->ai->temperature = 0.8;
        $this->ai->sys('Sei un aiutante per scrivere libri.');
        $this->ai->sys('Questa è la struttura del libro che stiamo scrivendo:'.json_encode($this->paths));
        if($research!==''){
            $this->ai->sys('Questa è la ricerca da cui prendere spunto per la creazione di questa sezione: '.$research);
        }
        if($media!==''){
            $this->ai->sys('Queste sono le immagini disponibili tra cui puoi scegliere (scegli massimo 3 id): '.$media);
        }
        $this->ai->sys('Inizia il capitolo senza menzionare il nome del capitolo.');
        $this->ai->sys('Crea da 5 a 8 paragrafi di circa 600 parole');
        $this->ai->setJsonModelFromArray($this->jsonModel);
        //$this->ai->sys('Testo capitoli già scritto Usa questo testo come riferimento per non scrivere cose simili o cose duplicate:'.$this->getRecap($chapter->book_id));

        $this->ai->message('Genera il contenuto del capitolo nr.'.$this->nrChapter.'.'.$this->nrSubChapther.'.');

        $this->ai->send($this->ai->model->value);


        $paragrafi = $this->ai->getLastMessage();

        //$this->addRecap($chapter->book_id, $chapter->index.PHP_EOL.$chapter->title.PHP_EOL.$chapter->book_id);
        $capitolo = '';
        if(count($paragrafi['Paragrafi']??[])===0){
            $this->ai->message('Manca qualcosa rispassami il Json completo');
            $this->ai->send($this->ai->model->value);
            $paragrafi = $this->ai->getLastMessage();
        }

        foreach ($paragrafi['Paragrafi'] as $paragrafo){
            //$this->addRecap($chapter->book_id, $paragrafo);
            $capitolo = $capitolo.PHP_EOL.'<p>'.$paragrafo.'</p>';
        }
        $images_data = str_replace(', ',',',$paragrafi['Immagini_id']??'');
        $images = explode(',',$images_data);

        $chapter->text = $capitolo;
        $chapter->token_used = $this->ai->token_used;
        $chapter->image_1 = $images[0]??null;
        $chapter->image_2 = $images[1]??null;
        $chapter->image_3 = $images[2]??null;
        $chapter->save();
        if($has_quiz){
            CreateQuiz::dispatch($chapter)->onQueue(QueueEnum::BOOK_SECONDARY->value);
        }
        if($has_video){
            AiTavusService::create($chapter)->generate();
        }
        if($has_evidence){
            CreateEvidence::dispatch($chapter)->onQueue(QueueEnum::BOOK_SECONDARY->value);
        }
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
