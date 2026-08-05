@php
    $inputClass = 'h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-[14.5px] text-ink placeholder:text-faint focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';
    $labelClass = 'mb-1.5 block text-[13px] font-semibold text-ink-2';
    $memberStatusTone = fn (string $status) => match ($status) {
        'active' => 'positive',
        'suspended' => 'warning',
        'exited' => 'muted',
        default => 'neutral',
    };
    $loanStatusTone = fn (string $status) => match ($status) {
        'active' => 'positive',
        'closed' => 'muted',
        'defaulted' => 'negative',
        default => 'neutral',
    };
    $meetingStatusTone = fn (string $status) => match ($status) {
        'held' => 'positive',
        'cancelled' => 'muted',
        default => 'neutral',
    };
    $voteStatusTone = fn (string $status) => $status === 'open' ? 'positive' : 'muted';
    $addAction = [
        'members' => 'startAdding',
        'loans' => 'startAddingLoan',
        'meetings' => 'startAddingMeeting',
        'votes' => 'startAddingVote',
    ][$tab];
@endphp

<div class="px-5 pb-8 lg:px-6 lg:pt-6">

    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[25px] font-bold leading-tight tracking-[-0.03em] text-ink lg:text-[28px]">Cooperative</h1>
            <p class="mt-1 text-[14.5px] text-muted">Member registry, contributions, loans, and governance.</p>
        </div>
        @can('cooperative.create')
            <button type="button" wire:click="{{ $addAction }}"
                    class="tap focusable flex shrink-0 items-center gap-2 rounded-full bg-fill-brand px-5 py-2.5 text-[14.5px] font-semibold text-white transition-opacity hover:opacity-90">
                <x-icon name="plus" class="size-[18px]" />
                <span class="sr-only min-[420px]:not-sr-only">Add</span>
            </button>
        @endcan
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (['members' => 'Members', 'loans' => 'Loans', 'meetings' => 'Meetings', 'votes' => 'Votes'] as $value => $label)
            <button type="button" wire:click="$set('tab', '{{ $value }}')"
                    class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $tab === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'members')
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['' => 'All', 'active' => 'Active', 'suspended' => 'Suspended', 'exited' => 'Exited'] as $value => $label)
                <button type="button" wire:click="$set('statusFilter', '{{ $value }}')"
                        class="tap focusable rounded-full px-4 py-2 text-[13.5px] font-semibold {{ $statusFilter === $value ? 'bg-fill-brand text-white' : 'bg-surface-2 text-ink-2 hover:bg-tint-blue hover:text-brand' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($adding)
            <x-ui.panel :title="$editingId ? 'Edit member' : 'New member'" class="mt-5">
                <form wire:submit="save" class="space-y-4">
                    @unless ($editingId)
                        <label class="block">
                            <span class="{{ $labelClass }}">Contact</span>
                            <select wire:model="contactId" class="{{ $inputClass }}">
                                <option value="">Choose a contact</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                                @endforeach
                            </select>
                            @error('contactId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                    @endunless
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="{{ $labelClass }}">Membership number</span>
                            <input type="text" wire:model="membershipNumber" class="{{ $inputClass }}">
                            @error('membershipNumber') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $labelClass }}">Joined on</span>
                            <input type="date" wire:model="joinedOn" class="{{ $inputClass }}">
                        </label>
                    </div>
                    <label class="block">
                        <span class="{{ $labelClass }}">Notes</span>
                        <textarea wire:model="notes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                        <button type="button" wire:click="$set('adding', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($members as $member)
                <div class="card p-4" wire:key="member-{{ $member->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">
                                {{ $member->contact->name ?? 'Member' }}
                                @if ($member->membership_number) · #{{ $member->membership_number }} @endif
                            </p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                Balance {{ number_format((float) $member->balance, 2) }}
                            </p>
                        </div>
                        <x-ui.status-badge :tone="$memberStatusTone($member->status)" :label="ucfirst($member->status)" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @can('update', $member)
                            <button type="button" wire:click="edit('{{ $member->id }}')" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-brand hover:bg-tint-blue">Edit</button>
                        @endcan
                        @can('recordContribution', $member)
                            <button type="button" wire:click="$dispatch('open-contribution', { memberId: '{{ $member->id }}' })" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Record contribution</button>
                        @endcan
                        @can('delete', $member)
                            <button type="button" wire:click="delete('{{ $member->id }}')" wire:confirm="Remove this member's record?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Delete</button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-[13.5px] text-muted">No members recorded yet.</p>
            @endforelse
        </div>
    @elseif ($tab === 'loans')
        @if ($addingLoan)
            <x-ui.panel title="New loan" class="mt-5">
                <form wire:submit="saveLoan" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Member</span>
                        <select wire:model="loanMemberId" class="{{ $inputClass }}">
                            <option value="">Choose a member</option>
                            @foreach ($coopMembers as $coopMember)
                                <option value="{{ $coopMember->id }}">{{ $coopMember->contact->name ?? 'Member' }}</option>
                            @endforeach
                        </select>
                        @error('loanMemberId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="{{ $labelClass }}">Principal</span>
                            <input type="number" step="0.01" wire:model="loanPrincipal" class="{{ $inputClass }}">
                            @error('loanPrincipal') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $labelClass }}">Interest rate (e.g. 0.10 for 10%)</span>
                            <input type="number" step="0.0001" wire:model="loanInterestRate" class="{{ $inputClass }}">
                        </label>
                    </div>
                    <label class="block">
                        <span class="{{ $labelClass }}">Term (months, optional)</span>
                        <input type="number" wire:model="loanTermMonths" class="{{ $inputClass }}">
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Notes</span>
                        <textarea wire:model="loanNotes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                        <button type="button" wire:click="$set('addingLoan', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        @error('loan') <p class="mt-4 text-[13px] font-medium text-negative">{{ $message }}</p> @enderror

        @if ($repayingLoanId)
            <x-ui.panel title="Record repayment" class="mt-5">
                <form wire:submit="saveRepayment" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Amount</span>
                        <input type="number" step="0.01" wire:model="repaymentAmount" autofocus class="{{ $inputClass }}">
                        @error('repaymentAmount') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Method</span>
                        <select wire:model="repaymentMethod" class="{{ $inputClass }}">
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile money</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="card">Card</option>
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                        <button type="button" wire:click="closeRepayment" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($loans as $loan)
                <div class="card p-4" wire:key="loan-{{ $loan->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">
                                {{ $loan->member->contact->name ?? 'Member' }}
                            </p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                Principal {{ number_format((float) $loan->principal, 2) }}
                                @if ($loan->status !== 'pending') · Balance {{ number_format((float) $loan->balance, 2) }} @endif
                            </p>
                        </div>
                        <x-ui.status-badge :tone="$loanStatusTone($loan->status)" :label="ucfirst($loan->status)" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @can('disburse', $loan)
                            @if ($loan->status === 'pending')
                                <button type="button" wire:click="disburseLoan('{{ $loan->id }}')" wire:confirm="Disburse this loan?" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Disburse</button>
                            @endif
                        @endcan
                        @can('recordRepayment', $loan)
                            @if ($loan->status === 'active')
                                <button type="button" wire:click="openRepayment('{{ $loan->id }}')" class="focusable rounded-lg bg-surface-2 px-3 py-1.5 text-[12.5px] font-semibold text-ink-2 hover:bg-tint-blue hover:text-brand">Record repayment</button>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-[13.5px] text-muted">No loans recorded yet.</p>
            @endforelse
        </div>
    @elseif ($tab === 'meetings')
        @if ($addingMeeting)
            <x-ui.panel title="New meeting" class="mt-5">
                <form wire:submit="saveMeeting" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Title</span>
                        <input type="text" wire:model="meetingTitle" placeholder="e.g. Annual General Meeting" class="{{ $inputClass }}">
                        @error('meetingTitle') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="{{ $labelClass }}">Scheduled on</span>
                            <input type="date" wire:model="meetingScheduledOn" class="{{ $inputClass }}">
                            @error('meetingScheduledOn') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $labelClass }}">Quorum required</span>
                            <input type="number" wire:model="meetingQuorumRequired" class="{{ $inputClass }}">
                        </label>
                    </div>
                    <label class="block">
                        <span class="{{ $labelClass }}">Notes</span>
                        <textarea wire:model="meetingNotes" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Save</button>
                        <button type="button" wire:click="$set('addingMeeting', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        @if ($markingAttendanceMeetingId)
            <x-ui.panel title="Mark attendance" class="mt-5">
                <form wire:submit="saveAttendance" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Member</span>
                        <select wire:model="attendanceMemberId" class="{{ $inputClass }}">
                            <option value="">Choose a member</option>
                            @foreach ($coopMembers as $coopMember)
                                <option value="{{ $coopMember->id }}">{{ $coopMember->contact->name ?? 'Member' }}</option>
                            @endforeach
                        </select>
                        @error('attendanceMemberId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Mark present</button>
                        <button type="button" wire:click="closeAttendance" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Done</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($meetings as $meeting)
                <div class="card p-4" wire:key="meeting-{{ $meeting->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">{{ $meeting->title }}</p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                {{ $meeting->scheduled_on?->format('d M Y') }}
                                · {{ $meeting->attendances_count }} attended
                                @if ($meeting->quorum_required > 0)
                                    of {{ $meeting->quorum_required }} needed
                                    ({{ $meeting->quorumMet() ? 'quorum met' : 'quorum not met' }})
                                @endif
                            </p>
                        </div>
                        <x-ui.status-badge :tone="$meetingStatusTone($meeting->status)" :label="ucfirst($meeting->status)" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @can('recordAttendance', $meeting)
                            <button type="button" wire:click="openAttendance('{{ $meeting->id }}')" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Mark attendance</button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-[13.5px] text-muted">No meetings recorded yet.</p>
            @endforelse
        </div>
    @else
        @if ($addingVote)
            <x-ui.panel title="New vote" class="mt-5">
                <form wire:submit="saveVote" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Title</span>
                        <input type="text" wire:model="voteTitle" placeholder="e.g. Approve the new bylaws" class="{{ $inputClass }}">
                        @error('voteTitle') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Meeting (optional)</span>
                        <select wire:model="voteMeetingId" class="{{ $inputClass }}">
                            <option value="">Not tied to a meeting</option>
                            @foreach ($meetings as $meeting)
                                <option value="{{ $meeting->id }}">{{ $meeting->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Description</span>
                        <textarea wire:model="voteDescription" rows="2" class="{{ $inputClass }} h-auto py-3"></textarea>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Open vote</button>
                        <button type="button" wire:click="$set('addingVote', false)" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Cancel</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        @if ($ballotingVoteId)
            <x-ui.panel title="Cast ballot" class="mt-5">
                <form wire:submit="saveBallot" class="space-y-4">
                    <label class="block">
                        <span class="{{ $labelClass }}">Member</span>
                        <select wire:model="ballotMemberId" class="{{ $inputClass }}">
                            <option value="">Choose a member</option>
                            @foreach ($coopMembers as $coopMember)
                                <option value="{{ $coopMember->id }}">{{ $coopMember->contact->name ?? 'Member' }}</option>
                            @endforeach
                        </select>
                        @error('ballotMemberId') <p class="mt-1 text-[12.5px] font-medium text-negative">{{ $message }}</p> @enderror
                    </label>
                    <label class="block">
                        <span class="{{ $labelClass }}">Choice</span>
                        <select wire:model="ballotChoice" class="{{ $inputClass }}">
                            <option value="for">For</option>
                            <option value="against">Against</option>
                            <option value="abstain">Abstain</option>
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="tap focusable rounded-xl bg-fill-brand px-5 py-2.5 text-[14px] font-semibold text-white">Cast ballot</button>
                        <button type="button" wire:click="closeBallot" class="tap focusable rounded-xl bg-surface-2 px-5 py-2.5 text-[14px] font-semibold text-ink-2">Done</button>
                    </div>
                </form>
            </x-ui.panel>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($votes as $vote)
                @php($tally = $vote->tally())
                <div class="card p-4" wire:key="vote-{{ $vote->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[14.5px] font-bold text-ink">{{ $vote->title }}</p>
                            <p class="mt-0.5 text-[12.5px] text-muted">
                                For {{ $tally['for'] }} · Against {{ $tally['against'] }} · Abstain {{ $tally['abstain'] }}
                                @if ($vote->meeting) · {{ $vote->meeting->title }} @endif
                            </p>
                        </div>
                        <x-ui.status-badge :tone="$voteStatusTone($vote->status)" :label="ucfirst($vote->status)" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($vote->status === 'open')
                            @can('castVote', $vote)
                                <button type="button" wire:click="openBallot('{{ $vote->id }}')" class="focusable rounded-lg bg-fill-brand px-3 py-1.5 text-[12.5px] font-semibold text-white">Cast ballot</button>
                            @endcan
                            @can('update', $vote)
                                <button type="button" wire:click="closeVote('{{ $vote->id }}')" wire:confirm="Close this vote?" class="focusable rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-negative hover:bg-tint-red">Close</button>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-[13.5px] text-muted">No votes recorded yet.</p>
            @endforelse
        </div>
    @endif

    <livewire:cooperative.record-contribution />
</div>
