<?php
namespace BitbucketReviews\Command;

use BitbucketReviews\Config;
use BitbucketReviews\Manager;
use BitbucketReviews\Stash\API;
use League\StatsD\Client as StatsdClient;
use League\StatsD\Exception\ConfigurationException;
use ptlis\DiffParser\Parser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Команда запуска
 */
class Run extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Parse checkstyle and generate feedback')
            ->setDefinition([
                new InputArgument(
                    'branch',
                    InputArgument::REQUIRED,
                    'Branch name, fully path refs/heads/master'
                ),
                new InputArgument(
                    'diff',
                    InputArgument::REQUIRED,
                    'git diff output file path'
                ),
                new InputOption(
                    'diff-vsc',
                    null,
                    InputOption::VALUE_OPTIONAL,
                    'git diff output file path',
                    Parser::VCS_GIT
                ),
                new InputOption(
                    'checkstyle',
                    'c',
                    InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                    'checkstyle file path <filename>:<root>'
                ),
                new InputOption(
                    'config',
                    '-k',
                    InputOption::VALUE_OPTIONAL,
                    'config file',
                    '.config.php'
                ),
            ]);

        parent::configure();
    }

    /**
     * Запуск комманды
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|null|void
     * @throws \BitbucketReviews\Exception\CheckStyleFormatException
     * @throws \BitbucketReviews\Exception\StashException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = new Config();
        $config->loadConfig($input->getOption('config'));

        $diffFilename = $input->getArgument('diff');
        $branch       = $input->getArgument('branch');
        $checkFiles   = $input->getOption('checkstyle');

        if (!$checkFiles) {
            throw new InvalidArgumentException(
                'Required option --checkstyle <filename>:<name>:<root>'
            );
        }

        $stashConfig = $config->get('stash');
        $api         = new API(
            $stashConfig['url'],
            $stashConfig['accessToken'],
            $stashConfig['project'],
            $stashConfig['repository'],
            $stashConfig['debug']
        );

        $manager = new Manager($api, $branch);

        $manager->readDiff($diffFilename);

        foreach ($checkFiles as $option) {
            $parts    = explode(':', $option);
            $filename = $parts[0];
            $root     = $parts[1] ?? '';
            $name     = pathinfo($filename, PATHINFO_BASENAME);

            $manager->readCheckStyle($filename, $name, $root);
        }

        $analyzer = $config->get('analyzer');

        $manager->setInspect($analyzer['inspect']);
        $manager->setIgnoredText($analyzer['ignoredText']);
        $manager->setIgnoredFiles($analyzer['ignoredFiles']);
        $manager->setMinSeverity($analyzer['minSeverity']);
        $manager->setLimit($analyzer['limit']);
        $manager->setLimitPerFile($analyzer['limitPerFile']);
        $manager->setLimitPerGroup($analyzer['limitPerGroup']);
        $manager->setGroup($analyzer['group']);

        $stats = $manager->run();

        try {
            $this->sendStats($config, array_filter($stats, 'is_numeric'));
        } catch (ConfigurationException $e) {
            $output->writeln("<error>Error while send stats: {$e->getMessage()}</error>");
        }

        $output->writeln('Comments updated: ' . json_encode($stats));
    }

    /**
     * Отправляет статистику
     *
     * @param \BitbucketReviews\Config $config
     * @param array                    $stats
     * @throws \League\StatsD\Exception\ConfigurationException
     */
    protected function sendStats(Config $config, array $stats)
    {
        $statsConfig = $config->get('statsd');

        if (empty($statsConfig['host'])) {
            return;
        }

        $statsd = new StatsdClient();
        $statsd->configure($statsConfig);

        foreach ($stats as $name => $value) {
            $statsd->increment($name, $value);
        }
    }
}
