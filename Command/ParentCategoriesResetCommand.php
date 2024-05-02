<?php

namespace App\Command;

use App\Services\AsyncService;
use App\EventListener\DatabaseIntegrityWatcher;
use App\EventListener\ElementJsonGenerator;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Services\DocumentManagerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ParentCategoriesResetCommand extends GoGoAbstractCommand
{
    public function __construct(DocumentManagerFactory $dm, LoggerInterface $commandsLogger,
                                ElementJsonGenerator $elementJsonService,
                                TokenStorageInterface $security,
                                TranslatorInterface $t, AsyncService $as)
    {
        $this->optionIds = [];
        $this->isUpdatedElement = false;
        $this->nbUpdatedElements = 0;
        $this->elementJsonService = $elementJsonService;
        parent::__construct($dm, $commandsLogger, $security, $t, $as);
    }

    protected function gogoConfigure(): void
    {
        $this
            ->setName('app:elements:parentCategoriesReset')
            ->setDescription('For each element, check categories and add ancestors if needed')
            ->addArgument('nbElementsMax', InputArgument::REQUIRED, 'Maximum number of elements to process')
            ->addOption('elementId', null, InputOption::VALUE_REQUIRED, 'element id')
        ;
        // ex : bin/console app:elements:parentCategoriesReset 10 gogocarto_default --elementId=4Gm
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('elementId')) {
            $elements[] = $dm->get('Element')->find($input->getOption('elementId'));
        } else {
            $elements = $dm->get('Element')->findAll();
        }

        $this->log('***** ----------------------------------');
        $this->log('***** app:elements:parentCategoriesReset');
        $this->log('***** ----------------------------------');

        $total = min(count($elements), $input->getArgument('nbElementsMax'));
        $index = 0;
        foreach ($elements as $element) {
            $index++;
            if ($index > $input->getArgument('nbElementsMax')) {
                break;
            }
            $this->log('(' . $element->getId() . ') ' . $element->getName());
            $optionIds = $element->getOptionIds();
            $this->optionIds = $optionIds;
            $this->isUpdatedElement = false;
            foreach ($optionIds as $optionId) {
                $option = $this->dm->get('Option')->find($optionId);
                if ($option) {
                    $this->recursivlyAddParentOptions($element, $option);
                }
            }
            if ($this->isUpdatedElement) {
                $this->nbUpdatedElements++;
                $element->setOptionIds($this->optionIds);
                $this->elementJsonService->updateJsonRepresentation($element);
                $dm->persist($element);
                $this->log(' ----> Element updated');
            }
            
            if (0 == ($index % 100)) {
                $this->log('***** ' . $index.' / '.$total.' elements in progress...');
                $dm->flush();
            }
        }

        $dm->flush();
        $this->log('***** All elements successfully updated (' . $total . ')');
        $this->log('***** ' . $this->nbUpdatedElements . ' elements updated');
    }
    
    private function recursivlyAddParentOptions($element, $option) {
        $parentOption = $option->getParentOption();
        if (!$parentOption) {
            return;
        }
        if ($this->isOptionAlreadyLinked($parentOption)) {
            // nothing to do
        } else {
            $this->optionIds[] = $parentOption->getId();
            $this->isUpdatedElement = true;
        }
        $this->recursivlyAddParentOptions($element, $parentOption);
    }
    
    private function isOptionAlreadyLinked($option) {
        if (count($this->optionIds) === 0) {
            return false;
        } else {
            return in_array($option->getId(), $this->optionIds);
        }
    }
}
