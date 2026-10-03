<?php

namespace App\Services\CompanyLookup;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Codium's CIN/GST/PAN API (the same service the legacy app used, see legacy app/Helpers/api.php).
 * Signs in with the company code and password for a bearer token (cached), then POSTs the identifier;
 * the record is in `data.data`. Response shapes are taken from the legacy integration and must be
 * re-checked against UAT once credentials are available.
 */
class CodiumCompanyLookup implements CompanyLookup
{
    private const TOKEN_CACHE_KEY = 'company-lookup:codium:token';

    /**
     * @param  array<string, mixed>  $config  config('services.company_lookup.codium')
     */
    public function __construct(private readonly array $config) {}

    public function cin(string $cin): CinDetails
    {
        $record = $this->fetch('cin_url', ['cin' => $cin, 'flag' => null], 'company');
        $info = (array) data_get($record, 'details.company_info', []);

        return new CinDetails(
            cin: (string) ($info['cin'] ?? $cin),
            name: $this->requireName(data_get($record, 'company_name'), 'company'),
            incorporatedOn: $this->date($info['date_of_incorporation'] ?? null),
            category: CinDetails::categoryFrom($info['company_category'] ?? null),
            status: $this->text($info['company_status'] ?? null),
            email: $this->text($info['email_id'] ?? null),
            registeredAddress: $this->text($info['registered_address'] ?? null),
        );
    }

    public function gstin(string $gstin): GstinDetails
    {
        $record = $this->fetch('gst_url', ['gst' => $gstin, 'flag' => 1], 'GST');
        $tradeName = $this->text(data_get($record, 'business_name'));

        return new GstinDetails(
            gstin: (string) (data_get($record, 'gstin') ?: $gstin),
            legalName: $this->text(data_get($record, 'legal_name')),
            tradeName: $tradeName !== null && strcasecmp($tradeName, 'NA') === 0 ? null : $tradeName,
            registeredOn: $this->date(data_get($record, 'date_of_registration')),
            status: $this->text(data_get($record, 'gstin_status')),
            cancelledOn: $this->date(data_get($record, 'date_of_cancellation')),
            constitution: $this->text(data_get($record, 'constitution_of_business')),
            principalAddress: $this->text(data_get($record, 'contact_details.principal.address')),
        );
    }

    public function pan(string $pan): PanDetails
    {
        $record = $this->fetch('pan_url', ['pan' => $pan, 'flag' => null], 'PAN');

        return new PanDetails(
            (string) (data_get($record, 'pan_number') ?: $pan),
            $this->requireName(data_get($record, 'full_name'), 'PAN'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fetch(string $urlKey, array $payload, string $what): array
    {
        try {
            $response = $this->send($urlKey, $payload);

            // A rejected token: sign in again once.
            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $this->send($urlKey, $payload);
            }
        } catch (ConnectionException $e) {
            Log::warning('Company lookup unreachable', ['endpoint' => $urlKey, 'error' => $e->getMessage()]);
            throw LookupFailed::unavailable();
        }

        if ($response->serverError() || $response->status() === 401) {
            Log::warning('Company lookup failed', ['endpoint' => $urlKey, 'status' => $response->status()]);
            throw LookupFailed::unavailable();
        }

        $record = $response->json('data.data');
        if (! is_array($record) || $record === []) {
            throw LookupFailed::notFound($what);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $urlKey, array $payload): Response
    {
        return $this->client()
            ->withToken($this->token())
            ->withHeaders(array_filter([
                'apikey' => $this->config['surepass_key'] ?? null,
                'token' => $this->config['stack_token'] ?? null,
                'email' => $this->config['email'] ?? null,
            ]))
            ->post((string) $this->config[$urlKey], $payload);
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            try {
                $response = $this->client()
                    ->withHeaders(array_filter(['apikey' => $this->config['jwt_secret'] ?? null]))
                    ->post((string) $this->config['login_url'], [
                        'company_code' => $this->config['company_code'] ?? null,
                        'password' => $this->config['password'] ?? null,
                    ]);
            } catch (ConnectionException) {
                throw LookupFailed::unavailable();
            }

            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                Log::warning('Company lookup sign-in failed', ['status' => $response->status()]);
                throw LookupFailed::unavailable();
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->withOptions(['verify' => (bool) ($this->config['verify_ssl'] ?? true)]);
    }

    private function requireName(mixed $value, string $what): string
    {
        return $this->text($value) ?? throw LookupFailed::notFound($what);
    }

    private function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /** The API's date format isn't documented; accept the common ones and drop anything else. */
    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-Y', 'd M Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date !== null && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        return null;
    }
}
