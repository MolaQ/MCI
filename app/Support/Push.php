<?php

namespace App\Support;

use App\Enums\Feature;
use App\Models\School;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Whether Web Push can be used: VAPID keys are configured and the `push`
 * feature is on (site, and the given school when there is one).
 */
class Push
{
    public static function configured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public static function enabled(School|int|false|null $school = false): bool
    {
        return self::configured() && app(Features::class)->enabled(Feature::WebPush, $school);
    }

    /**
     * The Web Push client used by the notification channel. Same as the
     * package's, plus a logger: without GMP/BCMath (common on shared hosting)
     * the library otherwise raises a PHP notice, which Laravel turns into an
     * exception and no push would ever leave. With pure-PHP maths it works,
     * only a little slower; real warnings still go to the Laravel log.
     */
    public static function client(): WebPush
    {
        $vapid = config('webpush.vapid');
        $auth = self::configured() ? ['VAPID' => [
            'subject' => $vapid['subject'] ?: config('app.url'),
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ]] : [];

        $logger = new class extends AbstractLogger
        {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (! in_array($level, [LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG], true)) {
                    Log::log($level, '[webpush] '.$message, $context);
                }
            }
        };

        return (new WebPush($auth, [], Http::timeout((int) (config('webpush.client_options.timeout') ?? 10))->buildClient(), new HttpFactory, new HttpFactory, null, $logger))
            ->setReuseVAPIDHeaders(true)
            ->setAutomaticPadding((bool) config('webpush.automatic_padding'));
    }

    public static function publicKey(): ?string
    {
        return self::configured() ? (string) config('webpush.vapid.public_key') : null;
    }
}
