<?php

namespace App\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Sign in with a nikniq.com account (Laravel Passport authorization-code flow).
 */
class NikNiqProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = [];

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.nikniq.base_url'), '/');
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl().'/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl().'/oauth/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl().'/api/user', [
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @param  array{id: int|string, name?: string, email?: string}  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => (string) $user['id'],
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }
}
