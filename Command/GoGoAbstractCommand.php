<?php

namespace App\Command;

use App\Document\GoGoLog;
use App\Document\GoGoLogLevel;
use App\Services\AsyncService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Services\DocumentManagerFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

class GoGoAbstractCommand extends Command
{
    protected $dm;
    protected $dmFactory;
    protected $logger;
    protected $security;
    protected $output;
    protected $runOnlyOnRootDatabase = false;

    public function __construct(DocumentManagerFactory $dmFactory, LoggerInterface $commandsLogger,
                               TokenStorageInterface $tokenStorage, TranslatorInterface $t,
                               AsyncService $asyncService)
    {
        $this->dmFactory = $dmFactory;
        $this->logger = $commandsLogger;
        $this->security = $tokenStorage;
        $this->t = $t;
        $this->asyncService = $asyncService;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:abstract:command');
        $this->gogoConfigure();
        $this->addArgument('dbname', InputArgument::OPTIONAL, 'Db name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dm = $this->dmFactory->getRootManager();
        try {
            $this->output = $output;

            // create dummy user, as some code called from command will maybe need the current user informations
            $token = new AnonymousToken('admin', 'GoGoBot', ['ROLE_ADMIN']);
            $this->security->setToken($token);

            if ($this->runOnlyOnRootDatabase) {
                $this->gogoExecute($this->dm, $input, $output);
            } else if ($input->getArgument('dbname')) {
                $this->dm = $this->dmFactory->switchCurrManagerToUseDb($input->getArgument('dbname'));
                $this->gogoExecute($this->dm, $input, $output);
            } else if ($_ENV['USE_AS_SAAS'] === 'true') {
                $qb = $this->dm->query('Project');
                $this->filterProjects($qb);
                $dbs = $qb->select('domainName')->getArray();
                $count = count($dbs);
                $this->log("---- Run {$this->getName()} for $count projects", false);

                $commandArgs = $input->getArguments();
                unset($commandArgs['command']);
                unset($commandArgs['dbname']);

                foreach($dbs as $dbName) {
                    if ($this->runInSeparateProcess()) {
                        $this->asyncService->setRunSynchronously(true);
                        $this->asyncService->setDisplayOutput(true);
                        $this->asyncService->callCommand($this->getName(), $commandArgs, $dbName);
                    } else {
                        $this->dm = $this->dmFactory->createForDB($dbName);
                        try {
                            $this->gogoExecute($this->dm, $input, $output);
                        } catch (\Exception $e) {
                            $message = $e->getMessage().'<br/>'.$e->getFile().' LINE '.$e->getLine();
                            $this->error('Error executing command for specific project: '.$message);
                        }   
                    }
                }
            } else {
                $this->gogoExecute($this->dm, $input, $output);
            }
        } catch (\Exception $e) {
            $message = $e->getMessage().'<br/>'.$e->getFile().' LINE '.$e->getLine();
            $this->error('Error executing command: '.$message);
        }
        return 1;
    }

    protected function gogoExecute(DocumentManager $dm, InputInterface $input, OutputInterface $output): void
    {
    }

    protected function gogoConfigure(): void
    {
    }

    // when calling the command without a dbName, we run it for all projects
    // Here we can filter the project that really need to be processed
    protected function filterProjects($qb)
    {
    }

    // when calling the command for multiple project, it can be tricky cause the DocumentManager
    // is changed for every project. But if a service has already been initialized, then it's dm will not get
    // updated. therefore the easiest solution is to run the command per DB, reinitializaing everything at each time
    protected function runInSeparateProcess()
    {
        return false;
    }

    protected function log($message, $usePrefix = true)
    {
        if ($usePrefix) $message = "DB {$this->dm->getConfiguration()->getDefaultDB()} : $message";
        $this->logger->info($message);
        $this->output->writeln($message);
    }

    protected function error($message)
    {
        $message = substr($message, 0, 1000); // try to fix strange error https://sentry.io/organizations/gogocarto/issues/2239894356/?project=1402018&query=is%3Aunresolved&statsPeriod=14d
        $log = new GoGoLog(GoGoLogLevel::Error, 'Error running '.$this->getName().' : '.$message);
        $this->dm->persist($log);
        $message = "DB {$this->dm->getConfiguration()->getDefaultDB()} : $message";
        $this->logger->error($message);
        $this->output->writeln('ERROR '.$message);
        $this->dm->flush();
    }

    protected function trans($transKey, $args = []) {
        $config = $this->dm->get('Configuration')->findConfiguration();
        return $this->t->trans($transKey, $args, 'admin', $config ? $config->getLocale() : null);
    }
}
