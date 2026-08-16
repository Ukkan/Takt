<?php

namespace App\Admin;

use App\Entity\Employee;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

final class TimeEntryAdmin extends AbstractAdmin
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
            ->add('employee', EntityType::class, [
                'class'        => Employee::class,
                'choice_label' => fn(Employee $e) => sprintf(
                    '%s – %s',
                    $e->getCompany()->getName(),
                    $e->getUser()?->getFullName() ?? $e->getUser()?->getEmail() ?? '#' . $e->getId()
                ),
                'query_builder' => $companyScope !== null
                    ? fn ($er) => $er->createQueryBuilder('e')->where('e.company = :company')->setParameter('company', $companyScope)
                    : null,
            ])
            ->add('startTime', DateTimeType::class, ['widget' => 'single_text'])
            ->add('endTime', DateTimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('breakMinutes', IntegerType::class)
            ->add('source', ChoiceType::class, [
                'choices' => [
                    'App'    => 'app',
                    'Manual' => 'manual',
                    'Import' => 'import',
                ],
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('employee.company', null, ['label' => 'Company'])
            ->addIdentifier('employee', null, ['label' => 'Employee'])
            ->add('startTime')
            ->add('endTime')
            ->add('breakMinutes', null, ['label' => 'Break (min)'])
            ->add('source')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []],
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('employee')
            ->add('source')
            ->add('startTime');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('employee')
            ->add('startTime')
            ->add('endTime')
            ->add('breakMinutes')
            ->add('source')
            ->add('createdAt')
            ->add('updatedAt');
    }
}