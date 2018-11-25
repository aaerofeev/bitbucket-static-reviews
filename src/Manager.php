<?php
namespace BitbucketReviews;

use BitbucketReviews\Checkstyle\Collector;
use BitbucketReviews\Exception\StashException;
use BitbucketReviews\Stash\API;
use BitbucketReviews\Stash\Comment;
use BitbucketReviews\Stock\Error;
use BitbucketReviews\Stock\Group;
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
     * @var \BitbucketReviews\Stash\API
     */
    protected $stashApi;

    /**
     * @var string Ветка с которой работаем
     */
    protected $branch;

    /**
     * @var \BitbucketReviews\Checkstyle\Collector[] Отчеты
     */
    protected $collector;

    /**
     * @var int Тип проверки
     */
    protected $inspect;

    /**
     * @var array Тип линий которые следует обрабатывать
     */
    protected $inspectLineType;

    /**
     * @var array Список паттернов которые следует игнорировать
     */
    protected $ignoredText = [];

    /**
     * @var array Список путей файлов которые следует игнорировать
     */
    protected $ignoredFiles = [];

    /**
     * @var string Минимальная важность
     */
    protected $minSeverity = Error::SEVERITY_ERROR;

    /**
     * @var int Общий лимит
     */
    protected $limit = 0;

    /**
     * @var int Лимит на группу
     */
    protected $limitPerGroup = 0;

    /**
     * @var int Лимит на файл
     */
    protected $limitPerFile = 0;

    /**
     * @var bool Нужно ли группировать
     */
    protected $group;

    /**
     * @var \BitbucketReviews\Stash\Comment[] Комментарии которые нужно создать
     */
    protected $commentsNew = [];

    /**
     * @var \BitbucketReviews\Stash\Comment[] Комментарии которые нужно отметить исправленными
     */
    protected $commentsUpdated = [];

    /**
     * @var int Счетчик всех комментариев какие есть
     */
    protected $commentsCount = 0;
    /**
     * @var array Статические данные
     */
    protected $stat = [
        'new'                 => 0,
        'fixed'               => 0,
        'updated'             => 0,
        'exists'              => 0,
        'exists_for_line'     => 0,
        'exists_for_group'    => 0,
        'ignored_by_severity' => 0,
        'ignored_by_text'     => 0,
        'ignored_by_file'     => 0,
        'errors'              => 0,
        'errors_hit'          => 0,
        'errors_api'          => [],
        'limit_per_file'      => 0,
        'limit_main'          => 0,
    ];

    /**
     * Конструктор
     *
     * @param \BitbucketReviews\Stash\API $stashApi
     * @param string                      $branch
     */
    public function __construct(API $stashApi, string $branch)
    {
        $this->stashApi  = $stashApi;
        $this->branch    = $branch;
        $this->collector = new Collector();
    }

    /**
     * Читает diff
     *
     * @param string $filename
     * @param string $vcs
     */
    public function readDiff(string $filename, string $vcs = DiffParser::VCS_GIT)
    {
        $parser     = new DiffParser();
        $this->diff = $parser->parseFile($filename, $vcs);
    }

    /**
     * Добавляет отчет checkstyle
     *
     * @param string $filename
     * @param string $name
     * @param string $rootPath
     * @throws \BitbucketReviews\Exception\CheckStyleFormatException
     */
    public function readCheckStyle(string $filename, string $name, string $rootPath = '')
    {
        $this->stat['errors'] += $this->collector->parseFile($filename, $name, $rootPath);
    }

    /**
     * Запускает процесс анализа
     *
     * @return array
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function run(): array
    {
        $placeId = $this->getStashApi()->getOpenPullRequestId($this->getBranch());

        foreach ($this->getDiff()->getFiles() as $sourceFile) {
            if ($sourceFile->getOperation() === File::DELETED) {
                continue;
            }

            $errorFile = $this->collector->getGroup($sourceFile->getNewFilename());

            if (!$errorFile) {
                continue;
            }

            foreach ($this->getIgnoredFiles() as $pattern) {
                $ignored = fnmatch($pattern, $sourceFile->getOriginalFilename()) ||
                    fnmatch($pattern, $sourceFile->getNewFilename());

                if ($ignored) {
                    $this->stat['ignored_by_file'] += $errorFile->getCount();
                    continue 2;
                }
            }

            try {
                $comments = $this->getStashApi()->getComments($placeId, $sourceFile->getOriginalFilename());

                // Для того чтобы не было дублирования, получаем со старого и нового файла комментарии
                // Возможно это лишнее, время покажет
                if ($sourceFile->getNewFilename() !== $sourceFile->getOriginalFilename()) {
                    foreach ($this->getStashApi()->getComments($placeId, $sourceFile->getNewFilename()) as $comment) {
                        $comments[] = $comment;
                    }
                }

                $this->analyzeFile($sourceFile, $errorFile, $comments);
            } catch (StashException $e) {
                $this->stat['errors_api'][] = $e->getMessage();
            }
        }

        foreach ($this->commentsNew as $comment) {
            if ($this->commentsCount > $this->getLimit()) {
                $this->stat['limit_main']++;
                break;
            }

            try {
                $this->getStashApi()->postComment($placeId, $comment);
                $this->stat['new']++;
                $this->commentsCount++;
            } catch (Exception\StashException $e) {
                $this->stat['errors_api'][] = $e->getMessage();
            }
        }

        foreach ($this->commentsUpdated as $comment) {
            try {
                $this->getStashApi()->updateComment($placeId, $comment);
                $this->stat['updated']++;
            } catch (Exception\StashException $e) {
                $this->stat['errors_api'][] = $e->getMessage();
            }
        }

        return $this->stat;
    }

    /**
     * @see Manager::$stashApi
     * @return \BitbucketReviews\Stash\API
     */
    public function getStashApi(): \BitbucketReviews\Stash\API
    {
        return $this->stashApi;
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
     * @see Manager::$ignoredFiles
     * @return array
     */
    public function getIgnoredFiles(): array
    {
        return $this->ignoredFiles;
    }

    /**
     * @see Manager::$ignoredFiles
     * @param array $ignoredFiles
     * @return Manager
     */
    public function setIgnoredFiles(array $ignoredFiles): Manager
    {
        $this->ignoredFiles = $ignoredFiles;
        return $this;
    }

    /**
     * Анализирует файл
     *
     * @param \ptlis\DiffParser\File            $sourceFile
     * @param \BitbucketReviews\Stock\Group     $errorFile
     * @param \BitbucketReviews\Stash\Comment[] $comments
     */
    private function analyzeFile(File $sourceFile, Group $errorFile, array $comments)
    {
        $comments = array_filter($comments, function (Comment $comment) {
            return $comment->getAuthorId() === $this->getStashApi()->getUserId();
        });

        $count               = \count($comments);
        $this->commentsCount += $count;

        $errors = array_filter($errorFile->getErrors(), function (Error $error) {
            if (!$error->isSeverityMatch($this->getMinSeverity())) {
                $this->stat['ignored_by_severity']++;

                return false;
            }

            foreach ($this->ignoredText as $pattern) {
                if (strpos($error->getText(), $pattern) !== false) {
                    $this->stat['ignored_by_text']++;

                    return false;
                }
            }

            return true;
        });

        foreach ($sourceFile->getHunks() as $hunk) {
            if ($count > $this->getLimitPerFile()) {
                $this->stat['limit_per_file']++;
                continue;
            }

            $lines = array_filter($hunk->getLines(), function (Line $line) {
                return $this->isOperationSupported($line->getOperation());
            });

            $count += $this->processHunk($sourceFile, $lines, $errors, $comments, $this->getLimitPerFile() - $count);
        }
    }

    /**
     * @see Manager::$minSeverity
     * @return string
     */
    public function getMinSeverity(): string
    {
        return $this->minSeverity;
    }

    /**
     * @see Manager::$minSeverity
     * @param string $minSeverity
     * @return Manager
     */
    public function setMinSeverity(string $minSeverity): Manager
    {
        $this->minSeverity = $minSeverity;

        return $this;
    }

    /**
     * @see Manager::$limitPerFile
     * @return int
     */
    public function getLimitPerFile(): int
    {
        return $this->limitPerFile;
    }

    /**
     * @see Manager::$limitPerFile
     * @param int $limitPerFile
     * @return Manager
     */
    public function setLimitPerFile(int $limitPerFile): Manager
    {
        $this->limitPerFile = $limitPerFile;
        return $this;
    }

    /**
     * Провереяет требуется ли обработка данного типа линии
     *
     * @param string $lineType
     * @return bool
     */
    protected function isOperationSupported(string $lineType): bool
    {
        return \in_array($lineType, $this->inspectLineType, false);
    }

    /**
     * Обработка одного участка изменений кода
     *
     * @param \ptlis\DiffParser\File            $sourceFile
     * @param \ptlis\DiffParser\Line[]          $lines
     * @param \BitbucketReviews\Stock\Error[]   $errors
     * @param \BitbucketReviews\Stash\Comment[] $comments
     * @param int                               $limit
     * @return int
     */
    protected function processHunk(File $sourceFile, array $lines, array $errors, array $comments, int $limit): int
    {
        /** @var \BitbucketReviews\Stash\Comment[] $newComments */
        $newComments = [];
        /** @var \BitbucketReviews\Stock\Error[][] $errorContents */
        $errorContents = [];

        foreach ($errors as $error) {
            foreach ($lines as $line) {
                if ($error->getLine() !== $line->getNewLineNo()) {
                    continue;
                }

                $fileLine = $line->getNewLineNo();
                $lineType = Comment::LINE_TYPE_ADDED;
                $srcPath  = $sourceFile->getNewFilename();

                if ($line->getOperation() !== Line::ADDED) {
                    $fileLine = $line->getOriginalLineNo();
                    $lineType = Comment::LINE_TYPE_CONTEXT;
                    $srcPath  = $sourceFile->getOriginalFilename();
                }

                $commentForLine = null;

                foreach ($comments as $comment) {
                    if ($fileLine === $comment->getLine() && $comment->getLineType() === $lineType) {
                        $commentForLine                     = $comment;
                        $errorContents[$comment->getId()][] = $error;
                    }

                    $has = $fileLine === $comment->getLine()
                        && Helper::containsError($comment->getText(), $error->getText());

                    if ($has) {
                        $this->stat['exists']++;
                        continue 2;
                    }
                }

                $this->stat['errors_hit']++;

                if ($commentForLine && $this->getCommentErrorCount($commentForLine) <= $this->getLimitPerGroup()) {
                    $commentForLine->appendText($error->getText());
                    $this->commentsUpdated[$commentForLine->getId()] = $commentForLine;
                    $this->stat['exists_for_line']++;
                } elseif (
                    isset($newComments[$fileLine]) &&
                    $this->isGroupEnabled() &&
                    $newComments[$fileLine]->getLineType() === $lineType &&
                    $this->getCommentErrorCount($newComments[$fileLine]) <= $this->getLimitPerGroup()
                ) {
                    $newComments[$fileLine]->appendText($error->getText());
                    $this->stat['exists_for_group']++;
                } else {
                    $comment = new Comment();
                    $comment->appendText($error->getText());
                    $comment->setLineType($lineType);
                    $comment->setDestination($fileLine, $sourceFile->getNewFilename(), $srcPath);

                    $newComments[$fileLine] = $comment;

                    if (\count($newComments) <= $limit) {
                        $this->commentsNew[] = $comment;
                    } else {
                        $this->stat['limit_per_file']++;
                    }
                }

                break;
            }
        }

        $this->resolveFixed($comments, $errorContents);

        return \count($newComments);
    }

    /**
     * Получает количество текстовых ошибок в комментарии
     *
     * @param \BitbucketReviews\Stash\Comment $comment
     * @return int
     */
    protected function getCommentErrorCount(Comment $comment): int
    {
        return \count(Helper::splitByErrorText($comment->getText()));
    }

    /**
     * @see Manager::$limitPerGroup
     * @return int
     */
    public function getLimitPerGroup(): int
    {
        return $this->limitPerGroup;
    }

    /**
     * @see Manager::$limitPerGroup
     * @param int $limitPerGroup
     * @return Manager
     */
    public function setLimitPerGroup(int $limitPerGroup): Manager
    {
        $this->limitPerGroup = $limitPerGroup;
        return $this;
    }

    /**
     * @see Manager::$group
     * @return bool
     */
    public function isGroupEnabled(): bool
    {
        return $this->group;
    }

    /**
     * Решает какие комментарии отметить исправленными
     *
     * @param \BitbucketReviews\Stash\Comment[] $comments
     * @param \BitbucketReviews\Stock\Error[][] $contests
     * @return int
     */
    protected function resolveFixed(array $comments, array $contests): int
    {
        $count = 0;

        foreach ($comments as $comment) {
            $parts = Helper::splitByErrorText($comment->getText());
            $fixed = [];

            foreach ($parts as $key => $part) {
                if (!empty($contests[$comment->getId()])) {
                    $found = false;

                    foreach ($contests[$comment->getId()] as $error) {
                        if (Helper::containsError($part, $error->getText())) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $fixed[] = $key;
                    }
                } else {
                    $fixed[] = $key;
                }
            }

            foreach ($fixed as $k => $partKey) {
                try {
                    $parts[$partKey] = Helper::markAsFixed($parts[$partKey]);
                    $count++;
                } catch (\InvalidArgumentException $e) {
                    unset($fixed[$k]);
                }
            }

            if ($fixed) {
                $this->commentsUpdated[$comment->getId()] = $comment->setText(Helper::join($parts));
            }
        }

        $this->stat['fixed'] += $count;

        return $count;
    }

    /**
     * @see Manager::$limit
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @see Manager::$limit
     * @param int $limit
     * @return Manager
     */
    public function setLimit(int $limit): Manager
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @see Manager::$group
     * @param bool $group
     * @return Manager
     */
    public function setGroup(bool $group): Manager
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @see Manager::$inspect
     * @return int
     */
    public function getInspect(): int
    {
        return $this->inspect;
    }

    /**
     * @see Manager::$inspect
     * @param int $inspect
     * @return Manager
     */
    public function setInspect(int $inspect): Manager
    {
        $this->inspect         = $inspect;
        $this->inspectLineType = [Line::ADDED];

        if ($this->inspect === Config::INSPECT_CONTEXT) {
            $this->inspectLineType[] = Line::UNCHANGED;
        }

        return $this;
    }

    /**
     * @see Manager::$ignoredText
     * @return array
     */
    public function getIgnoredText(): array
    {
        return $this->ignoredText;
    }

    /**
     * @see Manager::$ignoredText
     * @param array $ignoredText
     * @return Manager
     */
    public function setIgnoredText(array $ignoredText): Manager
    {
        $this->ignoredText = $ignoredText;

        return $this;
    }
}
