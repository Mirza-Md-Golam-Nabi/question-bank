<?php

namespace App\Models;

use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Enums\Locale;
use App\Enums\UserRole;
use Database\Factories\GuideFactory;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A how-to guide (CLAUDE.md rule 14): written by an Admin in Bangla and,
 * optionally, English, for some roles, filed under a category and,
 * optionally, under the pages whose "How do I…?" button opens it. The one
 * place that decides who sees a guide and in which language.
 */
#[Fillable([
    'slug', 'category', 'title_bn', 'title_en', 'body_bn', 'body_en', 'video_url',
    'target_roles', 'page_keys', 'sort_order', 'is_active',
])]
class Guide extends Model
{
    /** @use HasFactory<GuideFactory> */
    use HasFactory;

    /**
     * The roles a guide can be for — everyone who works in a panel but Admins.
     *
     * @var array<int, UserRole>
     */
    public const AUDIENCE_ROLES = [UserRole::Student, UserRole::Teacher, UserRole::Staff, UserRole::School];

    protected function casts(): array
    {
        return [
            'category' => GuideCategory::class,
            'target_roles' => 'array',
            'page_keys' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The guides a user sees: switched on and for their role, in order.
     */
    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where('is_active', true)
            ->whereJsonContains('target_roles', $user->role->value)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Those of a user's guides that a page's "How do I…?" button opens.
     */
    public function scopeOnPage(Builder $query, User $user, GuidePage $page): Builder
    {
        return $query->for($user)->whereJsonContains('page_keys', $page->value);
    }

    /**
     * Narrows to the guides whose title or text mention every word searched
     * for, in either language.
     */
    public function scopeMatching(Builder $query, ?string $search): Builder
    {
        foreach (preg_split('/\s+/u', trim((string) $search), flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $like = "%{$word}%";

            $query->where(fn (Builder $query) => $query
                ->where('title_bn', 'like', $like)
                ->orWhere('title_en', 'like', $like)
                ->orWhere('body_bn', 'like', $like)
                ->orWhere('body_en', 'like', $like));
        }

        return $query;
    }

    /**
     * The title in the viewer's language — the Bangla one when there is no
     * English one.
     */
    public function title(): string
    {
        return $this->inViewersLanguage('title');
    }

    /**
     * The guide's text in the viewer's language, ready to show, with its
     * pictures.
     */
    public function bodyHtml(): string
    {
        return RichContentRenderer::make($this->inViewersLanguage('body'))
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->toHtml();
    }

    /**
     * The video to show above the text, as an embeddable YouTube address;
     * null for none, or a link that isn't a YouTube video.
     */
    public function videoEmbedUrl(): ?string
    {
        if (blank($this->video_url)) {
            return null;
        }

        preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', (string) $this->video_url, $match);

        return isset($match[1]) ? "https://www.youtube-nocookie.com/embed/{$match[1]}" : null;
    }

    private function inViewersLanguage(string $field): string
    {
        $english = $this->getAttribute("{$field}_en");

        return Locale::current() === Locale::En && filled($english)
            ? (string) $english
            : (string) $this->getAttribute("{$field}_bn");
    }
}
