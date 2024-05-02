<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GoGoCartoJsService
{
    public function __construct(DocumentManager $dm, TokenStorageInterface $securityContext, 
                                RouterInterface $router, SessionInterface $session,
                                TaxonomyService $taxonomyService, TranslatorInterface $t)
    {
        $this->dm = $dm;
        $this->taxonomyService = $taxonomyService;
        $this->securityContext = $securityContext;
        $this->router = $router;
        $this->session = $session;
        $this->urlType = UrlGeneratorInterface::ABSOLUTE_PATH;
        $this->t = $t;
    }

    public function setUrlType($type) {
        $this->urlType = $type;
        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string $elementId Include this element in the config so it's loaded directly
     * @return void
     */
    public function getConfig($elementId = null)
    {
        $config = $this->dm->get('Configuration')->findConfiguration();
        if (!$config) return [];

        $elementsRep = $this->dm->get('Element');
        $tileLayers = $this->dm->get('TileLayer')->findAll();

        $taxonomyJson = $this->taxonomyService->getTaxonomyJson();
        
        $user = $this->securityContext->getToken() ? $this->securityContext->getToken()->getUser() : null;

        $roles = is_object($user) ? $user->getRoles() : [];
        $userGogocartoRole = is_object($user) && $user->isAdmin() ? 'admin' : (in_array('ROLE_USER', $roles) ? 'user' : 'anonymous');
        $userGogocartoRole = [$userGogocartoRole];
        $userEmail = is_object($user) ? $user->getEmail() : null;
        $userGroups = is_object($user) ? $user->getGroupNames() : [];

        $allowedStamps = [];
        if ($config->getStampFeature()->getActive()) {
            $publicStamps = $this->dm->get('Stamp')->findByIsPublic(true);
            $userStamps = is_object($user) ? $user->getAllowedStamps()->toArray() : [];
            $allowedStamps = array_unique(array_merge($publicStamps, $userStamps));
            foreach ($allowedStamps as $stamp) {
                $elementIds = $elementsRep->findStampedWithId($stamp->getId());
                $stamp->setElementIds($elementIds);
            }
        }

        $readonlySources = $this->dm->query('ImportDynamic')
                                    ->field('isSynchronized')->notEqual(true)
                                    ->select('sourceName')->getArray();
        $editFeatureConf = [
            'options' => ['readonlySources' => array_values($readonlySources)]
        ];
        if (!$config->getEditFeature()->isOnlyAllowedForAdmin()) {
            $editFeatureConf['roles'] = ['anonymous', 'user', 'admin'];
        }
        $result = [
            'security' => [
                'userRoles' => $userGogocartoRole,
                'userEmail' => $userEmail,
                'userGroups' => $userGroups,
                'loginAction' => '$("#popup-login").openModal();',
            ],
            'language' => $config->getLocale(),
            'translations' => [
                'element' => $config->getElementDisplayName(),
                'element.definite' => $config->getElementDisplayNameDefinite(),
                'element.indefinite' => $config->getElementDisplayNameIndefinite(),
                'element.plural' => $config->getElementDisplayNamePlural(),
                'collaborative.moderation.paragaphs' => $config->getCollaborativeModerationExplanations(),
                'open_hours.days_of_week' => [
                    0 => $this->t->trans('element.form.open_hours.days_of_week.0'),
                    1 => $this->t->trans('element.form.open_hours.days_of_week.1'),
                    2 => $this->t->trans('element.form.open_hours.days_of_week.2'),
                    3 => $this->t->trans('element.form.open_hours.days_of_week.3'),
                    4 => $this->t->trans('element.form.open_hours.days_of_week.4'),
                    5 => $this->t->trans('element.form.open_hours.days_of_week.5'),
                    6 => $this->t->trans('element.form.open_hours.days_of_week.6')
                ],
                'open_hours.closed' => $this->t->trans('element.form.open_hours.closed')
            ],
            'menu' => [
                'width' => $config->getMenu()->getWidth(),
                'smallWidthStyle' => $config->getMenu()->getSmallWidthStyle(),
                'showOnePanePerMainOption' => $config->getMenu()->getShowOnePanePerMainOption(),
                'showCheckboxForMainFilterPane' => $config->getMenu()->getShowCheckboxForMainFilterPane(),
                'showCheckboxForSubFilterPane' => $config->getMenu()->getShowCheckboxForSubFilterPane(),
                'displayNumberOfElementForEachCategory' => $config->getMenu()->getDisplayNumberOfElementForEachCategory(),
                'displayNumberOfElementRoundResults' => $config->getMenu()->getDisplayNumberOfElementRoundResults(),
                'filters' => $config->getMenu()->getFilters()
            ],
            'infobar' => [
                'width' => $config->getInfobar()->getWidth(),
                'headerTemplate' => [
                    'content' => $config->getInfobar()->getHeaderTemplate(),
                    'isMarkdown' => $config->getInfobar()->getHeaderTemplateUseMarkdown(),
                ],
                'bodyTemplate' => [
                    'content' => $config->getInfobar()->getBodyTemplate(),
                    'isMarkdown' => $config->getInfobar()->getBodyTemplateUseMarkdown(),
                ],
                'elementRefreshNeeded' => [
                    'needed' => [
                        'active' => $config->getRefreshNeededMail()->getActive(),
                        'shownOnInfoBar' => $config->getRefreshNeededShownOnInfoBar(),
                        'maxDaysBeforeSendingMail' => $config->getMaxDaysBeforeSendingRefreshNeededMail(),
                    ],
                    'muchNeeded' => [
                        'active' => $config->getRefreshMuchNeededMail()->getActive(),
                        'shownOnInfoBar' => $config->getRefreshMuchNeededShownOnInfoBar(),
                        'maxDaysBeforeSendingMail' => $config->getMaxDaysBeforeSendingRefreshMuchNeededMail(),
                    ]
                ],
            ],
            'map' => [
                'defaultBounds' => $config->getDefaultBounds(),
                'geocoding' => [
                    'geocodingBoundsType' => $config->getGeocodingBoundsType(),
                    'geocodingBoundsByCountryCodes' => $config->getGeocodingBoundsByCountryCodes(),
                    'geocodingBounds' => $config->getGeocodingBounds(),
                ],
                'defaultTileLayer' => $config->getDefaultTileLayer()->getName(),
                'tileLayers' => $tileLayers,
                'geojsonLayers' => $config->getMandatoryGeojsonLayers(),
                'optionnalGeojsonLayers' => $config->getOptionnalGeojsonLayers(),
                'saveViewportInCookies' => $config->getSaveViewportInCookies(),
                'saveTileLayerInCookies' => $config->getSaveTileLayerInCookies(),
                'useClusters' => $config->getMarker()->getUseClusters(),
            ],
            'marker' => [
                'displayPopup' => $config->getMarker()->getDisplayPopup(),
                'popupAlwaysVisible' => $config->getMarker()->getPopupAlwaysVisible(),
                'popupTemplate' => [
                    'content' => $config->getMarker()->getPopupTemplate(),
                    'isMarkdown' => $config->getMarker()->getPopupTemplateUseMarkdown(),
                ],
                'defaultColor' => $config->getMarker()->getDefaultColor(),
                'defaultIcon' => $config->getMarker()->getDefaultIcon(),
                'defaultShape' => $config->getMarker()->getDefaultShape(),
                'defaultSize' => $config->getMarker()->getDefaultSize()
            ],
            'theme' => $config->getTheme(),
            'colors' => [
                'text' => $config->getTextColor(),
                'primary' => $config->getPrimaryColor(),

                // Optional colors
                'secondary' => $config->getDefaultSecondaryColor(),
                'background' => $config->getDefaultBackgroundColor(),
                'searchBar' => $config->getDefaultSearchBarColor(),
                'disabled' => $config->getDefaultDisableColor(),
                'pending' => $config->getDefaultPendingColor(),
                'contentBackground' => $config->getDefaultContentBackgroundColor(),
                'textDark' => $config->getDefaultTextDarkColor(),
                'textDarkSoft' => $config->getDefaultTextDarkSoftColor(),
                'textLight' => $config->getDefaultTextLightColor(),
                'textLightSoft' => $config->getDefaultTextLightSoftColor(),
                'interactiveSection' => $config->getDefaultInteractiveSectionColor(),
                'contentBackgroundElementBody' => $config->getDefaultContentBackgroundElementBodyColor(), // by default calculated from contentBackground
                'error' => $config->getDefaultErrorColor(),

                // Non implemented colors
                // infoBarHeader => undefined, // by default auto colored with main option color, except for transiscope theme
                // infoBarMenu => undefined,   // by default auto colored with main option color, except for transiscope theme

                // menuOptionHover => undefined, // by default calculated from contentBackground
                // lineBorder => undefined, // by default calculated from contentBackground

                // mapControlsBgd => undefined,
                // mapControls => undefined,
                // mapListBtn => undefined,
            ],
            'fonts' => [
                'mainFont' => $config->getMainFont(),
                'titleFont' => $config->getTitleFont(),
            ],
            'features' => [
                'listMode' => $this->getConfigFrom($config->getListModeFeature()),
                'searchPlace' => $this->getConfigFrom($config->getSearchPlaceFeature()),
                'searchCategories' => $this->getConfigFrom($config->getSearchCategoriesFeature()),
                'searchElements' => $this->getConfigFrom($config->getSearchElementsFeature(), 'gogo_api_elements_from_text'),
                'searchGeolocate' => $this->getConfigFrom($config->getSearchGeolocateFeature()),
                'share' => $this->getConfigFrom($config->getShareFeature()),
                'report' => $this->getConfigFrom($config->getReportFeature(), 'gogo_report_error_for_element'),
                'favorite' => $this->getConfigFrom($config->getFavoriteFeature()),
                'export' => $this->getConfigFrom($config->getExportIframeFeature()),
                'pending' => $this->getConfigFrom($config->getPendingFeature()),
                'directModeration' => $this->getConfigFrom($config->getDirectModerationFeature()),
                'moderation' => $this->getConfigFrom($config->getDirectModerationFeature(), 'gogo_resolve_reports_element'),
                'elementHistory' => $this->getConfigFrom($config->getDirectModerationFeature()),
                'layers' => $this->getConfigFrom($config->getLayersFeature()),
                'mapdefaultview' => $this->getConfigFrom($config->getMapDefaultViewFeature()),
                'subscribe' => $this->getConfigFrom($config->getSubscribeFeature(), 'gogo_element_subscribe'),
                
                // overwrite roles so even if edit is just allowed to user or admin, an anonymous will see
                // the edit button in the element info menu
                'edit' => $this->getConfigFrom(
                                $config->getEditFeature(),
                                'gogo_element_edit',
                                $editFeatureConf
                            ),
                'delete' => $this->getConfigFrom($config->getDeleteFeature(), 'gogo_delete_element'),
                'sendMail' => [ 'url' => $this->getAbsolutePath('gogo_element_send_mail') ],

                'vote' => $this->getConfigFrom($config->getCollaborativeModerationFeature(), 'gogo_vote_for_element'),
                'stamp' => $this->getConfigFrom(
                        $config->getStampFeature(),
                        'gogo_element_stamp',
                        ['options' => ['allowedStamps' => $allowedStamps]]
                    ),
                'customPopup' => $this->getConfigFrom(
                        $config->getCustomPopupFeature(),
                        null,
                        ['options' => [
                            'text' => $config->getCustomPopupText(),
                            'showOnlyOnce' => $config->getCustomPopupShowOnlyOnce(),
                            'id' => $config->getCustomPopupId(),
                        ]]
                    ),
            ],
            'data' => [
                'taxonomy' => json_decode($taxonomyJson),
                'elementsApiUrl' => $this->getAbsolutePath('gogo_api_elements_index'),
                'requestByBounds' => true,
            ],
        ];

        if ($elementId) {
            $element = $this->dm->get('Element')->find($elementId);
            if ($element) {
                $elementJson = $this->dm->get('Element')->find($elementId)->getJson();
                $result['data']['elements'] = [json_decode($elementJson)];
            }
        }

        if ('transiscope' == $config->getTheme()) {
            $result['images'] = [
                'buttonOpenMenu' => $config->getFavicon() ? $config->getFavicon()->getImageUrl() : ($config->getLogo() ? $config->getLogo()->getImageUrl() : null),
            ];
        }

        return $result;
    }

    private function getConfigFrom($feature, $route = null, $overwrite = [])
    {
        if (!$feature) {
            return null;
        }
        $result = [];
        $result['active'] = array_key_exists('active', $overwrite) ? $overwrite['active'] : $feature->getActive();
        $result['inIframe'] = $feature->getActiveInIframe();

        if ('gogo_element_edit' == $route) {
            $url = str_replace('fake', '', $this->getAbsolutePath('gogo_element_edit', ['id' => 'fake']));
        } elseif ($route) {
            $url = $this->getAbsolutePath($route);
        } else {
            $url = '';
        }
        $result['url'] = $url;

        $result['roles'] = array_key_exists('roles', $overwrite) ? $overwrite['roles'] : $feature->getAllowedRoles();
        if (array_key_exists('options', $overwrite)) {
            $result['options'] = $overwrite['options'];
        }

        return $result;
    }

    private function getAbsolutePath($route, $params = [])
    {
        return $this->router->generate($route, $params, $this->urlType);
    }
}
