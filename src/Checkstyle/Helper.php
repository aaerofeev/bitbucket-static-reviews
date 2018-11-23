<?php
namespace BitbucketReviews\Checkstyle;

/**
 * Вспомогательные инструменты
 */
class Helper
{
    /**
     * Ключевое слово для исправленной ошибки
     */
    public const FIXED_WORD = '**[FIXED]**';

    /**
     * Разделяем текст комментария на отдельные ошибки
     *
     * @param string $text
     * @return array
     */
    public static function splitByErrorText(string $text): array
    {
        return array_filter(explode(self::separator(), $text));
    }

    /**
     * Объеденяет через разделитель ошибки
     *
     * @param array $parts
     * @return string
     */
    public static function join(array $parts): string
    {
        return implode(self::separator(), $parts);
    }

    /**
     * Добавляет пометку об исправлении в текст ошибки
     *
     * @param string $text
     * @return string
     */
    public static function markAsFixed(string $text): string
    {
        if (strpos($text, self::FIXED_WORD) !== false) {
            throw new \InvalidArgumentException('Text already fixed');
        }

        return str_replace('Source:', self::FIXED_WORD . ' Source:', $text);
    }

    /**
     * Проверяет находися ли ошибка в данном тексте
     *
     * @param $subject
     * @param $errorText
     * @return bool
     */
    public static function containsError($subject, $errorText): bool
    {
        $subject   = str_replace([self::FIXED_WORD . ' ', self::separator()], '', $subject);
        $errorText = str_replace([self::separator()], '', $errorText);

        return strpos($subject, $errorText) !== false;
    }

    /**
     * Форматирует ошибку в виде текста для пользователя
     *
     * @param \BitbucketReviews\Checkstyle\Error $error
     * @return string
     */
    public static function formatError(Error $error): string
    {
        return "```\n{$error->getMessage()}\n```\n" .
            "Source: {$error->getSource()}, severity: {$error->getSeverity()}" . self::separator();
    }

    /**
     * Разделитель ошибок
     *
     * @return string
     */
    public static function separator(): string
    {
        return "&shy;\n";
    }
}
