<?php

namespace App\Filament\Teacher\Resources\OmrChecks;

use App\Enums\ExamType;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Teacher\Resources\OmrChecks\Pages\ListOmrChecks;
use App\Filament\Teacher\Resources\OmrChecks\Tables\OmrChecksTable;
use App\Models\Exam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Teacher's and School's OMR checks (CLAUDE.md rule 10.12): OMR sheets
 * of a paper made outside the system, checked against an answer key typed
 * in here. Each is an exam of its own type, so its sheet, scanning and
 * results are the exams' own pages (the OMR page, ExamResults); made and
 * changed only through OmrCheckService.
 */
class OmrCheckResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Exam::class;

    protected static ?string $modelLabel = 'OMR check';

    protected static ?string $pluralModelLabel = 'OMR checks';

    protected static ?string $navigationLabel = 'OMR checks';

    protected static ?string $slug = 'omr-checks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Exams;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('created_by', auth()->id())
            ->where('exam_type', ExamType::OmrCheck);
    }

    public static function table(Table $table): Table
    {
        return OmrChecksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOmrChecks::route('/'),
        ];
    }
}
