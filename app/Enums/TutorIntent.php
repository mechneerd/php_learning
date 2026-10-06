<?php

namespace App\Enums;

/**
 * Intent of a tutor message (docs/10 intent handling table). Classification
 * is keyword-based so behaviour is deterministic with the stub client.
 */
enum TutorIntent: string
{
    case Explain = 'explain';
    case ExplainSimpler = 'explain_simpler';
    case AnotherExample = 'another_example';
    case Why = 'why';
    case RealWorld = 'real_world';
    case Hint = 'hint';
    case Solution = 'solution';
    case Quiz = 'quiz';
    case Exercise = 'exercise';

    public static function classify(string $text): self
    {
        $text = mb_strtolower(trim($text));

        if (preg_match('/\bsolution\b|\bgive (me )?(the )?answer\b|write (it|this|this one|the (code|answer)) for me\b/', $text) === 1) {
            return self::Solution;
        }

        if (preg_match('/\bhint\b|\bstuck\b|\bclue\b|no idea how to start\b/', $text) === 1) {
            return self::Hint;
        }

        if (preg_match('/explain simpler|\bsimpler\b|like i\'?m new\b|\beli5\b|in plain english\b|for beginners?\b/', $text) === 1) {
            return self::ExplainSimpler;
        }

        if (preg_match('/another example|one more example|more examples?\b/', $text) === 1) {
            return self::AnotherExample;
        }

        if (preg_match('/\bquiz me\b|\btest me\b/', $text) === 1) {
            return self::Quiz;
        }

        if (preg_match('/give (me )?(an? )?(practice )?exercise|more exercises?\b/', $text) === 1) {
            return self::Exercise;
        }

        if (str_contains($text, 'laravel') || preg_match('/real[- ]?world|in practice|on the job/', $text) === 1) {
            return self::RealWorld;
        }

        if (preg_match('/\bwhy\b|how does (it|this) work/', $text) === 1) {
            return self::Why;
        }

        return self::Explain;
    }

    public function label(): string
    {
        return match ($this) {
            self::Explain => 'Explain',
            self::ExplainSimpler => 'Explain simpler',
            self::AnotherExample => 'Another example',
            self::Why => 'Why it works',
            self::RealWorld => 'Real world',
            self::Hint => 'Hint',
            self::Solution => 'Solution',
            self::Quiz => 'Quiz me',
            self::Exercise => 'Exercise',
        };
    }
}
