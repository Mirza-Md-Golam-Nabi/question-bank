<?php

namespace App\Filament\Support\Resources;

use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\QuestionFormSchema;
use App\Filament\Support\QuestionsTable;
use App\Models\Question;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the Admin, Teacher and Staff QuestionResource have in common: the
 * model, where it sits in the navigation, and the one question form and
 * table every panel uses.
 *
 * What a panel's own resource must still state itself is exactly what
 * differs: its pages, and — never to be shared or left to a default —
 * its `getEloquentQuery()`, the hard-coded filter deciding which questions
 * that panel's users can reach at all (CLAUDE.md rule 2).
 */
abstract class QuestionResourceBase extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(QuestionFormSchema::components());
    }

    public static function table(Table $table): Table
    {
        return QuestionsTable::configure($table);
    }

    /**
     * Every panel's query starts here, and each panel then narrows it to
     * what its users may reach. What is added for all of them is what
     * QuestionPolicy asks of every listed question — whether it is on an
     * exam — so that isn't looked up once per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withExamUsage();
    }

    /**
     * Narrows a question count shown on the browse cards to the current
     * versions this panel's users can actually open — so a card never
     * promises more questions than the list behind it shows. It reads the
     * panel's own `getEloquentQuery()`, so that filter stays the one place
     * deciding who sees what.
     *
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public static function scopeCountedQuestions(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('is_latest'), true)
            ->whereIn($query->qualifyColumn('id'), static::getEloquentQuery()->select('questions.id'));
    }
}
