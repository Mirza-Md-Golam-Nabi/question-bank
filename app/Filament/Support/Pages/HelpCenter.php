<?php

namespace App\Filament\Support\Pages;

use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Models\Guide;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * "Help" (CLAUDE.md rule 14): the guides for the signed-in user's role, by
 * category, with a search — one page, registered in the Student, Teacher,
 * Staff and School panels. Opening one shows it here; `?page=` narrows to
 * the guides of one page (the "Get started" list's "How?" links).
 */
class HelpCenter extends Page
{
    protected string $view = 'filament.support.pages.help-center';

    protected static ?string $slug = 'help';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 99;

    #[Url]
    public string $search = '';

    #[Url(as: 'guide')]
    public ?int $guideId = null;

    #[Url(as: 'page')]
    public ?string $pageKey = null;

    public static function getNavigationLabel(): string
    {
        return __('Help');
    }

    public function getTitle(): string
    {
        return __('Help');
    }

    /**
     * The guide being read, if one is open and the user may see it.
     */
    #[Computed]
    public function guide(): ?Guide
    {
        return $this->guideId ? Guide::query()->for(Auth::user())->find($this->guideId) : null;
    }

    /**
     * The user's guides, searched and narrowed, by category in its order.
     *
     * @return Collection<string, array{category: GuideCategory, guides: Collection<int, Guide>}>
     */
    #[Computed]
    public function sections(): Collection
    {
        $page = GuidePage::tryFrom((string) $this->pageKey);

        $guides = Guide::query()
            ->for(Auth::user())
            ->matching($this->search)
            ->when($page, fn ($query) => $query->whereJsonContains('page_keys', $page->value))
            ->get(['id', 'category', 'title_bn', 'title_en', 'sort_order']);

        return collect(GuideCategory::cases())
            ->map(fn (GuideCategory $category): array => [
                'category' => $category,
                'guides' => $guides->filter(fn (Guide $guide): bool => $guide->category === $category)->values(),
            ])
            ->filter(fn (array $section): bool => $section['guides']->isNotEmpty())
            ->keyBy(fn (array $section): string => $section['category']->value);
    }

    public function narrowedToPage(): ?GuidePage
    {
        return GuidePage::tryFrom((string) $this->pageKey);
    }

    public function open(int $guideId): void
    {
        $this->guideId = $guideId;
    }

    public function back(): void
    {
        $this->guideId = null;
    }

    public function showAll(): void
    {
        $this->pageKey = null;
        $this->search = '';
    }
}
