<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Subject" column of a table short on width: the subject's short name
 * where it has one, otherwise its name in the active language
 * (Subject::short_label) — with the full name on hover.
 */
class SubjectColumn
{
    /**
     * @param  string  $relationship  The path from the table's record to its subject, e.g. `subject` or `chapter.classSubject.subject`.
     */
    public static function make(string $relationship = 'subject'): TextColumn
    {
        // Named after a real column of the subject, so the table loads the
        // subjects of all its rows together.
        return TextColumn::make("{$relationship}.short_name")
            ->label(__('Subject'))
            ->state(fn (Model $record): ?string => data_get($record, $relationship)?->short_label)
            ->tooltip(fn (Model $record): ?string => data_get($record, $relationship)?->display_name);
    }
}
