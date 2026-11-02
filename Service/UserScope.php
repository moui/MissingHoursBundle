<?php

declare(strict_types=1);

/*
 * This file is part of the "MissingHoursBundle" for Kimai.
 * Copyright (c) 2026 Mauricio Barrera (https://github.com/moui)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\MissingHoursBundle\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\RolePermissionManager;

/**
 * Who is subject to the missing-hours check, and who may see whom.
 */
final class UserScope
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RolePermissionManager $permissions,
    ) {
    }

    /**
     * Enabled, non-system users without ROLE_ADMIN / ROLE_SUPER_ADMIN: admins
     * manage the system and are not expected to book working hours.
     *
     * With a viewer, the list is narrowed by Kimai's own visibility rules:
     * admins see everyone, team leads see the members of the teams they lead.
     *
     * @return list<User>
     */
    public function listTracked(?User $viewer = null): array
    {
        $users = array_filter(
            $this->users->findAll(),
            fn (User $user): bool => $user->isEnabled()
                && !$user->isSystemAccount()
                && !$user->hasRole('ROLE_ADMIN')
                && !$user->hasRole('ROLE_SUPER_ADMIN')
                && ($viewer === null || $this->permissions->checkUserAccess($user, $viewer))
        );

        usort($users, static fn (User $a, User $b): int => strcasecmp($a->getDisplayName(), $b->getDisplayName()));

        return $users;
    }
}
