<?php

namespace App\Services;

use App\Models\User;
use App\Models\Branch;
use App\Support\AccessMatrix;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LdapAuthenticator
{
    public function enabled(): bool
    {
        return (bool) config('ldap.enabled', false);
    }

    public function attempt(string $username, string $password): ?User
    {
        if (! $this->enabled() || $password === '' || ! function_exists('ldap_connect')) {
            return null;
        }

        $connection = $this->connect();

        if (! $connection) {
            return null;
        }

        try {
            $this->configureConnection($connection);

            if (! $this->bindForSearch($connection)) {
                return null;
            }

            $entry = $this->findDirectoryUser($connection, $username);

            if (! $entry || empty($entry['dn'])) {
                $this->log('warning', 'LDAP user not found.', ['username' => $username]);

                return null;
            }

            if (! @ldap_bind($connection, $entry['dn'], $password)) {
                $this->log('warning', 'LDAP credential bind failed.', ['username' => $username, 'dn' => $entry['dn']]);

                return null;
            }

            return $this->syncUser($entry, $username);
        } finally {
            @ldap_unbind($connection);
        }
    }

    public function testConnection(?string $lookupUsername = null): array
    {
        $result = [
            'enabled' => $this->enabled(),
            'extension_loaded' => function_exists('ldap_connect'),
            'host' => (string) config('ldap.host'),
            'port' => (int) config('ldap.port', 389),
            'base_dn' => (string) config('ldap.base_dn'),
            'bind_dn' => (string) config('ldap.username'),
            'bind_password_configured' => config('ldap.password') !== null && config('ldap.password') !== '',
            'connected' => false,
            'search_bind_ok' => false,
            'lookup_username' => $lookupUsername,
            'lookup_found' => false,
            'lookup_dn' => null,
            'lookup_name' => null,
            'lookup_email' => null,
            'last_error' => null,
        ];

        if (! $result['enabled'] || ! $result['extension_loaded']) {
            return $result;
        }

        $connection = $this->connect();

        if (! $connection) {
            return $result;
        }

        $result['connected'] = true;

        try {
            $this->configureConnection($connection);

            if (! $this->bindForSearch($connection)) {
                $result['last_error'] = $this->ldapError($connection);

                return $result;
            }

            $result['search_bind_ok'] = true;

            if ($lookupUsername) {
                $entry = $this->findDirectoryUser($connection, $lookupUsername);

                if ($entry && ! empty($entry['dn'])) {
                    $result['lookup_found'] = true;
                    $result['lookup_dn'] = $entry['dn'];
                    $result['lookup_name'] = $this->attribute($entry, 'displayname') ?: $this->attribute($entry, 'cn');
                    $result['lookup_email'] = $this->attribute($entry, 'mail');
                }
            }

            return $result;
        } finally {
            @ldap_unbind($connection);
        }
    }

    protected function connect()
    {
        $host = (string) config('ldap.host');
        $port = (int) config('ldap.port', 389);

        if ($host === '') {
            $this->log('warning', 'LDAP host is missing.');

            return null;
        }

        $prefix = config('ldap.ssl') ? 'ldaps://' : 'ldap://';
        $connection = @ldap_connect(Str::startsWith($host, ['ldap://', 'ldaps://']) ? $host : $prefix.$host, $port);

        if (! $connection) {
            $this->log('error', 'LDAP connection could not be created.', ['host' => $host, 'port' => $port]);
        }

        return $connection;
    }

    protected function configureConnection($connection): void
    {
        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, (int) config('ldap.timeout', 5));

        if ((bool) config('ldap.tls', false) && ! (bool) config('ldap.ssl', false)) {
            @ldap_start_tls($connection);
        }
    }

    protected function bindForSearch($connection): bool
    {
        $bindDn = (string) config('ldap.username');
        $bindPassword = (string) config('ldap.password');

        $bound = $bindDn !== ''
            ? @ldap_bind($connection, $bindDn, $bindPassword)
            : @ldap_bind($connection);

        if (! $bound) {
            $this->log('error', 'LDAP search bind failed.', ['bind_dn' => $bindDn !== '' ? $bindDn : null]);

            return false;
        }

        return true;
    }

    protected function ldapError($connection): ?string
    {
        if (! function_exists('ldap_error')) {
            return null;
        }

        $error = @ldap_error($connection);

        return is_string($error) && $error !== '' ? $error : null;
    }

    protected function findDirectoryUser($connection, string $username): ?array
    {
        $baseDn = (string) config('ldap.base_dn');

        if ($baseDn === '') {
            $this->log('warning', 'LDAP base DN is missing.');

            return null;
        }

        $safeUsername = function_exists('ldap_escape')
            ? ldap_escape($username, '', LDAP_ESCAPE_FILTER)
            : addcslashes($username, '\\()*'."\x00");
        $filter = sprintf('(|(sAMAccountName=%1$s)(userPrincipalName=%1$s)(mail=%1$s)(cn=%1$s))', $safeUsername);
        $attributes = ['cn', 'displayname', 'mail', 'samaccountname', 'userprincipalname'];
        $search = @ldap_search($connection, $baseDn, $filter, $attributes);

        if (! $search) {
            $this->log('error', 'LDAP search failed.', ['username' => $username, 'base_dn' => $baseDn]);

            return null;
        }

        $entries = @ldap_get_entries($connection, $search);

        if (! is_array($entries) || ($entries['count'] ?? 0) < 1) {
            return null;
        }

        return $entries[0];
    }

    protected function syncUser(array $entry, string $submittedUsername): User
    {
        $directoryUsername = Str::lower($this->attribute($entry, 'samaccountname')
            ?: $this->attribute($entry, 'userprincipalname')
            ?: $submittedUsername);

        $email = Str::lower($this->attribute($entry, 'mail') ?: "{$directoryUsername}@ldap.local");
        $name = $this->attribute($entry, 'displayname')
            ?: $this->attribute($entry, 'cn')
            ?: Str::title(str_replace(['.', '_'], ' ', $directoryUsername));

        $user = User::query()
            ->where('username', $directoryUsername)
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = new User();
            $user->role = 'viewer';
            $user->permissions = AccessMatrix::permissionsForKlStaff();
            $user->password = Hash::make(Str::random(40));
            $isNew = true;
        }

        $user->name = $name;
        $user->username = $directoryUsername;
        $user->email = $email;
        $user->email_verified_at ??= now();
        $user->save();

        if (($isNew ?? false) && $user->branches()->count() === 0) {
            $kl = Branch::query()->where('code', 'KL-IT')->first();
            if ($kl) {
                $user->branches()->attach($kl->id, ['access_level' => 'edit', 'is_default' => true]);
            }
        }

        return $user;
    }

    protected function attribute(array $entry, string $key): ?string
    {
        $attribute = strtolower($key);
        $value = $entry[$attribute][0] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if (! (bool) config('ldap.logging', true)) {
            return;
        }

        Log::channel(config('ldap.log_channel'))->{$level}($message, $context);
    }
}
