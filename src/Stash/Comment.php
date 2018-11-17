<?php
namespace CheckstyleStash\Stash;

/**
 * Сущьность комментария
 */
class Comment implements \JsonSerializable
{
    /**
     * Использовать общий diff pull request
     */
    public const DIFF_TYPE_EFFECTIVE = 'EFFECTIVE';

    /**
     * Использовать diff между двумя произвольными коммитами
     */
    public const DIFF_TYPE_COMMIT = 'COMMIT';

    /**
     * Использовать diff между двумя диапозонами коммитов
     */
    public const DIFF_TYPE_RANGE = 'RANGE';

    /**
     * Использовать изменненый файл
     */
    public const FILE_TYPE_TO = 'TO';

    /**
     * Использовать исходный файл
     */
    public const FILE_TYPE_FROM = 'FROM';

    /**
     * Комментарий для контекста
     */
    public const LINE_TYPE_CONTEXT = 'CONTEXT';

    /**
     * Для добавленной строки
     */
    public const LINE_TYPE_ADDED = 'ADDED';

    /**
     * Для удаленной строки
     */
    public const LINE_TYPE_REMOVED = 'REMOVED';

    /**
     * @var int
     */
    protected $id = 0;

    /**
     * @var int Родительский комментарий
     */
    protected $parentId = 0;

    /**
     * @var \CheckstyleStash\Stash\Comment[] Дочерние комментарии
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
     * @var int
     */
    protected $authorId = 0;

    /**
     * @var string
     */
    protected $text = 0;

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
     * Создает сущьность из массива
     *
     * @param array $data
     * @return \CheckstyleStash\Stash\Comment
     */
    public static function fromArray(array $data): Comment
    {
        $comment           = new self();
        $comment->id       = $data['id'] ?? 0;
        $comment->text     = $data['text'] ?? '';
        $comment->authorId = $data['author']['id'] ?? 0;
        $comment->line     = $data['anchor']['line'] ?? 0;
        $comment->path     = $data['anchor']['path'] ?? '';
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
     * @param \CheckstyleStash\Stash\Comment $comment
     * @return \CheckstyleStash\Stash\Comment
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
     * @param string $path
     * @param int    $line
     * @return Comment
     */
    public function setDestination(string $path, int $line): Comment
    {
        $this->line = $line;
        $this->path = $path;

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
     * @return \CheckstyleStash\Stash\Comment[]
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
     * @see Comment::$path
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
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
     * @see Comment::$orphaned
     * @return bool
     */
    public function isOrphaned(): bool
    {
        return $this->orphaned;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        $data = ['text' => $this->text];

        if ($this->parentId) {
            $data['parent'] = ['id' => $this->parentId];
        } else {
            $data['anchor']             = [];
            $data['anchor']['line']     = $this->line;
            $data['anchor']['lineType'] = $this->lineType;
            $data['anchor']['path']     = $this->path;
            $data['anchor']['srcPath']  = $this->path;
            $data['anchor']['fileType'] = $this->fileType;
            $data['anchor']['diffType'] = $this->diffType;
            $data['anchor']['fromHash'] = $this->fromHash;
            $data['anchor']['toHash']   = $this->toHash;
        }

        return $data;
    }
}
