<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Créer un nouvel utilisateur',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nom de l\'utilisateur')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Créer un administrateur')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $name = $input->getOption('name');
        $isAdmin = $input->getOption('admin');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $email));
            return Command::FAILURE;
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setName($name ?? explode('@', $email)[0]);
        
        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Définir les rôles
        if ($isAdmin) {
            $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        // Sauvegarder
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Utilisateur créé avec succès ! Email: %s, Rôles: %s',
            $email,
            implode(', ', $user->getRoles())
        ));

        return Command::SUCCESS;
    }
}

