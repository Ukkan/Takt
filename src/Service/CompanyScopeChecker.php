<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CompanyScopeChecker
{
    /**
     * Throws a 403 unless the given user's company matches the entity's company.
     * Super admins are NOT exempted here — this is for controllers scoped to a
     * single company (manager routes); use getEffectiveCompanyScope() for
     * admin surfaces where a super admin should see everything.
     */
    public function denyAccessUnlessCompanyMatch(User $user, Company $entityCompany): void
    {
        $company = $user->getCompany();

        if ($company === null || $entityCompany->getId() !== $company->getId()) {
            throw new AccessDeniedException();
        }
    }

    /**
     * Returns the company a user's admin-surface access should be scoped to:
     * null for ROLE_SUPER_ADMIN (unscoped, platform-wide access), otherwise
     * the user's own company.
     */
    public function getEffectiveCompanyScope(User $user): ?Company
    {
        if (\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return null;
        }

        return $user->getCompany();
    }
}
