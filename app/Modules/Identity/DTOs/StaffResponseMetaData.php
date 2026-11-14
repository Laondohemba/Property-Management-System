<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use Spatie\LaravelData\Optional;

/**
 * Meta of single-user responses: the screen's options, and after an invite whether an e-mail was sent.
 */
class StaffResponseMetaData extends StaffOptionsData
{
    /**
     * @param  array<int, PropertyRefData>  $properties
     * @param  array<int, AssignableRoleData>  $roles
     */
    public function __construct(
        array $properties,
        array $roles,
        bool $can_manage_accounts,
        public bool|Optional $invited,
    ) {
        parent::__construct($properties, $roles, $can_manage_accounts);
    }

    public static function make(StaffOptionsData $options, ?bool $invited = null): self
    {
        return new self($options->properties, $options->roles, $options->can_manage_accounts, $invited ?? Optional::create());
    }
}
