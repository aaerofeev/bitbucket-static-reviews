<?php
namespace BitbucketReviews\Checkstyle;

use BitbucketReviews\Exception\CheckStyleFormatException;

/**
 * Работа с форматом checkstyle
 */
class Collector
{
    /**
     * @var \BitbucketReviews\Checkstyle\File[]
     */
    protected $files = [];

    /**
     * Получает информацию из файла
     *
     * @param string $xmlFilename
     * @param string $name
     * @param string $rootPath
     * @return int
     * @throws \BitbucketReviews\Exception\CheckStyleFormatException
     */
    public function parseFile(string $xmlFilename, string $name, string $rootPath = ''): int
    {
        $xml   = simplexml_load_string(file_get_contents($xmlFilename));
        $count = 0;

        foreach ($xml->children() as $fileNode) {
            $filename = (string) $fileNode->attributes()->name;

            if ($rootPath && strpos($filename, $rootPath) === 0) {
                $filename = ltrim(mb_substr($filename, mb_strlen($rootPath)), DIRECTORY_SEPARATOR);
            }

            if (!$filename) {
                throw new CheckStyleFormatException(
                    "Имя файла не задано в {$xmlFilename} для <{$fileNode->getName()}>"
                );
            }

            $file = new File($filename, $name, []);

            if (!$fileNode->error) {
                continue;
            }

            foreach ($fileNode->error as $error) {
                $attr  = $error->attributes();
                $error = new Error(
                    (int) $attr->line,
                    (int) $attr->column,
                    (string) $attr->severity,
                    (string) $attr->message,
                    (string) $attr->source
                );

                $file->addError($error);
                $count ++;
            }

            $this->files[] = $file;
        }

        return $count;
    }

    /**
     * @see Collector::$files
     * @return \BitbucketReviews\Checkstyle\File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Получает файл с ошибками
     *
     * @param string $filename
     * @return \BitbucketReviews\Checkstyle\File|null
     */
    public function getFile(string $filename)
    {
        $files = array_filter($this->files, function (File $file) use ($filename) {
            return $file->getFilename() === $filename && $file->getCount() > 0;
        });

        return array_pop($files);
    }
}
