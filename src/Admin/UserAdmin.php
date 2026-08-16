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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAdmin extends AbstractAdmin
{
    use CompanyScopedAdminTrait;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('General', ['class' => 'col-md-8'])
                ->add('email', EmailType::class)
                ->add('fullName', TextType::class, ['required' => false])
                ->add('plainPassword', PasswordType::class, [
                    'label'    => 'Password',
                    'required' => !$this->hasSubject() || $this->getSubject()->getId() === null,
                    'mapped'   => false,
                ])
                ->add('company', EntityType::class, [
                    'class'        => Company::class,
                    'choice_label' => 'name',
                    'required'     => false,
                    'query_builder' => $this->companyQueryBuilderRestriction(),
                ])
                ->add('role', ChoiceType::class, [
                    'choices' => [
                        'Employee'    => 'employee',
                        'Manager'     => 'manager',
                        'Admin'       => 'admin',
                        'Super Admin' => 'super_admin',
                    ],
                ])
            ->end()
            ->with('Status', ['class' => 'col-md-4'])
                ->add('isActive')
            ->end();
    }

    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query = parent::configureQuery($query);

        return $this->applyCompanyScope($query, 'company');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('email')
            ->add('fullName')
            ->add('company')
            ->add('role')
            ->add('isActive', null, ['label' => 'Active'])
            ->add('lastLogin')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => ['show' => [], 'edit' => [], 'delete' => []],
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('email')
            ->add('fullName')
            ->add('role')
            ->add('isActive');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('email')
            ->add('fullName')
            ->add('company')
            ->add('role')
            ->add('isActive')
            ->add('lastLogin')
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function prePersist(object $object): void
    {
        $this->hashPasswordIfProvided($object);
    }

    protected function preUpdate(object $object): void
    {
        $this->hashPasswordIfProvided($object);
    }

    private function hashPasswordIfProvided(object $object): void
    {
        /** @var User $object */
        $plain = $this->getForm()->get('plainPassword')->getData();
        if (!empty($plain)) {
            $object->setPasswordHash($this->hasher->hashPassword($object, $plain));
        }
    }
}