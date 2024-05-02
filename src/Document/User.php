<?php

/**
 * This file is part of the <name> project.
 *
 * (c) <yourname> <youremail>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Sonata\UserBundle\Document\BaseUser as BaseUser;
use Sonata\UserBundle\Model\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class NewsletterFrequencyOptions
{
    const Never = 0;
    const Weekly = 1;
    const Monthly = 2;
}
abstract class WatchModerationFrequencyOptions
{
    const DAILY = 'daily';
    const WEEKLY = 'weekly';
    const MONTHLY = 'monthly';
    
    static function getOptionsList($t) {
        $translatorPath = 'sonata.user.user.form.watchModerationFrequencyOptions.';
        return [
            $t->trans($translatorPath . self::DAILY, [], 'admin') => self::DAILY,
            $t->trans($translatorPath . self::WEEKLY, [], 'admin') => self::WEEKLY,
            $t->trans($translatorPath . self::MONTHLY, [], 'admin') => self::MONTHLY
        ];
    }
}
/**
 * @MongoDB\Document
 * @MongoDB\HasLifecycleCallbacks
 * @MongoDB\Index(keys={"geo"="2d"})
 * @MongoDB\Document(repositoryClass="App\Repository\UserRepository")
 */
class User extends BaseUser
{
    /**
     * @MongoDB\Id(strategy="auto")
     */
    protected $id;

    /**
     * Address of the user. Can be a simple postalCode, or a more precise address.
     *
     * @MongoDB\Field(type="string")
     */
    protected $location;

    /**
     * Geolocalisation of the location attribute.
     *
     * @MongoDB\EmbedOne(targetDocument="App\Document\Coordinates")
     */
    public $geo;

    /**
     * Newletter sending the recently added elements
     * See NewsletterFrequencyOptions.
     *
     * @MongoDB\Field(type="int") @MongoDB\Index
     */
    protected $newsletterFrequency;

    /**
     * We send to user the recently added elements in a specific range in km from location.
     *
     * @MongoDB\Field(type="int")
     */
    public $newsletterRange;

    /**
     * The date where the last newsletter has been sent.
     *
     * @MongoDB\Field(type="date")
     */
    protected $lastNewsletterSentAt;

    /**
     * The date where the next newsletter has to be send.
     *
     * @MongoDB\Field(type="date") @MongoDB\Index
     */
    protected $nextNewsletterDate;

    /**
     * Be notified by email when an Element need moderation
     * @MongoDB\Field(type="bool")
     */
    protected $watchModeration;

    /**
     * The date when the last moderation notification has been sent.
     *
     * @MongoDB\Field(type="date")
     */
    protected $lastModerationNotificationSentAt;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $watchModerationFrequency = WatchModerationFrequencyOptions::WEEKLY;

    /**
     * @MongoDB\ReferenceMany(targetDocument="App\Document\Option", cascade={"persist"})
     */
    protected $watchModerationOnlyWithOptions;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $watchModerationOnlyWithPostCodes;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $gamification;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $contributionsCount;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $reportsCount;

    /**
     * @MongoDB\Field(type="int")
     */
    protected $votesCount;

    /**
     * Private Labels/Tags than the user can use.
     *
     * @MongoDB\ReferenceMany(targetDocument="App\Document\Stamp", cascade={"all"})
     */
    protected $allowedStamps;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $username;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $usernameCanonical;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $email;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $emailCanonical;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean") @MongoDB\Index
     */
    protected $enabled;

    /**
     * The salt to use for hashing.
     *
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $salt;

    /**
     * Encrypted password. Must be persisted.
     *
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $password;

    /**
     * Plain password. Used for model validation. Must not be persisted.
     *
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $plainPassword;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $lastLogin;

    /**
     * Random string sent to the user email address in order to verify it.
     *
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $confirmationToken;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $passwordRequestedAt;

    /**
     * @var Collection
     * @MongoDB\ReferenceMany(targetDocument="App\Application\Sonata\UserBundle\Document\Group", cascade={"persist"}) @MongoDB\Index
     */
    protected $groups;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    protected $locked;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    protected $expired;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $expiresAt;

    /**
     * @var array
     * @MongoDB\Field(type="hash") @MongoDB\Index
     */
    protected $roles;

    /**
     * @var bool
     * @MongoDB\Field(type="boolean")
     */
    protected $credentialsExpired;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $credentialsExpireAt;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $updatedAt;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $twoStepVerificationCode;

    /**
     * @var \DateTime
     * @MongoDB\Field(type="date")
     */
    protected $dateOfBirth;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $firstname;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $lastname;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $website;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $biography;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $gender = UserInterface::GENDER_UNKNOWN; // set the default to unknown

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $locale;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $timezone;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $phone;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $facebookUid;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $facebookName;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $facebookData;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $twitterUid;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $twitterName;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $twitterData;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $communsUid;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $communsName;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $communsData;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $gplusUid;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $gplusName;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $gplusData;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    protected $token;

    public function __construct()
    {
        //parent::__construct();
        // your own logic
        $this->createToken();
    }

    /**
     * Returns the gender list.
     *
     * @return array
     */
    public static function getGenderList()
    {
        return [
            UserInterface::GENDER_UNKNOWN => 'gender_unknown',
            UserInterface::GENDER_FEMALE => 'gender_female',
            UserInterface::GENDER_MALE => 'gender_male',
        ];
    }

    public function getId()
    {
        return $this->id;
    }

    public function isAdmin()
    {
        return in_array('ROLE_ADMIN', $this->getRoles()) || in_array('ROLE_SUPER_ADMIN', $this->getRoles());
    }

    public function hasRole($role)
    {
        return in_array($role, $this->getRoles());
    }

    public function setGamification($value)
    {
        $this->gamification = $value;

        return $this;
    }

    public function getGamification()
    {
        return $this->gamification;
    }

    public function createToken()
    {
        if (!$this->getToken()) {
            $this->setToken(uniqid());
        }
    }

    public function addVoteCount()
    {
        ++$this->votesCount;
    }

    public function addReportsCount()
    {
        ++$this->votesCount;
    }

    public function addContributionCount()
    {
        ++$this->votesCount;
    }

    public function updateNextNewsletterDate()
    {
        if (0 == $this->getNewsletterFrequency()) {
            $this->setNextNewsletterDate(null);
        } else {
            switch ($this->getNewsletterFrequency()) {
                case NewsletterFrequencyOptions::Weekly: $interval = new \DateInterval('P7D'); break;
                case NewsletterFrequencyOptions::Monthly: $interval = new \DateInterval('P1M'); break;
            }
            $lastSent = clone $this->getLastNewsletterSentAt();
            $this->setNextNewsletterDate($lastSent->add($interval));
        }
    }

    public function getDisplayName()
    {
        if ($this->getUsername()) {
            return $this->getUsername();
        }

        return $this->getEmail();
    }

    public function getNextModerationNotificationPeriod()
    {
        if ($this->getLastModerationNotificationSentAt()) {
            $lastModerationNotificationSentAt = clone $this->getLastModerationNotificationSentAt();
            switch($this->getWatchModerationFrequency()) {
                case WatchModerationFrequencyOptions::WEEKLY :
                    return $lastModerationNotificationSentAt->add(new \DateInterval('P7D'))->format("Y-W");
                    break;
                case WatchModerationFrequencyOptions::MONTHLY :
                    return $lastModerationNotificationSentAt->add(new \DateInterval('P1M'))->format("Y-m");
                    break;
                default:
                    return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Get enabled.
     *
     * @return bool $enabled
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set Enabled.
     *
     * @param bool $salt
     *
     * @return $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Set salt.
     *
     * @param string $salt
     *
     * @return $this
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * Get locked.
     *
     * @return bool $locked
     */
    public function getLocked()
    {
        return $this->locked;
    }

    /**
     * Set Locked.
     *
     * @param bool $salt
     *
     * @return $this
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;

        return $this;
    }

    /**
     * Get expired.
     *
     * @return bool $expired
     */
    public function getExpired()
    {
        return $this->expired;
    }

    /**
     * Set Expired.
     *
     * @param bool $salt
     *
     * @return $this
     */
    public function setExpired($expired)
    {
        $this->expired = $expired;

        return $this;
    }

    /**
     * Get credentialsExpired.
     *
     * @return bool $credentialsExpired
     */
    public function getCredentialsExpired()
    {
        return $this->credentialsExpired;
    }

    /**
     * Set CredentialsExpired.
     *
     * @param bool $salt
     *
     * @return $this
     */
    public function setCredentialsExpired($credentialsExpired)
    {
        $this->credentialsExpired = $credentialsExpired;

        return $this;
    }

    /**
     * Set contributionsCount.
     *
     * @param int $contributionsCount
     *
     * @return $this
     */
    public function setContributionsCount($contributionsCount)
    {
        $this->contributionsCount = $contributionsCount;

        return $this;
    }

    /**
     * Get contributionsCount.
     *
     * @return int $contributionsCount
     */
    public function getContributionsCount()
    {
        return $this->contributionsCount;
    }

    /**
     * Set reportsCount.
     *
     * @param int $reportsCount
     *
     * @return $this
     */
    public function setReportsCount($reportsCount)
    {
        $this->reportsCount = $reportsCount;

        return $this;
    }

    /**
     * Get reportsCount.
     *
     * @return int $reportsCount
     */
    public function getReportsCount()
    {
        return $this->reportsCount;
    }

    /**
     * Set votesCount.
     *
     * @param int $votesCount
     *
     * @return $this
     */
    public function setVotesCount($votesCount)
    {
        $this->votesCount = $votesCount;

        return $this;
    }

    /**
     * Get votesCount.
     *
     * @return int $votesCount
     */
    public function getVotesCount()
    {
        return $this->votesCount;
    }

    /**
     * Set location.
     *
     * @param string $location
     *
     * @return $this
     */
    public function setLocation($location)
    {
        $this->location = $location;

        return $this;
    }

    /**
     * Get location.
     *
     * @return string $location
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set geo.
     *
     * @param App\Document\Coordinates $geo
     *
     * @return $this
     */
    public function setGeo(\App\Document\Coordinates $geo)
    {
        $this->geo = $geo;

        return $this;
    }

    /**
     * Get geo.
     *
     * @return App\Document\Coordinates $geo
     */
    public function getGeo()
    {
        return $this->geo;
    }

    /**
     * Set newsletterFrequency.
     *
     * @param int $newsletterFrequency
     *
     * @return $this
     */
    public function setNewsletterFrequency($newsletterFrequency)
    {
        // reset last newsletter sent at to now when user check to receive newsletter
        if (0 == $this->getNewsletterFrequency() && $newsletterFrequency > 0) {
            $this->setLastNewsletterSentAt(new \DateTime());
        }
        $this->newsletterFrequency = $newsletterFrequency;
        $this->updateNextNewsletterDate();

        return $this;
    }

    /**
     * Get newsletterFrequency.
     *
     * @return int $newsletterFrequency
     */
    public function getNewsletterFrequency()
    {
        return $this->newsletterFrequency;
    }

    /**
     * Set newsletterRange.
     *
     * @param int $newsletterRange
     *
     * @return $this
     */
    public function setNewsletterRange($newsletterRange)
    {
        $this->newsletterRange = $newsletterRange;

        return $this;
    }

    /**
     * Get newsletterRange.
     *
     * @return int $newsletterRange
     */
    public function getNewsletterRange()
    {
        return $this->newsletterRange;
    }

    /**
     * Set nextNewsletterDate.
     *
     * @param date $nextNewsletterDate
     *
     * @return $this
     */
    public function setNextNewsletterDate($nextNewsletterDate)
    {
        $this->nextNewsletterDate = $nextNewsletterDate;

        return $this;
    }

    /**
     * Get nextNewsletterDate.
     *
     * @return date $nextNewsletterDate
     */
    public function getNextNewsletterDate()
    {
        return $this->nextNewsletterDate;
    }

    /**
     * Set lastNewsletterSentAt.
     *
     * @param date $lastNewsletterSentAt
     *
     * @return $this
     */
    public function setLastNewsletterSentAt($lastNewsletterSentAt)
    {
        $this->lastNewsletterSentAt = $lastNewsletterSentAt;

        return $this;
    }

    /**
     * Get lastNewsletterSentAt.
     *
     * @return \DateTime $lastNewsletterSentAt
     */
    public function getLastNewsletterSentAt()
    {
        return $this->lastNewsletterSentAt;
    }

    /**
     * Add allowedStamp.
     *
     * @param App\Document\Stamp $allowedStamp
     */
    public function addAllowedStamp(\App\Document\Stamp $allowedStamp)
    {
        $this->allowedStamps[] = $allowedStamp;
    }

    /**
     * Remove allowedStamp.
     *
     * @param App\Document\Stamp $allowedStamp
     */
    public function removeAllowedStamp(\App\Document\Stamp $allowedStamp)
    {
        $this->allowedStamps->removeElement($allowedStamp);
    }

    /**
     * Get allowedStamps.
     *
     * @return \Doctrine\Common\Collections\Collection $allowedStamps
     */
    public function getAllowedStamps()
    {
        return $this->allowedStamps;
    }

    /**
     * Set communsUid.
     *
     * @param string $communsUid
     *
     * @return $this
     */
    public function setCommunsUid($communsUid)
    {
        $this->communsUid = $communsUid;

        return $this;
    }

    /**
     * Get communsUid.
     *
     * @return string $communsUid
     */
    public function getCommunsUid()
    {
        return $this->communsUid;
    }

    /**
     * Set communsName.
     *
     * @param string $communsName
     *
     * @return $this
     */
    public function setCommunsName($communsName)
    {
        $this->communsName = $communsName;

        return $this;
    }

    /**
     * Get communsName.
     *
     * @return string $communsName
     */
    public function getCommunsName()
    {
        return $this->communsName;
    }

    /**
     * Set communsData.
     *
     * @param string $communsData
     *
     * @return $this
     */
    public function setCommunsData($communsData)
    {
        $this->communsData = $communsData;

        return $this;
    }

    /**
     * Get communsData.
     *
     * @return string $communsData
     */
    public function getCommunsData()
    {
        return $this->communsData;
    }

    /**
     * Get be notified by email when an Element need moderation
     */ 
    public function getWatchModeration()
    {
        return $this->watchModeration;
    }

    /**
     * Set be notified by email when an Element need moderation
     *
     * @return  self
     */ 
    public function setWatchModeration($watchModeration)
    {
        $this->watchModeration = $watchModeration;

        return $this;
    }
    
    /**
     * Get the value of watchModerationFrequency
     */ 
    public function getWatchModerationFrequency()
    {
        return $this->watchModerationFrequency;
    }

    /**
     * Set the value of watchModerationFrequency
     *
     * @return  self
     */ 
    public function setWatchModerationFrequency($watchModerationFrequency)
    {
        $this->watchModerationFrequency = $watchModerationFrequency;

        return $this;
    }

    /**
     * Set lastModerationNotificationSentAt.
     *
     * @param date $lastModerationNotificationSentAt
     *
     * @return $this
     */
    public function setLastModerationNotificationSentAt($lastModerationNotificationSentAt)
    {
        $this->lastModerationNotificationSentAt = $lastModerationNotificationSentAt;

        return $this;
    }

    /**
     * Get lastModerationNotificationSentAt.
     *
     * @return \DateTime $lastModerationNotificationSentAt
     */
    public function getLastModerationNotificationSentAt()
    {
        return $this->lastModerationNotificationSentAt;
    }

    /**
     * Get the value of watchModerationOnlyWithOptions
     */ 
    public function getWatchModerationOnlyWithOptions()
    {
        return $this->watchModerationOnlyWithOptions;
    }

    /**
     * Set the value of watchModerationOnlyWithOptions
     *
     * @return  self
     */ 
    public function setWatchModerationOnlyWithOptions($watchModerationOnlyWithOptions)
    {
        $this->watchModerationOnlyWithOptions = $watchModerationOnlyWithOptions;

        return $this;
    }

    /**
     * Get the value of watchModerationOnlyWithPostCodes
     */ 
    public function getWatchModerationOnlyWithPostCodes()
    {
        return $this->watchModerationOnlyWithPostCodes;
    }

    /**
     * Get regexp associated with watchModerationOnlyWithPostCodes property
     */ 
    public function getWatchModerationOnlyWithPostCodesRegexp()
    {
        if ($this->getWatchModerationOnlyWithPostCodes()) {
            $regexp = str_replace(',', '|^', $this->getWatchModerationOnlyWithPostCodes());
            $regexp = "/^" . str_replace(' ', '', $regexp) . "/";
            $regexp = str_replace('*', '.*', $regexp);
            return $regexp;
        } else {
            return false;
        }
    }

    /**
     * Set the value of watchModerationOnlyWithPostCodes
     *
     * @return  self
     */ 
    public function setWatchModerationOnlyWithPostCodes($watchModerationOnlyWithPostCodes)
    {
        $this->watchModerationOnlyWithPostCodes = $watchModerationOnlyWithPostCodes;

        return $this;
    }
}
