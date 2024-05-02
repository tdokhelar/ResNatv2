<?php

namespace App\Command;

use App\Services\AsyncService;
use App\Services\MailService;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\DocumentManagerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Services\UrlService;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RemoveAbandonnedProjectsCommand extends GoGoAbstractCommand
{

    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                               TokenStorageInterface $security, UrlService $urlService,
                               MailService $mailService, TranslatorInterface $t, AsyncService $as,
                               $baseUrl
                           )
    {
        $this->baseUrl = $baseUrl;
        $this->urlService = $urlService;
        $this->mailService = $mailService;
        parent::__construct($dm, $commandsLogger, $security, $t, $as);

        $this->runOnlyOnRootDatabase = true;
    }

    protected function gogoConfigure(): void
    {
        $this
          ->setName('app:projects:check-for-deleting')
          ->setDescription('Check project that are abandonned (no login, no elements...) and ask owner to remove them') // 
       ;
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        $date = new \DateTime();
        $qb = $dm->query('Project');
        $projectsToWarn = $qb
            ->addAnd(
                $qb->expr()->field('lastActivity')->exists(true),
                $qb->expr()->addOr(
                    $qb->expr()->field('lastActivity')->lte($date->setTimestamp(strtotime("-12 month"))),
                    $qb->expr()->field('lastActivity')->lte($date->setTimestamp(strtotime("-4 month")))->field('dataSize')->lte(5)
                ),
                $qb->expr()->addOr(
                    $qb->expr()->field('warningToDeleteProjectSentAt')->exists(false),
                    // resend the message every month
                    $qb->expr()->field('warningToDeleteProjectSentAt')->lte($date->setTimestamp(strtotime("-1 month")))
                )
            )->getCursor();
                        
        if ($projectsToWarn->count() > 0)
            $this->log('Project warned count : '. $projectsToWarn->count());

        foreach ($projectsToWarn as $project) {
            $subject = $this->trans('projects.warnings.abandonned_map',[ 'baseUrl' => $this->baseUrl ]);
            $homeUrl = $this->urlService->generateUrlFor($project);
            $adminUrl = $this->urlService->generateUrlFor($project, 'sonata_admin_dashboard');
            $content = $this->trans('projects.warnings.message', [
              'projectName' => $project->getName(),
              'baseUrl' => $this->baseUrl,
              'homeUrl' => $homeUrl,
              'adminUrl' => $adminUrl
            ]);
            foreach ($project->getAdminEmailsArray() as $email) {
                $this->mailService->sendMail($email, $subject, $content);
            }

            $project->setWarningToDeleteProjectSentAt(time());
            $dm->persist($project);
        }

        // $projectsToDelete = $dm->query('Project')
        //                 ->field('lastLogin')->lte($date->setTimestamp(strtotime("-12 month")))
        //                 ->field('warningToDeleteProjectSentAt')->lte($date->setTimestamp(strtotime("-4 month")))
        //                 ->execute();

        // $message = "Les projets suivants sont probablement à supprimer : ";
        // foreach ($projectsToDelete as $project) {
        //     $projectUrl = $this->urlService->generateUrlFor($project);
        //     $message .= '<li><a target="_blank" href="' . $projectUrl .'">' . $project->getName() .' / Nombre de points : ' . $project->getDataSize() .'</a></li>';
        //     $project->setWarningToDeleteProjectSentAt(time());
        // }

        // if ($projectsToDelete->count() > 0) {
        //     $this->log('Nombre de projets à supprimer : '. $projectsToDelete->count());
        //     $log = new GoGoLogUpdate('info',  $message);
        //     $dm->persist($log);
        // }

        $dm->flush();
    }
}
