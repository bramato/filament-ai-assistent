<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use Rajentrivedi\TokenizerX\TokenizerX;

class TokenCalculatorService
{

    public string $text;
    public OpenAiModelEnum $model;
    public int $tokenIn = 0;
    public int $tokenOut = 0;
    public int $nrLingue = 1;

    public function __construct(OpenAiModelEnum $model)
    {
        $this->model = $model;
    }

    public static function create(OpenAiModelEnum $model){
        return new self($model);
    }


    public function execute($text, $moltiplicatoreOut = 1){
        $this->text = $text;
        $this->nrLingue = $moltiplicatoreOut;
        $this->tokenIn = TokenizerX::count($this->text,$this->model->value);
        $this->tokenOut = $this->tokenIn * $moltiplicatoreOut;
        return $this;
    }

    public function getToken(){
        return $this->token;
    }

    public function getCost(OpenAiModelEnum $model = null){
        if($model === null){
            $model = $this->model;
        }
        $costIn = $this->tokenIn/1000* self::getCostPerToken($model,true);
        $costOut = $this->tokenOut/1000 * self::getCostPerToken($model, false);

        //count nrCaratteri
        $count = strlen($this->text);

        return ['model'=> $model->value, 'costIn'=>self::getCostPerToken($model,true), 'costOut'=> self::getCostPerToken($model,false),'openaAI' => $costIn+$costOut, 'deepl' => 0.00002*$count*($this->nrLingue+1), 'tokenIn' => $this->tokenIn, 'tokenOut' => $this->tokenOut, 'nrCaratteri' => $count, 'nrLingueDeepl'=>$this->nrLingue];
    }



    /**
     * Restituisce il costo per token del modello specificato.
     *
     * @param string $model Enum del modello di GPT.
     * @return float Costo per token del modello, o NULL se il modello non è noto.
     */
    public static function getCostPerToken(OpenAiModelEnum $model, $in = true): ?float {
        // Mappatura dei modelli ai loro costi per token.
        // I valori qui sono puramente ipotetici.
        $costMapIn = [
            'gpt-3.5-turbo' => 0.0030,
            'gpt-4' => 0.03,
            'codex' => 0.002,
            'davinci' => 0.002,
            'curie' => 0.002,
            'babbage' => 0.002,
            'ada' => 0.001,
            'gpt-3.5-turbo-0125' => 0.0005,
            'gpt-4-turbo-preview' => 0.01,
            'gpt-3.5-turbo-1106' => 0.0010,
        ];

        $costMapOut = [
            'gpt-3.5-turbo' => 0.0060,
            'gpt-4' => 0.06,
            'codex' => 0.007,
            'davinci' => 0.006,
            'curie' => 0.005,
            'babbage' => 0.003,
            'ada' => 0.001,
            'gpt-3.5-turbo-0125' => 0.0015,
            'gpt-4-turbo-preview' => 0.03,
            'gpt-3.5-turbo-1106' => 0.0020,
        ];

        // Restituisce il costo per token se il modello è noto, altrimenti NULL.
        return $in ? ($costMapIn[$model->value] ?? null) : ($costMapOut[$model->value] ?? null);
    }



}
