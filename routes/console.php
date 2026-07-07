<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\LdapAuthenticator;

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
