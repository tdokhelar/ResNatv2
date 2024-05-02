<?php

namespace App\Services;

use App\Document\Coordinates;
use App\Document\Element;
use App\Document\OptionValue;
use App\Document\PostalAddress;
use App\Document\UserInteractionVote;
use Doctrine\ODM\MongoDB\DocumentManager;
use joshtronic\LoremIpsum;

class RandomCreationService
{
    protected $dm;

    /**
     * Constructor.
     */
    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function generate($number, $generateVotes = true)
    {
        $SOlat = 43.55;
        $SOlng = -0.94;
        $NElat = 49.22;
        $NElng = 5.89;

        $statusSet = [
          -4 => 0.05,
          -3 => 0.05,
          -2 => 0.05,
          -1 => 0.1,
          0 => 0.15,
          1 => 0.2,
          2 => 0.2,
          3 => 0.1,
          4 => 0.1,
        ];

        $pendingTypeSet = [
          0 => 0.3,
          1 => 0.7,
        ];

        $moderationTypeSet = [
          0 => 0.8,
          1 => 0.1,
          2 => 0.1,
        ];

        $voteEditSet = [
          -1 => 0.3,
          1 => 0.7,
        ];

        $voteNewSet = [
          -2 => 0.1,
          -1 => 0.1,
          0 => 0.3,
          1 => 0.3,
          2 => 0.2,
        ];

        $lngSpan = $NElng - $SOlng;
        $latSpan = $NElat - $SOlat;

        $mainCategories = $this->dm->get('Category')->findRootCategories();

        $lipsum = new LoremIpsum();

        for ($i = 1; $i <= $number; ++$i) {
            $new_element = new Element();

            $new_element->setName($lipsum->words(rand(2, 8)));

            $lat = $SOlat + $latSpan * $this->random_0_1();
            $lng = $SOlng + $lngSpan * $this->random_0_1();

            $new_element->setGeo(new Coordinates($lat, $lng));
            $new_element->setAddress(new PostalAddress(5, $lipsum->words(rand(4, 8)), $lipsum->words(rand(1, 3))));
            $new_element->setEmail('element@bio.fr');
            $new_element->setStatus($this->randWithSet($statusSet));

            if ($new_element->isPending()) {
                if ($generateVotes) {
                    // $nbreVotes = rand(0,5);
                  // for ($j=0; $j < $nbreVotes; $j++)
                  // {
                  // 	$vote = new UserInteractionVote();
                  // 	$vote->setValue($this->randWithSet($new_element->getStatus() == 0 ? $voteNewSet : $voteEditSet));
                  // 	$vote->setUserEmail($lipsum->words(1) . '@protonmail.com');
                  // 	if (rand(0,1)) $vote->setComment($lipsum->words(rand(6,10)));
                  // 	$this->dm->persist($vote);
                  // 	$new_element->addVote($vote);
                  // }
                }
            }

            $new_element->setModerationState($this->randWithSet($moderationTypeSet));

            foreach ($mainCategories as $key => $mainCategory) {
                $this->recursivelyCreateOptionsforCategory($mainCategory, $new_element, $lipsum);
            }

            $this->dm->persist($new_element);

            if (0 == ($i % 100)) {
                $this->dm->flush();
                $this->dm->clear();
            }
        }

        $this->dm->flush();

        return $new_element;
    }

    private function recursivelyCreateOptionsforCategory($category, $element, $lipsum)
    {
        $nbreOptionsSet = [
      1 => 0.6,
      2 => 0.3,
      3 => 0.1,
    ];

        $nbreOptions = $this->randWithSet($nbreOptionsSet);

        $options = $category->getOptions();

        // store keys to avoid duplicate
        $optionKeys = [];
        for ($j = 0; $j < $nbreOptions; ++$j) {
            $optionValue = new OptionValue();
            $key = rand(0, count($options) - 1);

            if (!in_array($key, $optionKeys)) {
                $optionKeys[] = $key;
                $option = $options[$key];
                if ($option) {
                    $optionValue->setOptionId($option->getId());
                    $optionValue->setIndex($j);
                    if ($category->getEnableDescription()) {
                        $optionValue->setDescription($lipsum->words(rand(3, 10)));
                    }
                    $element->addOptionValue($optionValue);

                    // for each subcategory
                    for ($k = 0; $k < count($option->getSubcategories()); ++$k) {
                        $this->recursivelyCreateOptionsforCategory($option->getSubcategories()[$k], $element, $lipsum);
                    }
                }
            }
        }
    }

    private function randWithSet(array $set, $length = 10000)
    {
        $left = 0;
        foreach ($set as $num => $right) {
            $set[$num] = $left + $right * $length;
            $left = $set[$num];
        }
        $test = mt_rand(1, $length);
        $left = 1;
        foreach ($set as $num => $right) {
            if ($test >= $left && $test <= $right) {
                return $num;
            }
            $left = $right;
        }

        return null; //debug, no event realized
    }

    private function randomText($quantite = 1, $type = 'paras', $lorem = false)
    {
        $url = "http://www.lipsum.com/feed/xml?amount=$quantite&what=$type&start=".($lorem ? 'yes' : 'no');

        return simplexml_load_file($url)->lipsum;
    }

    private function random_0_1()
    {
        // returns random number with flat distribution from 0 to 1
        return (float) rand() / (float) getrandmax();
    }
}
