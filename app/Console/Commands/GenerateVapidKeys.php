<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID public/private key pair for browser push notifications';

    public function handle(): int
    {
        if (filled(config('webpush.public_key')) || filled(config('webpush.private_key'))) {
            $this->warn('VAPID keys are already configured. Rotating them invalidates every existing browser subscription.');
        }

        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->line('Add these lines to your .env file:');
        $this->newLine();
        $this->line('WEBPUSH_ENABLED=true');
        $this->line('VAPID_SUBJECT='.config('webpush.subject'));
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Keep VAPID_PRIVATE_KEY server-side. Never expose it to JavaScript or commit it.');

        return self::SUCCESS;
    }
}
