<?php

namespace Database\Factories;

use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Enums\UserRole;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    protected $model = Guide::class;

    public function definition(): array
    {
        return [
            'category' => GuideCategory::GettingStarted,
            'title_bn' => 'গাইড '.fake()->unique()->numberBetween(1, 99999),
            'title_en' => 'Guide '.fake()->unique()->numberBetween(1, 99999),
            'body_bn' => '<p>বাংলায় ধাপগুলো।</p>',
            'body_en' => '<p>The steps in English.</p>',
            'target_roles' => [UserRole::Student->value, UserRole::Teacher->value],
            'is_active' => true,
        ];
    }

    /**
     * @param  array<int, UserRole>|UserRole  $roles
     */
    public function forRoles(array|UserRole $roles): static
    {
        return $this->state(fn (array $attributes) => [
            'target_roles' => array_map(fn (UserRole $role): string => $role->value, is_array($roles) ? $roles : [$roles]),
        ]);
    }

    public function onPage(GuidePage ...$pages): static
    {
        return $this->state(fn (array $attributes) => [
            'page_keys' => array_map(fn (GuidePage $page): string => $page->value, $pages),
        ]);
    }
}
