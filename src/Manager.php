<?php
namespace CheckstyleStash;

use CheckstyleStash\Checkstyle\Collector;
use CheckstyleStash\Stash\API;
use CheckstyleStash\Stash\Comment;
use ptlis\DiffParser\File;
use ptlis\DiffParser\Line;
use ptlis\DiffParser\Parser as DiffParser;

/**
 * Реализует базовую логику
 */
class Manager
{
    /**
     * @var \ptlis\DiffParser\Changeset
     */
    protected $diff;

    /**
     * @var \CheckstyleStash\Stash\API
     */
    protected $stashApi;

    /**
     * @var string Ветка с которой работаем
     */
    protected $branch;

    /**
     * @var \CheckstyleStash\Checkstyle\Collector[] Отчеты
     */
    protected $collector;

    /**
     * Конструктор
     *
     * @param \CheckstyleStash\Stash\API $stashApi
     * @param string                     $branch
     */
    public function __construct(API $stashApi, string $branch)
    {
        $this->stashApi  = $stashApi;
        $this->branch    = $branch;
        $this->collector = new Collector();
    }

    /**
     * @see Manager::$branch
     * @return string
     */
    public function getBranch(): string
    {
        return $this->branch;
    }

    /**
     * @see Manager::$diff
     * @return \ptlis\DiffParser\Changeset
     */
    public function getDiff(): \ptlis\DiffParser\Changeset
    {
        return $this->diff;
    }

    /**
     * @see Manager::$stashApi
     * @return \CheckstyleStash\Stash\API
     */
    public function getStashApi(): \CheckstyleStash\Stash\API
    {
        return $this->stashApi;
    }

    /**
     * Читает diff
     *
     * @param string $filename
     * @param string $vcs
     */
    public function readDiff(string $filename, string $vcs = DiffParser::VCS_GIT): void
    {
        $parser     = new DiffParser();
        $this->diff = $parser->parseFile($filename, $vcs);
    }

    /**
     * Добавляет отчет checkstyle
     *
     * @param string $filename
     * @param string $rootPath
     * @throws \CheckstyleStash\Exception\CheckStyleFormatException
     */
    public function readCheckStyle(string $filename, string $rootPath = '')
    {
        $this->collector->parseFile($filename, $rootPath);
    }

    /**
     * Запускает процесс анализа
     *
     * @throws \CheckstyleStash\Exception\StashException
     */
    public function run()
    {
        $placeId = $this->getStashApi()->getOpenPullRequestId($this->getBranch());

        foreach ($this->getDiff()->getFiles() as $sourceFile) {
            // TODO: сделать для CONTEXT
            if ($sourceFile->getOperation() !== File::CHANGED) {
                continue;
            }

            $errorFile = $this->collector->getFile($sourceFile->getNewFilename());

            if (!$errorFile) {
                continue;
            }

            $comments = $this->getStashApi()->getComments($placeId, $sourceFile->getOriginalFilename());
            $errors   = $errorFile->getErrors();

            foreach ($sourceFile->getHunks() as $hunk) {
                foreach ($hunk->getLines() as $line) {
                    // TODO: сделать для CONTEXT
                    if ($line->getOperation() !== Line::ADDED) {
                        continue;
                    }

                    foreach ($errors as $error) {
                        if ($error->getLine() !== $line->getNewLineNo()) {
                            continue;
                        }

                        $skipError = false;

                        foreach ($comments as $comment) {
                            $has = $comment->getAuthorId() === $this->getStashApi()->getUserId()
                                && $comment->getLine() === $error->getLine()
                                && $comment->getText() === $error->getMessage();

                            if ($has) {
                                $skipError = true;
                            }
                        }

                        if ($skipError) {
                            continue;
                        }

                        $comment = new Comment();
                        $comment->setLineType(Comment::LINE_TYPE_ADDED);
                        $comment->setText($error->getMessage());
                        $comment->setDestination($sourceFile->getNewFilename(), $error->getLine());

                        $this->getStashApi()->postComment($placeId, $comment);
                    }
                }
            }
        }
    }
}
