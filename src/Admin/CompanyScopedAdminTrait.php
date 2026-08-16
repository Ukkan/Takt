<?php

namespace App\Admin;

use App\Entity\Company;
use App\Entity\User;
use App\Service\CompanyScopeChecker;
use Doctrine\ORM\QueryBuilder;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Restricts Sonata Admin classes to the current admin's own company.
 * ROLE_SUPER_ADMIN users are unrestricted (see CompanyScopeChecker::getEffectiveCompanyScope).
 */
trait CompanyScopedAdminTrait
{
    private Security $security;
    private CompanyScopeChecker $companyScopeChecker;

    #[Required]
    public function setCompanyScoping(Security $security, CompanyScopeChecker $companyScopeChecker): void
    {
        $this->security = $security;
        $this->companyScopeChecker = $companyScopeChecker;
    }

    /**
     * The company the current admin is restricted to, or null when unrestricted
     * (ROLE_SUPER_ADMIN, or no user available e.g. during CLI/cache warmup).
     */
    protected function currentCompanyScope(): ?Company
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->companyScopeChecker->getEffectiveCompanyScope($user);
    }

    /**
     * Filters the admin's list/edit/show query to rows whose $companyField
     * association matches the current admin's company. No-op when unrestricted.
     */
    protected function applyCompanyScope(ProxyQueryInterface $query, string $companyField): ProxyQueryInterface
    {
        $scope = $this->currentCompanyScope();
        if ($scope === null) {
            return $query;
        }

        /** @var QueryBuilder $qb */
        $qb = $query->getQueryBuilder();
        $rootAlias = $qb->getRootAliases()[0];
        $parameterValue = $companyField === 'id' ? $scope->getId() : $scope;
        $qb->andWhere(sprintf('%s.%s = :scopedCompany', $rootAlias, $companyField))
            ->setParameter('scopedCompany', $parameterValue);

        return $query;
    }

    /**
     * Query builder restriction for EntityType(Company::class) form fields,
     * so a company-scoped admin can't pick another company from a dropdown.
     * Returns null (no restriction) when unrestricted.
     */
    protected function companyQueryBuilderRestriction(): ?callable
    {
        $scope = $this->currentCompanyScope();
        if ($scope === null) {
            return null;
        }

        return static fn ($er) => $er->createQueryBuilder('c')
            ->where('c.id = :id')
            ->setParameter('id', $scope->getId());
    }
}
