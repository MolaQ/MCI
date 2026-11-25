<?php

use App\Domain\Communication\Actions\PublishAnnouncement;
use App\Enums\Feature;
use App\Enums\Role;
use App\Listeners\RecordPushResult;
use App\Livewire\Settings\Notifications;
use App\Models\PushSubscription;
use App\Notifications\AnnouncementNotification;
use App\Notifications\TestPushNotification;
use App\Support\Features;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

beforeEach(function () {
    config([
        'webpush.vapid.public_key' => 'BK9yJJl6kBbgArjj4qYx6KsJOvIBNdSrsjZ-yvHKsOOD1Kxh7G0t3RCuog9laukyFIOpUUQbbu3K3lIDMvmtOhQ',
        'webpush.vapid.private_key' => 'yuEsiOPGX7X1D5jrP2vAkUM-u9Ru7UhQguGPvfpkK3s',
    ]);
});

function subscription(array $overrides = []): array
{
    return array_replace_recursive([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.bin2hex(random_bytes(12)),
        'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
    ], $overrides);
}

function deviceFor($user): PushSubscription
{
    $s = subscription();

    return $user->updatePushSubscription($s['endpoint'], $s['keys']['p256dh'], $s['keys']['auth'], 'aes128gcm');
}

it('serves an installable manifest, icons, service worker and offline page', function () {
    $manifest = $this->get('/manifest.webmanifest')->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')->json();

    expect($manifest)->toMatchArray(['display' => 'standalone', 'lang' => 'pl', 'scope' => '/', 'start_url' => '/pulpit?source=pwa'])
        ->and(collect($manifest['icons'])->pluck('purpose')->unique()->values()->all())->toBe(['any', 'maskable'])
        ->and(collect($manifest['icons'])->pluck('sizes')->unique()->values()->all())->toBe(['192x192', '512x512']);

    foreach (['/pwa/icon-192.png' => 192, '/pwa/icon-512-maskable.png' => 512, '/pwa/icon-96-badge.png' => 96] as $url => $size) {
        $png = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        expect(getimagesizefromstring($png)[0])->toBe($size);
    }
    $this->get('/pwa/icon-100.png')->assertNotFound();

    $sw = $this->get('/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/');
    expect($sw->getContent())->not->toContain('__VERSION__')->toContain("const OFFLINE_URL = '/offline'")
        ->and($sw->headers->get('Content-Type'))->toContain('javascript');

    $this->get('/offline')->assertOk()->assertSee('Brak połączenia z internetem');
    $this->get(route('login'))->assertSee('rel="manifest"', false);
});

it('registers this device for push and keeps a device list', function () {
    $user = userWithRole(Role::Teacher);
    $this->actingAs($user);
    $sub = subscription();

    Livewire::test(Notifications::class)
        ->call('subscribe', $sub, 'Android · Chrome', 'android')
        ->assertHasNoErrors()
        ->assertSee('Android · Chrome');

    $device = $user->pushSubscriptions()->sole();
    expect($device->endpoint)->toBe($sub['endpoint'])->and($device->platform)->toBe('android');

    Livewire::test(Notifications::class)->call('removeDevice', $device->id);
    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('rejects non-https endpoints and moves a shared device to the new user', function () {
    $first = userWithRole(Role::Teacher);
    $second = userWithRole(Role::Teacher);
    $sub = subscription();

    $this->actingAs($first);
    Livewire::test(Notifications::class)->call('subscribe', subscription(['endpoint' => 'http://evil.test/x']))->assertHasErrors('s.endpoint');
    Livewire::test(Notifications::class)->call('subscribe', $sub);

    $this->actingAs($second);
    Livewire::test(Notifications::class)->call('subscribe', $sub);

    expect($first->pushSubscriptions()->count())->toBe(0)->and($second->pushSubscriptions()->count())->toBe(1);
});

it('does not offer push without VAPID keys or with the feature off', function () {
    $this->actingAs(userWithRole(Role::Teacher));

    app(Features::class)->setForSchool($this->school, Feature::WebPush, false);
    Livewire::test(Notifications::class)->call('subscribe', subscription())->assertForbidden();

    app(Features::class)->setForSchool($this->school, Feature::WebPush, null);
    config(['webpush.vapid.public_key' => null]);
    Livewire::test(Notifications::class)->assertSee('nie są jeszcze skonfigurowane')->call('sendTest')->assertForbidden();
});

it('saves which kinds of posts may ring the phone', function () {
    $user = userWithRole(Role::Teacher);
    $this->actingAs($user);

    expect($user->wantsPush('mentions'))->toBeTrue()->and($user->wantsPush('announcements'))->toBeFalse();

    Livewire::test(Notifications::class)->set('prefs.announcements', true)->set('prefs.mentions', false);

    $user->refresh();
    expect($user->wantsPush('announcements'))->toBeTrue()->and($user->wantsPush('mentions'))->toBeFalse()
        ->and($user->wantsPush('important'))->toBeTrue();
});

it('pushes important posts and mentions to devices, routine posts only to people who opted in', function () {
    Notification::fake();
    $author = userWithRole(Role::Coordinator);
    $withDevice = userWithRole(Role::Teacher);
    deviceFor($withDevice);
    $optedIn = userWithRole(Role::Teacher, ['notification_prefs' => ['push' => ['announcements' => true]]]);
    deviceFor($optedIn);
    $noDevice = userWithRole(Role::Teacher);
    actAsSchool($this->school, $author);
    $publish = app(PublishAnnouncement::class);

    $channels = fn ($user, string $title) => Notification::sent($user, AnnouncementNotification::class)
        ->filter(fn ($n) => $n->announcement->title === $title)
        ->map(fn ($n) => $n->via($user->fresh()->load('pushSubscriptions')))->first();

    $publish->handle($author, $this->school, ['title' => 'Pilne', 'body' => '<p>x</p>', 'priority' => 'urgent'], ['role:teacher']);
    $publish->handle($author, $this->school, ['title' => 'Zwykły', 'body' => '<p>x</p>'], ['role:teacher']);

    expect($channels($withDevice, 'Pilne'))->toBe(['database', WebPushChannel::class])
        ->and($channels($noDevice, 'Pilne'))->toBe(['database'])
        ->and($channels($withDevice, 'Zwykły'))->toBeNull() // no channel at all: nothing is sent
        ->and($channels($optedIn, 'Zwykły'))->toBe([WebPushChannel::class]);

    app(Features::class)->setForSchool($this->school, Feature::WebPush, false);
    $publish->handle($author, $this->school, ['title' => 'Bez push', 'body' => '<p>x</p>', 'priority' => 'urgent'], ['role:teacher']);
    expect($channels($withDevice, 'Bez push'))->toBe(['database']);
});

it('builds a push message that opens the post and stays for urgent ones', function () {
    $author = userWithRole(Role::Coordinator);
    $jan = userWithRole(Role::Teacher);
    actAsSchool($this->school, $author);
    $a = app(PublishAnnouncement::class)->handle($author, $this->school, ['title' => 'Ewakuacja próbna', 'body' => '<p>Jutro o 10:00</p>', 'priority' => 'urgent'], ['user:'.$jan->id]);

    $message = (new AnnouncementNotification($a))->toWebPush($jan);
    $data = $message->toArray();

    expect($message)->toBeInstanceOf(WebPushMessage::class)
        ->and($data['title'])->toContain('ZSM')
        ->and($data['body'])->toContain('Ewakuacja próbna')->toContain('Jutro o 10:00')
        ->and($data['requireInteraction'])->toBeTrue()
        ->and($data['data']['url'])->toBe('/komunikaty/'.$a->id)
        ->and($message->getOptions())->toMatchArray(['urgency' => 'high']);
});

it('renews a rotated subscription from the service worker by its old endpoint', function () {
    $user = userWithRole(Role::Teacher);
    $old = deviceFor($user);
    $new = subscription();

    $this->postJson(route('push.renew'), ['old_endpoint' => $old->endpoint, 'subscription' => $new])->assertOk();
    expect($old->fresh()->endpoint)->toBe($new['endpoint']);

    $this->postJson(route('push.renew'), ['old_endpoint' => 'https://unknown.test/x', 'subscription' => subscription()])->assertNotFound();
});

it('records delivery results per device', function () {
    $user = userWithRole(Role::Teacher);
    $device = deviceFor($user);
    $report = new MessageSentReport(new Psr7Request('POST', $device->endpoint), new Psr7Response(201), true);

    app(RecordPushResult::class)->handle(new NotificationSent($report, $device, new WebPushMessage));

    expect($device->fresh()->last_success_at)->not->toBeNull();
});

it('only sends the test notification over push', function () {
    expect((new TestPushNotification)->via(new stdClass))->toBe([WebPushChannel::class]);
});
