<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Demo mode from the shell (same rules as Witryna → Tryb demo):
 *   php artisan mci:demo status | reset | on | off
 */
#[Signature('mci:demo {action=status : status, reset, on or off} {--force : Do not ask for confirmation}')]
#[Description('Separate demo database: show status, rebuild it, or switch the site to it and back')]
class DemoModeCommand extends Command
{
    public function handle(): int
    {
        $action = (string) $this->argument('action');

        $this->line('Main database: '.DemoMode::mainDatabaseName());
        $this->line('Demo database: '.DemoMode::databaseName());
        $this->line('Demo mode:     '.(DemoMode::isOn() ? 'ON' : 'off').'   Ready: '.(DemoMode::isReady() ? 'yes' : 'no'));

        try {
            switch ($action) {
                case 'status':
                    return self::SUCCESS;
                case 'reset':
                    DemoMode::assertSafe();
                    if (! $this->option('force') && ! $this->confirm('Rebuild the DEMO database from scratch? (the main database is not touched)')) {
                        return self::FAILURE;
                    }
                    DemoMode::reset();
                    $this->info('Demo database rebuilt. Log in as '.config('mci.demo.admin_email').' when demo mode is on.');

                    return self::SUCCESS;
                case 'on':
                    DemoMode::assertSafe();
                    if (! $this->option('force') && ! $this->confirm('Switch the WHOLE SITE to the demo database?')) {
                        return self::FAILURE;
                    }
                    DemoMode::enable();
                    $this->info('Demo mode is ON.');

                    return self::SUCCESS;
                case 'off':
                    DemoMode::disable();
                    $this->info('Demo mode is off: the site uses the main database again.');

                    return self::SUCCESS;
                default:
                    $this->error('Unknown action. Use: status, reset, on, off.');

                    return self::INVALID;
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
