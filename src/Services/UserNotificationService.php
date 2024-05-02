<?php

namespace App\Services;

use App\Document\WatchModerationFrequencyOptions;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserNotificationService
{
    public function __construct(DocumentManager $dm, MailService $mailService,
                                UrlService $urlService, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->mailService = $mailService;
        $this->urlService = $urlService;
        $this->t = $t;
    }

    function sendModerationNotifications()
    {
        $users = $this->dm->get('User')->findByWatchModeration(true);
        $usersNotified = 0;
        $config = $this->dm->get('Configuration')->findConfiguration();
        if (!$config) return;
        foreach ($users as $user) {
            $elementsCount = $this->dm->get('Element')->findModerationElementToNotifyToUser($user);
            if ($elementsCount > 0) {
                $nextPeriod = $user->getNextModerationNotificationPeriod();
                if ($nextPeriod) {
                    $now = new \DateTime();
                    switch($user->getWatchModerationFrequency()) {
                        case WatchModerationFrequencyOptions::WEEKLY:
                            $currentPeriod = $now->format('Y-W');
                            break;
                        case WatchModerationFrequencyOptions::MONTHLY:
                            $currentPeriod = $now->format('Y-n');
                            break;
                        default:
                            $currentPeriod = $nextPeriod;
                    }
                    if ($currentPeriod < $nextPeriod) { 
                        continue;
                    }
                }
                $subject = $this->t->trans('notifications.moderation.subject', [ 'appname' => $config->getAppName() ]);
                $content = $this->t->trans('notifications.moderation.content', [
                    'count' => $elementsCount,
                    'element_singular' => $config->getElementDisplayName(),
                    'element_plural' => $config->getElementDisplayNamePlural(),
                    'appname' => $config->getAppName(),
                    'url' => $this->urlService->generateUrlFor($config->getDbName(), 'gogo_directory'),
                    'edit_url' => $this->urlService->generateUrlFor($config->getDbName(), 'admin_app_user_edit', ['id' => $user->getId()])
                ]);
                $user->setLastModerationNotificationSentAt(new \Datetime());
                $this->mailService->sendMail($user->getEmail(), $subject, $content);
                $usersNotified++;
            }
        }   
        $this->dm->flush();
        return $usersNotified; 
    }

    function notifyImportError($import)
    {
        if (!$import->isDynamicImport()) return;
        foreach($import->getUsersToNotify() as $user) {
            $config = $this->dm->get('Configuration')->findConfiguration();
            $subject = $this->t->trans('notifications.import_error.subject', [ 'appname' => $config->getAppName() ]);
            $content = $this->t->trans('notifications.import_error.content', [ 
                'import' => $import->getSourceName(),
                'url' => $this->urlService->generateUrlFor($config, 'admin_app_import_edit', ['id' => $import->getId()])
            ]);
            $this->mailService->sendMail($user->getEmail(), $subject, $content);
        }
    }

    function notifyImportMapping($import)
    {
        if (!$import->isDynamicImport()) return;
        foreach($import->getUsersToNotify() as $user) {
            $config = $this->dm->get('Configuration')->findConfiguration();
            $subject = $this->t->trans('notifications.import_mapping.subject', [ 'appname' => $config->getAppName() ]);
            $content = $this->t->trans('notifications.import_mapping.content', [
                'import' => $import->getSourceName(),
                'url' => $this->urlService->generateUrlFor($config, 'admin_app_import_edit', ['id' => $import->getId()])
            ]);
            $this->mailService->sendMail($user->getEmail(), $subject, $content);
        }
    }
}