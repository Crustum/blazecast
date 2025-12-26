<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\I18n\DateTime;

/**
 * Restart Server Command
 *
 * Sends a restart signal to the BlazeCast WebSocket server.
 */
class RestartServerCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Restart the BlazeCast WebSocket server')
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force restart even if server is not running',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $timestamp = (new DateTime())->getTimestamp();

        $cacheKey = 'blazecast:server:restart';
        $cache = Configure::read('Cache.default') ?: 'default';

        Cache::write($cacheKey, $timestamp, $cache);

        $io->success('Broadcasting BlazeCast server restart signal.');
        $io->info("Restart timestamp: {$timestamp}");

        return self::CODE_SUCCESS;
    }
}
