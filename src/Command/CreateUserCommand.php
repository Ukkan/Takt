<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Create a new user')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CompanyRepository $companyRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create User');

        $email = $io->ask('Email');
        $fullName = $io->ask('Full name (optional)', null);
        $role = $io->choice('Role', ['employee', 'manager', 'admin', 'super_admin'], 'employee');
        $password = $io->askHidden('Password');
        $confirm = $io->askHidden('Confirm password');

        if (empty($email) || empty($password)) {
            $io->error('Email and password are required.');
            return Command::FAILURE;
        }

        if ($password !== $confirm) {
            $io->error('Passwords do not match.');
            return Command::FAILURE;
        }

        $company = null;

        if ($role === 'super_admin') {
            $io->note('Platform super admins are not assigned to a company.');
        } else {
            $companies = $this->companyRepository->findAll();

            if (count($companies) > 0) {
                $choices = ['(none)'];
                foreach ($companies as $c) {
                    $choices[] = $c->getName();
                }
                $chosen = $io->choice('Assign to company', $choices, '(none)');
                if ($chosen !== '(none)') {
                    foreach ($companies as $c) {
                        if ($c->getName() === $chosen) {
                            $company = $c;
                            break;
                        }
                    }
                }
            }
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRole($role);
        $user->setIsActive(true);
        $user->setCompany($company);
        $user->setPasswordHash($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created with role "%s" (id: %d).', $email, $role, $user->getId()));

        return Command::SUCCESS;
    }
}