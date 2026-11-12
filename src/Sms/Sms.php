<?php

namespace RSE\Delivra\Sms;

use Illuminate\Support\Collection;
use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Sms\Schema\DriverDefinition;
use RSE\Delivra\Exceptions\Sms\DriverNotFoundException;
use RSE\Delivra\Exceptions\Sms\SmsBatchException;
use RSE\Delivra\Traits\SanitizesNumbers;

class Sms
{
    use SanitizesNumbers;

    protected array $config;

    protected array $settings;

    protected string $driver;

    protected SmsBuilder $builder;

    protected static array $validated = [];

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->setBuilder(new SmsBuilder);

        $this->via($this->config['default']);
    }

    public function to($recipients): self
    {
        $this->builder->to($recipients);

        return $this;
    }

    public function via(string $driver, ?array $settings = null): self
    {
        $this->driver = $driver;

        $this->validateDriver();

        $this->builder->via($driver);

        $this->settings = $settings ?? $this->config['drivers'][$driver];

        $this->validateSettings();

        return $this;
    }

    public function body(string $message): self
    {
        $this->builder->body($message);

        return $this;
    }

    public function withCredits(bool $bool = true)
    {
        $this->builder->withCredits($bool);

        return $this;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function send($message = null, $callback = null): SmsResponse|SmsBatchResponse
    {
        if (is_null($message)) {
            return $this->setBuilder($this->builder)->dispatch();
        }

        if ($message instanceof SmsBuilder) {
            return $this->setBuilder($message)->dispatch();
        }

        $driver = $this->getDriverInstance()->message($message);

        call_user_func($callback, $driver);

        return $driver->send();
    }

    public function dispatch(): SmsResponse|SmsBatchResponse
    {
        $this->driver = $this->builder->getDriver() ?: $this->driver;

        if (empty($this->driver)) {
            $this->via($this->config['default']);
        }

        $result = $this->getDriverInstance()->setBuilder($this->builder)->send();

        // Handle batch exceptions
        if ($result instanceof SmsBatchResponse && $result->hasFailures()) {
            throw new SmsBatchException($result);
        }

        return $result;
    }

    public function getBalance(): ?int
    {
        $this->driver = $this->builder->getDriver() ?: $this->driver;

        if (empty($this->driver)) {
            $this->via($this->config['default']);
        }

        return $this->getDriverInstance()->setBuilder($this->builder)->getBalance();
    }

    protected function setBuilder(SmsBuilder $builder): self
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * Every configured driver's definition, keyed by driver name. Skips the null driver.
     *
     * @return Collection<string, DriverDefinition>
     */
    public function drivers(): Collection
    {
        return collect(array_keys($this->config['drivers'] ?? []))
            ->reject(fn (string $driver) => $driver === 'null')
            ->mapWithKeys(fn (string $driver) => [$driver => $this->definition($driver)]);
    }

    /**
     * @throws DriverNotFoundException
     */
    public function definition(string $driver): DriverDefinition
    {
        $class = $this->getDriverClass($driver);

        if (! isset($this->config['drivers'][$driver]) || ! is_subclass_of($class, SmsDriver::class)) {
            throw new DriverNotFoundException($driver);
        }

        return $class::definition()->name($driver);
    }

    /**
     * Laravel validation rules for a driver's settings.
     */
    public function rules(string $driver): array
    {
        return $this->definition($driver)->rules();
    }

    /**
     * Send from this sender name instead of the driver's configured one.
     */
    public function from(?string $sender): self
    {
        $this->builder->from($sender);

        return $this;
    }

    protected function getDriverInstance(): SmsDriver
    {
        $this->validateDriver();

        $class = $this->getDriverClass($this->driver);

        $driver = new $class($this->driver, $this->settings);

        $driver->validateSettings();

        return $driver;
    }

    protected function getDriverClass(string $driver): string
    {
        return $this->config['drivers'][$driver]['class']
            ?? '\\RSE\\Delivra\\Sms\\Drivers\\' . ucfirst(strtolower($driver)) . 'Driver';
    }

    protected function validateDriver(): void
    {
        $class = $this->getDriverClass($this->driver);

        // Use cached validation if available
        if (! isset(self::$validated[$class])) {
            // Check driver is selected
            if (empty($this->driver)) {
                throw new DriverNotFoundException($this->driver ?? '');
            }

            // Check driver exists in config
            if (! isset($this->config['drivers'][$this->driver])) {
                throw new DriverNotFoundException($this->driver);
            }

            // Check driver class exists
            if (! class_exists($class)) {
                throw new DriverNotFoundException($this->driver);
            }

            // Check driver is instance of SmsDriver contract
            if (! is_subclass_of($class, SmsDriver::class)) {
                throw new DriverNotFoundException($this->driver);
            }

            self::$validated[$class] = true;
        }
    }

    protected function validateSettings(): void
    {
        if ($this->driver === 'null') {
            return;
        }

        if (empty($this->settings)) {
            throw new DriverNotFoundException("Settings not found for driver: {$this->driver}");
        }
    }
}
