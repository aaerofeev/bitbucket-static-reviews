<?php
namespace CheckstyleStash\Checkstyle;

use CheckstyleStash\Exception\CheckStyleFormatException;

/**
 * Работа с форматом checkstyle
 */
class Collector
{
    /**
     * @var \CheckstyleStash\Checkstyle\File[]
     */
    protected $files = [];

    /**
     * Получает информацию из файла
     *
     * @param string $xmlFilename
     * @param string $rootPath
     * @throws \CheckstyleStash\Exception\CheckStyleFormatException
     */
    public function parseFile(string $xmlFilename, string $rootPath = '')
    {
        $xml = simplexml_load_string(file_get_contents($xmlFilename));

        foreach ($xml->children() as $fileNode) {
            $filename = (string) $fileNode->attributes()->name;

            if ($rootPath && strpos($filename, $rootPath) === 0) {
                $filename = ltrim(mb_substr($filename, mb_strlen($rootPath) - 1), DIRECTORY_SEPARATOR);
            }

            if (!$filename) {
                throw new CheckStyleFormatException(
                    "Имя файла не задано в {$xmlFilename} для <{$fileNode->getName()}>"
                );
            }

            $file = new File($filename, $xmlFilename, []);

            foreach ($fileNode->children() as $error) {
                $attr  = $error->attributes();
                $error = new Error(
                    (int) $attr->line,
                    (int) $attr->column,
                    (string) $attr->severity,
                    (string) $attr->message,
                    (string) $attr->source
                );

                $this->files[] = $file->addError($error);
            }
        }
    }

    /**
     * @see Collector::$files
     * @return \CheckstyleStash\Checkstyle\File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Получает файл с ошибками
     *
     * @param string $filename
     * @return \CheckstyleStash\Checkstyle\File|null
     */
    public function getFile(string $filename)
    {
        $files = array_filter($this->files, function (File $file) use ($filename) {
            return $file->getFilename() === $filename && $file->getCount() > 0;
        });

        return array_pop($files);
    }
}
