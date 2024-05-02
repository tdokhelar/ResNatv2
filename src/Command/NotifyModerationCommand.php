<?php

namespace App\Command;

use App\Services\AsyncService;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\DocumentManagerFactory;
use App\Services\UserNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotifyModerationCommand extends GoGoAbstractCommand
{
    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                               TokenStorageInterface $security,
                               UserNotificationService $notifService,
                               TranslatorInterface $t,
                               AsyncService $as)
    {
        $this->notifService = $notifService;
        parent::__construct($dm, $commandsLogger, $security, $t, $as);
    }

    protected function gogoConfigure(): void
    {
        $this
        ->setName('app:notify-moderation')
        ->setDescription('Notify users about pending moderation or problem on an import'); // 
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        $userNotified = $this->notifService->sendModerationNotifications();
        if ($userNotified) {
            $this->log("Notify Moderation, $userNotified users notified"); // 
        }        
    }
}
