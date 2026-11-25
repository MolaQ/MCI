<?php

use App\Http\Controllers\InvitePosterController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MentionSearchController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SwitchSchoolController;
use App\Livewire\Admin;
use App\Livewire\Admin\Appearance;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Users;
use App\Livewire\Catalog;
use App\Livewire\Communication;
use App\Livewire\Dashboard;
use App\Livewire\Events;
use App\Livewire\Groups;
use App\Livewire\Invites;
use App\Livewire\Publicly;
use App\Livewire\Settings;
use App\Livewire\Settings\Profile;
use App\Livewire\Site;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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

/*
| Installable app (Step 4): manifest, icons, service worker, offline page.
*/
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
Route::get('/pwa/icon-{size}.png', [PwaController::class, 'icon'])->whereNumber('size')->name('pwa.icon');
Route::get('/pwa/icon-{size}-{variant}.png', [PwaController::class, 'icon'])->whereNumber('size')->whereIn('variant', ['maskable', 'badge'])->name('pwa.icon.variant');
// Called by the service worker when the browser rotates a push subscription (no session).
Route::post('/push/odnow', [PushSubscriptionController::class, 'renew'])->middleware('throttle:20,1')
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])->name('push.renew');

// Images: access is checked per file (public logos/covers vs private media).
Route::get('/media/{uuid}.{ext}', [MediaController::class, 'show'])
    ->whereUuid('uuid')->where('ext', 'webp|png')->middleware('throttle:600,1')->name('media.show');

/*
| Public front — no login (Step 3). Only "publiclyVisible" data is read here.
*/
Route::middleware('throttle:240,1')->group(function () {
    Route::livewire('/', Publicly\Home::class)->name('home');
    Route::livewire('/szkoly/{slug}', Publicly\SchoolPage::class)->name('public.school');
    Route::get('/szkoly/{slug}/kalendarz.ics', [PublicContentController::class, 'schoolIcs'])->middleware('feature:events.ics')->name('public.school.ics');
    Route::get('/publiczne/wydarzenia/{event}', [PublicContentController::class, 'event'])->whereNumber('event')->name('public.event');
    Route::get('/publiczne/wydarzenia/{event}.ics', [PublicContentController::class, 'ics'])->whereNumber('event')->middleware('feature:events.ics')->name('public.event.ics');
    Route::get('/publiczne/komunikaty/{announcement}', [PublicContentController::class, 'announcement'])->whereNumber('announcement')->name('public.announcement');
    Route::livewire('/tv', Publicly\Board::class)->middleware('feature:public.tv')->name('tv');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/pulpit', Dashboard::class)->name('dashboard');
    Route::get('/wzmianki', MentionSearchController::class)->middleware('throttle:120,1')->name('mentions.search');

    Route::middleware('feature:announcements')->group(function () {
        Route::livewire('/komunikaty', Communication\Center::class)->name('communication.index');
        Route::livewire('/komunikaty/nowy', Communication\Compose::class)->name('communication.create');
        Route::livewire('/komunikaty/{announcement}', Communication\Show::class)->whereNumber('announcement')->name('communication.show');
    });

    Route::middleware('feature:events')->group(function () {
        Route::livewire('/wydarzenia', Events\Index::class)->name('events.index');
        Route::livewire('/wydarzenia/nowe', Events\Edit::class)->name('events.create');
        Route::livewire('/wydarzenia/{event}', Events\Show::class)->whereNumber('event')->name('events.show');
        Route::livewire('/wydarzenia/{event}/edycja', Events\Edit::class)->whereNumber('event')->name('events.edit');
    });

    Route::post('/szkola/przelacz', SwitchSchoolController::class)->name('school.switch');

    Route::livewire('/ustawienia/profil', Profile::class)->name('profile');
    Route::livewire('/ustawienia/powiadomienia', Settings\Notifications::class)->name('settings.notifications');

    Route::livewire('/grupy', Groups\Index::class)->name('groups.index');
    Route::livewire('/grupy/nowa', Groups\Edit::class)->name('groups.create');
    Route::livewire('/grupy/{group}', Groups\Show::class)->name('groups.show');
    Route::livewire('/grupy/{group}/edycja', Groups\Edit::class)->name('groups.edit');

    Route::middleware('feature:invites')->group(function () {
        Route::livewire('/zaproszenia', Invites\Index::class)->name('invites.index');
        Route::livewire('/zaproszenia/nowe', Invites\Create::class)->name('invites.create');
        Route::livewire('/zaproszenia/{invite}', Invites\Show::class)->name('invites.show');
        Route::get('/zaproszenia/{invite}/plakat', [InvitePosterController::class, 'poster'])->middleware('feature:invites.qr')->name('invites.poster');
        Route::get('/zaproszenia/{invite}/qr.svg', [InvitePosterController::class, 'svg'])->middleware('feature:invites.qr')->name('invites.qr');
    });

    Route::prefix('administracja')->name('admin.')->group(function () {
        Route::livewire('/uzytkownicy', Users\Index::class)->name('users.index');
        Route::livewire('/uzytkownicy/nowy', Users\Edit::class)->name('users.create');
        Route::livewire('/uzytkownicy/import', Users\Import::class)->name('users.import');
        Route::livewire('/uzytkownicy/{user}', Users\Edit::class)->name('users.edit');
        Route::livewire('/szkola', Site\SchoolEdit::class)->name('school');
        Route::livewire('/szkola/profil-publiczny', Admin\SchoolProfile::class)->name('school.profile');
        Route::livewire('/szkola/funkcje', Admin\SchoolFeatures::class)->name('school.features');
        Route::livewire('/szkola/kategorie', Catalog\Manage::class)->name('school.catalog');
        Route::livewire('/logi', AuditLog::class)->name('audit');
    });

    Route::prefix('witryna')->name('site.')->middleware('can:site-admin')->group(function () {
        Route::livewire('/szkoly', Site\Schools::class)->name('schools');
        Route::livewire('/szkoly/nowa', Site\SchoolEdit::class)->name('schools.create');
        Route::livewire('/szkoly/{school}', Site\SchoolEdit::class)->name('schools.edit');
        Route::livewire('/wyglad', Appearance::class)->name('appearance');
        Route::livewire('/funkcje', Site\Features::class)->name('features');
        Route::livewire('/kategorie', Catalog\Manage::class)->defaults('scope', 'site')->name('catalog');
        Route::livewire('/dziennik', AuditLog::class)->defaults('scope', 'site')->name('journal');
    });
});
