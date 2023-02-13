<?php

declare(strict_types=1);

namespace Wherd\Foundation;

use Closure;

class System
{
    protected static self $instance;

    /** @var array<string,mixed> */
    protected array $providers = [];

    /** @var callable|null */
    protected $terminator;

    /** @var callable|null */
    protected $exceptionHandler;

    public static function getInstance(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }

    public function __construct()
    {
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }

        register_shutdown_function([$this, 'terminate']);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }

    public function onTerminate(callable $callback): void
    {
        $this->terminator = $callback;
    }

    public function onException(callable $callback): void
    {
        $this->exceptionHandler = $callback;
    }

    public function terminate(bool $exit=true): void
    {
        if (defined('SID')) {
            session_write_close();
        }

        if (null !== ($error = error_get_last()) && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->halt(500, $error['message'], $error['file'], $error['line']);
        }

        if (isset($this->terminator) && is_callable($this->terminator)) {
            ($this->terminator)();
        }

        if ($exit) {
            exit;
        }
    }

    public function handleError(int $level, string $message, string $file='', int $line=0): bool
    {
        if (! (error_reporting() & $level)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        $this->halt(500, $message, $file, $line);
        return true;
    }

    public function handleException(\Throwable $e): void
    {
        $this->halt(500, $e->getMessage(), $e->getFile(), $e->getLine());
    }

    protected function halt(int $errno, string $msg, string $file='', int $line=0): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        if (is_callable($this->exceptionHandler)) {
            call_user_func($this->exceptionHandler, $errno, $msg, $file, $line);
            die;
        }

        // No custom error handler:
        echo "Unhandled exception '$msg' on $file:$line", '<pre>';
        debug_print_backtrace();
        echo '</pre>';
        die;
    }

    public function providerOf(string $name): mixed
    {
        if (empty($this->providers[$name])) {
            throw new \RuntimeException("$name was not found on the provider.");
        }

        $object = $this->providers[$name];

        if (is_object($object) && !($object instanceof Closure)) {
            return $object;
        }

        if (is_callable($object)) {
            $this->providers[$name] = $object();
        }

        return $this->providers[$name];
    }

    public function provide(string $name, mixed $object): void
    {
        $this->providers[$name] = $object;
    }

    /** @param array<string,mixed> $providers */
    public function provideMultiple(array $providers): void
    {
        $this->providers = array_merge($this->providers, $providers);
    }
}
