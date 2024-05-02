<?php

namespace App\Services;

use App\Document\ConfImage;
use App\Enum\UserInteractionType;
use App\Document\UserInteractionContribution;
use App\Document\WebhookFormat;
use App\Services\ElementSynchronizationService;
use Doctrine\ODM\MongoDB\DocumentManager;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use App\Document\GoGoLog;
use App\Document\GoGoLogLevel;
use Symfony\Contracts\Translation\TranslatorInterface;

class WebhookService
{
    protected $dm;
    protected $config;
    protected $router;

    const MAX_ATTEMPTS = 7; // number of attempts for posting webhook

    public function __construct(DocumentManager $dm, RouterInterface $router,
                                TokenStorageInterface $securityContext,
                                ElementSynchronizationService $synchService,
                                UrlService $urlService,
                                LoggerInterface $commandsLogger,
                                TranslatorInterface $translator)
    {
        $this->dm = $dm;
        $this->router = $router;
        $this->urlService = $urlService;
        $this->securityContext = $securityContext;
        $this->synchService = $synchService;
        $this->logger = $commandsLogger;
        $this->translator = $translator;
    }

    public function getConfig()
    {
        if (!$this->config) $this->config = $this->dm->get('Configuration')->findConfiguration();
        return $this->config;
    }

    private function t($key, $params = [])
    {
        return $this->translator->trans($key, $params, 'admin');
    }

    public function processPosts($limit = 5)
    {
        $now = (new \DateTime())->format(\DateTime::ATOM);
        $maxAttemps = self::MAX_ATTEMPTS;
        $contributions = $this->dm->createQueryBuilder(UserInteractionContribution::class)
            ->field('status')->exists(true) // null status are pending contributions, so ignore
            ->field('webhookPosts')->exists(true)
            ->field('webhookPosts.numAttempts')->lt(self::MAX_ATTEMPTS)
            ->field('webhookPosts.nextAttemptAt')->lte(new \DateTime())
            ->field('webhookPosts.completeAt')->exists(false)
            ->execute();
        if (!$contributions || 0 == $contributions->count()) {
            return 0;
        }
        $client = new Client();
        $promises = [];

        // DISPATCH EACH POST
        foreach ($contributions as $contribution) {
            $data = $this->calculateData($contribution);
            foreach ($contribution->getWebhookPosts() as $post) {
                // The query return contributions that have at least one webhookPost to process
                // Need to double check here per webhookPost so we do not process others
                if ($post->getCompleteAt() ||
                    $post->getNextAttemptAt() > new \DateTime() ||
                    $post->getNumAttempts() >= $maxAttemps) continue;

                $webhook = $post->getWebhook();
                if ($webhook) {
                    $jsonData = $this->formatData($webhook->getFormat(), $data);
                    $promise = $client->postAsync($webhook->getUrl(), ['json' => $jsonData]);
                } else {
                    // when no webhook it mean it's a special handling, like for OpenStreetMap
                    $element = $contribution->getElement();
                    if ($element->isFromOsm()) {
                        $promise = $this->synchService->asyncDispatchToOSM($element, $data['action']);
                    }
                    if ($element->isFromGogocarto()) {
                        $promise = $this->synchService->asyncDispatchToGogocarto($element, $data['action']);
                    }
                }

                $promise->then(
                    function (ResponseInterface $res) use ($post, $contribution) {
                        if ($res->getStatusCode() == 200)
                            $this->handlePostSuccess($post, $contribution, $res->getReasonPhrase());
                        else
                            $this->handlePostFailure($post, $contribution, $res->getReasonPhrase(), $res->getStatusCode());
                    },
                    function (RequestException $e) use($post, $contribution) {
                        $this->handlePostFailure($post, $contribution, $e->getMessage());
                    }
                );
                $promises[] = $promise;
            }
            if (count($promises) >= $limit) break;
        }

        // Wait for the requests to complete, even if some of them fail
        // Not sure if we need that or not... maybe just for the flush
        Promise\Utils::settle($promises)->wait();

        $this->dm->flush();
        return count($promises);
    }

    private function handlePostSuccess($post, $contribution, $message = '')
    {
        $post->setCompleteAt(new \DateTime());
        $post->setNextAttemptAt(null);
        $post->setMessage($message);
        $this->dm->flush();
    }

    private function handlePostFailure($post, $contribution, $errorMessage, $code = 500)
    {
        $attempts = $post->incrementNumAttempts();
        $this->logger->error("Webhook for contribution {$contribution->getId()} : $errorMessage");
        // After first try, wait 5m, 25m, 2h, 10h, 2d
        $intervalInMinutes = pow(5, $attempts);
        $elName = "\"{$contribution->getElement()->getName()}\" ({$contribution->getElement()->getId()})";
        if ($post->getWebhook()) {
            $message = $this->t('webhooks.messages.error_send', [ 'url' => $post->getWebhook()->getUrl(), 'element' => $elName ], 'admin');
        } else {
            $message = $this->t('webhooks.messages.error_sync', [ 'element' => $elName ], 'admin');
            if ($code == 401 && $contribution->getElement()->getSource()->getSourceType() === 'osm') {
                $message .= " ".$this->t('webhooks.messages.error_osm_auth', [], 'admin');
            }
        }
        $message .= " ".$this->t('webhooks.messages.error_msg', [ 'attempts' => $attempts, 'message' => $errorMessage ], 'admin');
        $post->setMessage($errorMessage);
        $log = new GoGoLog(GoGoLogLevel::Error, $message);
        $this->dm->persist($log);
        $this->dm->flush();
        $interval = new \DateInterval("PT{$intervalInMinutes}M");
        $now = new \DateTime();
        $post->setNextAttemptAt($now->add($interval));
    }

    private function calculateData($contribution)
    {
        // STANDARD CONTRIBUTION
        if ($contribution->getElement()) {
            $element = $contribution->getElement();
            $element->setPreventJsonUpdate(true);
            $link = str_replace('%23', '#', $this->urlService->generateUrl('gogo_directory_showElement', ['id' => $element->getId()]));
            $data = json_decode($element->getJson(false), true);
        }
        // BATCH CONTRIBUTION
        else {
            $link = '';
            $data = ['ids' => $contribution->getElementIds()];
        }

        $mappingType = [UserInteractionType::Deleted => 'delete',
                        UserInteractionType::Add => 'add', UserInteractionType::PendingAdd => 'add',
                        UserInteractionType::Edit => 'edit', UserInteractionType::PendingEdit => 'edit',
                        UserInteractionType::Import => 'add',     UserInteractionType::Restored => 'add', ];
        $result = [
            'action' => $mappingType[$contribution->getType()],
            'user' => $contribution->getUserDisplayName(),
            'link' => $link,
            'data' => $data,
        ];
        $result['text'] = $contribution->getElement() ? $this->getNotificationText($result) : $this->getBatchNotificationText($result);

        return $result;
    }

    private function getNotificationText($result)
    {
        $element = $this->getConfig()->getElementDisplayName();
        switch ($result['action']) {
            case 'add':
                return "**ADD** {$element} **{$result['data']['name']}** created by {$result['user']}\n[Link]({$result['link']})";
            case 'edit':
                return "**EDIT** {$element} **{$result['data']['name']}** updated by *{$result['user']}*\n[Link]({$result['link']})";
            case 'delete':
                return "**DELETION** {$element} **{$result['data']['name']}** deleted by *{$result['user']}*";
            default:
                throw new \InvalidArgumentException(sprintf('The webhook action "%s" is invalid.', $result['action']));
        }
    }

    private function getBatchNotificationText($result)
    {
        $elements = $this->getConfig()->getElementDisplayNamePlural();
        $title = $this->t('webhooks.titles.'.$result['action'], [], 'admin');
        $text = $this->t('webhooks.texts.'.$result['action'], [], 'admin');
        $count = count($result['data']['ids']);

        return "**{$title}** {$count} {$elements} {$text} par {$result['user']}";
    }

    private function getBotIcon()
    {
        /** @var ConfImage $img */
        $img = $this->getConfig()->getFavicon() ? $this->getConfig()->getFavicon() : $this->getConfig()->getLogo();

        return $img ? $img->getImageUrl() : $this->urlService->getAssetUrl('/img/gogo-bot.png');
    }

    private function formatData($format, $data)
    {
        switch ($format) {
            case WebhookFormat::Raw:
                return $data;

            case WebhookFormat::Mattermost:
                return [
                    'username' => $this->getConfig()->getAppName(),
                    'icon_url' => $this->getBotIcon(),
                    'text' => $data['text'],
                ];

            case WebhookFormat::Slack:
                return ['text' => $data['text']];

            default:
                throw new \InvalidArgumentException(sprintf('The webhook format "%s" is invalid.', $format));
        }
    }
}
