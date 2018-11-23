<?php
namespace BitbucketReviews\Checkstyle;

/**
 * Сущность ошибка
 */
class Error
{
    /**
     * Уровни важности
     */
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_IGNORE = 'ignore';

    /**
     * Порядок важности
     */
    public const SEVERITY_ORDER = [
        self::SEVERITY_IGNORE,
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_ERROR,
    ];

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
     * Проверяет подходит ли ошибка под заданную важность
     *
     * @param string $min
     * @return bool
     */
    public function isSeverityMatch(string $min): bool
    {
        return array_search($min, self::SEVERITY_ORDER, false) <=
            array_search($this->severity, self::SEVERITY_ORDER, false);
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

    /**
     * Получает форматированный текст
     *
     * @return string
     */
    public function getText(): string
    {
        return Helper::formatError($this);
    }
}
