<?php

namespace Asciisd\Zoho;

use com\zoho\api\authenticator\OAuthBuilder;
use com\zoho\api\authenticator\store\FileStore;
use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\dc\AUDataCenter;
use com\zoho\crm\api\dc\CNDataCenter;
use com\zoho\crm\api\dc\CADataCenter;
use com\zoho\crm\api\dc\Environment;
use com\zoho\crm\api\dc\EUDataCenter;
use com\zoho\crm\api\dc\INDataCenter;
use com\zoho\crm\api\dc\USDataCenter;
use com\zoho\crm\api\exception\SDKException;
use com\zoho\crm\api\InitializeBuilder;
use com\zoho\crm\api\Initializer;
use com\zoho\crm\api\SDKConfigBuilder;
use com\zoho\crm\api\UserSignature;

class Zoho
{
    /**
     * The Zoho library version.
     */
    public const VERSION = '3.0.0';

    /**
     * Indicates if Zoho migrations will be run.
     */
    public static bool $runsMigrations = true;

    /**
     * Indicates if Zoho routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if Zoho routes will be registered.
     */
    public static Environment|null $environment = null;

    /**
     * Configure Zoho to not register its migrations.
     */
    public static function ignoreMigrations(): static
    {
        static::$runsMigrations = false;

        return new static();
    }

    /**
     * Configure Zoho to not register its routes.
     */
    public static function ignoreRoutes(): static
    {
        static::$registersRoutes = false;

        return new static();
    }

    /**
     * Configure Zoho to use a specific environment
     */
    public static function useEnvironment(Environment $environment): static
    {
        static::$environment = $environment;

        return new static();
    }

    /**
     * @throws SDKException
     */
    public static function initialize($code = null): void
    {
        if (self::isInitialized()) {
            return;
        }

        // //Don't initialize if the zoho.token_persistence_path file does not exist
        // if (!file_exists(config('zoho.token_persistence_path'))) {
        //     logger()->warning('Zoho token persistence path does not exist, you can run `php artisan zoho:install` to create it.');
        //     return;
        // }

        // //Don't initialize if the zoho.token_persistence_path file is empty
        // if (filesize(config('zoho.token_persistence_path')) === 0) {
        //     logger()->warning('Zoho token persistence path is empty, you can run `php artisan zoho:authentication` generate token.');
        //     return;
        // }

        // //Don't initialize if the zoho.token_persistence_path file has only one line
        // if (count(file(config('zoho.token_persistence_path'))) === 1) {
        //     return;
        // }

        $environment = self::$environment ?: self::getDataCenterEnvironment();
        $resourcePath = config('zoho.resourcePath');
        $token_store = new FileStore(config('zoho.token_persistence_path'));
        $logger = (new LogBuilder())->level(Levels::ALL)
            ->filePath(config('zoho.application_log_file_path'))
            ->build();

        switch (config('zoho.auth_flow_type')) {
            case 'accessToken':
                $token = (new OAuthBuilder())
                    ->userSignature(new UserSignature(config('zoho.current_user_email')))
                    ->accessToken(config('zoho.token'))
                    ->build();
                break;

            case 'refreshToken':
                $token = (new OAuthBuilder())
                    ->userSignature(new UserSignature(config('zoho.current_user_email')))
                    ->clientId(config('zoho.client_id'))
                    ->clientSecret(config('zoho.client_secret'))
                    ->refreshToken($code ?? config('zoho.token'))
                    ->redirectURL(config('zoho.redirect_uri'))
                    ->build();
                break;

            case 'grantToken':
                $token = (new OAuthBuilder())
                    ->userSignature(new UserSignature(config('zoho.current_user_email')))
                    ->clientId(config("zoho.client_id"))
                    ->clientSecret(config("zoho.client_secret"))
                    ->grantToken($code ?? config("zoho.token"))
                    ->redirectURL(config("zoho.redirect_uri"))
                    ->build();
                break;
        }

        $sdkConfig = (new SDKConfigBuilder())
            ->autoRefreshFields(config('zoho.autoRefreshFields'))
            ->pickListValidation(config('zoho.pickListValidation'))
            ->sslVerification(config('zoho.enableSSLVerification'))
            ->connectionTimeout(config('zoho.connectionTimeout'))
            ->timeout(config('zoho.timeout'))
            ->build();


        (new InitializeBuilder())
            ->environment($environment)
            ->token($token)
            ->store($token_store)
            ->SDKConfig($sdkConfig)
            ->resourcePath($resourcePath)
            ->logger($logger)
            ->initialize();
    }

    public static function isInitialized(): bool
    {
        return Initializer::getInitializer() !== null;
    }

    public static function getDataCenterEnvironment(): ?Environment
    {
        if (!empty(static::$environment)) {
            return static::$environment;
        }

        return match (config('zoho.datacenter')) {
            'USDataCenter' => config('zoho.environment') ? USDataCenter::SANDBOX() : USDataCenter::PRODUCTION(),
            'EUDataCenter' => config('zoho.environment') ? EUDataCenter::SANDBOX() : EUDataCenter::PRODUCTION(),
            'INDataCenter' => config('zoho.environment') ? INDataCenter::SANDBOX() : INDataCenter::PRODUCTION(),
            'CNDataCenter' => config('zoho.environment') ? CNDataCenter::SANDBOX() : CNDataCenter::PRODUCTION(),
            'AUDataCenter' => config('zoho.environment') ? AUDataCenter::SANDBOX() : AUDataCenter::PRODUCTION(),
            'CADataCenter' => config('zoho.environment') ? CADataCenter::SANDBOX() : CADataCenter::PRODUCTION(),
        };
    }
}
