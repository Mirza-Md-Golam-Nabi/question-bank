<?php

namespace App\Filament\Teacher\Resources\Exams;

use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Teacher\Resources\Exams\Pages\ExamQuestionAnalysis;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Filament\Teacher\Resources\Exams\Tables\ExamsTable;
use App\Filament\Teacher\Resources\OmrChecks\OmrCheckResource;
use App\Models\Exam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Exams;

    /**
     * An exam is created and edited only on the question picker
     * (SelectQuestions → TeacherExamBuilder), where its questions and the
     * teacher's plan are checked — so this resource has no form pages.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('created_by', auth()->id());
    }

    /**
     * The list an exam's own pages (results, printed paper, OMR page) go
     * back to: the exams, or the OMR checks for one of those.
     */
    public static function listUrlFor(Exam $exam): string
    {
        return $exam->isOmrCheck() ? OmrCheckResource::getUrl('index') : static::getUrl('index');
    }

    public static function table(Table $table): Table
    {
        return ExamsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'results' => ExamResults::route('/{record}/results'),
            'question-analysis' => ExamQuestionAnalysis::route('/{record}/question-analysis'),
        ];
    }
}
