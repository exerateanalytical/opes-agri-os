<?php

namespace App\Livewire\Settings;

use App\Models\PersonalAccessToken;
use App\Services\ApiTokenIssuer;
use App\Support\CurrentCompany;
use App\Support\Permissions;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Company Settings → API Keys — the self-serve half of "API as a product".
 *
 * A token can only be minted with abilities the issuing user already holds
 * (ApiTokenIssuer enforces this too; the picklist here just keeps the form
 * honest about what's actually available, rather than offering a choice that
 * would be rejected). The plaintext token is shown exactly once, immediately
 * after creation — Sanctum stores only its hash, so there is no "reveal"
 * later; losing it means revoking and minting a new one.
 */
class ApiKeys extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedAbilities = [];

    public ?int $expiresInDays = null;

    public ?string $newlyIssuedToken = null;

    public function mount(): void
    {
        $this->authorize('settings.update');
    }

    public function create(ApiTokenIssuer $issuer): void
    {
        $this->authorize('settings.update');

        $available = $this->availableAbilities();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'selectedAbilities' => ['required', 'array', 'min:1'],
            'selectedAbilities.*' => ['string', 'in:'.implode(',', $available)],
            'expiresInDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $company = app(CurrentCompany::class)->get();

        $newToken = $issuer->issue(
            auth()->user(),
            $company,
            $this->name,
            $this->selectedAbilities,
            $this->expiresInDays ? now()->addDays($this->expiresInDays) : null,
        );

        $this->newlyIssuedToken = $newToken->plainTextToken;
        $this->reset('name', 'selectedAbilities', 'expiresInDays');
    }

    public function revoke(int $tokenId): void
    {
        $this->authorize('settings.update');

        $company = app(CurrentCompany::class)->get();

        // Scoped by company_id explicitly rather than a global tenant scope —
        // PersonalAccessToken deliberately has none, see TenancyTest's
        // exemption list — so a token from another company can never be
        // revoked from here even if its id were guessed.
        PersonalAccessToken::query()
            ->where('company_id', $company?->id)
            ->where('id', $tokenId)
            ->delete();
    }

    public function dismissNewToken(): void
    {
        $this->newlyIssuedToken = null;
    }

    /** Permission slugs the signed-in user actually holds in this company. */
    protected function availableAbilities(): array
    {
        $user = auth()->user();
        $company = app(CurrentCompany::class)->get();

        if ($company === null) {
            return [];
        }

        return array_values(array_filter(
            Permissions::slugs(),
            fn (string $slug) => $user->hasPermissionIn($company, $slug),
        ));
    }

    public function render(): View
    {
        $company = app(CurrentCompany::class)->get();

        $tokens = $company
            ? PersonalAccessToken::query()
                ->where('company_id', $company->id)
                ->latest()
                ->get()
            : collect();

        return view('livewire.settings.api-keys', [
            'company' => $company,
            'tokens' => $tokens,
            'availableAbilities' => $this->availableAbilities(),
        ])->layout('components.layouts.app', ['title' => 'API Keys', 'active' => 'settings']);
    }
}
