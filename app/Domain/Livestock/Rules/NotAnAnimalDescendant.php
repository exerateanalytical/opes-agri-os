<?php

namespace App\Domain\Livestock\Rules;

use App\Models\Animal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a sire_id/dam_id value that is either the animal itself
 * (self-parentage) or one of the animal's own descendants (which would
 * create a parentage cycle — an ancestor becoming its own descendant's
 * child). Only meaningful on update, since a brand-new animal has no
 * descendants yet and can't reference itself before it has an id.
 */
class NotAnAnimalDescendant implements ValidationRule
{
    public function __construct(protected ?Animal $animal) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->animal === null || $value === null || $value === '') {
            return;
        }

        if ((string) $value === (string) $this->animal->id) {
            $fail('An animal cannot be its own parent.');

            return;
        }

        if (in_array((string) $value, $this->animal->descendantIds(), true)) {
            $fail('This would make one of the animal\'s own descendants its parent, creating a cycle.');
        }
    }
}
