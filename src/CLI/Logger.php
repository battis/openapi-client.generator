<?php

namespace Battis\OpenAPI\CLI;

use Psr\Log\LoggerInterface;

class Logger
{
    public const EMERGENCY = "emergency";
    public const ALERT = "alert";
    public const CRITICAL = "critical";
    public const ERROR = "error";
    public const WARNING = "warning";
    public const NOTICE = "notice";
    public const INFO = "info";
    public const DEBUG = "debug";

    private static ?LoggerInterface $logger = null;

    /**
     * @psalm-suppress UnusedConstructor singleton
     */
    private function __construct() {}

    public static function init(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function log(
        mixed $message,
        string $level = self::INFO,
        bool $includeDebugInformation = null
    ): void {
        if (self::$logger !== null) {
            if (
                $includeDebugInformation ||
                ($includeDebugInformation === null && $level === self::DEBUG)
            ) {
                /** @var mixed $message */
                $message = self::collectDebugInformation($message);
            }
            self::$logger->$level(self::prepareMessage($message));
        }
    }

    protected static function collectDebugInformation(mixed $message): mixed
    {
        /**
         * @var array<array{
         *    file: string,
         *    line: string,
         *    function: string
         *  }> $debug
         */
        $debug = debug_backtrace(0, 3);

        $debug =
          $debug[1]["file"] .
          ", line " .
          $debug[1]["line"] .
          " @ " .
          $debug[2]["function"] .
          "()";
        if (is_array($message)) {
            for ($key = "debug"; array_key_exists($key, $message); $key = "_$key");
            $message[$key] = $debug;
        } else {
            $message = [
              "message" => $message,
              "debug" => $debug,
            ];
        }
        return $message;
    }

    protected static function prepareMessage(mixed $message): string
    {
        if (!is_string($message)) {
            $message = var_export($message, true);
        }
        return $message;
    }
}
