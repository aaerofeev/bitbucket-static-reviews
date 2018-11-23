<?php
namespace BitbucketReviews\Checkstyle;

/**
 * Сущность файл
 */
class File
{
    /**
     * @var string Имя файла
     */
    protected $filename;

    /**
     * @var \BitbucketReviews\Checkstyle\Error[] Ошибки
     */
    protected $errors = [];

    /**
     * @var string Источник
     */
    protected $source;

    /**
     * Конструктор
     *
     * @param string $filename
     * @param string $source
     * @param array  $errors
     */
    public function __construct(string $filename, string $source, array $errors)
    {
        $this->filename = $filename;
        $this->errors   = $errors;
        $this->source   = $source;
    }

    /**
     * @see File::$filename
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @see File::$errors
     * @return \BitbucketReviews\Checkstyle\Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @see File::$source
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Добавляет ошибку
     *
     * @param \BitbucketReviews\Checkstyle\Error $error
     * @return \BitbucketReviews\Checkstyle\File
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
