<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('grants cms-editor user admin-access + articles.publish via role inheritance', function (): void {
    $user = User::factory()->create();
    $user->assignRole('cms-editor');

    $user = $user->fresh();
    expect($user->can('admin-access'))->toBeTrue();
    expect($user->can('articles.publish'))->toBeTrue();
    expect($user->can('articles.create'))->toBeTrue();
    expect($user->can('articles.update'))->toBeTrue();
    expect($user->can('articles.view'))->toBeTrue();
    expect($user->can('categories.manage'))->toBeTrue();
});

it('lets cms-editor user reach /admin via canAccessPanel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('cms-editor');

    $this->actingAs($user)
        ->get('/admin')
        ->assertStatus(200);
});

it('denies cms-editor user the articles.delete permission (PermissionSeeder omits it)', function (): void {
    $user = User::factory()->create();
    $user->assignRole('cms-editor');

    $user = $user->fresh();
    // Direct permission absent.
    expect($user->can('articles.delete'))->toBeFalse();

    // ArticlePolicy::delete defence-in-depth: requires super-admin role membership.
    $category = Category::factory()->create();
    $article = Article::factory()->for($category)->create(['author_user_id' => $user->id]);

    expect(Gate::forUser($user)->allows('delete', $article))->toBeFalse();
});

it('grants super-admin user articles.delete via role + permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $admin = $admin->fresh();
    expect($admin->can('articles.delete'))->toBeTrue();
    expect($admin->hasRole('super-admin'))->toBeTrue();

    $category = Category::factory()->create();
    $article = Article::factory()->for($category)->create();
    expect(Gate::forUser($admin)->allows('delete', $article))->toBeTrue();
});

it('denies non-cms-editor user access to /admin', function (): void {
    $user = User::factory()->create();
    // No role / no admin-access permission assigned.

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

it('allows cms-editor to update their own draft via ArticlePolicy', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('cms-editor');
    $editor = $editor->fresh();

    $category = Category::factory()->create();
    $ownArticle = Article::factory()->for($category)->create([
        'author_user_id' => $editor->id,
        'status' => 'draft',
    ]);

    expect(Gate::forUser($editor)->allows('update', $ownArticle))->toBeTrue();
});

it('allows cms-editor with articles.publish to override another author\'s article (editorial team)', function (): void {
    // T-07-04-02 mitigation pathway: senior editors with articles.publish CAN
    // edit another author's article. cms-editor role grants articles.publish by
    // default, so this is the happy path for the editorial cohort.
    $editor = User::factory()->create();
    $editor->assignRole('cms-editor');
    $editor = $editor->fresh();

    $otherAuthor = User::factory()->create();
    $category = Category::factory()->create();
    $otherArticle = Article::factory()->for($category)->create([
        'author_user_id' => $otherAuthor->id,
        'status' => 'draft',
    ]);

    expect(Gate::forUser($editor)->allows('update', $otherArticle))->toBeTrue();
});

it('grants cms-editor full categories.manage CRUD via CategoryPolicy', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole('cms-editor');
    $editor = $editor->fresh();

    $category = Category::factory()->create();

    expect(Gate::forUser($editor)->allows('create', Category::class))->toBeTrue();
    expect(Gate::forUser($editor)->allows('update', $category))->toBeTrue();
    expect(Gate::forUser($editor)->allows('delete', $category))->toBeTrue();
});
