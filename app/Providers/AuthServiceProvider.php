<?php

namespace App\Providers;

use App\Models\Animal;
use App\Models\AnimalBatch;
use App\Models\Artisan;
use App\Models\BusinessDocument;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\CropCycle;
use App\Models\Document;
use App\Models\Event;
use App\Models\Farm;
use App\Models\Field;
use App\Models\Form;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AnimalBatchPolicy;
use App\Policies\AnimalPolicy;
use App\Policies\ArtisanPolicy;
use App\Policies\BusinessDocumentPolicy;
use App\Policies\ContactPolicy;
use App\Policies\CooperativeMemberPolicy;
use App\Policies\CropCyclePolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EventPolicy;
use App\Policies\FarmPolicy;
use App\Policies\FieldPolicy;
use App\Policies\FormPolicy;
use App\Policies\ItemPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\SeasonPolicy;
use App\Policies\TicketPolicy;
use App\Support\CurrentCompany;
use App\Support\Modules;
use App\Support\Permissions;
use App\Support\PlanEntitlements;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Document::class => DocumentPolicy::class,
        Contact::class => ContactPolicy::class,
        Item::class => ItemPolicy::class,
        Payment::class => PaymentPolicy::class,
        Receipt::class => ReceiptPolicy::class,
        Artisan::class => ArtisanPolicy::class,
        BusinessDocument::class => BusinessDocumentPolicy::class,
        Form::class => FormPolicy::class,
        Event::class => EventPolicy::class,
        Ticket::class => TicketPolicy::class,
        Farm::class => FarmPolicy::class,
        Field::class => FieldPolicy::class,
        Season::class => SeasonPolicy::class,
        CropCycle::class => CropCyclePolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        Animal::class => AnimalPolicy::class,
        AnimalBatch::class => AnimalBatchPolicy::class,
        CooperativeMember::class => CooperativeMemberPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        /*
         * The generated API docs (`/docs/api`) are a product surface, not an
         * internal tool — Scramble's own RestrictedDocsAccess middleware
         * defaults to local-only otherwise, which would hide them from the
         * partners/integrators they exist for. The nullable, defaulted
         * parameter is required: Laravel's Gate only treats a callback as
         * guest-allowed when its first parameter is explicitly nullable
         * (Gate::callbackAllowsGuests) — a zero-parameter closure does not
         * qualify and would deny every unauthenticated visitor.
         */
        Gate::define('viewApiDocs', fn (?User $user = null) => true);

        /*
         * A gate per catalogued permission, named after it. Model-backed checks
         * go through the policies above; these cover the page-level abilities
         * with no model behind them ("can this user open Reports at all") and
         * the verb-only ones like `sales.issue`.
         */
        foreach (Permissions::slugs() as $ability) {
            Gate::define($ability, function (User $user) use ($ability) {
                $company = app(CurrentCompany::class)->get();

                // No company means no company-scoped permission, ever.
                if ($company === null) {
                    return false;
                }

                /*
                 * The partner programme is a property of the account, not of
                 * the person: a plain business has no client book to manage and
                 * no balance to withdraw, so no role inside it can reach these.
                 * Checked here rather than route-by-route so the navigation,
                 * the routes and the components all inherit one answer.
                 */
                if (str_starts_with($ability, 'partners.') && ! $company->isSecretariat()) {
                    return false;
                }

                return $user->hasPermissionIn($company, $ability);
            });
        }

        /*
         * Can this BUSINESS use this module at all? Plan denial is absolute and
         * comes before every other question: no role escalates past a plan the
         * business isn't paying for. A Basic-plan Owner is still a Basic-plan
         * Owner.
         *
         * Nothing else is answered here. Whether this PERSON may act is left to
         * the gates and policies below, including for the Owner — a `true`
         * returned from here would satisfy the whole check and skip the policy,
         * taking the ownership and immutability guards down with it. The Owner's
         * blanket grant lives in User::hasPermissionIn() instead, where it
         * answers the permission question without also answering the ones the
         * policies exist to ask.
         */
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            $company = app(CurrentCompany::class)->get();

            if ($company === null) {
                return null;
            }

            /*
             * Has this business switched the module off? Asked here, once, so
             * the navigation, the routes, the quick actions and the components
             * all go quiet together — see config/modules.php.
             *
             * Two lookups because a page-level check asks `sales.view` while a
             * policy check asks `view` with a Document: the ability names the
             * module in the first case and the model names it in the second,
             * and missing the second would leave a disabled module's detail
             * pages reachable by their direct URL.
             */
            $module = Modules::forAbility($ability)
                ?? (isset($arguments[0]) && ($arguments[0] instanceof Model || is_string($arguments[0]))
                    ? Modules::forModel($arguments[0])
                    : null);

            if ($module !== null && ! Modules::enabled($company, $module)) {
                return Response::deny(
                    Modules::label($module).' is switched off for this business. Turn it on in Settings.'
                );
            }

            if (! PlanEntitlements::allowsAbility($company, $ability)) {
                $module = Str::headline(explode('.', $ability, 2)[0] ?? $ability);
                $minPlan = Str::headline(PlanEntitlements::minimumPlanFor(explode('.', $ability, 2)[0] ?? '') ?? 'a higher plan');

                return Response::deny("{$module} isn't included in the {$company->plan} plan. Upgrade to {$minPlan} to use it.");
            }

            return null;
        });
    }
}
