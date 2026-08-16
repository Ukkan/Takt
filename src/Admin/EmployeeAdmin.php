<?php

namespace App\Admin;

use App\Entity\Company;
use App\Entity\User;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class EmployeeAdmin extends AbstractAdmin
{
    use CompanyScopedAdminTrait;

    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query = parent::configureQuery($query);

        return $this->applyCompanyScope($query, 'company');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $companyScope = $this->currentCompanyScope();

        $form
            ->with('General', ['class' => 'col-md-6'])
                ->add('company', EntityType::class, [
                    'class'         => Company::class,
                    'choice_label'  => 'name',
                    'query_builder' => $this->companyQueryBuilderRestriction(),
                    'disabled'      => $companyScope !== null,
                ])
                ->add('user', EntityType::class, [
                    'class'        => User::class,
                    'choice_label' => fn(User $u) => sprintf('%s (%s)', $u->getFullName() ?? $u->getEmail(), $u->getEmail()),
                    'required'     => false,
                    'query_builder' => $companyScope !== null
                        ? fn ($er) => $er->createQueryBuilder('u')->where('u.company = :company')->setParameter('company', $companyScope)
                        : null,
                ])
                ->add('position', TextType::class, ['required' => false])
                ->add('externalId', TextType::class, ['required' => false])
            ->end()
            ->with('Contract', ['class' => 'col-md-6'])
                ->add('contractMinutesPerWeek', IntegerType::class, ['required' => false])
                ->add('hiredAt', DateType::class, ['widget' => 'single_text', 'required' => false])
                ->add('terminatedAt', DateType::class, ['widget' => 'single_text', 'required' => false])
            ->end();
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('id')
            ->addIdentifier('user', null, ['label' => 'User'])
            ->add('company')
            ->add('position')
            ->add('hiredAt')
            ->add('terminatedAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []],
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('company')
            ->add('position');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('company')
            ->add('user')
            ->add('position')
            ->add('externalId')
            ->add('contractMinutesPerWeek')
            ->add('hiredAt')
            ->add('terminatedAt')
            ->add('createdAt')
            ->add('updatedAt');
    }
}