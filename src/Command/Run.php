<?php
namespace BitbucketReviews\Command;

use BitbucketReviews\Config;
use BitbucketReviews\Manager;
use BitbucketReviews\Stash\API;
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
                    'checkstyle file path <filename>:<name>:<root>'
                ),
                new InputOption(
                    'config',
                    null,
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
        $manager->setGroup($analyzer['group']);

        $stat = $manager->run();

        $output->writeln('Comments updated: ' . json_encode($stat));
    }
}
