<?php

namespace Mamun724682\DbGovernor\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;

class AccessGuard
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    public function login(string $email): ?string
    {
        $normalized = strtolower($email);

        if (in_array($normalized, $this->adminEmails(), strict: true)) {
            return $this->issueToken($normalized, 'admin');
        }

        if (in_array($normalized, $this->employeeEmails(), strict: true)) {
            return $this->issueToken($normalized, 'employee');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateToken(string $token): array
    {
        try {
            $raw = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid token: decryption failed.', previous: $e);
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['email'], $data['role'], $data['expires_at'])) {
            throw new \RuntimeException('Invalid token: malformed payload.');
        }

        if (Date::parse($data['expires_at'])->isPast()) {
            throw new \RuntimeException('Token expired.');
        }

        $email = strtolower($data['email']);

        if (
            ! in_array($email, $this->adminEmails(), strict: true)
            && ! in_array($email, $this->employeeEmails(), strict: true)
        ) {
            throw new \RuntimeException('Email is no longer authorized.');
        }

        // Re-derive role in case the email was promoted/demoted since login
        $role = in_array($email, $this->adminEmails(), strict: true) ? 'admin' : 'employee';

        return array_merge($data, ['email' => $email, 'role' => $role]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function email(): string
    {
        return $this->payload['email'] ?? '';
    }

    public function role(): string
    {
        return $this->payload['role'] ?? '';
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    public function assertAdmin(): void
    {
        if (! $this->isAdmin()) {
            abort(403, 'Forbidden: admin access required.');
        }
    }

    private function issueToken(string $email, string $role): string
    {
        $expiryHours = (int) config('db-governor.token_expiry_hours', 8);

        $payload = json_encode([
            'email'      => $email,
            'role'       => $role,
            'expires_at' => now()->addHours($expiryHours)->toISOString(),
        ]);

        return Crypt::encryptString($payload);
    }

    /**
     * @return array<int, string>
     */
    private function adminEmails(): array
    {
        return array_map(
            'strtolower',
            config('db-governor.allowed.admins', [])
        );
    }

    /**
     * @return array<int, string>
     */
    private function employeeEmails(): array
    {
        return array_map(
            'strtolower',
            config('db-governor.allowed.employees', [])
        );
    }
}

