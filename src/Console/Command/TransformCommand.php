<?php

namespace App\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Filesystem\Filesystem;

class TransformCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('transform:data')
            ->setDescription('Transform xml database dump serialized data to JSON.')
            ->addArgument(
                'input_dir',
                InputArgument::OPTIONAL,
                'The directory of the data to transform (defaults to "data").'
            )
            ->addArgument(
                'output_dir',
                InputArgument::OPTIONAL,
                'The directory of the data to output (defaults to "data/out").'
            )
            ->addOption(
                'keep-comments',
                null,
                InputOption::VALUE_NONE,
                'If set, will not strip comments from XML.'
            )
            ->addOption(
                'delete-output-dir',
                null,
                InputOption::VALUE_NONE,
                'Delete all files in the output directory and the output directory before starting.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filesystem = new Filesystem();

        // Parse input and output directories.
        $input_dir = $input->getArgument('input_dir');
        if ($input_dir === null) {
            $input_dir = BASE_DIR . '/data';
        } elseif (substr($input_dir, 0, 1) != '/') {
            $input_dir  = BASE_DIR . '/' . $input_dir;
        }
        $output_dir = $input->getArgument('output_dir');
        if ($output_dir === null) {
            $output_dir = BASE_DIR . '/data/out';
        } elseif (substr($output_dir, 0, 1) != '/') {
            $output_dir  = BASE_DIR . '/' . $output_dir;
        }

        // Clear output directory.
        if ($input->getOption('delete-output-dir')) {
            $filesystem->remove($output_dir);
        }

        // If the output directory doesn't exist create it.
        if (!is_dir($output_dir)) {
            $filesystem->mkdir($output_dir);
        }

        // Find files to process.
        $finder = new Finder();
        $finder
            ->files()
            ->name('*.xml')
            ->notName('*cache*.xml')
            ->notName('*search_index*')
            ->depth('== 0')
            ->in($input_dir);

        // Start a progress bar.
        $progress = new ProgressBar($output, count($finder));
        $progress->setFormat(" %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% %memory:6s%\n %message%");
        $progress->start();
        $progress->setMessage('Starting processing...');

        // Iterate over files.
        $errors = [];
        foreach ($finder as $file) {
            // Optionally clear comments.
            $contents = $file->getContents();
            if (!$input->getOption('keep-comments')) {
                $contents = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $contents);
            }

            // Create a DOMDocument if possible.
            try {
                $dom = new \DOMDocument();
                $dom->loadXML($contents);
            } catch (\ErrorException $e) {
                // Couldn't read the file for some reason
                $dom = null;
                $errors[] = 'DOMDocument could not load file ' . $file->getRelativePathname();
            }

            // Iterate over fields in this document.
            if ($dom !== null) {
                $xpath = new \DOMXPath($dom);
                foreach ($xpath->query("/mysqldump/database/table_data/row/field") as $field) {
                    // Attempt to skip serialized fields.
                    if ($this::isSerialized($field->nodeValue)) {
                        // Try to unserialize the data, catch errors and log them.
                        try {
                            $raw = unserialize(htmlspecialchars_decode($field->nodeValue));
                            $json = json_encode($raw, JSON_PRETTY_PRINT);
                            $field->nodeValue = htmlspecialchars($json);
                        } catch (\ErrorException $e) {
                            // Couldn't unserialize the field.
                            $errors[] = 'Could not unserialize field "' . $field->getAttribute('name') . '" in file ' . $file->getRelativePathname();
                        }
                    }
                }
                // Update contents.
                $contents = $dom->saveXML();
            }

            file_put_contents($output_dir . '/' . $file->getRelativePathname(), $contents);

            if (count($errors) > 0) {
                $error = end($errors);
                $progress->setMessage("Last error: <error>$error</error>");
            }
            $progress->advance();
        }
        $progress->finish();
        if (count($errors) > 0) {
            $output->writeln('');
            $output->writeln('Finished with errors:');
            foreach ($errors as $error) {
                $output->writeln("<error>$error</error>");
            }
        }
    }

    public static function isSerialized($data)
    {
        // if it isn't a string, it isn't serialized
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' == $data) {
            return true;
        }
        if (!preg_match('/^([adObis]):/', $data, $badions)) {
            return false;
        }
        switch ($badions[1]) {
            case 'a':
            case 'O':
            case 's':
                if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
                    return true;
                }
                break;
            case 'b':
            case 'i':
            case 'd':
                if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
                    return true;
                }
                break;
        }
        return false;
    }
}
