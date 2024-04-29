<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Jobs\CreateChapterJob;
use Bramato\FilamentAiAssistent\Jobs\CreateCoverBookJob;
use Bramato\FilamentAiAssistent\Jobs\CreateRequest;
use App\Domains\Books\Jobs\CompleteBookJob;
use App\Domains\Wikipedia\Services\WikipediaService;
use App\Enums\QueueEnum;
use App\Models\Book;
use App\Models\Content;
use App\Models\Request;
use App\NotificationTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;

class AiBookService
{
    public AiClientService $ai;
    public int $request_id;
    public int $user_id;

    public array $jsonModel = [
        "Capitoli" => [
            [
                "NrCapitolo" => "Numero del capitolo",
                "SottoCapitoli" => [
                    [
                        "nrSottoCapitolo" => "Numero del sotto-capitolo",
                        "titolo" => "Titolo del sotto-capitolo",
                        "riassunto" => "Un breve riassunto dei punti chiave e degli eventi del sotto-capitolo. (max ",
                        "research" => "id fonti da utilizzare divisi da virgola"
                    ]
                ]
            ]
        ],
    ];

    public array $jsonModelRecap = [
        "Titolo" => 'Titolo del libro',
        "Recap" => 'Riassunto libro',
        "Obbiettivo" => 'Obiettivo del libro',
        "Stile_scrittura" => 'Stile prosa del libro',
        "Target" => 'A chi è destinato il libro'
    ];

    public function __construct(int $request_id, int $user_id, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->user_id = $user_id;
        $this->request_id = $request_id;
    }

    public static function create(int $request_id, int $user_id): AiBookService
    {
        $ai = AiClientService::create($user_id);
        return new self($request_id, $user_id, $ai);
    }

    public function execute(int $request_id)
    {
        CreateRequest::dispatch($request_id, Auth::id())->onQueue(QueueEnum::BOOK->value);
    }

    public function generate()
    {


        $book_request = Request::find($this->request_id);
        if ($book_request === null) {
            return false;
        }

        $keyWords = AiKeyWordsService::create($book_request->title.':'.$book_request->text, $this->user_id)->generate();
        //Popola ricerca;
        $researchData = WikipediaService::create($this->user_id)->searchWikiDataFromKeyWordsArray($keyWords)->getDataFinded();




        $this->ai->setModel(OpenAiModelEnum::GPT4_TURBOPREVIEW);
        $this->ai->token = 12000;
        $this->ai->temperature = 0.2;
        $this->ai->sys('Sei un aiutante per scrivere libri.');
        $this->ai->sys('Stile scrittura da utilizzare:"'. self::getScrittori()[$book_request->write_like]??'Usa uno stile classico."');
        $this->ai->sys('Soggetto libro: ' . $book_request->text);
        $this->ai->sys('Genere libro: ' . $book_request->book_type);
        $this->ai->sys('Target libro: ' . $book_request->write_for);
        $this->ai->sys('Research Data: ' . $researchData);
        $this->ai->sys('Nr Capitoli: '.$book_request->chapter_count);
        $this->ai->sys('Nr Sotto-capitoli per capitolo: '.$book_request->subchapter_count);
        $this->ai->sys('Tipologia argomenti, personaggi e luoghi: argomenti reali realmente esistiti, nessun argomento o personaggio o storia devono essere inventati.');
        $this->ai->setJsonModelFromArray($this->jsonModel);
        $this->ai->message('Genera la struttura del libro utilizzando la research data e annotando gli id delle sezioni research da utilizzare.');

        $capitoli = [];

        for ($i = 1; $i <= $book_request->chapter_count; $i += 5) {
            $end = min($i + 4, $book_request->chapter_count);
            $this->ai->message("Crea i capitoli da $i a $end, usa la research data per recuperare gli id da leggere per completare il task successivo.");
            $this->ai->send();
            $data = $this->ai->getLastMessage();
            foreach ($data['Capitoli'] as $capitolo){
                $capitoli[] = $capitolo;
            }
        }
        $this->ai->setJsonModelFromArray($this->jsonModelRecap);
        $this->ai->message('Crea i dati aggiuntivi del libro seguendo il JSON appena passato');
        $this->ai->send();
        $data = $this->ai->getLastMessage();
        $fullData = $data;
        $fullData['Capitoli'] = $capitoli;
        $book = new Book();
        $book->title = $data['Titolo']??'Titolo non trovato';
        $book->structure_json = json_encode($fullData);
        $book->chapter_count = count($capitoli);
        $totaleSottocapitoli = 0;
        foreach ($capitoli as $capitolo) {
            $totaleSottocapitoli += count($capitolo['SottoCapitoli']);
        }


        $book->subchapter_count = $totaleSottocapitoli;
        $book->request_id = $book_request->id;
        $book->recap = $data['Recap'];
        $book->write_for = $data['Target'];
        $book->path = '';
        $book->characters_json = '';
        $book->locations_json = '';
        $book->section_count = $book_request->section_count;
        $book->partial_summary = $data['Obbiettivo'];
        $book->user_id = $this->user_id;
        $book->save();
        CreateCoverBookJob::dispatch($book)->onQueue(QueueEnum::BOOK->value);
        $chainJobs = [];
        foreach ($capitoli as $nrCapitoloIndex => $capitolo){
            $nrCapitolo = $capitolo['NrCapitolo']??$nrCapitoloIndex+1;
            foreach ($capitolo['SottoCapitoli'] as $nrSottoCapitoloIndex => $sottocapitolo){
                $nrSottoCapitolo = $sottocapitolo['nrSottoCapitolo']??$nrSottoCapitoloIndex+1;
                $chapter = new Content();
                $chapter->index = $nrCapitolo.'.'.$nrSottoCapitolo;
                $chapter->title = $sottocapitolo['titolo'];
                $chapter->summary = $sottocapitolo['riassunto'];
                $chapter->user_id = $this->user_id;
                $chapter->book_id = $book->id;
                $chapter->research = str_replace(', ',',',$sottocapitolo['research']);
                $chapter->save();
                $chainJobs[] = new CreateChapterJob($chapter, $book_request->has_quiz, $book_request->has_evidence, $book_request->has_video);
            }
        }
        $chainJobs[] = new CompleteBookJob($book->id);
        Bus::chain($chainJobs)->onQueue(QueueEnum::BOOK->value)->dispatch();
        return true;
    }


    public static function getScrittori():array{
        return [
            'Scrittura Minimalista' => "Prosa essenziale e priva di fronzoli, con un'enfasi sulla chiarezza e la brevità, mirando a trasmettere il massimo significato con il minimo di parole.",
            'Narrativa Epistolare' => 'Utilizzo di lettere, diari o altri documenti personali per raccontare una storia, offrendo una prospettiva intima sui personaggi e sugli eventi.',
            'Realismo Magico' => "Incorpora elementi magici o soprannaturali nella realtà quotidiana, sfumando i confini tra il reale e l'immaginario in modo sottile e naturale.",
            'Stream of Consciousness' => "Tecnica narrativa che cerca di catturare il flusso di pensieri e sensazioni che attraversano la mente dei personaggi, spesso senza una struttura ordinata.",
            'Scrittura Non Lineare' => 'Struttura narrativa che rompe la sequenza cronologica, utilizzando flashback, flashforward o frammentazione per costruire la storia.',
            'Poesia Visiva' => "Uso di parole e immagini per creare composizioni che sfruttano l'aspetto visivo del testo, oltre al suo significato letterale.",
            'Meta Narrativa' => 'Tecnica che gioca con la struttura narrativa, mettendo in discussione la natura della narrazione e coinvolgendo il lettore in riflessioni sul processo di scrittura.',
            'Narrativa Gotica' => 'Storie che esplorano temi di terrore, mistero e romanticismo, ambientate spesso in luoghi oscuri e antichi come castelli o monasteri.',
            'Cyberpunk' => 'Genere che fonde elementi di fantascienza e narrativa distopica, concentrato su storie ambientate in futuri tecnologicamente avanzati ma disumanizzanti.',
            'Scrittura Epigrammatica' => 'Uso di aforismi, massime o brevi riflessioni cariche di acume e ironia, spesso per sottolineare verità pungenti o paradossi sociali.',
            'Scrittura Espositiva' => "Presentazione chiara e diretta dei fatti, concetti e istruzioni, utilizzando una struttura logica che facilita la comprensione e l'apprendimento del lettore.",
'Scrittura Didattica' => "Utilizzo di esempi, analogie e domande frequenti per spiegare concetti complessi in modo che siano accessibili a un pubblico ampio, inclusi lettori senza una formazione specifica nell'argomento trattato.",
'Stile Modulare' => 'Organizzazione del contenuto in moduli o unità che possono essere letti indipendentemente o in sequenza, permettendo al lettore di scegliere percorsi personalizzati di apprendimento.',
'Scrittura Analitica' => 'Esame approfondito di temi e argomenti, scomponendoli in parti per una migliore comprensione, spesso includendo confronti, contrasti e analisi critiche.',
'Approccio Problema-Soluzione' => 'Presentazione di un problema seguita da una o più soluzioni dettagliate, utile per manuali tecnici, guide pratiche e testi che mirano a insegnare come affrontare sfide specifiche.',
'Scrittura Procedurale' => 'Fornitura di istruzioni passo dopo passo per eseguire compiti o processi, chiaro e diretto, spesso accompagnato da illustrazioni o diagrammi esplicativi.',
'Narrativa Informativa' => "Incorporazione di elementi narrativi, come storie o casi studio, per illustrare applicazioni pratiche di teorie o concetti, rendendo l'apprendimento più coinvolgente e memorabile.",
'Stile Socratico' => 'Uso di domande e risposte per guidare il lettore attraverso un processo di scoperta e comprensione, ispirato al metodo dialettico di Socrate.',
'Scrittura Comparativa' => 'Confronto sistematico tra teorie, metodi o casi per evidenziare somiglianze e differenze, facilitando la comprensione critica e la valutazione da parte del lettore.',
'Scrittura Integrativa' => 'Integrazione di diverse fonti e prospettive su un argomento, fornendo una visione olistica e multidisciplinare che arricchisce la comprensione del lettore.',
            'Stephen King' => 'Stile incisivo e dettagliato, con un focus sulla psicologia dei personaggi e la creazione di atmosfere inquietanti.',
            'J.K. Rowling' => 'Linguaggio chiaro e immaginifico, capacità di costruire mondi fantastici dettagliati e coinvolgenti.',
            'J.R.R. Tolkien' => 'Linguaggio ricco e arcaico, con dettagliate descrizioni di mondi, razze e lingue inventate.',
            'George R.R. Martin' => 'Narrativa corposa con molteplici punti di vista, enfasi sui dettagli e sugli intrighi complessi.',
            'Agatha Christie' => 'Stile diretto e conciso, con dialoghi incisivi e trame ingegnose.',
            'Arthur Conan Doyle' => 'Prosa lucida e meticolosa, con un attento uso del ragionamento deduttivo e descrizioni vivide.',
            'Dan Brown' => 'Ritmo serrato con suspense costante, mescolando storia, arte e codici in narrazioni moderne.',
            'Umberto Eco' => 'Stile erudito con ricchezza di riferimenti culturali e storici, intrecciando mistero e conoscenza.',
            'Leopardi' => 'Poesia profonda con riflessioni filosofiche, esprimendo malinconia e bellezza nella natura umana.',
            'Dante Alighieri' => 'Linguaggio elevato e simbolico, con uso di allegorie per esplorare tematiche morali e spirituali.',
            'Jane Austen' => 'Ironia sottile e critica sociale, con attenzione alle dinamiche relazionali e alla psicologia dei personaggi.',
            'Leo Tolstoy' => 'Ampie narrazioni con profondità psicologica e morale, esplorando la condizione umana attraverso dettagli storici.',
            'William Shakespeare' => 'Uso maestoso della lingua, con giochi di parole, metafore e soliloqui che esplorano l\'essenza umana.',
            'F. Scott Fitzgerald' => 'Prosa elegante e simbolica, ritraendo la fragilità del sogno americano e la decadenza sociale.',
            'Virginia Woolf' => 'Tecnica del flusso di coscienza e introspezione profonda, rompendo le convenzioni narrative tradizionali.',
            'Mark Twain' => 'Umorismo e satira sociale, con una narrazione vivace e personaggi memorabili.',
            'Herman Melville' => 'Narrativa epica e simbolica, con profonde riflessioni filosofiche intrise di dettagli naturalistici.',
            'Gabriel García Márquez' => 'Stile lirico e uso del realismo magico, fondendo il quotidiano con l\'elemento fantastico.',
            'Harper Lee' => 'Narrativa diretta e morale, esplorando temi di giustizia e integrità attraverso occhi innocenti.',
            'Kurt Vonnegut' => 'Scrittura satirica e stile esistenzialista, spesso intrecciando elementi di fantascienza con critica sociale.',
            'Charles Dickens' => 'Descrizioni dettagliate e critica sociale, con personaggi memorabili e trame elaborate.',
            'H.P. Lovecraft' => 'Stile barocco e atmosfere opprimenti, con temi di orrore cosmico e l\'inconoscibile.',
            'Jorge Luis Borges' => 'Prosa intellettuale e tematiche metafisiche, esplorando labirinti, specchi e universi infiniti.',
            'Ernest Hemingway' => 'Stile sobrio e diretto, con dialoghi taglienti e narrazione sottintesa.',
            'Toni Morrison' => 'Prosa poetica e intensa, esplorando le radici culturali e le esperienze afroamericane.',
            'Roald Dahl' => 'Stile giocoso e a volte macabro, con un talento unico nel catturare l\'immaginazione dei bambini.',
            'Jules Verne' => 'Dettagliate avventure scientifiche, anticipando invenzioni e esplorazioni con un linguaggio chiaro e accessibile.',
            'Emily Brontë' => 'Narrativa passionale e tormentata, esplorando la psicologia profonda e i paesaggi come specchio dell\'anima.',
            'Oscar Wilde' => 'Eleganza stilistica e acume intellettuale, con un approccio satirico verso la società.',
            'Franz Kafka' => 'Stile asciutto e atmosfere surreali, creando mondi angoscianti che riflettono l\'assurdità dell\'esistenza.',
            'Alberto Angela' => 'Linguaggio chiaro e appassionato, rendendo la storia e la scienza accessibili e coinvolgenti.',
            'Giorgio Faletti' => 'Suspense e ritmo nella narrazione, con personaggi ben costruiti e colpi di scena.',
            'Alessandro Baricco' => 'Stile narrativo elegante e fluido, con una predilezione per temi onirici e romantici.',
            'Italo Calvino' => 'Narrativa leggera e filosofica, con esplorazioni inventive di concetti e strutture narrative.',
            'Giovanni Verga' => 'Realismo e attenzione ai dettagli della vita quotidiana, con una forte connessione con il territorio.',
            'Luigi Pirandello' => 'Esplorazione della relatività della realtà e dell\'identità, con uno stile narrativo innovativo.',
            'Dino Buzzati' => 'Atmosfere surreali e tematiche esistenziali, con una prosa capace di creare tensione e riflessione.',
        ];
    }


}
