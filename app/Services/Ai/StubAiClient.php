<?php

namespace App\Services\Ai;

use App\Enums\TutorIntent;

/**
 * Deterministic canned provider (config ai.provider = "stub", the default).
 * The whole app - and every test - works with no API key.
 */
final class StubAiClient implements AiClient
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function complete(string $prompt, array $context = []): string
    {
        return match ($context['task'] ?? null) {
            'recall' => $this->recallScore($context),
            'tutor' => $this->tutorReply($context),
            'lesson' => $this->lessonFixture($context),
            'exercise' => $this->exerciseFixture($context),
            'quiz' => $this->quizFixture($context),
            'cards' => $this->cardsFixture($context),
            'diagram' => $this->diagramFixture($context),
            'code_examples' => $this->codeExamplesFixture($context),
            'concepts' => $this->conceptsFixture($context),
            'prerequisites' => '{"edges": []}',
            'outdated' => $this->outdatedFixture($context),
            default => 'Stub AI response.',
        };
    }

    /**
     * Pipeline A fixtures: schema-valid JSON for every generator task so
     * content:generate works with no API key (docs/10 model selection).
     *
     * @param  array<string, mixed>  $context
     */
    private function lessonFixture(array $context): string
    {
        $slug = (string) ($context['section_slug'] ?? 'the-topic');
        $label = str_replace('-', ' ', $slug);

        return (string) json_encode([
            'title' => $label,
            'summary' => "Understand {$label} from first principles, with a small runnable example.",
            'est_minutes' => 8,
            'blocks' => [
                ['type' => 'heading', 'payload' => ['text' => 'What it is', 'level' => 2]],
                ['type' => 'paragraph', 'payload' => ['markdown' => "This lesson explains {$label}. Say what it does in one sentence before any syntax."]],
                ['type' => 'bullets', 'payload' => ['items' => ["Why {$label} matters", 'What problem it removes', 'How you will use it']]],
                ['type' => 'callout', 'payload' => ['text' => 'Read the definition first, then run the example.', 'variant' => 'tip']],
                ['type' => 'code', 'payload' => ['lang' => 'php', 'code' => "<?php\n\$thing = new Thing();\necho \$thing->run();\n"]],
                ['type' => 'output', 'payload' => ['text' => 'ok']],
                ['type' => 'table', 'payload' => ['headers' => ['Term', 'Meaning'], 'rows' => [[str_replace('-', ' ', $slug), 'the subject of this lesson']]]],
                ['type' => 'book_quote', 'payload' => ['text' => "A foundational idea in {$label}.", 'attribution' => 'Matt Zandstra, PHP 8 Objects, Patterns and Practice']],
                ['type' => 'modern_panel', 'payload' => ['book' => "The book introduces {$label} step by step.", 'modern' => 'Current PHP adds types and promotion to the same idea.', 'why' => 'Less boilerplate, earlier errors.']],
                ['type' => 'prereq_list', 'payload' => ['items' => ['Variables and types']]],
                ['type' => 'heading', 'payload' => ['text' => 'Summary', 'level' => 2]],
                ['type' => 'paragraph', 'payload' => ['markdown' => "You can now explain {$label} and run its smallest example."]],
                ['type' => 'exercise_ref', 'payload' => ['labels' => ["Explain {$label} in your own words"], 'ids' => []]],
                ['type' => 'quiz_ref', 'payload' => ['labels' => ["Check your understanding of {$label}"], 'ids' => []]],
                ['type' => 'card_refs', 'payload' => ['labels' => [str_replace('-', ' ', $slug)]]],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function exerciseFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace(['ch', '-'], ['', ' '], $slug);

        return (string) json_encode([
            'exercises' => [
                [
                    'prompt' => "Explain {$label} in your own words, with one tiny code sketch.",
                    'type' => 'explain',
                    'difficulty' => 'easy',
                    'hints' => [
                        "Name what {$label} is for before showing syntax.",
                        'Sketch the smallest declaration first.',
                        'Add one line that proves it works.',
                    ],
                    'answer' => [$label, 'explain'],
                ],
                [
                    'prompt' => "Predict what this snippet about {$label} prints, then run it.",
                    'type' => 'predict_output',
                    'difficulty' => 'medium',
                    'hints' => ['Read it top to bottom.', 'Trace one variable at a time.'],
                    'answer' => ['ok'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function quizFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace(['ch', '-'], ['', ' '], $slug);

        return (string) json_encode([
            'questions' => [
                [
                    'question' => "What is the main job of {$label}?",
                    'type' => 'mcq',
                    'options' => ['Solve one defined problem', 'Replace the whole framework', 'Do nothing useful', 'Only style code'],
                    'correct' => 0,
                    'explanation' => "Each construct in this course has one clear job - {$label} is no different.",
                ],
                [
                    'question' => "{$label} always requires a database.",
                    'type' => 'true_false',
                    'options' => ['True', 'False'],
                    'correct' => 1,
                    'explanation' => 'Nothing in this lesson needs a database to demonstrate the idea.',
                ],
                [
                    'question' => "Which habit helps most when learning {$label}?",
                    'type' => 'mcq',
                    'options' => ['Memorise every word', 'Rewrite the example from memory', 'Skip the exercises', 'Avoid running code'],
                    'correct' => 1,
                    'explanation' => 'Recreating the example from memory is the transfer step.',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function cardsFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace(['ch', '-'], ['', ' '], $slug);

        return (string) json_encode([
            'cards' => [
                ['front' => "What is {$label}?", 'back' => 'One idea from the course that solves a single, well-defined problem.', 'card_type' => 'definition'],
                ['front' => "Give the shape of {$label} in code.", 'back' => 'A short declaration with a name, inputs and one clear output.', 'card_type' => 'syntax'],
                ['front' => "{$label} vs. copying examples - difference?", 'back' => 'Copying fills the page; recreating from memory proves the skill.', 'card_type' => 'difference'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function diagramFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace('-', ' ', $slug);

        return (string) json_encode([
            'title' => ucfirst($label).' at a glance',
            'kind' => 'flowchart',
            'mermaid_source' => "flowchart TD\n    A[Input] --> B[{$label}]\n    B --> C[Result]\n",
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function codeExamplesFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace(['ch', '-'], ['', ' '], $slug);

        return (string) json_encode([
            'examples' => [
                [
                    'title' => "Smallest {$label} example",
                    'tier' => 1,
                    'code' => "<?php\n\$value = 'ok';\necho \$value;\n",
                    'expected_output' => 'ok',
                    'explanation' => 'The smallest shape that runs - build this first.',
                ],
                [
                    'title' => "Using {$label} in a tiny class",
                    'tier' => 2,
                    'code' => "<?php\nfinal class Runner\n{\n    public function run(): string\n    {\n        return 'ok';\n    }\n}\n\necho (new Runner())->run();\n",
                    'expected_output' => 'ok',
                    'explanation' => 'The same idea wrapped the way the book teaches classes.',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function conceptsFixture(array $context): string
    {
        $chapter = (string) ($context['chapter_number'] ?? '1');
        $slug = "ch{$chapter}-core-idea";

        return (string) json_encode([
            'concepts' => [
                [
                    'slug' => $slug,
                    'name' => "Chapter {$chapter} core idea",
                    'definition' => "The central idea of chapter {$chapter} in one plain sentence.",
                    'skill_domain' => 'oop',
                    'granularity' => 'concept',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function outdatedFixture(array $context): string
    {
        $slug = (string) ($context['lesson_slug'] ?? 'the-topic');
        $label = str_replace('-', ' ', $slug);

        return (string) json_encode([
            'is_outdated' => false,
            'book' => "The book teaches {$label} with explicit boilerplate typical of 2021 PHP.",
            'modern' => 'Current PHP expresses the same thing with promotion and readonly.',
            'why' => 'The language absorbed the pattern, so the boilerplate disappeared.',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Schema-valid rubric scoring by keyword coverage - deterministic, no AI.
     *
     * @param  array<string, mixed>  $context
     */
    private function recallScore(array $context): string
    {
        $rubric = array_values(array_filter((array) ($context['rubric'] ?? []), 'is_string'));
        $answerWords = $this->wordSet((string) ($context['answer'] ?? ''));

        $matched = [];
        $missing = [];

        foreach ($rubric as $point) {
            $significant = $this->significantWords((string) $point);

            if ($significant === []) {
                $matched[] = $point;

                continue;
            }

            $hits = 0;

            foreach ($significant as $word) {
                if (isset($answerWords[$word])) {
                    $hits++;
                }
            }

            if ($hits / count($significant) >= 0.6) {
                $matched[] = $point;
            } else {
                $missing[] = $point;
            }
        }

        $total = count($rubric);
        $score = $total === 0 ? 100.0 : round(100 * count($matched) / $total, 2);

        return (string) json_encode([
            'score' => $score,
            'matched_points' => $matched,
            'missing_points' => $missing,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tutorReply(array $context): string
    {
        $intent = TutorIntent::tryFrom((string) ($context['intent'] ?? '')) ?? TutorIntent::Explain;
        $citation = trim((string) ($context['lesson_citation'] ?? ''));
        $cite = $citation !== '' ? "As the book says ({$citation}), " : '';

        return match ($intent) {
            TutorIntent::Solution => $this->solution($context),
            TutorIntent::Hint => $this->hint($context),
            TutorIntent::ExplainSimpler => $this->simpler($cite),
            TutorIntent::AnotherExample => $this->example(),
            TutorIntent::Why => "Here is the mechanism, step by step: {$cite}the runtime reads what you wrote, then acts on it in order. Trace one small case on paper and the order becomes obvious.",
            TutorIntent::RealWorld => "Real-world shape: a small service receives input, checks it, then stores or returns a result - the same three moves you just learned. {$cite}Sketch that flow for the concept first, then map each box to a class.",
            TutorIntent::Quiz => 'Quiz generation ships in Phase 7. Until then, answer this from memory: explain the concept in your own words, then check yourself against the lesson summary.',
            TutorIntent::Exercise => 'Generated exercises ship in Phase 7. Until then, redo the exercises on this lesson without looking at your previous answer.',
            default => $this->explain($cite, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function solution(array $context): string
    {
        if (! (bool) ($context['solution_allowed'] ?? false)) {
            return $this->hint($context)
                ."\n\nThat is as far as I go: the full solution unlocks after 2 failed attempts or once you have walked the whole hint ladder. Try the hint first.";
        }

        $task = trim((string) ($context['exercise_prompt'] ?? ''));
        $modify = $task !== '' ? "it so that it satisfies: {$task}" : 'it so it also handles one new edge case';

        return "Here is one way to solve it - read it line by line before you touch your code:\n\n"
            ."1. Start with the smallest shape that runs.\n"
            ."2. Add the required behaviour in one small step.\n"
            ."3. Check the result against the task.\n\n"
            ."Transfer task: now modify {$modify}. Close the tab and recreate it from memory.";
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hint(array $context): string
    {
        $level = max(1, min(3, (int) ($context['hint_level'] ?? 1)));

        return match ($level) {
            1 => 'Hint 1 (conceptual): name the pieces you need before writing any code. What must exist, and what must each piece do?',
            2 => 'Hint 2 (syntax): sketch the declaration first - keyword, name, then the members you need.',
            default => 'Hint 3 (implementation): assemble the pieces in order and check each line against the task.',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function explain(string $cite, array $context): string
    {
        $topic = trim((string) ($context['lesson_title'] ?? ''));

        return "Let us take it in small steps. {$cite}"
            .($topic !== '' ? "The topic is \"{$topic}\". " : '')
            .'First say what it does in your own words, then I will check the gap.';
    }

    private function simpler(string $cite): string
    {
        return 'Plain English: think of it as a labelled box with rules. '
            ."{$cite}one idea at a time - (1) what goes in the box, (2) what the box does with it, (3) what you get back.";
    }

    private function example(): string
    {
        return 'Another example, tiny on purpose: a counter object starts at 0, +1 adds one, and read returns the total. Same shape as the lesson, different story - rewrite it with your own nouns.';
    }

    /**
     * @return array<string, true>
     */
    private function wordSet(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : array_fill_keys($words, true);
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $point): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($point), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        return array_values(array_filter($words, static fn (string $w): bool => mb_strlen($w) >= 4));
    }
}
