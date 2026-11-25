<?php

use App\Livewire\Admin\Appearance;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Groups;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::livewire('/', Dashboard::class)->name('dashboard');

    Route::livewire('/ustawienia/profil', Profile::class)->name('profile');

    Route::livewire('/grupy', Groups\Index::class)->name('groups.index');
    Route::livewire('/grupy/nowa', Groups\Edit::class)->name('groups.create');
    Route::livewire('/grupy/{group}', Groups\Show::class)->name('groups.show');
    Route::livewire('/grupy/{group}/edycja', Groups\Edit::class)->name('groups.edit');

    Route::prefix('administracja')->name('admin.')->group(function () {
        Route::livewire('/uzytkownicy', Users\Index::class)->name('users.index');
        Route::livewire('/uzytkownicy/nowy', Users\Edit::class)->name('users.create');
        Route::livewire('/uzytkownicy/import', Users\Import::class)->name('users.import');
        Route::livewire('/uzytkownicy/{user}', Users\Edit::class)->name('users.edit');

        Route::livewire('/wyglad', Appearance::class)
            ->middleware('permission:settings.manage')->name('appearance');
        Route::livewire('/dziennik', AuditLog::class)
            ->middleware('permission:audit.view')->name('audit');
    });
});
