<?php

namespace App\Command;

use App\Services\AsyncService;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\DocumentManagerFactory;
use App\Services\MailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NewsletterCommand extends GoGoAbstractCommand
{
    // This command is executed every hour. Each time it get executed for each project
    // so keep the total of emails sent with this class attribute
    private $remainingEmailsCount;

    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                                TokenStorageInterface $security, MailService $mailService,
                                TranslatorInterface $t, AsyncService $as)
    {
        parent::__construct($dm, $commandsLogger, $security, $t, $as);
        $this->mailService = $mailService;
        // Note that this MAX_EMAIL_PER_HOUR works only for single project, it's not working on
        // SAAS mode cause the comand is ran for each project
        $this->remainingEmailsCount = $_ENV['MAX_EMAIL_PER_HOUR'];
    }

    protected function gogoConfigure(): void
    {
        $this
          ->setName('app:users:sendNewsletter')
          ->setDescription('Send the newsletter to each user')
       ;
    }

    protected function filterProjects($qb)
    {
        return $qb->field('haveNewsletter')->equals(true);
    }

    protected function runInSeparateProcess()
    {
        return true;
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        $users = $dm->get('User')->findNeedsToReceiveNewsletter(100);
        $count = 0;
        foreach ($users as $user) {
            if (!$user->getGeo()) continue;
            if ($this->remainingEmailsCount <= 0) break;
            $dm->persist($user);
            $elements = $dm->get('Element')->findWithinCenterFromDate(
                $user->getGeo()->getLatitude(),
                $user->getGeo()->getLongitude(),
                $user->getNewsletterRange(),
                $user->getLastNewsletterSentAt());

            $elementCount = $elements->count();
            if ($elementCount > 0) {
                $result = $this->mailService->sendAutomatedMail('newsletter', $user, null, $elements);
                
                if ($result['success']) {
                    $count += 1;
                    $this->remainingEmailsCount -= 1;
                } else {
                    $this->error(" -> Error while sending newsletter to {$user->getEmail()} : {$result['message']}");
                }
            }
            $user->setLastNewsletterSentAt(new \DateTime());
            $user->updateNextNewsletterDate();
        }
        $dm->flush();
        if ($count > 0) $this->log("Newsletter Sent to $count users");
    }
}
