<?php

namespace Bramato\FilamentAiAssistent\Services;

use Bramato\FilamentAiAssistent\Enum\OpenAiModelEnum;
use App\Models\Content;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\NotificationTrait;

class AiQuizService
{
    use NotificationTrait;
    public AiClientService $ai;
    public Content $chapter;

    public int $user_id;

    public array $jsonModel = ["questions" =>
            [
                "question" => "Domanda",
                "options" => [
                    "A" => "Opzione A",
                    "B" => "Opzione B",
                    "C" => "Opzione C",
                    "D" => "Opzione D",
                    "E" => "Opzione E"
                ],
                "level" => "difficoltà intero da 1 a 10",
                "correct_answer" => "E"
            ]
        ];

    public function __construct(Content $chapter, AiClientService $ai)
    {
        $this->ai = $ai;
        $this->chapter = $chapter;
    }

    public static function create(Content $chapter): AiQuizService
    {
        $ai = AiClientService::create($chapter->user_id);
        return new self($chapter, $ai);
    }


    public function generate(): bool
    {


        $this->ai->setModel(OpenAiModelEnum::GPT4_TURBOPREVIEW);
        $this->ai->token = 120000;
        $this->ai->temperature = 0.8;
        $this->ai->sys('Crea un quiz a risposte multiple sul testo che ti verrà passato.');
        $this->ai->setJsonModelFromArray($this->jsonModel);
        $this->ai->message('Genera delle domande su questo testo:'.$this->chapter->text);
        $this->ai->send($this->ai->model->value);


        $quiz = $this->ai->getLastMessage();
        $capitolo = '';
        $id = Quiz::where('content_id',$this->chapter->id)->pluck('id')->toArray();

        QuizResult::whereIn('quiz_id',$id)->delete();
        Quiz::whereIn('id',$id)->delete();
        foreach ($quiz['questions'] as $question){
            $q = $question['question'];
            $options = $question['options'];
            $level = (int) $question['level'];
            $correctAnswer = $question['correct_answer'];
            $quiz = new Quiz();
            $quiz->question = $q;
            $quiz->content_id = $this->chapter->id;
            $quiz->option_a = $options['A'];
            $quiz->option_b = $options['B'];
            $quiz->option_c = $options['C'];
            $quiz->option_d = $options['D'];
            $quiz->option_e = $options['E'];
            $quiz->level = $level;
            $quiz->correct_answer = $correctAnswer;
            $quiz->save();
        }
        return true;
    }



}
