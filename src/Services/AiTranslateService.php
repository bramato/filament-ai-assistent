<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Dto\AiTranslateDto;
use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiTextFormat;
use Bramato\FilamentAiAssistent\Jobs\AiTranslateJob;
use Bramato\FilamentAiAssistent\Jobs\CreateChapterJob;
use Bramato\FilamentAiAssistent\Jobs\CreateCoverBookJob;
use Bramato\FilamentAiAssistent\Jobs\CreateRequest;
use App\Models\Book;
use App\Models\Content;
use App\Models\Request;
use App\NotificationTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class AiTranslateService
{
    use NotificationTrait;
    public AiClientService $ai;
    public string $text;
    public array $languagesDestination;
    public int $user_id;
    public ?OpenAiTextFormat $textFormat;

    public array $jsonModel = [
        'html_translated' => [
        "Codice_ISO_lingua_1" => "<div>Testo tradotto in lingua 1</div>",
        "Codice_ISO_lingua_2" => "<h1>Testo tradotto in lingua 2</h1>",
        "Codice_ISO_lingua_n" => "Testo tradotto in lingua n"
            ]
    ];

    public function __construct(string $text, array $languageDestination, int $user_id, AiClientService $ai, OpenAiTextFormat $textFormat = null)
    {
        $this->ai = $ai;
        $this->user_id = $user_id;
        $this->text = $text;
        $this->languagesDestination = $languageDestination;
        $this->textFormat = $textFormat;
    }

    public static function create(string $text, array $languageDestination, int $user_id, OpenAiTextFormat $textFormat = null): AiTranslateService
    {
        $ai = AiClientService::create($user_id);
        return new self($text, $languageDestination, $user_id, $ai, $textFormat);
    }

    /**
     * @throws UnknownProperties
     * @throws ValidationException
     */
    public function generate()
    {

        $this->ai->setModel(OpenAiModelEnum::GPT3_5_1106);
        $this->ai->max_tokens = 4000;
        //Calcolo token ingresso
        $token = TokenCalculatorService::create($this->ai->model)->execute($this->text);

        $totalLanguages = count($this->languagesDestination);
        $rate = (int) ($this->ai->max_tokens / $token->tokenIn);
        $languagesPerRequest = max(1, $rate); // Assicura almeno una lingua per richiesta

        if ($totalLanguages > $languagesPerRequest) {
            $languageChunks = array_chunk($this->languagesDestination, $languagesPerRequest);
        } else {
            $languageChunks = [$this->languagesDestination];
        }

        $results = [];

        foreach ($languageChunks as $chunk) {
            $chunkLanguages = implode(', ', $chunk);
            $this->ai->temperature = 0.3;
            $this->ai->clearChat();
            $this->ai->sys('Sei un traduttore e programmatore web. Scrivi codice HTML. Assicurati di chiudere tutti i tag html.');
            $this->ai->setJsonModelFromArray($this->jsonModel);
            $this->ai->sys("Per favore, traduci completamente il testo HTML, per localizzare il sito, senza tralasciare niente, verso le lingue ". $chunkLanguages .", mantenendo inalterata la struttura HTML.");
            $this->ai->message($this->text);
            $this->ai->send($this->textFormat->value ?? '');

            $data = $this->ai->getLastMessage();
            try {
                $dto = new AiTranslateDto($data);
                foreach ($dto->html_translated as $iso => $language){
                    $results[$iso] = $language;
                }
            }catch (\Throwable $e){
                Log::error($e->getMessage());
            }
        }
        return $results;
    }




}
