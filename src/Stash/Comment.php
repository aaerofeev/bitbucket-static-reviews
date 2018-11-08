<?php
namespace CheckstyleStash\Stash;

/**
 * Сущьность комментария
 */
class Comment implements \JsonSerializable
{
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
     * @see Comment::$line
     * @param int $line
     * @return Comment
     */
    public function setLine(int $line): Comment
    {
        $this->line = $line;

        return $this;
    }

    /**
     * @see Comment::$path
     * @param string $path
     * @return Comment
     */
    public function setPath(string $path): Comment
    {
        $this->path = $path;

        return $this;
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
     * @see Comment::$id
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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
     * @see Comment::$text
     * @return string
     */
    public function getText(): string
    {
        return (string) $this->text;
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
            $data['anchor']['lineType'] = 'CONTEXT';
            $data['anchor']['path']     = $this->path;
            $data['anchor']['srcPath']  = $this->path;
        }

        return $data;
    }

    /**
     * Создает сущьность из массива
     *
     * @param array $data
     * @return \CheckstyleStash\Stash\Comment
     */
    public static function fromArray(array $data): Comment
    {
        $comment = new self();
        $comment->id = $data['id'] ?? 0;
        $comment->text = $data['text'] ?? '';
        $comment->authorId = $data['author']['id'] ?? 0;
        $comment->line = $data['anchor']['line'] ?? 0;
        $comment->path = $data['anchor']['path'] ?? '';

        foreach ($data['comments'] ?? [] as $child) {
            $comment->addComment(self::fromArray($child));
        }

        return $comment;
    }
}
