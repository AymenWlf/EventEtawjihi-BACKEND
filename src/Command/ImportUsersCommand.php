<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-users',
    description: 'Import users from CSV data',
)]
class ImportUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Données des utilisateurs
        $usersData = [
            ['Nadir', 'Akram', '624567910', '624567910', 'arjae.9000@gmail.com'],
            ['Ouaziz', 'Mohamed Taha', '658686209', '658686209', 'OuazizMohamedTaha@gmail.com'],
            ['El Mouden', 'Jad', '693348003', '693348003', 'Jadelmouden2008@gmail.com'],
            ['Gabbas', 'Salma', '704399606', '704399606', 'gabbassalma@gmail.com'],
            ['Nfise', 'Yasser', '620023077', '620023077', 'yassernfis@gmail.com'],
            ['Ababou', 'Adam', '690483969', '690483969', 'ababouadam356@gmail.com'],
            ['Islah', 'Abdellah', '644545485', '644545485', 'abdellahislah6102008@gmail.com'],
            ['Er-rafay', 'Ziad', '615594327', '615594327', 'ziader24@gmail.com'],
            ['Sedki Alaoui', 'Itimad', '613649350', '613649350', 'itimad.s.alaoui@gmail.com'],
            ['Tayoun', 'Marwa', '679217741', '679217741', 'Tayounmarwa@gmail.com'],
            ['Mares', 'talide-georgeanes', '646835823', '40799144300', 'manesgeorgianaa@gmail.com'],
            ['Achouaou', 'Fatima-ezzahra', '625968121', '625968121', 'fatiachouaou@gmail.com'],
            ['El idrissi Dafali', 'Aliaa', '774613736', '774613736', 'elidrissidafalialiaa@gmail.com'],
            ['Moussadak', 'Chahd', '634479792', '762181683', 'chahdmoussadak12@gmail.com'],
            ['El idrissi Alouani', 'Ghita', '779325126', '779325126', 'gelidrissi61@gmail.com'],
            ['Benchakroun', 'Ghali', '771635213', '771635213', 'ghalibenchakroun2@gmail.com'],
            ['Baraka', 'Rania', '774592271', '774592271', 'barakarana9@gmai.com'],
            ['Tamaki', 'Noha', '671607171', '671607171', 'tamakinoha0@gmail.com'],
            ['AitAllah', 'Rayane', '636315985', '624051029', 'rayanAitallah2008@gmail.com'],
            ['Cherqaoui', 'Chada', '662173826', '662173826', 'chada.cherqaoui@gmail.com'],
            ['Ahmaiddouch', 'Yassir', '642334439', '642334439', 'Yassir.ggreza@gmail.com'],
            ['El Harfi', 'Omar', '774908450', '774908450', ''],
            ['Boukhris', 'Taha', '645016610', '645016610', 'tahaboukh97@gmail.com'],
            ['Rafiki', 'Abdellah', '77258818', '77258818', 'rafikiabdellah098@gmail.com'],
            ['Robbane', 'Hiba', '623453805', '623453805', 'robbanehiba2008@gmail.com'],
            ['Drief', 'Jannat', '678972908', '678972908', 'jannatanhar1@gmail.com'],
            ['Bassane', 'Zaynab', '660354262', '660354262', 'bassanezaynab@gmail.com'],
            ['Machou', 'Jannate', '604232009', '604232009', 'jannat1jannar23456@gmail.com'],
            ['Boulama', 'Aya', '619808707', '619808707', 'boulama228@gmail.com'],
            ['Nejnaoui', 'Sara', '639780836', '639780836', 'saranejnaoui@gmail.com'],
            ['Soror', 'Sami', '641178411', '641178411', 'sororsami12@gmail.com'],
            ['boushib', 'Adam', '668198882', '668198882', ''],
            ['Jabrane', 'khalil', '664507960', '664507960', 'khalil.jab.kech@gmail.com'],
            ['Essalhi', 'Ismail', '651133749', '651133749', ''],
            ['Eddabiri', 'Zine eddine', '674276369', '674276369', 'zindinnnn1@gmail.com'],
            ['Assabti', 'Ilyas', '765507710', '765507710', 'ilyasassabti12344@gmail.com'],
            ['El kouas', 'Ouissal', '616401042', '616401042', 'OuissalElkouas20@gmai.com'],
            ['El boumeshouli', 'Nour', '637392979', '637392979', 'nour.elboumeshouli@gmail.com'],
            ['Jillou', 'Mays', '672504892', '672504892', 'jilloumays@gmail.com'],
            ['Achoual', 'Salma', '720935517', '720935517', 'achoualSalma@gmail.com'],
            ['El idrissi Hamidi', 'Othman', '618929421', '618929421', 'oelidrissihami@gmail.com'],
            ['Beniji', 'Omar', '706040342', '706040342', 'omar.rayyne@gmail.com'],
            ['Berrada El Mahhzoumi', 'Ouassila', '631268839', '631268839', 'berradaouassila8@gmail.com'],
            ['Tifaq', 'Mohamed Ilias', '765550957', '765550957', 'tifasilias@gmail.com'],
            ['El Aasimi', 'Rayane', '655802863', '655802863', 'rayaneelaassimi10@gmail.com'],
            ['Ait ouakrim', 'Ali', '779925737', '779925737', 'aliouakrim2009@gmail.com'],
            ['Adlane', 'Salsabil', '650707428', '650707428', 'salsabiladlane@gmail.com'],
            ['Ouaziz', 'Maroua', '619597213', '619597213', 'matouaouaziz8@gmail.com'],
            ['Ait Zati', 'Ammar', '764834041', '764834041', 'ammarzati08@gmail.com'],
            ['Doughri', 'Nizar', '716789016', '716789016', 'hamidaderj@gmail.com'],
            ['Semmar', 'Mohamed', '669454155', '669454155', 'Semmarmed151@gmail.com'],
            ['Belkamel', 'Tasnim', '672367105', '672367105', 'belkameltasnim9@gmail.com'],
            ['Kouis', 'Adam', '681594534', '681594534', 'kouisadam45@gmail.com'],
            ['Kaddi', 'mohammed Amine', '612788643', '612788643', 'minekaddi2008@gmail.com'],
            ['Toufik', 'Adam', '616160020', '616160020', 'adamtfk8@gmail.com'],
            ['Haddad', 'Mohamed Wael', '660564607', '660564607', 'waelator08@gmail.com'],
            ['Haouine', 'Anas', '623456690', '623456690', 'anashaouine03@gmail.com'],
            ['Cherrat', 'Fatima Ezzahra', '697130133', '697130133', 'cherratfatimaezzahra736@gmail.com'],
            ['Ismaili Aloui', 'Mohamed Zayane', '664268851', '664268851', 'rayanealaouismail123@gmail.com'],
            ['Mhaoune', 'Ayda', '621060468', '621060468', 'aydamh2008@gmail.com'],
            ['Zkkiri', 'Ghalia', '622867774', '622867774', 'ghaliazkkiri10@gmail.com'],
            ['Benabbou', 'khadija', '652711570', '652711570', 'benabboukhadija585@gmail.com'],
            ['cherkaoui', 'Ines', '688164543', '688164543', 'ines.cherkaoui22@gmail.com'],
            ['Mateescu', 'Maya Alexandra', '630848538', '630848538', 'mayaalexandra172@gmail.com'],
            ['Tazi Elmoula', 'Louay', '642849986', '642849986', 'tazilouay2008@gmail.com'],
            ['Elassad', 'Ikrame', '610811637', '610811637', 'elassadikrame44@gmail.com'],
            ['Nefdaoui', 'Ghita', '670702019', '670702019', 'g.nefdaoui@gmail.com'],
            ['Tamim', 'Nour El Yakour', '694288833', '694288833', 'nourelyakoutj@gmail.com'],
            ['Ouakrim', 'Doha', '672444474', '672444474', 'dohaouakrim08@gmail.com'],
        ];

        $io->title('Importation des utilisateurs');
        $io->progressStart(count($usersData));

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($usersData as $index => $userData) {
            try {
                [$lastName, $firstName, $telephone, $whatsapp, $email] = $userData;

                // Nettoyer les données
                $lastName = trim($lastName);
                $firstName = trim($firstName);
                $telephone = trim($telephone);
                $whatsapp = trim($whatsapp);
                $email = trim($email);

                // Gérer le téléphone : ajouter 0 au début sauf si commence par 407
                if (!empty($telephone)) {
                    if (!str_starts_with($telephone, '407') && !str_starts_with($telephone, '0')) {
                        $telephone = '0' . $telephone;
                    }
                }

                // Gérer le WhatsApp : ajouter 0 au début sauf si commence par 407
                if (!empty($whatsapp)) {
                    if (!str_starts_with($whatsapp, '407') && !str_starts_with($whatsapp, '0')) {
                        $whatsapp = '0' . $whatsapp;
                    }
                }

                // Si pas d'email, générer un email temporaire basé sur le nom
                if (empty($email)) {
                    $email = strtolower(str_replace(' ', '', $firstName . '.' . $lastName)) . '@temp.e-tawjihi.ma';
                }

                // Vérifier si l'utilisateur existe déjà
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $io->progressAdvance();
                    $skipped++;
                    $errors[] = "Ligne " . ($index + 1) . ": Utilisateur avec l'email $email existe déjà";
                    continue;
                }

                // Générer un mot de passe aléatoire
                $password = $this->generatePassword();
                $passwordPlain = $password;

                // Créer l'utilisateur
                $user = new User();
                $user->setEmail($email);
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                $user->setPasswordPlain($passwordPlain);
                $user->setFirstName($firstName);
                $user->setLastName($lastName);
                $user->setTelephone($telephone ?: null);
                $user->setWhatsappNumber($whatsapp ?: null);
                $user->setIsStaff(false);
                $user->setIsSuperAdmin(false);
                $user->setRoles(['ROLE_USER']);

                // Générer le QR code
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // Générer le code utilisateur après la persistance
                $user->generateUserCode();
                $user->generateQrCode();
                $this->entityManager->flush();

                $created++;
                $io->progressAdvance();

            } catch (\Exception $e) {
                $io->progressAdvance();
                $errors[] = "Ligne " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        $io->progressFinish();
        $io->newLine(2);

        $io->success("Importation terminée !");
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Utilisateurs créés', $created],
                ['Utilisateurs ignorés (déjà existants)', $skipped],
                ['Erreurs', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $io->warning('Erreurs rencontrées :');
            foreach ($errors as $error) {
                $io->writeln("  - $error");
            }
        }

        return Command::SUCCESS;
    }

    private function generatePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $max)];
        }
        
        return $password;
    }
}

