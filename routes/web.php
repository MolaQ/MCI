<?php

use App\Http\Controllers\InvitePosterController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\SwitchSchoolController;
use App\Livewire\Admin\Appearance;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Users;
use App\Livewire\Dashboard;
use App\Livewire\Groups;
use App\Livewire\Invites;
use App\Livewire\Settings\Profile;
use App\Livewire\Site;
use Illuminate\Support\Facades\Route;

/*
| Invite links / QR codes — public, rate limited (see JoinController).
*/
Route::prefix('dolacz')->name('join.')->group(function () {
    Route::get('/potwierdz/{user}/{hash}', [JoinController::class, 'verify'])
        ->middleware(['signed', 'throttle:10,1'])->name('verify');
    Route::get('/oczekuje', [JoinController::class, 'pending'])->name('pending');

    Route::middleware('throttle:join')->group(function () {
        Route::get('/{token}', [JoinController::class, 'show'])->name('show');
        Route::post('/{token}/logowanie', [JoinController::class, 'login'])->middleware('guest')->name('login');
        Route::post('/{token}', [JoinController::class, 'accept'])->middleware('auth')->name('accept');
        Route::post('/{token}/rejestracja', [JoinController::class, 'register'])->middleware(['guest', 'throttle:join-register'])->name('register');
        Route::post('/{token}/aktywacja', [JoinController::class, 'activate'])->middleware(['guest', 'throttle:join-register'])->name('activate');
        // Anything else under /dolacz (malformed links, path tricks) gets the same neutral page.
        Route::get('/{any}', fn () => response()->view('join.invalid', [], 404))->where('any', '.*')->name('invalid');
    });
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', Dashboard::class)->name('dashboard');
    Route::post('/szkola/przelacz', SwitchSchoolController::class)->name('school.switch');

    Route::livewire('/ustawienia/profil', Profile::class)->name('profile');

    Route::livewire('/grupy', Groups\Index::class)->name('groups.index');
    Route::livewire('/grupy/nowa', Groups\Edit::class)->name('groups.create');
    Route::livewire('/grupy/{group}', Groups\Show::class)->name('groups.show');
    Route::livewire('/grupy/{group}/edycja', Groups\Edit::class)->name('groups.edit');

    Route::livewire('/zaproszenia', Invites\Index::class)->name('invites.index');
    Route::livewire('/zaproszenia/nowe', Invites\Create::class)->name('invites.create');
    Route::livewire('/zaproszenia/{invite}', Invites\Show::class)->name('invites.show');
    Route::get('/zaproszenia/{invite}/plakat', [InvitePosterController::class, 'poster'])->name('invites.poster');
    Route::get('/zaproszenia/{invite}/qr.svg', [InvitePosterController::class, 'svg'])->name('invites.qr');

    Route::prefix('administracja')->name('admin.')->group(function () {
        Route::livewire('/uzytkownicy', Users\Index::class)->name('users.index');
        Route::livewire('/uzytkownicy/nowy', Users\Edit::class)->name('users.create');
        Route::livewire('/uzytkownicy/import', Users\Import::class)->name('users.import');
        Route::livewire('/uzytkownicy/{user}', Users\Edit::class)->name('users.edit');
        Route::livewire('/szkola', Site\SchoolEdit::class)->name('school');
        Route::livewire('/logi', AuditLog::class)->name('audit');
    });

    Route::prefix('witryna')->name('site.')->middleware('can:site-admin')->group(function () {
        Route::livewire('/szkoly', Site\Schools::class)->name('schools');
        Route::livewire('/szkoly/nowa', Site\SchoolEdit::class)->name('schools.create');
        Route::livewire('/szkoly/{school}', Site\SchoolEdit::class)->name('schools.edit');
        Route::livewire('/wyglad', Appearance::class)->name('appearance');
        Route::livewire('/dziennik', AuditLog::class)->defaults('scope', 'site')->name('journal');
    });
});
