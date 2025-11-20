<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:set-super-admin',
    description: 'Définir un utilisateur comme super administrateur',
)]
class SetSuperAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);
        
        if (!$user) {
            $io->error(sprintf('Utilisateur avec l\'email "%s" non trouvé.', $email));
            return Command::FAILURE;
        }

        $user->setIsSuperAdmin(true);
        $user->setIsStaff(true);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Utilisateur "%s" défini comme super administrateur avec succès !',
            $email
        ));

        return Command::SUCCESS;
    }
}


