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
}
