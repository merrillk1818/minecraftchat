<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    public function __construct(private string $secretKey) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail('Security check is required.');
            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret'   => $this->secretKey,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]
            );

            if (! $response->json('success')) {
                $fail('Security check failed. Please try again.');
            }
        } catch (\Exception) {
            $fail('Could not verify security check. Please try again.');
        }
    }
}
