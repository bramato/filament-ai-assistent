<?php

namespace Bramato\FilamentAiAssistent\Dto;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;

class AiTranslateDto extends DataTransferObject
{
    public array $html_translated;

    /**
     * @throws UnknownProperties
     * @throws ValidationException
     */
    public function __construct(array $parameters)
    {
        // Assicurati che 'html_code' sia presente e sia un array
        if (!isset($parameters['html_translated']) || !is_array($parameters['html_translated'])) {
            throw new \Exception("'html_code' deve essere un array.");
        }

        // Costruisci le regole di validazione dinamicamente in base alla struttura del tuo modello
        $htmlCodeRules = [];
        foreach ($parameters['html_translated'] as $languageCode => $htmlCode) {
            // Assumi che le chiavi siano codici ISO di lingue e i valori siano stringhe HTML
            $htmlCodeRules['html_translated.' . $languageCode] = ['required', 'string'];
        }

        // Esegue la validazione
        $validator = Validator::make($parameters, ['html_translated' => 'required|array'] + $htmlCodeRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        parent::__construct($parameters);
    }
}
