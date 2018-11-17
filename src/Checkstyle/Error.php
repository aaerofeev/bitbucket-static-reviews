<?php
namespace CheckstyleStash\Checkstyle;

/**
 * Сущность ошибка
 */
class Error
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_IGNORE = 'ignore';

    /**
     * @var int Строка
     */
    protected $line;

    /**
     * @var int Позиция
     */
    protected $column;

    /**
     * @var string Важность
     */
    protected $severity;

    /**
     * @var string Сообщение
     */
    protected $message;

    /**
     * @var string Тип ошибки
     */
    protected $source;

    /**
     * Конструктор
     *
     * @param int    $line
     * @param int    $column
     * @param string $severity
     * @param string $message
     * @param string $source
     */
    public function __construct(int $line, int $column, string $severity, string $message, string $source)
    {
        $this->line     = $line ?? 0;
        $this->column   = $column ?? 0;
        $this->severity = $severity ?? self::SEVERITY_ERROR;
        $this->message  = $message;
        $this->source   = $source;
    }

    /**
     * @see Error::$line
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @see Error::$severity
     * @return string
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * @see Error::$message
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @see Error::$source
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @see Error::$column
     * @return int
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Проверяет соответствие
     *
     * @return bool
     */
    public function validate(): bool
    {
        return $this->message && $this->source;
    }
}
