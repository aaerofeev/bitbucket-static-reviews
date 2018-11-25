<?php
namespace BitbucketReviews\Stash;

/**
 * Сущьность комментария
 */
class Comment
{
    /**
     * Использовать общий diff pull request
     */
    const DIFF_TYPE_EFFECTIVE = 'EFFECTIVE';

    /**
     * Использовать diff между двумя произвольными коммитами
     */
    const DIFF_TYPE_COMMIT = 'COMMIT';

    /**
     * Использовать diff между двумя диапозонами коммитов
     */
    const DIFF_TYPE_RANGE = 'RANGE';

    /**
     * Использовать изменненый файл
     */
    const FILE_TYPE_TO = 'TO';

    /**
     * Использовать исходный файл
     */
    const FILE_TYPE_FROM = 'FROM';

    /**
     * Комментарий для контекста
     */
    const LINE_TYPE_CONTEXT = 'CONTEXT';

    /**
     * Для добавленной строки
     */
    const LINE_TYPE_ADDED = 'ADDED';

    /**
     * Для удаленной строки
     */
    const LINE_TYPE_REMOVED = 'REMOVED';

    /**
     * @var int
     */
    protected $id = 0;

    /**
     * @var int Родительский комментарий
     */
    protected $parentId = 0;

    /**
     * @var \BitbucketReviews\Stash\Comment[] Дочерние комментарии
     */
    protected $comments = [];

    /**
     * @var int Номер строки
     */
    protected $line = 0;

    /**
     * @var string Путь до файла
     */
    protected $path = '';

    /**
     * @var string Путь до исходного файла, оригинального, обычно то же что и path
     */
    protected $srcPath = '';

    /**
     * @var int
     */
    protected $authorId = 0;

    /**
     * @var string
     */
    protected $text = '';

    /**
     * @var string Откуда хеш
     */
    protected $fromHash;

    /**
     * @var string Куда хеш
     */
    protected $toHash;

    /**
     * @var string Исходный файл или редактируемый
     */
    protected $fileType = self::FILE_TYPE_TO;

    /**
     * @var string Тип diff
     */
    protected $diffType = self::DIFF_TYPE_EFFECTIVE;

    /**
     * @var string Тип строки
     */
    protected $lineType = self::LINE_TYPE_ADDED;

    /**
     * @var bool Устаревший
     */
    protected $orphaned = false;

    /**
     * @var int Версия
     */
    protected $version = 0;

    /**
     * Создает сущьность из массива
     *
     * @param array $data
     * @return \BitbucketReviews\Stash\Comment
     */
    public static function fromArray(array $data): Comment
    {
        $comment           = new self();
        $comment->id       = $data['id'] ?? 0;
        $comment->version  = $data['version'] ?? 0;
        $comment->text     = $data['text'] ?? '';
        $comment->authorId = $data['author']['id'] ?? 0;
        $comment->line     = $data['anchor']['line'] ?? 0;
        $comment->path     = $data['anchor']['path'] ?? '';
        $comment->srcPath  = $data['anchor']['srcPath'] ?? '';
        $comment->fileType = $data['anchor']['fileType'] ?? '';
        $comment->diffType = $data['anchor']['diffType'] ?? '';
        $comment->lineType = $data['anchor']['lineType'] ?? '';
        $comment->orphaned = $data['anchor']['orphaned'] ?? false;

        foreach ($data['comments'] ?? [] as $child) {
            $comment->addComment(self::fromArray($child));
        }

        return $comment;
    }

    /**
     * Добавляет дочерний комментарий
     *
     * @param \BitbucketReviews\Stash\Comment $comment
     * @return \BitbucketReviews\Stash\Comment
     */
    public function addComment(Comment $comment): self
    {
        $comment->setParentId($this->getId());
        $this->comments[] = $comment;

        return $this;
    }

    /**
     * @see Comment::$id
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @see Comment::$id
     * @param int $id
     * @return Comment
     */
    public function setId(int $id): Comment
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @see Comment::$path
     * @see Comment::$line
     * @param int         $line
     * @param string      $path
     * @param string|null $srcPath
     * @return Comment
     */
    public function setDestination(int $line, string $path, string $srcPath = null): Comment
    {
        $this->line    = $line;
        $this->path    = $path;
        $this->srcPath = $srcPath ?? $path;

        return $this;
    }

    /**
     * @see Comment::$parentId
     * @return int
     */
    public function getParentId(): int
    {
        return $this->parentId;
    }

    /**
     * @see Comment::$parentId
     * @param int $parentId
     * @return Comment
     */
    public function setParentId(int $parentId): Comment
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * @see Comment::$comments
     * @return \BitbucketReviews\Stash\Comment[]
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * @see Comment::$line
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @see Comment::$lineType
     * @return string
     */
    public function getLineType(): string
    {
        return $this->lineType;
    }

    /**
     * @see Comment::$lineType
     * @param string $lineType
     * @return Comment
     */
    public function setLineType(string $lineType): Comment
    {
        $this->lineType = $lineType;

        return $this;
    }

    /**
     * @see Comment::$path
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @see Comment::$srcPath
     * @return string
     */
    public function getSrcPath(): string
    {
        return $this->srcPath;
    }

    /**
     * @see Comment::$authorId
     * @return int
     */
    public function getAuthorId(): int
    {
        return (int) $this->authorId;
    }

    /**
     * @see Comment::$authorId
     * @param int $authorId
     * @return Comment
     */
    public function setAuthorId(int $authorId): Comment
    {
        $this->authorId = $authorId;
        return $this;
    }

    /**
     * @see Comment::$text
     * @return string
     */
    public function getText(): string
    {
        return (string) $this->text;
    }

    /**
     * @see Comment::$text
     * @param string $text
     * @return Comment
     */
    public function setText(string $text): Comment
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @see Comment::$fileType
     * @param string $fileType
     * @return Comment
     */
    public function setFileType(string $fileType): Comment
    {
        $this->fileType = $fileType;

        return $this;
    }

    /**
     * @see Comment::$diffType
     * @param string $diffType
     * @return Comment
     */
    public function setDiffType(string $diffType): Comment
    {
        $this->diffType = $diffType;

        return $this;
    }

    /**
     * @see Comment::$fromHash
     * @see Comment::$toHash
     * @param string $fromHash
     * @param string $toHash
     * @return Comment
     */
    public function setRange(string $fromHash, string $toHash): Comment
    {
        $this->fromHash = $fromHash;
        $this->toHash   = $toHash;

        return $this;
    }

    /**
     * Дописывает комментарий
     *
     * @param string $text
     * @return \BitbucketReviews\Stash\Comment
     */
    public function appendText(string $text): Comment
    {
        $this->text .= $text;

        return $this;
    }

    /**
     * @see Comment::$orphaned
     * @return bool
     */
    public function isOrphaned(): bool
    {
        return $this->orphaned;
    }

    /**
     * Получает данные для создания
     *
     * @return array
     */
    public function asArrayCreate(): array
    {
        $data = ['text' => $this->text];

        if ($this->parentId) {
            $data['parent'] = ['id' => $this->parentId];
        } else {
            $data['anchor']             = [];
            $data['anchor']['line']     = $this->line;
            $data['anchor']['lineType'] = $this->lineType;
            $data['anchor']['path']     = $this->path;
            $data['anchor']['srcPath']  = $this->srcPath;
            $data['anchor']['fileType'] = $this->fileType;
            $data['anchor']['diffType'] = $this->diffType;

            if ($this->fromHash) {
                $data['anchor']['fromHash'] = $this->fromHash;
            }

            if ($this->toHash) {
                $data['anchor']['toHash']   = $this->toHash;
            }
        }

        return $data;
    }

    /**
     * Получает данные для обновления
     *
     * @return array
     */
    public function asArrayUpdate(): array
    {
        return [
            'text'    => $this->text,
            'version' => $this->version ++,
        ];
    }
}
