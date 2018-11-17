<?php
namespace CheckstyleStash\Checkstyle;

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
     * @var \CheckstyleStash\Checkstyle\Error[] Ошибки
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
     * @return \CheckstyleStash\Checkstyle\Error[]
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
     * @param \CheckstyleStash\Checkstyle\Error $error
     * @return \CheckstyleStash\Checkstyle\File
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
