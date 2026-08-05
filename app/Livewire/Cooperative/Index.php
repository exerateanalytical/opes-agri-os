<?php

namespace App\Livewire\Cooperative;

use App\Enums\PaymentMethod;
use App\Models\Contact;
use App\Models\CooperativeMember;
use App\Models\Loan;
use App\Services\Cooperative\LoanDisburser;
use App\Services\Cooperative\LoanRepaymentRecorder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Members and loans in one module page — following the Farms `Index.php`
 * tabbed precedent. Contribution and repayment recording are their own
 * components/actions with their own validation shapes, same reasoning as
 * Crops' RecordHarvest.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $tab = 'members'; // members|loans

    #[Url]
    public string $statusFilter = '';

    // ── Member form ─────────────────────────────────────────────────────
    public bool $adding = false;

    public ?string $editingId = null;

    public string $contactId = '';

    public string $membershipNumber = '';

    public string $joinedOn = '';

    public string $notes = '';

    // ── Loan form ───────────────────────────────────────────────────────
    public bool $addingLoan = false;

    public string $loanMemberId = '';

    public string $loanPrincipal = '';

    public string $loanInterestRate = '';

    public string $loanTermMonths = '';

    public string $loanNotes = '';

    // ── Repayment form ──────────────────────────────────────────────────
    public ?string $repayingLoanId = null;

    public string $repaymentAmount = '';

    public string $repaymentMethod = 'cash';

    public function mount(): void
    {
        Gate::authorize('cooperative.view');
    }

    public function startAdding(): void
    {
        $this->authorize('create', CooperativeMember::class);
        $this->resetForm();
        $this->adding = true;
    }

    public function edit(string $memberId): void
    {
        $member = CooperativeMember::findOrFail($memberId);
        $this->authorize('update', $member);

        $this->editingId = $member->id;
        $this->contactId = $member->contact_id;
        $this->membershipNumber = (string) $member->membership_number;
        $this->joinedOn = (string) $member->joined_on?->toDateString();
        $this->notes = (string) $member->notes;
        $this->adding = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'contactId' => ['required_without:editingId', 'exists:contacts,id'],
            'membershipNumber' => ['nullable', 'string', 'max:100'],
            'joinedOn' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $member = CooperativeMember::findOrFail($this->editingId);
            $this->authorize('update', $member);
            $member->update([
                'membership_number' => $data['membershipNumber'] ?: null,
                'joined_on' => $data['joinedOn'] ?: null,
                'notes' => $data['notes'] ?: null,
            ]);
        } else {
            $this->authorize('create', CooperativeMember::class);
            CooperativeMember::create([
                'contact_id' => $data['contactId'],
                'membership_number' => $data['membershipNumber'] ?: null,
                'joined_on' => $data['joinedOn'] ?: null,
                'notes' => $data['notes'] ?: null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
        }

        $this->resetForm();
        $this->adding = false;
    }

    public function delete(string $memberId): void
    {
        $member = CooperativeMember::findOrFail($memberId);
        $this->authorize('delete', $member);
        $member->delete();
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'contactId', 'membershipNumber', 'joinedOn', 'notes']);
    }

    public function startAddingLoan(): void
    {
        $this->authorize('create', Loan::class);
        $this->reset(['loanMemberId', 'loanPrincipal', 'loanInterestRate', 'loanTermMonths', 'loanNotes']);
        $this->addingLoan = true;
    }

    public function saveLoan(): void
    {
        $this->authorize('create', Loan::class);

        $data = $this->validate([
            'loanMemberId' => ['required', 'exists:cooperative_members,id'],
            'loanPrincipal' => ['required', 'numeric', 'gt:0'],
            'loanInterestRate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'loanTermMonths' => ['nullable', 'integer', 'min:1'],
            'loanNotes' => ['nullable', 'string'],
        ]);

        Loan::create([
            'cooperative_member_id' => $data['loanMemberId'],
            'principal' => $data['loanPrincipal'],
            'interest_rate' => $data['loanInterestRate'] !== '' ? $data['loanInterestRate'] : 0,
            'term_months' => $data['loanTermMonths'] ?: null,
            'total_interest' => 0,
            'total_repayable' => 0,
            'balance' => 0,
            'notes' => $data['loanNotes'] ?: null,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        $this->reset(['loanMemberId', 'loanPrincipal', 'loanInterestRate', 'loanTermMonths', 'loanNotes']);
        $this->addingLoan = false;
    }

    public function disburseLoan(string $loanId, LoanDisburser $disburser): void
    {
        $loan = Loan::findOrFail($loanId);
        $this->authorize('disburse', $loan);

        try {
            $disburser->disburse($loan, auth()->user(), PaymentMethod::Cash);
        } catch (RuntimeException $e) {
            $this->addError('loan', $e->getMessage());
        }
    }

    public function openRepayment(string $loanId): void
    {
        $loan = Loan::findOrFail($loanId);
        $this->authorize('recordRepayment', $loan);

        $this->repayingLoanId = $loan->id;
        $this->repaymentAmount = '';
        $this->repaymentMethod = 'cash';
    }

    public function closeRepayment(): void
    {
        $this->repayingLoanId = null;
    }

    public function saveRepayment(LoanRepaymentRecorder $recorder): void
    {
        $data = $this->validate([
            'repaymentAmount' => ['required', 'numeric', 'gt:0'],
            'repaymentMethod' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
        ]);

        $loan = Loan::findOrFail($this->repayingLoanId);
        $this->authorize('recordRepayment', $loan);

        try {
            $recorder->record($loan, auth()->user(), (float) $data['repaymentAmount'], PaymentMethod::from($data['repaymentMethod']));
        } catch (RuntimeException $e) {
            $this->addError('repaymentAmount', $e->getMessage());

            return;
        }

        $this->repayingLoanId = null;
    }

    /** No-op body — Livewire re-renders the list on any listened event. */
    #[On('contribution-recorded')]
    public function refreshAfterContribution(): void {}

    public function render(): View
    {
        $members = CooperativeMember::query()
            ->with('contact')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        $loans = Loan::query()
            ->with('member.contact')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.cooperative.index', [
            'members' => $members,
            'loans' => $loans,
            'contacts' => Contact::query()->orderBy('name')->get(),
            'coopMembers' => CooperativeMember::query()->with('contact')->orderBy('id')->get(),
        ])->layout('components.layouts.app', ['title' => 'Cooperative', 'active' => 'cooperative']);
    }
}
