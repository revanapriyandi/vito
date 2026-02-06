<?php

namespace App\Providers;

use App\DTOs\DynamicField;
use App\DTOs\DynamicForm;
use App\Enums\LoadBalancerMethod;
use App\Plugins\RegisterSiteFeature;
use App\Plugins\RegisterSiteFeatureAction;
use App\Plugins\RegisterSiteType;
use App\SiteTypes\CodeIgniter;
use App\SiteTypes\Laravel;
use App\SiteTypes\LoadBalancer;
use App\SiteTypes\NodeJS;
use App\SiteTypes\PHPBlank;
use App\SiteTypes\PHPMyAdmin;
use App\SiteTypes\PHPSite;
use App\SiteTypes\Wordpress;
use Illuminate\Support\ServiceProvider;

class SiteTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->php();
        $this->phpBlank();
        $this->laravel();
        $this->nodeJS();
        $this->python();
        $this->go();
        $this->genericPort();
        $this->staticHtml();
        $this->loadBalancer();
        $this->phpMyAdmin();
        $this->wordpress();
        $this->codeIgniter();
    }

    private function php(): void
    {
        RegisterSiteType::make(PHPSite::id())
            ->label('PHP')
            ->handler(PHPSite::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->component()
                    ->label('Repository'),
                DynamicField::make('branch')
                    ->component()
                    ->label('Branch'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('composer')
                    ->checkbox()
                    ->label('Run `composer install --no-dev`')
                    ->default(false),
            ]))
            ->register();
    }

    private function phpBlank(): void
    {
        RegisterSiteType::make(PHPBlank::id())
            ->label('PHP Blank')
            ->handler(PHPBlank::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
            ]))
            ->register();
    }

    private function laravel(): void
    {
        RegisterSiteType::make(Laravel::id())
            ->label('Laravel')
            ->handler(Laravel::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->default('public')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('composer')
                    ->checkbox()
                    ->label('Run `composer install --no-dev`')
                    ->default(false),
            ]))
            ->register();
        RegisterSiteFeature::make(Laravel::id(), 'modern-deployment')
            ->label('Modern Deployment (beta)')
            ->description('Enables zero downtime deployment and deployment rollbacks')
            ->register();
        RegisterSiteFeatureAction::make(Laravel::id(), 'modern-deployment', 'enable')
            ->label('Enable')
            ->handler(\App\SiteFeatures\ModernDeployment\Enable::class)
            ->register();
        RegisterSiteFeatureAction::make(Laravel::id(), 'modern-deployment', 'disable')
            ->label('Disable')
            ->handler(\App\SiteFeatures\ModernDeployment\Disable::class)
            ->register();
        RegisterSiteFeatureAction::make(Laravel::id(), 'modern-deployment', 'configuration')
            ->label('Configure')
            ->handler(\App\SiteFeatures\ModernDeployment\Configuration::class)
            ->register();
    }

    private function nodeJS(): void
    {
        RegisterSiteType::make(NodeJS::id())
            ->label('NodeJS with NPM')
            ->handler(NodeJS::class)
            ->form(DynamicForm::make([
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('port')
                    ->text()
                    ->label('Port')
                    ->placeholder('3000')
                    ->description('On which port your app will be running'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository')
                    ->description('Your package.json must have start and build scripts'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('nodejs_version')
                    ->component()
                    ->label('Node.js Version'),
            ]))
            ->register();
    }

    private function genericPort(): void
    {
        RegisterSiteType::make(\App\SiteTypes\GenericPort::id())
            ->label('Generic App (Port)')
            ->handler(\App\SiteTypes\GenericPort::class)
            ->form(DynamicForm::make([
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('port')
                    ->text()
                    ->label('Port')
                    ->placeholder('8080'),
                DynamicField::make('install_command')
                    ->text()
                    ->label('Install Command')
                    ->placeholder('e.g. npm install, go build')
                    ->description('Command to install dependencies/build'),
                DynamicField::make('build_command')
                    ->text()
                    ->label('Build Command')
                    ->placeholder('e.g. npm run build')
                    ->description('Command to build the application (optional)'),
                DynamicField::make('start_command')
                    ->text()
                    ->label('Start Command')
                    ->placeholder('e.g. ./app, npm start')
                    ->description('Long running command to start the application'),
            ]))
            ->register();
    }

    private function staticHtml(): void
    {
        RegisterSiteType::make(\App\SiteTypes\StaticHTML::id())
            ->label('Static HTML')
            ->handler(\App\SiteTypes\StaticHTML::class)
            ->form(DynamicForm::make([
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->placeholder('e.g. public, dist')
                    ->description('Directory containing index.html'),
            ]))
            ->register();
    }

    private function python(): void
    {
        RegisterSiteType::make(\App\SiteTypes\Python::id())
            ->label('Python')
            ->handler(\App\SiteTypes\Python::class)
            ->form(DynamicForm::make([
                DynamicField::make('python_version')
                    ->component()
                    ->label('Python Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('port')
                    ->text()
                    ->label('Port')
                    ->placeholder('8000'),
                DynamicField::make('install_command')
                    ->text()
                    ->label('Install Command')
                    ->default('pip install -r requirements.txt')
                    ->placeholder('pip install -r requirements.txt'),
                DynamicField::make('start_command')
                    ->text()
                    ->label('Start Command')
                    ->placeholder('gunicorn app:app'),
            ]))
            ->register();
    }

    private function go(): void
    {
        RegisterSiteType::make(\App\SiteTypes\Go::id())
            ->label('Go')
            ->handler(\App\SiteTypes\Go::class)
            ->form(DynamicForm::make([
                DynamicField::make('go_version')
                    ->component()
                    ->label('Go Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('port')
                    ->text()
                    ->label('Port')
                    ->placeholder('8080'),
                DynamicField::make('install_command')
                    ->text()
                    ->label('Install Command')
                    ->default('go build -o app')
                    ->placeholder('go build -o app'),
                DynamicField::make('start_command')
                    ->text()
                    ->label('Start Command')
                    ->default('./app')
                    ->placeholder('./app'),
            ]))
            ->register();
    }

    public function loadBalancer(): void
    {
        RegisterSiteType::make(LoadBalancer::id())
            ->label('Load Balancer')
            ->handler(LoadBalancer::class)
            ->form(DynamicForm::make([
                DynamicField::make('method')
                    ->select()
                    ->label('Load Balancing Method')
                    ->options([
                        LoadBalancerMethod::IP_HASH->value,
                        LoadBalancerMethod::ROUND_ROBIN->value,
                        LoadBalancerMethod::LEAST_CONNECTIONS->value,
                    ]),
            ]))
            ->register();
    }

    public function phpMyAdmin(): void
    {
        RegisterSiteType::make(PHPMyAdmin::id())
            ->label('PHPMyAdmin')
            ->handler(PHPMyAdmin::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
            ]))
            ->register();
    }

    public function wordpress(): void
    {
        RegisterSiteType::make(Wordpress::id())
            ->label('WordPress')
            ->handler(Wordpress::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('title')
                    ->text()
                    ->label('Site Title')
                    ->placeholder('My WordPress Site'),
                DynamicField::make('username')
                    ->text()
                    ->label('Admin Username')
                    ->placeholder('admin'),
                DynamicField::make('password')
                    ->text()
                    ->label('Admin Password'),
                DynamicField::make('email')
                    ->text()
                    ->label('Admin Email'),
                DynamicField::make('database')
                    ->text()
                    ->label('Database Name')
                    ->placeholder('wordpress')
                    ->componentProps(['defaultCharset' => 'utf8mb4', 'defaultCollation' => 'utf8mb4_0900_ai_ci']),
                DynamicField::make('database_user')
                    ->text()
                    ->label('Database User')
                    ->placeholder('wp_user'),
                DynamicField::make('database_password')
                    ->text()
                    ->label('Database Password'),
            ]))
            ->register();
    }

    private function codeIgniter(): void
    {
        RegisterSiteType::make(CodeIgniter::id())
            ->label('CodeIgniter 4')
            ->handler(CodeIgniter::class)
            ->form(DynamicForm::make([
                DynamicField::make('php_version')
                    ->component()
                    ->label('PHP Version'),
                DynamicField::make('source_control')
                    ->component()
                    ->label('Source Control'),
                DynamicField::make('web_directory')
                    ->text()
                    ->label('Web Directory')
                    ->default('public')
                    ->placeholder('e.g., public, www, dist (leave empty for root)')
                    ->description('The relative path of your website from /home/vito/your-domain/'),
                DynamicField::make('repository')
                    ->text()
                    ->label('Repository')
                    ->placeholder('organization/repository'),
                DynamicField::make('branch')
                    ->text()
                    ->label('Branch')
                    ->default('main'),
                DynamicField::make('composer')
                    ->checkbox()
                    ->label('Run `composer install --no-dev`')
                    ->default(false),
            ]))
            ->register();
    }
}
