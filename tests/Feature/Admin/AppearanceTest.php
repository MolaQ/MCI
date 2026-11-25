<?php

use App\Enums\Role;
use App\Livewire\Admin\Appearance;
use App\Support\Appearance as AppearanceService;
use App\Support\Settings;
use Livewire\Livewire;

it('is restricted to site admins', function () {
    $this->actingAs(userWithRole(Role::Coordinator))->get(route('site.appearance'))->assertForbidden();
    $this->actingAs(admin())->get(route('site.appearance'))->assertForbidden();
    $this->actingAs(siteAdmin())->get(route('site.appearance'))->assertOk();
});

it('saves the theme and emits it as CSS variables for every page', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)
        ->set('school_name', 'Zespół Szkół Mechanicznych')
        ->set('short_name', 'ZSM')
        ->set('primary_color', '#0F766E')
        ->set('radius', 'lg')
        ->call('save')
        ->assertHasNoErrors();

    $css = app(AppearanceService::class)->cssVariables();
    expect($css)->toContain('--mci-primary:#0f766e')
        ->toContain('--color-accent:#0f766e')
        ->toContain('--mci-radius:0.75rem');

    $this->get('/')->assertSee('--mci-primary:#0f766e', false)->assertSee('ZSM');
});

it('rejects unsafe colour values', function () {
    $this->actingAs(siteAdmin());

    Livewire::test(Appearance::class)
        ->set('primary_color', 'red}body{display:none')
        ->call('save')
        ->assertHasErrors(['primary_color']);
});

it('falls back to defaults for tampered stored values', function () {
    app(Settings::class)->set('appearance', ['primary_color' => '</style><script>', 'radius' => 'huge']);

    $css = app(AppearanceService::class)->cssVariables();

    expect($css)->not->toContain('<script>')
        ->toContain('--mci-primary:#1d4ed8')
        ->toContain('--mci-radius:0.5rem');
});
