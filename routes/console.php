<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Branch;
use App\Models\EmailActivityLog;
use App\Models\ItLicense;
use App\Notifications\SupervisorWorkflowNotification;
use App\Notifications\MiriCertificateExpiryNotification;
use App\Models\MajorEquipmentCertificate;
use App\Services\LdapAuthenticator;
use App\Services\SupervisorNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Console\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ldap:test {username? : Optional LDAP username to look up or verify} {password? : Optional LDAP password to verify credentials}', function (LdapAuthenticator $ldap) {
    $username = $this->argument('username');
    $password = $this->argument('password');

    $result = $ldap->testConnection($username ?: null);

    $this->newLine();
    $this->info('LDAP Diagnostics');
    $this->table(
        ['Check', 'Value'],
        [
            ['Enabled', $result['enabled'] ? 'yes' : 'no'],
            ['PHP ldap extension', $result['extension_loaded'] ? 'yes' : 'no'],
            ['Host', $result['host'] ?: '(empty)'],
            ['Port', (string) $result['port']],
            ['Base DN', $result['base_dn'] ?: '(empty)'],
            ['Bind DN', $result['bind_dn'] ?: '(empty)'],
            ['Bind password configured', $result['bind_password_configured'] ? 'yes' : 'no'],
            ['Connection created', $result['connected'] ? 'yes' : 'no'],
            ['Search bind', $result['search_bind_ok'] ? 'ok' : 'failed'],
            ['LDAP error', $result['last_error'] ?: '-'],
        ]
    );

    if ($username) {
        $this->newLine();
        $this->info("Lookup result for [{$username}]");
        $this->table(
            ['Field', 'Value'],
            [
                ['Found in LDAP', $result['lookup_found'] ? 'yes' : 'no'],
                ['DN', $result['lookup_dn'] ?: '-'],
                ['Name', $result['lookup_name'] ?: '-'],
                ['Email', $result['lookup_email'] ?: '-'],
            ]
        );
    }

    if ($username && $password !== null) {
        $user = $ldap->attempt((string) $username, (string) $password);

        $this->newLine();
        if ($user) {
            $this->info('Credential verification passed.');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Local user id', (string) $user->id],
                    ['Name', $user->name],
                    ['Username', $user->username],
                    ['Email', $user->email],
                    ['Role', $user->role ?? 'viewer'],
                ]
            );
        } else {
            $this->error('Credential verification failed.');
        }
    } elseif ($username) {
        $this->warn('Username supplied without password. Lookup was tested, but credential bind was skipped.');
    } else {
        $this->warn('No username supplied. Only connection and service-account bind were tested.');
    }
})->purpose('Test LDAP connectivity, lookup, and optional credential verification.');

Artisan::command('it-licenses:notify-supervisors', function (SupervisorNotificationService $supervisorNotifications) {
    $branchId = Branch::query()->where('code', 'KL-IT')->value('id');

    if (! $branchId) {
        $this->error('KL-IT branch was not found.');
        return 1;
    }

    $today = today();
    $expiryCutoff = today()->addDays(30);
    $licenses = ItLicense::query()
        ->withoutBranchScope()
        ->where('branch_id', $branchId)
        ->where('active', true)
        ->where(function ($query) use ($today, $expiryCutoff) {
            $query->whereBetween('expiry_date', [$today, $expiryCutoff])
                ->orWhereDate('expiry_date', '<', $today)
                ->orWhereColumn('seats_assigned', '>=', 'seats_total');
        })
        ->get();

    $sent = 0;

    foreach ($licenses as $license) {
        if ($license->expiry_date && $license->expiry_date->betweenIncluded($today, $expiryCutoff)) {
            $subject = "IT licence expiring soon: {$license->license_code} ({$license->expiry_date->format('Y-m-d')})";
            $alreadySent = EmailActivityLog::query()->where('status', 'sent')->where('subject', $subject)->exists();

            if (! $alreadySent) {
                $supervisorNotifications->send(new SupervisorWorkflowNotification(
                    subject: $subject,
                    intro: "The IT licence {$license->software_name} will expire within 30 days.",
                    details: [
                        'Licence code' => $license->license_code,
                        'Software' => $license->software_name,
                        'Expiry date' => $license->expiry_date->format('Y-m-d'),
                        'Seats assigned' => $license->seats_assigned,
                        'Total seats' => $license->seats_total,
                        'Auto renewal' => $license->auto_renew ? 'Yes' : 'No',
                    ],
                    url: route('it-licenses.show', $license),
                    actionLabel: 'View licence',
                ), 'Unable to send expiring IT licence supervisor notification.');
                $sent++;
            }
        }

        if ($license->expiry_date && $license->expiry_date->lt($today)) {
            $subject = "IT licence expired: {$license->license_code} ({$license->expiry_date->format('Y-m-d')})";
            $alreadySent = EmailActivityLog::query()->where('status', 'sent')->where('subject', $subject)->exists();

            if (! $alreadySent) {
                $supervisorNotifications->send(new SupervisorWorkflowNotification(
                    subject: $subject,
                    intro: "The active IT licence {$license->software_name} has expired.",
                    details: [
                        'Licence code' => $license->license_code,
                        'Software' => $license->software_name,
                        'Expiry date' => $license->expiry_date->format('Y-m-d'),
                        'Seats assigned' => $license->seats_assigned,
                        'Total seats' => $license->seats_total,
                        'Auto renewal' => $license->auto_renew ? 'Yes' : 'No',
                    ],
                    url: route('it-licenses.show', $license),
                    actionLabel: 'View licence',
                ), 'Unable to send expired IT licence supervisor notification.');
                $sent++;
            }
        }

        if ((int) $license->seats_assigned >= (int) $license->seats_total) {
            $subject = "IT licence fully allocated: {$license->license_code}";
            $alreadySent = EmailActivityLog::query()->where('status', 'sent')->where('subject', $subject)->exists();

            if (! $alreadySent) {
                $supervisorNotifications->send(new SupervisorWorkflowNotification(
                    subject: $subject,
                    intro: "The IT licence {$license->software_name} has no seats remaining.",
                    details: [
                        'Licence code' => $license->license_code,
                        'Software' => $license->software_name,
                        'Seats assigned' => $license->seats_assigned,
                        'Total seats' => $license->seats_total,
                    ],
                    url: route('it-licenses.show', $license),
                    actionLabel: 'View licence',
                ), 'Unable to send fully allocated IT licence supervisor notification.');
                $sent++;
            }
        }
    }

    $this->info("KL-IT licence notifications sent: {$sent}.");
})->purpose('Notify KL IT supervisors about licence expiry and exhausted seats.');

Schedule::command('it-licenses:notify-supervisors')->dailyAt('08:00')->withoutOverlapping();


Artisan::command('paint:rollover', function (\App\Services\PaintStockLedger $ledger) {
    $ledger->rollover();
    $this->info('Paint stock months are current. Previous months retained.');
})->purpose('Carry Paint closing quantities into the current month without replaying COGs.');
Schedule::command('paint:rollover')->timezone('Asia/Kuala_Lumpur')->dailyAt('00:05')->withoutOverlapping();

Artisan::command('miri-certificates:notify-expiring', function () {
    $branchId = Branch::query()->where('code', 'MIRI')->value('id');
    if (! $branchId) {
        $this->error('MIRI branch was not found.');
        return Command::FAILURE;
    }

    $recipients = config('mail.miri_certificate_expiry_recipients', []);
    $targetDate = today()->addDays(60)->toDateString();
    $sent = 0;

    MajorEquipmentCertificate::withoutGlobalScopes()
        ->with('equipment')
        ->where('branch_id', $branchId)
        ->whereDate('expiry_date', $targetDate)
        ->where(function ($query) {
            $query->whereNull('expiry_reminder_sent_for')
                ->orWhereColumn('expiry_reminder_sent_for', '!=', 'expiry_date');
        })
        ->orderBy('id')
        ->each(function (MajorEquipmentCertificate $certificate) use ($recipients, &$sent): void {
            $location = mb_strtolower(trim((string) $certificate->equipment?->current_location));
            $email = $recipients[$location] ?? null;
            if (! $email) {
                Log::warning('No Miri certificate expiry recipient is configured for the equipment location.', [
                    'certificate_id' => $certificate->id,
                    'location' => $certificate->equipment?->current_location,
                ]);
                return;
            }

            try {
                Notification::route('mail', $email)->notify(new MiriCertificateExpiryNotification($certificate));
                $certificate->forceFill(['expiry_reminder_sent_for' => $certificate->expiry_date])->save();
                $sent++;
            } catch (Throwable $exception) {
                Log::error('Unable to send Miri certificate expiry reminder.', [
                    'certificate_id' => $certificate->id,
                    'recipient' => $email,
                    'exception' => $exception,
                ]);
            }
        });

    $this->info("Miri certificate expiry reminders sent: {$sent}.");
    return Command::SUCCESS;
})->purpose('Email Miri location contacts 60 days before equipment certificate expiry.');
Schedule::command('miri-certificates:notify-expiring')->timezone('Asia/Kuala_Lumpur')->dailyAt('08:00')->withoutOverlapping();
