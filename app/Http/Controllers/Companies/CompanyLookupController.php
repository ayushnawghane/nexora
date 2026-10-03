<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyGstin;
use App\Services\CompanyLookup\CompanyLookup;
use App\Services\CompanyLookup\LookupFailed;
use App\Support\IndianIdentifiers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only registry lookup used by the company forms to pre-fill fields. Also reports a company
 * already holding the identifier, so users open it instead of creating a duplicate.
 */
class CompanyLookupController extends Controller
{
    public function __invoke(Request $request, string $type, CompanyLookup $lookup): JsonResponse
    {
        $this->authorize('create', Company::class);

        $value = (string) IndianIdentifiers::normalise($request->query('value'));

        // The lookup to run, or null when the value isn't a well-formed identifier of that type.
        $fetch = match ($type) {
            'cin' => IndianIdentifiers::isCin($value) ? fn () => $lookup->cin($value) : null,
            'gstin' => IndianIdentifiers::isGstin($value) ? fn () => $lookup->gstin($value) : null,
            'pan' => IndianIdentifiers::isPan($value) ? fn () => $lookup->pan($value) : null,
            default => null,
        };
        if ($fetch === null) {
            return response()->json(['message' => 'Enter a valid '.strtoupper($type).' before looking it up.'], 422);
        }

        try {
            $details = $fetch();
        } catch (LookupFailed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $details->toArray(),
            'existing' => $this->existing($type, $value),
        ]);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function existing(string $type, string $value): ?array
    {
        $company = match ($type) {
            'cin' => Company::query()->where('cin', $value)->first(),
            'pan' => Company::query()->where('pan', $value)->first(),
            'gstin' => CompanyGstin::query()->with('company')->where('gstin', $value)->first()?->company,
            default => null,
        };

        return $company ? ['id' => $company->ulid, 'name' => $company->name] : null;
    }
}
