<?php

declare(strict_types=1);

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Source: .planning/phases/07-cms/07-05-PLAN.md task 2. Replaces the Wave 0 RED
| stub from plan 07-01 (it('placeholder for ArticleResource registration ...').
|
| Pattern: Phase 6 plan 06-11 TournamentResourceTest.php — Filament v3 panel
| bootstrap via Filament::setCurrentPanel + actingAs the test user. Cross-cuts:
|   - Resource registration (URL reach)
|   - Page mounting (Livewire panel context)
|   - Form field presence
|   - Slug unique-rule (T-07-05-03 LOCKED Open Question 4)
|   - scheduled_at past-date guard (T-07-05-02)
|   - cms-editor delete denial (T-07-05-05) + super-admin override
|   - CategoryResource delete denial when articles exist
|
| Pest convention: bare functions, no namespace (Pest.php wires TestCase +
| RefreshDatabase via uses(...)->in('Feature')).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->editor = User::factory()->create();
    $this->editor->assignRole('cms-editor');
    $this->editor = $this->editor->fresh();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super-admin');
    $this->superAdmin = $this->superAdmin->fresh();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// -----------------------------------------------------------------------------
// 1. Resource reachable + list page mounts for cms-editor
// -----------------------------------------------------------------------------

it('reaches ArticleResource list page as cms-editor user', function (): void {
    $this->actingAs($this->editor);

    $this->get('/admin/articles')->assertStatus(200);

    Livewire::test(ListArticles::class)->assertOk();
});

// -----------------------------------------------------------------------------
// 2. Create page renders required fields
// -----------------------------------------------------------------------------

it('renders create form with all 8 required fields', function (): void {
    $this->actingAs($this->editor);

    Livewire::test(CreateArticle::class)
        ->assertFormFieldExists('title.en')
        ->assertFormFieldExists('slug')
        ->assertFormFieldExists('category_id')
        ->assertFormFieldExists('excerpt.en')
        ->assertFormFieldExists('hero')
        ->assertFormFieldExists('body.en')
        ->assertFormFieldExists('status')
        ->assertFormFieldExists('allow_discord_announce');
});

// -----------------------------------------------------------------------------
// 3. Slug unique-rule (T-07-05-03 — Open Question 4 LOCKED)
// -----------------------------------------------------------------------------

it('fails to create article with duplicate slug', function (): void {
    $this->actingAs($this->editor);
    $category = Category::factory()->create();
    Article::factory()->for($category)->create(['slug' => 'duplicate-permalink']);

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title.en' => 'Trying to dupe a permalink',
            'slug' => 'duplicate-permalink',
            'category_id' => $category->id,
            'body.en' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]],
                ],
            ],
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

// -----------------------------------------------------------------------------
// 4. Reactive scheduled_at visibility — only when status=scheduled
// -----------------------------------------------------------------------------

it('hides scheduled_at when status is draft', function (): void {
    $this->actingAs($this->editor);

    Livewire::test(CreateArticle::class)
        ->fillForm(['status' => 'draft'])
        ->assertFormFieldIsHidden('scheduled_at');
});

// -----------------------------------------------------------------------------
// 5. T-07-05-02 — scheduled_at must be >= now
// -----------------------------------------------------------------------------

it('rejects scheduled_at in the past when status=scheduled', function (): void {
    $this->actingAs($this->editor);
    $category = Category::factory()->create();

    Livewire::test(CreateArticle::class)
        ->fillForm([
            'title.en' => 'Past scheduled',
            'slug' => 'past-scheduled-' . uniqid(),
            'category_id' => $category->id,
            'body.en' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]],
            ],
            'status' => 'scheduled',
            'scheduled_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasFormErrors(['scheduled_at']);
});

// -----------------------------------------------------------------------------
// 6. T-07-05-05 — DeleteAction hidden for cms-editor
// -----------------------------------------------------------------------------

it('hides delete action from cms-editor user on EditArticle', function (): void {
    $this->actingAs($this->editor);
    $category = Category::factory()->create();
    $article = Article::factory()->for($category)->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionHidden('delete');
});

// -----------------------------------------------------------------------------
// 7. super-admin sees the DeleteAction (override)
// -----------------------------------------------------------------------------

it('shows delete action for super-admin user on EditArticle', function (): void {
    $this->actingAs($this->superAdmin);
    $category = Category::factory()->create();
    $article = Article::factory()->for($category)->create();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->assertActionVisible('delete');
});

// -----------------------------------------------------------------------------
// 8. CategoryResource forbids delete when articles_count > 0
// -----------------------------------------------------------------------------

it('CategoryResource hides delete action when category has articles', function (): void {
    $this->actingAs($this->superAdmin);
    $category = Category::factory()->create();
    Article::factory()->for($category)->create();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->assertActionHidden('delete');
});

it('CategoryResource shows delete action when category has zero articles', function (): void {
    $this->actingAs($this->superAdmin);
    $emptyCategory = Category::factory()->create();

    Livewire::test(EditCategory::class, ['record' => $emptyCategory->getRouteKey()])
        ->assertActionVisible('delete');
});

// -----------------------------------------------------------------------------
// 9. Resource metadata — labels + navigation
// -----------------------------------------------------------------------------

it('ArticleResource declares CMS navigation group and i18n labels', function (): void {
    expect(ArticleResource::getNavigationGroup())->toBe('CMS')
        ->and(ArticleResource::getModelLabel())->toBe('Article')
        ->and(ArticleResource::getPluralModelLabel())->toBe('Articles');
});

it('ArticleResource getPages returns the 3 LOCKED page registrations', function (): void {
    $pages = ArticleResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});
