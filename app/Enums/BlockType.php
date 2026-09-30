<?php

namespace App\Enums;

enum BlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case Bullets = 'bullets';
    case Callout = 'callout';
    case Code = 'code';
    case Output = 'output';
    case Table = 'table';
    case Diagram = 'diagram';
    case BookQuote = 'book_quote';
    case ModernPanel = 'modern_panel';
    case PrereqList = 'prereq_list';
    case ExerciseRef = 'exercise_ref';
    case QuizRef = 'quiz_ref';
    case CardRefs = 'card_refs';
    case InterviewRef = 'interview_ref';
    case Tabs = 'tabs';
    case Image = 'image';
    case CodeExample = 'code_example';

    /**
     * Block types whose payload is free-form markdown/text.
     *
     * @return list<string>
     */
    public static function textTypes(): array
    {
        return [
            self::Paragraph->value,
            self::Callout->value,
            self::BookQuote->value,
        ];
    }

    /**
     * Types collapsed behind a disclosure by default (blocks 10–18 of the
     * lesson template). Everything else renders open.
     *
     * @return list<string>
     */
    public static function collapsedByDefault(): array
    {
        return [
            self::BookQuote->value,
            self::ModernPanel->value,
            self::Tabs->value,
            self::Image->value,
            self::ExerciseRef->value,
            self::QuizRef->value,
            self::CardRefs->value,
            self::InterviewRef->value,
        ];
    }

    public function isCollapsedByDefault(): bool
    {
        return in_array($this->value, self::collapsedByDefault(), true);
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
