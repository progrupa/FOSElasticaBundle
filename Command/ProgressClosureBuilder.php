<?php

/**
 * This file is part of the FOSElasticaBundle project.
 *
 * (c) Tim Nagel <tim@nagel.com.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Command;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProgressClosureBuilder
{
    /**
     * Builds a loggerClosure to be called from inside the Provider to update the command
     * line.
     *
     * @param OutputInterface $output
     * @param string $action
     * @param string $index
     * @param string $type
     * @return callable
     */
    public function build(OutputInterface $output, $action, array $actionParameters = array())
    {
	    $actionMessage = vsprintf($action, $actionParameters);
	    if (!class_exists('Symfony\Component\Console\Helper\ProgressBar') ||
            !is_callable(array('Symfony\Component\Console\Helper\ProgressBar', 'getProgress'))) {
            return $this->buildLegacy($output, $actionMessage);
        }

        $progress = null;

        return function ($increment, $totalObjects, $message = null) use (&$progress, $output, $actionMessage) {
            if (null === $progress) {
                $progress = new ProgressBar($output, $totalObjects);
                $progress->start();
            }

            if (null !== $message) {
                $progress->clear();
                $output->writeln(sprintf('<info>%s</info> <error>%s</error>', $actionMessage, $message));
                $progress->display();
            }

            $progress->setMessage($actionMessage);
            $progress->advance($increment);
        };
    }

    /**
     * Builds a legacy closure that outputs lines for each step. Used in cases
     * where the ProgressBar component doesnt exist or does not have the correct
     * methods to support what we need.
     *
     * @param OutputInterface $output
     * @param string $action
     * @param string $index
     * @param string $type
     * @return callable
     */
    private function buildLegacy(OutputInterface $output, $actionMessage)
    {
        $lastStep = null;
        $current = 0;

        return function ($increment, $totalObjects, $message = null) use ($output, $actionMessage, &$lastStep, &$current) {
            if ($current + $increment > $totalObjects) {
                $increment = $totalObjects - $current;
            }

            if (null !== $message) {
                $output->writeln(sprintf('<info>%s</info> <error>%s</error>', $actionMessage, $message));
            }

            $currentTime = microtime(true);
            $timeDifference = $currentTime - $lastStep;
            $objectsPerSecond = $lastStep ? ($increment / $timeDifference) : $increment;
            $lastStep = $currentTime;
            $current += $increment;
            $percent = 100 * $current / $totalObjects;

            $output->writeln(sprintf(
                '%s %0.1f%% (%d/%d), %d objects/s (RAM: current=%uMo peak=%uMo)',
                $actionMessage,
                $percent,
                $current,
                $totalObjects,
                $objectsPerSecond,
                round(memory_get_usage() / (1024 * 1024)),
                round(memory_get_peak_usage() / (1024 * 1024))
            ));
        };
    }
}
