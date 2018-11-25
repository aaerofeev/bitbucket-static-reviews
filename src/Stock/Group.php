<?php
namespace BitbucketReviews\Stock;

/**
 * Сущность группы ошибок
 */
class Group
{
    /**
     * @var string Имя
     */
    protected $name;

    /**
     * @var \BitbucketReviews\Stock\Error[] Ошибки
     */
    protected $errors = [];

    /**
     * @var string Источник
     */
    protected $source;

    /**
     * Конструктор
     *
     * @param string $name
     * @param string $source
     * @param array  $errors
     */
    public function __construct(string $name, string $source, array $errors)
    {
        $this->name   = $name;
        $this->errors = $errors;
        $this->source = $source;
    }

    /**
     * @see Group::$name
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @see Group::$errors
     * @return \BitbucketReviews\Stock\Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @see Group::$source
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Добавляет ошибку
     *
     * @param \BitbucketReviews\Stock\Error $error
     * @return \BitbucketReviews\Stock\Group
     */
    public function addError(Error $error): self
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Получает количество ошибок
     *
     * @return int
     */
    public function getCount(): int
    {
        return \count($this->errors);
    }
}
