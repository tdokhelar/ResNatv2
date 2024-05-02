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
use Symfony\Contracts\Translation\TranslatorInterface;

class NotifyElementRefreshNeededCommand extends GoGoAbstractCommand
{
    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                               TokenStorageInterface $security,
                               TranslatorInterface $t,
                               AsyncService $as,
                               MailService $mailService)
    {
        $this->mailService = $mailService;
        parent::__construct($dm, $commandsLogger, $security, $t, $as);
    }

    protected function gogoConfigure(): void
    {
        $this
            ->setName('app:notify-element-refresh-needed')
            ->setDescription('Notify users about elements not updated since a duration set in mails configuration'); // 
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        $config = $dm->get('Configuration')->findConfiguration();
        if (!$config->getRefreshNeededMail()->getActive()) {
            // $this->log("Automated mail feature to Notify Element Refresh Needed is disabled");
            return;
        }

        $elementsThatNeedToBeRefreshed = $dm->get('Element')->findElementsThatNeedToBeRefreshed();
        $this->log("Nb Elements Refresh Needed : " . count($elementsThatNeedToBeRefreshed));
        
        $nbMailSent_refreshNeeded = 0;
        $nbMailSent_refreshMuchNeeded = 0;
        
        $elementsThatMuchNeedToBeRefreshed = null;
        if ($config->getRefreshMuchNeededMail()->getActive()) {
            $elementsThatMuchNeedToBeRefreshed = $dm->get('Element')->findElementsThatMuchNeedToBeRefreshed();
            $this->log("Nb Elements Refresh Much Needed : " . count($elementsThatMuchNeedToBeRefreshed));
            foreach($elementsThatMuchNeedToBeRefreshed as $element) {
                $response = $this->mailService->sendAutomatedMail('refreshMuchNeeded', $element);
                if ($response['success']) {
                    $element->setLastRefreshNeededMailSent(new \Datetime());
                    $nbMailSent_refreshMuchNeeded++;
                }
            }
            $dm->flush();
        }
        foreach($elementsThatNeedToBeRefreshed as $element) {
            if (!$elementsThatMuchNeedToBeRefreshed || !in_array($element, $elementsThatMuchNeedToBeRefreshed->toArray())) {
                $response = $this->mailService->sendAutomatedMail('refreshNeeded', $element);
                if ($response['success']) {
                    $element->setLastRefreshNeededMailSent(new \Datetime());
                    $nbMailSent_refreshNeeded++;
                }
            }
        }
        $dm->flush();

        $this->log("Nb mails sent of type refreshNeeded: $nbMailSent_refreshNeeded.");
        if ($config->getRefreshMuchNeededMail()->getActive()) {
            $this->log("Nb mails sent of type refreshMuchNeeded: $nbMailSent_refreshMuchNeeded.");
        }
    }
}
