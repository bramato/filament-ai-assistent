<?php

namespace Bramato\FilamentAiAssistent\Enum;

enum OpenAiModelEnum: string
{
    case GPT3_5 = 'gpt-3.5-turbo';

    case GPT4 = 'gpt-4';
    case CODX = 'codex';
    case DAVINCI = 'davinci';
    case CURIE = 'curie';
    case BABBAGE = 'babbage';
    case ADA = 'ada';
    case GPT3_5_0125 = 'gpt-3.5-turbo-0125';

    //CON JSON
    case GPT4_TURBOPREVIEW = 'gpt-4-turbo-preview';
    case GPT3_5_1106 = 'gpt-3.5-turbo-1106';
}
