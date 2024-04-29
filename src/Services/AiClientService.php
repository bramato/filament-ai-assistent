<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiResponseTypeEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiSizeImages;
use Bramato\FilamentAiAssistent\Enum\OpenAiStyleImages;
use Bramato\FilamentAiAssistent\Enum\OpenAiVoiceEnum;
use Bramato\FilamentAiAssistent\Enum\OpenAiVoiceModelEnum;
use App\Models\AiRequest;
use App\NotificationTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI;

class AiClientService
{
    use NotificationTrait;


    public OpenAiModelEnum $model;
    public OpenAiResponseTypeEnum $responseType;
    public int $token = 1000;
    public bool $force_json = true;
    public int $max_tokens = 4000;
    public float $temperature = 0.1;

    public array $messages = [];
    public int $token_used = 0;

    private array $jsonModel = [];

    private  OpenAI\Client $client;
    private int $user_id;

    public function __construct(OpenAI\Client $client, int $user_id)
    {
        $this->client = $client;
        $this->user_id = $user_id;
        $this->model = OpenAiModelEnum::GPT3_5;
        $this->responseType = OpenAiResponseTypeEnum::json;
    }

    public function setForceJson(bool $value = true):self{
        $this->force_json = $value;
        return $this;
    }

    public function setMaxToken(int $maxToken):self{
        $this->token = $maxToken;
        return $this;
    }

    public function setModel(OpenAiModelEnum $model){
        $this->notyInfo('Set model: '.$model->value);
        $this->model = $model;
    }


    public static function create(int $user_id, string $apiKey = null): AiClientService
    {
        $client = OpenAI::client($apiKey ?? env('OPEN_AI_API_KEY'));
        return new self($client, $user_id);
    }

    public function sys(string $message):self {
        $this->messages[] = ['role' => 'system', 'content' => $message];
        return $this;
    }

    public function assistant(string $message):self {
        $this->messages[] = ['role' => 'assistant', 'content' => $message];
        return $this;
    }

    public function message(string $message):self {
        $this->messages[] = ['role' => 'user', 'content' => $message];
        return $this;
    }

    public function clearChat(): static
    {
        $this->messages = [];
        return $this;
    }

    public function send(string $tagCache = '', bool $is_complation = false):self {

        $keyAi = md5(json_encode($this->messages).$tagCache);
        $response = AiRequest::where('key',$keyAi)->first();
        if($response!==null){
            $message = $response->risposta;
        }else{
            if ($is_complation) {
                $data = [
                    'model' => $this->model,
                    'prompt' => $this->getCompletionMessages(),
                    'max_tokens' => $this->max_tokens,
                    'temperature' => $this->temperature,
                ];
                if($this->responseType === OpenAiResponseTypeEnum::json){
                    $data['response_format'] = ["type" => "json_object"];
                }
                $result = $this->client->completions()->create($data);
                $this->token_used = $result->usage->totalTokens + $this->token_used;
                $message =  $result->choices[0]->text;
            }
            else {
                $data = [
                    'model' => $this->model,
                    'messages' => $this->messages,
                    'max_tokens' => $this->max_tokens,
                    'temperature' => $this->temperature,
                ];
                if($this->responseType === OpenAiResponseTypeEnum::json){
                    $data['response_format'] = ["type" => "json_object"];
                }
                $result = $this->client->chat()->create($data);
                $this->token_used = $result->usage->totalTokens + $this->token_used;
                $message = $result->choices[0]->message->content;
            }
            $request = new AiRequest();
            $request->user_id = $this->user_id;
            $request->key = $keyAi;
            $request->bot = $this->model;
            $request->token = $this->token_used;
            $request->risposta = $message;
            $request->save();
        }


        $this->messages[] = ['role' => 'assistant', 'content' => $message];
        return $this;
    }
    public function getCompletionMessages(): string
    {
        $messages = '';
        foreach ($this->messages as $message){
            $messages = $messages.$message['content'].PHP_EOL;
        }
        return $messages;
    }

        public function getLastMessage(){
        $data = end($this->messages)['content'];
        if($this->responseType===OpenAiResponseTypeEnum::json){
            return json_decode($data,   true);
        }
        return $data;


    }

    public function setJsonModelFromArray(array $model):self{
        $data = json_encode($model);
        $this->sys('Questo è il modello json da usare per la risposta:'. $data);
        return $this;
    }


    /**
     * Crea un'immagine secondo i criteri specificati
     *
     * @param string $prompt
     * @param OpenAiSizeImages $size
     * @param OpenAiStyleImages|null $style
     *
     * @return string
     *
     * @throws \Throwable Se c'è un errore nell'operazione di creazione dell'immagine
     */
    public function image(string $prompt, OpenAiSizeImages $size, OpenAiStyleImages $style = null){
        try {
            // Generazione di una chiave univoca per la richiesta
            $keyAi = md5(json_encode($prompt.$size->value.$style->value));
            $storage = Storage::disk('covers');
            if($storage->exists($keyAi.'.png')){
                return $storage->url($keyAi.'.png');
            }
            // Dati per la richiesta di creazione dell'immagine
            $data = [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size->value,
                'style' => $style->value
            ];


            // Creazione dell'immagine con il client
            $immagini = $this->client->images()->create($data);
            // Creazione di una nuova richiesta AI
            $richiesta = new AiRequest();

            // Impostazione dei campi della richiesta
            $richiesta->user_id = $this->user_id;
            $richiesta->key = $keyAi;
            $richiesta->bot = 'dall-e-3';
            $richiesta->token = 0;
            $richiesta->risposta = serialize($immagini);

            // Salvataggio della richiesta
            $richiesta->save();

            $data = $immagini->data;

            $url = $data[0]->url;
            //get file from url
            $file = file_get_contents($url);
            $filename = $keyAi.'.png';

            $storage->put($filename, $file);
            //return url file in storage
            return $storage->url($filename);
        } catch (\Throwable $e) {
            // Gestione dell'errore con un messaggio informativo
            echo 'Errore nella creazione dell\'immagine: ',  $e->getMessage(), "\n";
        }
        return '';
    }

    public function text2Speech(string $text, OpenAiVoiceEnum $voice, OpenAiVoiceModelEnum $model): string
    {
        try {
            // Generazione di una chiave univoca per la richiesta
            $keyAi = md5($text.'____'.$voice->value.$model->value);
            $storage = Storage::disk('audio');
            if($storage->exists($keyAi.'.mp3')){
                return $storage->url($keyAi.'.mp3');
            }
            $filename = $keyAi.'.mp3';
            $stream = fopen(storage_path('app/audio/' . $filename), 'wb');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPEN_AI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'tts-1-hd',
                'input' => strip_tags($text),
                'voice' => $voice->value,
            ]);

            $response->throw(); // Throw an exception for non-success responses

            $response->collect(function ($chunk) use ($stream) {
                fwrite($stream, $chunk);
            });

            fclose($stream);
            $file = file_get_contents('app/audio/'.$filename);
// Move the file to the desired storage directory
            $storage->put($file, $filename);



            // Creazione di una nuova richiesta AI
            $richiesta = new AiRequest();

            // Impostazione dei campi della richiesta
            $richiesta->user_id = $this->user_id;
            $richiesta->key = $keyAi;
            $richiesta->bot = $model->value;
            $richiesta->token = 0 ;
            $richiesta->risposta = $filename;
            $richiesta->save();

            return $storage->url($filename);
        } catch (\Throwable $e) {
            // Gestione dell'errore con un messaggio informativo
            echo 'Errore nella creazione dell\'immagine: ',  $e->getMessage(), "\n";
        }
        return '';
    }

}
