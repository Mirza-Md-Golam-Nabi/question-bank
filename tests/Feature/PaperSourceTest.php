<?php

use App\Enums\PaperSourceType;
use App\Filament\Resources\BoardQuestionPapers\Pages\ListBoardQuestionPapers;
use App\Filament\Resources\Boards\Pages\ManageBoards;
use App\Models\Board;
use App\Models\BoardQuestionPaper;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('treats every existing and new source as an education board unless told otherwise', function () {
    expect(Board::factory()->create()->type)->toBe(PaperSourceType::Board);
});

it('lets the admin add a medical admission test as a source of past papers', function () {
    livewire(ManageBoards::class)
        ->callAction('create', data: [
            'name' => 'MBBS Admission',
            'short_name' => 'MBBS',
            'type' => PaperSourceType::Medical->value,
            'order_index' => 1,
        ])
        ->assertHasNoActionErrors();

    expect(Board::sole()->type)->toBe(PaperSourceType::Medical);
});

it('narrows the paper sources and the past papers to one kind', function () {
    $dhaka = Board::factory()->create(['name' => 'Dhaka Board']);
    $mbbs = Board::factory()->create(['name' => 'MBBS Admission', 'type' => PaperSourceType::Medical]);
    $boardPaper = BoardQuestionPaper::factory()->create(['board_id' => $dhaka->id]);
    $medicalPaper = BoardQuestionPaper::factory()->create(['board_id' => $mbbs->id]);

    livewire(ManageBoards::class)
        ->filterTable('type', PaperSourceType::Medical->value)
        ->assertCanSeeTableRecords([$mbbs])
        ->assertCanNotSeeTableRecords([$dhaka]);

    livewire(ListBoardQuestionPapers::class)
        ->filterTable('source_type', PaperSourceType::Medical->value)
        ->assertCanSeeTableRecords([$medicalPaper])
        ->assertCanNotSeeTableRecords([$boardPaper]);
});
